<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Group
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2008-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * sql implementation of the groups interface
 * 
 * @package     Tinebase
 * @subpackage  Group
 */
class Tinebase_Group_Sql extends Tinebase_Group_Abstract
{
    use Tinebase_Controller_Record_ModlogTrait;


    /**
     * Model name
     *
     * @var string
     *
     * @todo perhaps we can remove that and build model name from name of the class (replace 'Controller' with 'Model')
     */
    protected $_modelName = 'Tinebase_Model_Group';

    /**
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_db;
    
    /**
     * the groups table
     *
     * @var Tinebase_Db_Table
     */
    protected $groupsTable;
    
    /**
     * the groupmembers table
     *
     * @var Tinebase_Db_Table
     */
    protected $groupMembersTable;
    
    /**
     * Table name without prefix
     *
     * @var string
     */
    protected $_tableName = 'groups';
    
    /**
     * set to true is addressbook table is found
     * 
     * @var boolean
     */
    protected $_addressBookInstalled = false;
    
    /**
     * in class cache 
     * 
     * @var array
     */
    protected $_classCache = array (
        'getGroupMemberships' => array()
    );
    
    /**
     * the constructor
     */
    public function __construct() 
    {
        $this->_db = Tinebase_Core::getDb();
        
        $this->groupsTable = new Tinebase_Db_Table(array('name' => SQL_TABLE_PREFIX . $this->_tableName));
        $this->groupMembersTable = new Tinebase_Db_Table(array('name' => SQL_TABLE_PREFIX . 'group_members'));
        
        try {
            // MySQL throws an exception         if the table does not exist
            // PostgreSQL returns an empty array if the table does not exist
            $adbSchema = Tinebase_Db_Table::getTableDescriptionFromCache(SQL_TABLE_PREFIX . 'addressbook');
            $adbListsSchema = Tinebase_Db_Table::getTableDescriptionFromCache(SQL_TABLE_PREFIX . 'addressbook_lists');
            if (! empty($adbSchema) && ! empty($adbListsSchema) ) {
                $this->_addressBookInstalled = TRUE;
            }
        } catch (Zend_Db_Statement_Exception $zdse) {
            // nothing to do
        }
    }
    
    /**
     * return all groups an account is member of
     *
     * @param mixed $_accountId the account as integer or Tinebase_Model_User
     * @return array
     */
    public function getGroupMemberships($_accountId)
    {
        $accountId = Tinebase_Model_User::convertUserIdToInt($_accountId);
        
        $classCacheId = $accountId;
        
        if (isset($this->_classCache[__FUNCTION__][$classCacheId])) {
            return $this->_classCache[__FUNCTION__][$classCacheId];
        }
        
        $cacheId     = Tinebase_Helper::convertCacheId(__FUNCTION__ . $classCacheId);
        $memberships = Tinebase_Core::getCache()->load($cacheId);
        
        if (! $memberships) {
            $select = $this->_db->select()
                ->distinct()
                ->from(array('group_members' => SQL_TABLE_PREFIX . 'group_members'), array('group_id'))
                ->where($this->_db->quoteIdentifier('account_id') . ' = ?', $accountId);
            
            $stmt = $this->_db->query($select);
            
            $memberships = $stmt->fetchAll(Zend_Db::FETCH_COLUMN);
            
            Tinebase_Core::getCache()->save($memberships, $cacheId);
        }
        
        $this->_classCache[__FUNCTION__][$classCacheId] = $memberships;
        
        return $memberships;
    }
    
    /**
     * get list of groupmembers
     *
     * @param int $_groupId
     * @return array with account ids
     */
    public function getGroupMembers($_groupId)
    {
        $groupId = Tinebase_Model_Group::convertGroupIdToInt($_groupId);
        
        $cacheId = Tinebase_Helper::convertCacheId(__FUNCTION__ . $groupId);
        $members = Tinebase_Core::getCache()->load($cacheId);

        if (false === $members) {
            $members = array();

            $select = $this->groupMembersTable->select();
            $select->where($this->_db->quoteIdentifier('group_id') . ' = ?', $groupId);

            $rows = $this->groupMembersTable->fetchAll($select);
            
            foreach($rows as $member) {
                $members[] = $member->account_id;
            }

            Tinebase_Core::getCache()->save($members, $cacheId);
        }

        return $members;
    }

    /**
     * replace all current groupmembers with the new groupmembers list
     *
     * @param  mixed $_groupId
     * @param  array $_groupMembers
     */
    public function setGroupMembers($_groupId, $_groupMembers)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Setting ' . count($_groupMembers) . ' new groupmembers for group ' . $_groupId);
        
        if ($this instanceof Tinebase_Group_Interface_SyncAble) {
            $_groupMembers = $this->setGroupMembersInSyncBackend($_groupId, $_groupMembers);
        }
        
        $this->setGroupMembersInSqlBackend($_groupId, $_groupMembers);
    }
     
    /**
     * replace all current groupmembers with the new groupmembers list
     *
     * @param  mixed  $_groupId
     * @param  array  $_groupMembers
     */
    public function setGroupMembersInSqlBackend($_groupId, $_groupMembers)
    {
        $groupId = Tinebase_Model_Group::convertGroupIdToInt($_groupId);

        $oldGroupMembers = $this->getGroupMembers($groupId);

        // remove old members
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('group_id') . ' = ?', $groupId);
        $this->groupMembersTable->delete($where);
        
        // check if users have accounts
        $userIdsWithExistingAccounts = Tinebase_User::getInstance()->getMultiple($_groupMembers)->getArrayOfIds();
        
        if (count($_groupMembers) > 0) {
            // add new members
            foreach ($_groupMembers as $accountId) {
                $accountId = Tinebase_Model_User::convertUserIdToInt($accountId);
                if (in_array($accountId, $userIdsWithExistingAccounts)) {
                    $this->_db->insert(SQL_TABLE_PREFIX . 'group_members', array(
                        'group_id'    => $groupId,
                        'account_id'  => $accountId
                    ));
                } else {
                    if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                        . ' User with ID ' . $accountId . ' does not have an account!');
                }
                
                $this->_clearCache(array('getGroupMemberships' => $accountId));
            }
        }
        
        $this->_clearCache(array('getGroupMembers' => $groupId));

        $newGroupMembers = $this->getGroupMembers($groupId);

        if (!empty(array_diff($oldGroupMembers, $newGroupMembers)) || !empty(array_diff($newGroupMembers, $oldGroupMembers)))
        {
            $oldGroup = new Tinebase_Model_Group(array('id' => $groupId, 'members' => $oldGroupMembers), true);
            $newGroup = new Tinebase_Model_Group(array('id' => $groupId, 'members' => $newGroupMembers), true);
            $this->_writeModLog($newGroup, $oldGroup);
        }
    }
    
    /**
     * invalidate cache by type/id
     * 
     * @param array $cacheIds
     */
    protected function _clearCache($cacheIds = array())
    {
        $cache = Tinebase_Core::getCache();
        
        foreach ($cacheIds as $type => $id) {
            $cacheId = Tinebase_Helper::convertCacheId($type . $id);
            $cache->remove($cacheId);
        }
        
        $this->resetClassCache();
    }
    
    /**
     * set all groups an account is member of
     *
     * @param  mixed  $_userId    the userid as string or Tinebase_Model_User
     * @param  mixed  $_groupIds
     * 
     * @return array
     */
    public function setGroupMemberships($_userId, $_groupIds)
    {
        if(count($_groupIds) === 0) {
            throw new Tinebase_Exception_InvalidArgument('user must belong to at least one group');
        }
        
        if($this instanceof Tinebase_Group_Interface_SyncAble) {
            $this->setGroupMembershipsInSyncBackend($_userId, $_groupIds);
        }
        
        return $this->setGroupMembershipsInSqlBackend($_userId, $_groupIds);
    }
    
    /**
     * set all groups an user is member of
     *
     * @param  mixed  $_usertId   the account as integer or Tinebase_Model_User
     * @param  mixed  $_groupIds
     * @return array
     */
    public function setGroupMembershipsInSqlBackend($_userId, $_groupIds)
    {
        if ($_groupIds instanceof Tinebase_Record_RecordSet) {
            $_groupIds = $_groupIds->getArrayOfIds();
        }
        
        if (count($_groupIds) === 0) {
            throw new Tinebase_Exception_InvalidArgument('user must belong to at least one group');
        }
        
        $userId = Tinebase_Model_User::convertUserIdToInt($_userId);
        
        $groupMemberships = $this->getGroupMemberships($userId);
        
        $removeGroupMemberships = array_diff($groupMemberships, $_groupIds);
        $addGroupMemberships    = array_diff($_groupIds, $groupMemberships);
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' current groupmemberships: ' . print_r($groupMemberships, true));
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' new groupmemberships: ' . print_r($_groupIds, true));
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' added groupmemberships: ' . print_r($addGroupMemberships, true));
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' removed groupmemberships: ' . print_r($removeGroupMemberships, true));
        
        foreach ($addGroupMemberships as $groupId) {
            $this->addGroupMemberInSqlBackend($groupId, $userId);
        }
        
        foreach ($removeGroupMemberships as $groupId) {
            $this->removeGroupMemberFromSqlBackend($groupId, $userId);
        }
        
        $event = new Tinebase_Group_Event_SetGroupMemberships(array(
            'user'               => $_userId,
            'addedMemberships'   => $addGroupMemberships,
            'removedMemberships' => $removeGroupMemberships
        ));
        Tinebase_Event::fireEvent($event);
        
        return $this->getGroupMemberships($userId);
    }
    
    /**
     * add a new groupmember to a group
     *
     * @param  string  $_groupId
     * @param  string  $_accountId
     */
    public function addGroupMember($_groupId, $_accountId)
    {
        if ($this instanceof Tinebase_Group_Interface_SyncAble) {
            $this->addGroupMemberInSyncBackend($_groupId, $_accountId);
        }
        
        $this->addGroupMemberInSqlBackend($_groupId, $_accountId);
    }
    
    /**
     * add a new groupmember to a group
     *
     * @param  string  $_groupId
     * @param  string  $_accountId
     */
    public function addGroupMemberInSqlBackend($_groupId, $_accountId)
    {
        $groupId   = Tinebase_Model_Group::convertGroupIdToInt($_groupId);
        $accountId = Tinebase_Model_User::convertUserIdToInt($_accountId);

        $memberShips = $this->getGroupMemberships($accountId);
        
        if (!in_array($groupId, $memberShips)) {

            $oldGroupMembers = $this->getGroupMembers($groupId);

            $data = array(
                'group_id'      => $groupId,
                'account_id'    => $accountId
            );
        
            $this->groupMembersTable->insert($data);
            
            $this->_clearCache(array(
                'getGroupMembers'     => $groupId,
                'getGroupMemberships' => $accountId,
            ));

            $newGroupMembers = $this->getGroupMembers($groupId);

            if (!empty(array_diff($oldGroupMembers, $newGroupMembers)) || !empty(array_diff($newGroupMembers, $oldGroupMembers)))
            {
                $oldGroup = new Tinebase_Model_Group(array('id' => $groupId, 'members' => $oldGroupMembers), true);
                $newGroup = new Tinebase_Model_Group(array('id' => $groupId, 'members' => $newGroupMembers), true);
                $this->_writeModLog($newGroup, $oldGroup);
            }
        }
        
    }
    
    /**
     * remove one groupmember from the group
     *
     * @param  mixed  $_groupId
     * @param  mixed  $_accountId
     */
    public function removeGroupMember($_groupId, $_accountId)
    {
        if ($this instanceof Tinebase_Group_Interface_SyncAble) {
            $this->removeGroupMemberInSyncBackend($_groupId, $_accountId);
        }
        
        return $this->removeGroupMemberFromSqlBackend($_groupId, $_accountId);
    }
    
    /**
     * remove one groupmember from the group
     *
     * @param  mixed  $_groupId
     * @param  mixed  $_accountId
     */
    public function removeGroupMemberFromSqlBackend($_groupId, $_accountId)
    {
        $groupId   = Tinebase_Model_Group::convertGroupIdToInt($_groupId);
        $accountId = Tinebase_Model_User::convertUserIdToInt($_accountId);

        $oldGroupMembers = $this->getGroupMembers($groupId);
        
        $where = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier('group_id') . '= ?', $groupId),
            $this->_db->quoteInto($this->_db->quoteIdentifier('account_id') . '= ?', $accountId),
        );
         
        $this->groupMembersTable->delete($where);
        
        $this->_clearCache(array(
            'getGroupMembers'     => $groupId,
            'getGroupMemberships' => $accountId,
        ));

        $newGroupMembers = $this->getGroupMembers($groupId);

        if (!empty(array_diff($oldGroupMembers, $newGroupMembers)) || !empty(array_diff($newGroupMembers, $oldGroupMembers)))
        {
            $oldGroup = new Tinebase_Model_Group(array('id' => $groupId, 'members' => $oldGroupMembers), true);
            $newGroup = new Tinebase_Model_Group(array('id' => $groupId, 'members' => $newGroupMembers), true);
            $this->_writeModLog($newGroup, $oldGroup);
        }
    }
    
    /**
     * create a new group
     *
     * @param   Tinebase_Model_Group  $_group
     * 
     * @return  Tinebase_Model_Group
     * 
     * @todo do not create group in sql if sync backend is readonly?
     */
    public function addGroup(Tinebase_Model_Group $_group)
    {
        if ($this instanceof Tinebase_Group_Interface_SyncAble) {
            $groupFromSyncBackend = $this->addGroupInSyncBackend($_group);
            
            if (isset($groupFromSyncBackend->id)) {
                $_group->setId($groupFromSyncBackend->getId());
            }
        }
        
        return $this->addGroupInSqlBackend($_group);
    }
    
    /**
     * alias for addGroup
     * 
     * @param Tinebase_Model_Group $group
     * @return Tinebase_Model_Group
     */
    public function create(Tinebase_Model_Group $group)
    {
        return $this->addGroup($group);
    }
    
    /**
     * create a new group in sql backend
     *
     * @param   Tinebase_Model_Group  $_group
     * 
     * @return  Tinebase_Model_Group
     * @throws  Tinebase_Exception_Record_Validation
     */
    public function addGroupInSqlBackend(Tinebase_Model_Group $_group)
    {
        if(!$_group->isValid()) {
            throw new Tinebase_Exception_Record_Validation('invalid group object');
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
            . ' Creating new group ' . $_group->name 
            //. print_r($_group->toArray(), true)
        );
        
        if(!isset($_group->id)) {
            $groupId = $_group->generateUID();
            $_group->setId($groupId);
        }
        
        if (empty($_group->list_id)) {
            $_group->visibility = 'hidden';
            $_group->list_id    = null;
        }
        
        $data = $_group->toArray();
        
        unset($data['members']);
        unset($data['container_id']);
        
        $this->groupsTable->insert($data);

        $newGroup = clone $_group;
        $newGroup->members = null;
        $newGroup->container_id = null;
        $this->_writeModLog($newGroup, null);
        
        return $_group;
    }
    
    /**
     * update a group
     *
     * @param  Tinebase_Model_Group  $_group
     * 
     * @return Tinebase_Model_Group
     */
    public function updateGroup(Tinebase_Model_Group $_group)
    {
        if ($this instanceof Tinebase_Group_Interface_SyncAble) {
            $this->updateGroupInSyncBackend($_group);
        }
        
        return $this->updateGroupInSqlBackend($_group);
    }
    
    /**
     * create a new group in sync backend
     * 
     * NOTE: sets visibility to HIDDEN if list_id is empty
     *
     * @param  Tinebase_Model_Group  $_group
     * @return Tinebase_Model_Group
     */
    public function updateGroupInSqlBackend(Tinebase_Model_Group $_group)
    {
        $groupId = Tinebase_Model_Group::convertGroupIdToInt($_group);

        $oldGroup = $this->getGroupById($groupId);

        if (empty($_group->list_id)) {
            $_group->visibility = Tinebase_Model_Group::VISIBILITY_HIDDEN;
            $_group->list_id    = null;
        }
        
        $data = array(
            'name'          => $_group->name,
            'description'   => $_group->description,
            'visibility'    => $_group->visibility,
            'email'         => $_group->email,
            'list_id'       => $_group->list_id,
            'created_by'            => $_group->created_by,
            'creation_time'         => $_group->creation_time,
            'last_modified_by'      => $_group->last_modified_by,
            'last_modified_time'    => $_group->last_modified_time,
            'is_deleted'            => $_group->is_deleted,
            'deleted_time'          => $_group->deleted_time,
            'deleted_by'            => $_group->deleted_by,
            'seq'                   => $_group->seq,
        );
        
        if (empty($data['seq'])) {
            unset($data['seq']);
        }
        
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' = ?', $groupId);
        
        $this->groupsTable->update($data, $where);
        
        $updatedGroup = $this->getGroupById($groupId);

        $this->_writeModLog($updatedGroup, $oldGroup);

        return $updatedGroup;
    }
    
    /**
     * delete groups
     *
     * @param   mixed $_groupId

     * @throws  Tinebase_Exception_Backend
     */
    public function deleteGroups($_groupId)
    {
        $groupIds = array();
        
        if (is_array($_groupId) or $_groupId instanceof Tinebase_Record_RecordSet) {
            foreach ($_groupId as $groupId) {
                $groupIds[] = Tinebase_Model_Group::convertGroupIdToInt($groupId);
            }
            if (count($groupIds) === 0) {
                return;
            }
        } else {
            $groupIds[] = Tinebase_Model_Group::convertGroupIdToInt($_groupId);
        }
        
        try {
            $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());

            $this->deleteGroupsInSqlBackend($groupIds);
            if ($this instanceof Tinebase_Group_Interface_SyncAble) {
                $this->deleteGroupsInSyncBackend($groupIds);
            }

            Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);

        } catch (Exception $e) {
            Tinebase_TransactionManager::getInstance()->rollBack();
            Tinebase_Exception::log($e);
            throw new Tinebase_Exception_Backend($e->getMessage());
        }
    }
    
    /**
     * set primary group for accounts with given primary group id
     * 
     * @param array $groupIds
     * @param string $newPrimaryGroupId
     * @throws Tinebase_Exception_Record_NotDefined
     */
    protected function _updatePrimaryGroupsOfUsers($groupIds, $newPrimaryGroupId = null)
    {
        if ($newPrimaryGroupId === null) {
            $newPrimaryGroupId = $this->getDefaultGroup()->getId();
        }
        foreach ($groupIds as $groupId) {
            $users = Tinebase_User::getInstance()->getUsersByPrimaryGroup($groupId);
            $users->accountPrimaryGroup = $newPrimaryGroupId;
            foreach ($users as $user) {
                Tinebase_User::getInstance()->updateUser($user);
            }
        }
    }
    
    /**
     * delete groups in sql backend
     * 
     * @param array $groupIds
     */
    public function deleteGroupsInSqlBackend($groupIds)
    {
        $this->_updatePrimaryGroupsOfUsers($groupIds);

        $groups = array();
        foreach($groupIds as $groupId) {
            $group = $this->getGroupById($groupId);
            $group->members = $this->getGroupMembers($groupId);
            $groups[] = $group;
        }

        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('group_id') . ' IN (?)', (array) $groupIds);
        $this->groupMembersTable->delete($where);
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' IN (?)', (array) $groupIds);
        $this->groupsTable->delete($where);

        foreach($groups as $group) {
            $this->_writeModLog(null, $group);
        }
    }
    
    /**
     * Delete all groups returned by {@see getGroups()} using {@see deleteGroups()}
     * @return void
     */
    public function deleteAllGroups()
    {
        $groups = $this->getGroups();
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Deleting ' . count($groups) .' groups');
        
        if(count($groups) > 0) {
            $this->deleteGroups($groups);
        }
    }
    
    /**
     * get list of groups
     *
     * @param string $_filter
     * @param string $_sort
     * @param string $_dir
     * @param int $_start
     * @param int $_limit
     * @return Tinebase_Record_RecordSet with record class Tinebase_Model_Group
     */
    public function getGroups($_filter = NULL, $_sort = 'name', $_dir = 'ASC', $_start = NULL, $_limit = NULL)
    {
        $select = $this->_getSelect();
        
        if($_filter !== NULL) {
            $select->where($this->_db->quoteIdentifier($this->_tableName. '.name') . ' LIKE ?', '%' . $_filter . '%');
        }
        if($_sort !== NULL) {
            $select->order($this->_tableName . '.' . $_sort . ' ' . $_dir);
        }
        if($_start !== NULL) {
            $select->limit($_limit, $_start);
        }
        
        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetchAll();
        $stmt->closeCursor();
        
        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Group', $queryResult, TRUE);
        
        return $result;
    }
    
    /**
     * get group by name
     *
     * @param   string $_name
     * @return  Tinebase_Model_Group
     * @throws  Tinebase_Exception_Record_NotDefined
     */
    public function getGroupByName($_name)
    {
        $result = $this->getGroupByPropertyFromSqlBackend('name', $_name);
        
        return $result;
    }
    
    /**
     * get group by property
     *
     * @param   string  $_property      the key to filter
     * @param   string  $_value         the value to search for
     *
     * @return  Tinebase_Model_Group
     * @throws  Tinebase_Exception_Record_NotDefined
     * @throws  Tinebase_Exception_InvalidArgument
     */
    public function getGroupByPropertyFromSqlBackend($_property, $_value)
    {
        if (! in_array($_property, array('id', 'name', 'description', 'list_id', 'email'))) {
            throw new Tinebase_Exception_InvalidArgument('property not allowed');
        }
        
        $select = $this->_getSelect();
        
        $select->where($this->_db->quoteIdentifier($this->_tableName . '.' . $_property) . ' = ?', $_value);
        
        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetch();
        $stmt->closeCursor();
        
        if (!$queryResult) {
            throw new Tinebase_Exception_Record_NotDefined('Group not found.');
        }
        
        $result = new Tinebase_Model_Group($queryResult, TRUE);
        
        return $result;
    }
    
    
    /**
     * get group by id
     *
     * @param   string $_name
     * @return  Tinebase_Model_Group
     * @throws  Tinebase_Exception_Record_NotDefined
     */
    public function getGroupById($_groupId)
    {
        $groupdId = Tinebase_Model_Group::convertGroupIdToInt($_groupId);
        
        $result = $this->getGroupByPropertyFromSqlBackend('id', $groupdId);
        
        return $result;
    }
    
    /**
     * Get multiple groups
     *
     * @param string|array $_ids Ids
     * @return Tinebase_Record_RecordSet
     * 
     * @todo this should return the container_id, too
     */
    public function getMultiple($_ids)
    {
        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Group');
        
        if (! empty($_ids)) {
            $select = $this->groupsTable->select();
            $select->where($this->_db->quoteIdentifier('id') . ' IN (?)', array_unique((array) $_ids));
            
            $rows = $this->groupsTable->fetchAll($select);
            foreach ($rows as $row) {
                $result->addRecord(new Tinebase_Model_Group($row->toArray(), TRUE));
            }
        }
        
        return $result;
    }
    
    /**
     * get the basic select object to fetch records from the database
     * 
     * NOTE: container_id is joined from addressbook lists table
     *  
     * @param array|string|Zend_Db_Expr $_cols columns to get, * per default
     * @param boolean $_getDeleted get deleted records (if modlog is active)
     * @return Zend_Db_Select
     */
    protected function _getSelect($_cols = '*', $_getDeleted = FALSE)
    {
        $select = $this->_db->select();
        
        $select->from(array($this->_tableName => SQL_TABLE_PREFIX . $this->_tableName), $_cols);
        
        if ($this->_addressBookInstalled === true) {
            $select->joinLeft(
                array('addressbook_lists' => SQL_TABLE_PREFIX . 'addressbook_lists'),
                $this->_db->quoteIdentifier($this->_tableName . '.list_id') . ' = ' . $this->_db->quoteIdentifier('addressbook_lists.id'), 
                array('container_id')
            );
        }
        
        return $select;
    }
    
    /**
     * Method called by {@see Addressbook_Setup_Initialize::_initilaize()}
     * 
     * @param $_options
     * @return mixed
     */
    public function __importGroupMembers($_options = null)
    {
        //nothing to do
        return null;
    }

    /**
     * @param Tinebase_Model_ModificationLog $modification
     */
    public function applyReplicationModificationLog(Tinebase_Model_ModificationLog $modification)
    {
        switch ($modification->change_type) {
            case Tinebase_Timemachine_ModificationLog::CREATED:
                $diff = new Tinebase_Record_Diff(json_decode($modification->new_value, true));
                $record = new Tinebase_Model_Group($diff->diff);
                Tinebase_Timemachine_ModificationLog::setRecordMetaData($record, 'create');
                $this->addGroup($record);
                break;

            case Tinebase_Timemachine_ModificationLog::UPDATED:
                $diff = new Tinebase_Record_Diff(json_decode($modification->new_value, true));
                if (isset($diff->diff['members']) && is_array($diff->diff['members'])) {
                    $this->setGroupMembers($modification->record_id, $diff->diff['members']);
                } else {
                    $record = $this->getGroupById($modification->record_id);
                    $currentRecord = clone $record;
                    $record->applyDiff($diff);
                    Tinebase_Timemachine_ModificationLog::setRecordMetaData($record, 'update', $currentRecord);
                    $this->updateGroup($record);
                }
                break;

            case Tinebase_Timemachine_ModificationLog::DELETED:
                $this->deleteGroups($modification->record_id);
                break;

            default:
                throw new Tinebase_Exception('unknown Tinebase_Model_ModificationLog->old_value: ' . $modification->old_value);
        }
    }
}