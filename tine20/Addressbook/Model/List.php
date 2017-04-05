<?php
/**
 * Tine 2.0
 * 
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2010-2016 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * class to hold addressbook list data
 *
 * @package     Addressbook
 *
 * @property    string      $id
 * @property    string      $container_id
 * @property    string      $name
 * @property    string      $description
 * @property    array       $members
 * @property    array       $memberroles
 * @property    string      $email
 * @property    string      $type                 type of list
 */
class Addressbook_Model_List extends Tinebase_Record_Abstract
{
    /**
     * key in $_validators/$_properties array for the filed which 
     * represents the identifier
     * 
     * @var string
     */
    protected $_identifier = 'id';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Addressbook';
    
    /**
     * list type: list (user defined lists)
     * 
     * @var string
     */
    const LISTTYPE_LIST = 'list';
    
    /**
     * list type: group (lists matching a system group)
     * 
     * @var string
     */
    const LISTTYPE_GROUP = 'group';

    /**
     * name of fields which require manage accounts to be updated
     *
     * @var array list of fields which require manage accounts to be updated
     */
    protected static $_manageAccountsFields = array(
        'name',
        'description',
        'email',
    );
    
    /**
     * list of zend validator
     * 
     * this validators get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_validators = array (
        // tine 2.0 generic fields
        'id'                    => array(Zend_Filter_Input::ALLOW_EMPTY => true, Zend_Filter_Input::DEFAULT_VALUE => NULL),
        'container_id'          => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        // modlog fields
        'created_by'            => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'creation_time'         => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'last_modified_by'      => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'last_modified_time'    => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'is_deleted'            => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'deleted_time'          => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'deleted_by'            => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'seq'                   => array(Zend_Filter_Input::ALLOW_EMPTY => true, Zend_Filter_Input::DEFAULT_VALUE => 0),
        
        // list specific fields
        'name'                  => array('presence' => 'required'),
        'description'           => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'members'               => array(Zend_Filter_Input::ALLOW_EMPTY => true, Zend_Filter_Input::DEFAULT_VALUE => array()),
        'email'                 => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'type'                  => array(
            Zend_Filter_Input::ALLOW_EMPTY => true,
            Zend_Filter_Input::DEFAULT_VALUE => self::LISTTYPE_LIST,
            array('InArray', array(self::LISTTYPE_LIST, self::LISTTYPE_GROUP)),
        ),
        'list_type'             => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'group_id'              => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'emails'                => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'memberroles'           => array(Zend_Filter_Input::ALLOW_EMPTY => true),

        // tine 2.0 generic fields
        'tags'                  => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'notes'                 => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'relations'             => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'customfields'          => array(Zend_Filter_Input::ALLOW_EMPTY => true, Zend_Filter_Input::DEFAULT_VALUE => array()),
        'paths'                 => array(Zend_Filter_Input::ALLOW_EMPTY => true),
    );
    
    /**
     * name of fields containing datetime or or an array of datetime information
     *
     * @var array list of datetime fields
     */
    protected $_datetimeFields = array(
        'creation_time',
        'last_modified_time',
        'deleted_time'
    );

    /**
     * @return array
     */
    static public function getManageAccountFields()
    {
        return self::$_manageAccountsFields;
    }

    /**
     * converts a string or Addressbook_Model_List to a list id
     *
     * @param   string|Addressbook_Model_List  $_listId  the contact id to convert
     * 
     * @return  string
     * @throws  UnexpectedValueException  if no list id set 
     */
    static public function convertListIdToInt($_listId)
    {
        if ($_listId instanceof self) {
            if ($_listId->getId() == null) {
                throw new UnexpectedValueException('No identifier set.');
            }
            $id = (string) $_listId->getId();
        } else {
            $id = (string) $_listId;
        }
        
        if (empty($id)) {
            throw new UnexpectedValueException('Identifier can not be empty.');
        }
        
        return $id;
    }

    /**
     * returns an array containing the parent neighbours relation objects or record(s) (ids) in the key 'parents'
     * and containing the children neighbours in the key 'children'
     *
     * @return array
     */
    public function getPathNeighbours()
    {
        if (!empty($this->members)) {
            foreach(Addressbook_Controller_Contact::getInstance()->getMultiple($this->members, true) as $member) {
                $members[$member->getId()] = $member;
            }
        } else {
            $members = array();
        }

        if (!empty($this->memberroles)) {

            $listRoles = array();
            /** @var Addressbook_Model_ListMemberRole $role */
            foreach($this->memberroles as $role)
            {
                $listRoles[$role->list_role_id] = $role->list_role_id;
                if (isset($members[$role->contact_id])) {
                    unset($members[$role->contact_id]);
                }
            }

            $pathController = Tinebase_Record_Path::getInstance();
            $pathController->addAfterRebuildQueueHook(array(array('Addressbook_Model_ListRole', 'setParent')));
            Addressbook_Model_ListRole::setParent($this);

            $memberRoles = Addressbook_Controller_ListRole::getInstance()->getMultiple($listRoles, true)->asArray();
            foreach($memberRoles as $memberRole) {
                $pathController->addToRebuildQueue(array($memberRole));
                $members[] = $memberRole;
            }

        }

        $result = array(
            'parents' => array(),
            'children' => $members
        );

        return $result;
    }
}
