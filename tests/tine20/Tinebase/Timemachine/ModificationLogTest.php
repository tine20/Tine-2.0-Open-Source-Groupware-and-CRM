<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tinebase
 * @subpackage  Record
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2008-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for Tinebase_Group
 */
class Tinebase_Timemachine_ModificationLogTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Tinebase_Timemachine_ModificationLog
     */
    protected $_modLogClass;

    /**
     * @var Tinebase_Record_RecordSet
     */
    protected $_logEntries;

    /**
     * @var Tinebase_Record_RecordSet
     * Persistant Records we need to cleanup at tearDown()
     */
    protected $_persistantLogEntries;

    /**
     * @var array holds recordId's we create log entries for
     */
    protected $_recordIds = array();


    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite = new PHPUnit_Framework_TestSuite('Tinebase_Timemachine_ModificationLogTest');
        PHPUnit_TextUI_TestRunner::run($suite);
    }

    /**
     * Lets update a record tree times
     *
     * @access protected
     */
    protected function setUp()
    {
        Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());

        $now = new Tinebase_DateTime();
        $this->_modLogClass = Tinebase_Timemachine_ModificationLog::getInstance();
        $this->_persistantLogEntries = new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog');
        $this->_recordIds = array('5dea69be9c72ea3d263613277c3b02d529fbd8bc');

        $tinebaseApp = Tinebase_Application::getInstance()->getApplicationByName('Tinebase');

        $this->_logEntries = new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array(
            array(
                'application_id' => $tinebaseApp,
                'record_id' => $this->_recordIds[0],
                'record_type' => 'TestType',
                'record_backend' => 'TestBackend',
                'modification_time' => $this->_cloner($now)->addDay(-2),
                'modification_account' => 7,
                'modified_attribute' => 'FirstTestAttribute',
                'old_value' => 'Hamburg',
                'new_value' => 'Bremen',
                'client' => 'unittest'
            ),
            array(
                'application_id' => $tinebaseApp,
                'record_id' => $this->_recordIds[0],
                'record_type' => 'TestType',
                'record_backend' => 'TestBackend',
                'modification_time' => $this->_cloner($now)->addDay(-1),
                'modification_account' => 7,
                'modified_attribute' => 'FirstTestAttribute',
                'old_value' => 'Bremen',
                'new_value' => 'Frankfurt',
                'client' => 'unittest'
            ),
            array(
                'application_id' => $tinebaseApp,
                'record_id' => $this->_recordIds[0],
                'record_type' => 'TestType',
                'record_backend' => 'TestBackend',
                'modification_time' => $this->_cloner($now),
                'modification_account' => 7,
                'modified_attribute' => 'FirstTestAttribute',
                'old_value' => 'Frankfurt',
                'new_value' => 'Stuttgart',
                'client' => 'unittest'
            ),
            array(
                'application_id' => $tinebaseApp,
                'record_id' => $this->_recordIds[0],
                'record_type' => 'TestType',
                'record_backend' => 'TestBackend',
                'modification_time' => $this->_cloner($now)->addDay(-2),
                'modification_account' => 7,
                'modified_attribute' => 'SecondTestAttribute',
                'old_value' => 'Deutschland',
                'new_value' => 'Östereich',
                'client' => 'unittest'
            ),
            array(
                'application_id' => $tinebaseApp,
                'record_id' => $this->_recordIds[0],
                'record_type' => 'TestType',
                'record_backend' => 'TestBackend',
                'modification_time' => $this->_cloner($now)->addDay(-1)->addSecond(1),
                'modification_account' => 7,
                'modified_attribute' => 'SecondTestAttribute',
                'old_value' => 'Östereich',
                'new_value' => 'Schweitz',
                'client' => 'unittest'
            ),
            array(
                'application_id' => $tinebaseApp->getId(),
                'record_id' => $this->_recordIds[0],
                'record_type' => 'TestType',
                'record_backend' => 'TestBackend',
                'modification_time' => $this->_cloner($now),
                'modification_account' => 7,
                'modified_attribute' => 'SecondTestAttribute',
                'old_value' => 'Schweitz',
                'new_value' => 'Italien',
                'client' => 'unittest'
            )), true, false);

        foreach ($this->_logEntries as $logEntry) {
            $this->_modLogClass->setModification($logEntry);
            $this->_persistantLogEntries->addRecord($logEntry/*$this->_modLogClass->getModification($id)*/);
        }
    }

    /**
     * cleanup database
     * @access protected
     */
    protected function tearDown()
    {
        Tinebase_TransactionManager::getInstance()->rollBack();
    }

    /**
     * tests that the returned mod logs equal the initial ones we defined
     * in this test setup.
     * If this works, also the setting of logs works!
     *
     */
    public function testGetModification()
    {
        foreach ($this->_logEntries as $num => $logEntry) {
            $rawLogEntry = $logEntry->toArray();
            $rawPersistantLogEntry = $this->_persistantLogEntries[$num]->toArray();

            foreach ($rawLogEntry as $field => $value) {
                $persistantValue = $rawPersistantLogEntry[$field];
                if ($value != $persistantValue) {
                    $this->fail("Failed asserting that contents of saved LogEntry #$num in field $field equals initial datas. \n" .
                        "Expected '$value', got '$persistantValue'");
                }
            }
        }
        $this->assertTrue(true);
    }

    /**
     * tests computation of a records differences described by a set of modification logs
     */
    public function testComputeDiff()
    {
        $diff = $this->_modLogClass->computeDiff($this->_persistantLogEntries);
        $this->assertEquals(2, count($diff->diff)); // we changed two attributes
        $changedAttributes = Tinebase_Timemachine_ModificationLog::getModifiedAttributes($this->_persistantLogEntries);
        foreach ($changedAttributes as $attrb) {
            switch ($attrb) {
                case 'FirstTestAttribute':
                    $this->assertEquals('Hamburg', $diff->oldData[$attrb]);
                    $this->assertEquals('Stuttgart', $diff->diff[$attrb]);
                    break;
                case 'SecondTestAttribute':
                    $this->assertEquals('Deutschland', $diff->oldData[$attrb]);
                    $this->assertEquals('Italien', $diff->diff[$attrb]);
            }
        }
    }

    /**
     * get modifications test
     */
    public function testGetModifications()
    {
        $testBase = array(
            'record_id' => '5dea69be9c72ea3d263613277c3b02d529fbd8bc',
            'type' => 'TestType',
            'backend' => 'TestBackend'
        );
        $firstModificationTime = $this->_persistantLogEntries[0]->modification_time;
        $lastModificationTime = $this->_persistantLogEntries[count($this->_persistantLogEntries) - 1]->modification_time;

        $toTest[] = $testBase + array(
                'from_add' => 'addDay,-3',
                'until_add' => 'addDay,1',
                'nums' => 6
            );
        $toTest[] = $testBase + array(
                'nums' => 4
            );
        $toTest[] = $testBase + array(
                'account' => Tinebase_Record_Abstract::generateUID(),
                'nums' => 0
            );

        foreach ($toTest as $params) {
            $from = clone $firstModificationTime;
            $until = clone $lastModificationTime;

            if (isset($params['from_add'])) {
                list($fn, $p) = explode(',', $params['from_add']);
                $from->$fn($p);
            }
            if (isset($params['until_add'])) {
                list($fn, $p) = explode(',', $params['until_add']);
                $until->$fn($p);
            }

            $account = isset($params['account']) ? $params['account'] : NULL;
            $diffs = $this->_modLogClass->getModifications('Tinebase', $params['record_id'], $params['type'], $params['backend'], $from, $until, $account);
            $count = 0;
            foreach ($diffs as $diff) {
                if ($diff->record_id == $params['record_id']) {
                    $count++;
                }
            }
            $this->assertEquals($params['nums'], $diffs->count());
        }
    }

    /**
     * test modlog undo
     *
     * @see 0006252: allow to undo history items (modlog)
     * @see 0000554: modlog: records can't be updated in less than 1 second intervals
     */
    public function testUndo()
    {
        // create a record
        $contact = Addressbook_Controller_Contact::getInstance()->create(new Addressbook_Model_Contact(array(
            'n_family' => 'tester',
            'tel_cell' => '+491234',
        )));
        // change something using the record controller
        $contact->tel_cell = NULL;
        $contact = Addressbook_Controller_Contact::getInstance()->update($contact);

        // fetch modlog and test seq
        /** @var Tinebase_Model_ModificationLog $modlog */
        $modlog = $this->_modLogClass->getModifications('Addressbook', $contact->getId(), NULL, 'Sql',
            Tinebase_DateTime::now()->subSecond(5), Tinebase_DateTime::now())->getLastRecord();
        $diff = new Tinebase_Record_Diff(json_decode($modlog->new_value, true));
        $this->assertTrue($modlog !== NULL);
        $this->assertEquals(2, $modlog->seq);
        $this->assertEquals('+491234', $diff->oldData['tel_cell']);

        // delete
        Addressbook_Controller_Contact::getInstance()->delete($contact->getId());

        $filter = new Tinebase_Model_ModificationLogFilter(array(
            array('field' => 'record_type',         'operator' => 'equals', 'value' => 'Addressbook_Model_Contact'),
            array('field' => 'record_id',           'operator' => 'equals', 'value' => $contact->getId()),
            array('field' => 'modification_time',   'operator' => 'within', 'value' => 'weekThis'),
            array('field' => 'change_type',         'operator' => 'not',    'value' => Tinebase_Timemachine_ModificationLog::CREATED)
        ));

        $result = $this->_modLogClass->undo($filter, true);
        $this->assertEquals(2, $result['totalcount'], 'did not get 2 undone modlog: ' . print_r($result, TRUE));

        // check record after undo
        $contact = Addressbook_Controller_Contact::getInstance()->get($contact);
        $this->assertEquals('+491234', $contact->tel_cell);
    }

    /**
     * purges mod log entries of given recordIds
     *
     * @param mixed [string|array|Tinebase_Record_RecordSet] $_recordIds
     *
     * @todo should be removed when other tests do not need this anymore
     */
    public static function purgeLogs($_recordIds)
    {
        $table = new Tinebase_Db_Table(array('name' => SQL_TABLE_PREFIX . 'timemachine_modlog'));

        foreach ((array)$_recordIds as $recordId) {
            $table->delete($table->getAdapter()->quoteInto('record_id = ?', $recordId));
        }
    }

    /**
     * Workaround as the php clone operator does not return cloned
     * objects right hand sided
     *
     * @param object $_object
     * @return object
     */
    protected function _cloner($_object)
    {
        return clone $_object;
    }

    /**
     * testDateTimeModlog
     *
     * @see 0000996: add changes in relations/linked objects to modlog/history
     */
    public function testDateTimeModlog()
    {
        $task = Tasks_Controller_Task::getInstance()->create(new Tasks_Model_Task(array(
            'summary' => 'test task',
        )));

        $task->due = Tinebase_DateTime::now();
        Tasks_Controller_Task::getInstance()->update($task);

        $task->seq = 1;
        $modlog = $this->_modLogClass->getModificationsBySeq(
            Tinebase_Application::getInstance()->getApplicationByName('Tasks')->getId(),
            $task, 2);

        $diff = new Tinebase_Record_Diff(json_decode($modlog->getFirstRecord()->new_value, true));
        $this->assertEquals(1, count($modlog));
        $this->assertEquals((string)$task->due, (string)($diff->diff['due']), 'new value mismatch: ' . print_r($modlog->toArray(), TRUE));
    }

    public function testGetReplicationModificationsByInstanceSeq()
    {
        $instance_seq = Tinebase_Timemachine_ModificationLog::getInstance()->getMaxInstanceSeq();

        /** @var Tinebase_Acl_Roles $roleController */
        $roleController = Tinebase_Core::getApplicationInstance('Tinebase_Model_Role');
        $this->assertEquals('Tinebase_Acl_Roles', get_class($roleController));

        $role = new Tinebase_Model_Role(array('name' => 'unittest test role'));
        $role = $roleController->create($role);

        $roleController->addRoleMember($role->getId(), array(
                'id' => Tinebase_Core::getUser()->getId(),
                'type' => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER)
        );
        $roleController->addRoleMember($role->getId(), array(
                'id' => 'test1',
                'type' => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER)
        );
        $roleController->addRoleMember($role->getId(), array(
                'id' => 'test2',
                'type' => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER)
        );
        $roleController->removeRoleMember($role->getId(), array(
            'id' => 'test2',
            'type' => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER
        ));

        $role = $roleController->get($role->getId());

        $modifications = Tinebase_Timemachine_ModificationLog::getInstance()->getReplicationModificationsByInstanceSeq($instance_seq);
        $roleModifications = $modifications->filter('record_type', 'Tinebase_Model_Role');
        //$groupModifications = $modifications->filter('record_type', 'Tinebase_Model_Group');
        //$userModifications = $modifications->filter('record_type', '/Tinebase_Model_User.*/', true);

        // rollback
        Tinebase_TransactionManager::getInstance()->rollBack();
        Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());

        $notFound = false;
        try {
            $roleController->get($role->getId());
        } catch (Tinebase_Exception_NotFound $tenf) {
            $notFound = true;
        }
        $this->assertTrue($notFound, 'roll back did not work...');

        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs($roleModifications);
        $this->assertTrue($result, 'applyReplactionModLogs failed');

        $newRole = $roleController->get($role->getId());

        $diff = $role->diff($newRole, array('creation_time', 'created_by', 'last_modified_by', 'last_modified_time'));

        $this->assertTrue($diff->isEmpty(), 'diff should be empty: ' . print_r($diff, true));

        $mod = clone ($roleModifications->getByIndex(2));
        $diff = new Tinebase_Record_Diff(json_decode($mod->new_value, true));
        $rsDiff = new Tinebase_Record_RecordSetDiff($diff->diff['members']);
        $modified = $rsDiff->added;
        $rsDiff->added = array();
        $modified[0]['account_id'] = 'test3';
        $rsDiff->modified = $modified;
        $diffArray = $diff->diff;
        $diffArray['members'] = $rsDiff;
        $diff->diff = $diffArray;
        $mod->new_value = json_encode($diff->toArray());

        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');

        /** @var Tinebase_Model_Role $newRole */
        $newRole = $roleController->get($role->getId());
        $this->assertEquals(1, $newRole->members->filter('account_id', 'test3')->count(), 'record set diff modified didn\'t work, test3 not found');
    }

    public function testGroupReplication()
    {
        $instance_seq = Tinebase_Timemachine_ModificationLog::getInstance()->getMaxInstanceSeq();

        $groupController = Tinebase_Group::getInstance();

        $group = new Tinebase_Model_Group(array('name' => 'unittest test group'));
        $group = $groupController->addGroup($group);

        $groupController->addGroupMember($group->getId(), Tinebase_Core::getUser()->getId());
        $groupController->removeGroupMember($group->getId(), Tinebase_Core::getUser()->getId());
        $group->description = 'test description';
        $group = $groupController->updateGroup($group);
        $groupController->deleteGroups($group->getId());

        $modifications = Tinebase_Timemachine_ModificationLog::getInstance()->getReplicationModificationsByInstanceSeq($instance_seq);
        $groupModifications = $modifications->filter('record_type', 'Tinebase_Model_Group');

        if ($groupController instanceof Tinebase_Group_Interface_SyncAble) {
            $this->assertEquals(0, $groupModifications->count(), ' for syncables group replication should be turned off!');
            // syncables should not create any replication logs, we can skip this test as of here
            return;
        }

        // rollback
        Tinebase_TransactionManager::getInstance()->rollBack();
        Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());

        $notFound = false;
        try {
            $groupController->getGroupById($group->getId());
        } catch (Tinebase_Exception_Record_NotDefined $ternd) {
            $notFound = true;
        }
        $this->assertTrue($notFound, 'roll back did not work...');

        // create the group
        $mod = $groupModifications->getFirstRecord();
        $groupModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $newGroup = $groupController->getGroupById($group->getId());
        $this->assertEquals($group->name, $newGroup->name);
        $this->assertEmpty($groupController->getGroupMembers($newGroup->getId()), 'group members not empty');

        // add group members
        $mod = $groupModifications->getFirstRecord();
        $groupModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $this->assertEquals(1, count($groupController->getGroupMembers($newGroup->getId())), 'group members not created');

        // remove group members
        $mod = $groupModifications->getFirstRecord();
        $groupModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $this->assertEmpty($groupController->getGroupMembers($newGroup->getId()), 'group members not deleted');

        // update group description
        $mod = $groupModifications->getFirstRecord();
        $groupModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $newGroup = $groupController->getGroupById($group->getId());
        $this->assertEquals('test description', $newGroup->description);

        // delete group
        $mod = $groupModifications->getFirstRecord();
        $groupModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $notFound = false;
        try {
            $groupController->getGroupById($group->getId());
        } catch (Tinebase_Exception_Record_NotDefined $ternd) {
            $notFound = true;
        }
        $this->assertTrue($notFound, 'delete group did not work');
    }

    public function testUserReplication()
    {
        $modifications = Tinebase_Timemachine_ModificationLog::getInstance()->getReplicationModificationsByInstanceSeq(-1, 10000);
        if ($modifications->count() > 0) {
            $instance_seq = $modifications->getLastRecord()->instance_seq;
        } else {
            $instance_seq = -1;
        }

        $userController = Tinebase_User::getInstance();

        $newUser = $userController->addUser(Tinebase_User_SqlTest::getTestRecord());
        $userController->setPassword($newUser->getId(), 'ssha256Password');
        $userController->setStatus($newUser->getId(), Tinebase_Model_User::ACCOUNT_STATUS_EXPIRED);
        $userController->setStatus($newUser->getId(), Tinebase_Model_User::ACCOUNT_STATUS_ENABLED);
        $expiryDate = Tinebase_DateTime::now();
        $userController->setExpiryDate($newUser->getId(), $expiryDate);
        /** @var Addressbook_Model_Contact $contact */
        $contact = Addressbook_Controller_Contact::getInstance()->get($newUser->contact_id);
        $contact->n_given = 'shoo';
        Addressbook_Controller_Contact::getInstance()->update($contact);
        $userController->deleteUser($newUser->getId());

        $modifications = Tinebase_Timemachine_ModificationLog::getInstance()->getReplicationModificationsByInstanceSeq($instance_seq);
        $userModifications = $modifications->filter('record_type', '/Tinebase_Model_(Full)?User/', true);

        if ($userController instanceof Tinebase_User_Interface_SyncAble) {
            $this->assertEquals(0, $userModifications->count(), ' for syncables user replication should be turned off!');
            // syncables should not create any replication logs, we can skip this test as of here
            return;
        }

        // rollback
        Tinebase_TransactionManager::getInstance()->rollBack();
        Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());

        $notFound = false;
        try {
            $userController->getUserById($newUser->getId());
        } catch (Tinebase_Exception_NotFound $tenf) {
            $notFound = true;
        }
        $this->assertTrue($notFound, 'roll back did not work...');

        // create the user
        $mod = $userModifications->getFirstRecord();
        $userModifications->removeRecord($mod);
        $mods[] = $mod;
        // set container id
        $mod = $userModifications->getFirstRecord();
        $userModifications->removeRecord($mod);
        $mods[] = $mod;
        // set visibility
        $mod = $userModifications->getFirstRecord();
        $userModifications->removeRecord($mod);
        $mods[] = $mod;
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', $mods));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $replicationUser = $userController->getUserById($newUser->getId(), 'Tinebase_Model_FullUser');
        $this->assertEquals($replicationUser->name, $newUser->name);

        // reset the user pwd
        $mod = $userModifications->getFirstRecord();
        $userModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        /** @var Tinebase_Model_FullUser $replicationUser */
        $replicationUser = $userController->getUserById($newUser->getId(), 'Tinebase_Model_FullUser');
        $authBackend = Tinebase_Auth_Factory::factory('Sql');
        $authBackend->setIdentity($replicationUser->accountLoginName);
        $authBackend->setCredential('ssha256Password');
        $authResult = $authBackend->authenticate();
        $this->assertEquals(Zend_Auth_Result::SUCCESS, $authResult->getCode(), 'changing password did not work');

        // set status to expired
        $mod = $userModifications->getFirstRecord();
        $userModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        /** @var Tinebase_Model_FullUser $replicationUser */
        $replicationUser = $userController->getUserById($newUser->getId(), 'Tinebase_Model_FullUser');
        $this->assertEquals(Tinebase_Model_User::ACCOUNT_STATUS_EXPIRED, $replicationUser->accountStatus);

        // set status to enabled
        $mod = $userModifications->getFirstRecord();
        $userModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        /** @var Tinebase_Model_FullUser $replicationUser */
        $replicationUser = $userController->getUserById($newUser->getId(), 'Tinebase_Model_FullUser');
        $this->assertEquals(Tinebase_Model_User::ACCOUNT_STATUS_ENABLED, $replicationUser->accountStatus);

        // set expiry date
        $mod = $userModifications->getFirstRecord();
        $userModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        /** @var Tinebase_Model_FullUser $replicationUser */
        $replicationUser = $userController->getUserById($newUser->getId(), 'Tinebase_Model_FullUser');
        $this->assertTrue($expiryDate->equals($replicationUser->accountExpires));

        // update contact
        $mod = $userModifications->getFirstRecord();
        $userModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        /** @var Tinebase_Model_FullUser $replicationUser */
        $replicationUser = $userController->getUserById($newUser->getId(), 'Tinebase_Model_FullUser');
        $this->assertEquals('shoo', $replicationUser->accountFirstName);

        // delete user
        $mod = $userModifications->getFirstRecord();
        $userModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $notFound = false;
        try {
            $userController->getUserById($newUser->getId());
        } catch (Tinebase_Exception_NotFound $tenf) {
            $notFound = true;
        }
        $this->assertTrue($notFound, 'delete didn\'t work');
    }

    public function testFileManagerReplication()
    {
        $modifications = Tinebase_Timemachine_ModificationLog::getInstance()->getReplicationModificationsByInstanceSeq(-1, 10000);
        $instance_seq = $modifications->getLastRecord()->instance_seq;

        $testPath = '/' . Tinebase_Model_Container::TYPE_PERSONAL . '/' . Tinebase_Core::getUser()->accountLoginName . '/unittestTestPath';
        $fmController = Filemanager_Controller_Node::getInstance();
        $filesystem = Tinebase_FileSystem::getInstance();

        // create two folders
        $fmController->createNodes(array($testPath, $testPath . '/subfolder'), Tinebase_Model_Tree_Node::TYPE_FOLDER);
        $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath))->statpath);
        // move subfolder to new name
        /*$newSubFolderNode = */$fmController->moveNodes(array($testPath . '/subfolder'), array($testPath . '/newsubfolder'))->getFirstRecord();
        // copy it back to old name
        /*$subFolderNode = */$fmController->copyNodes(array($testPath . '/newsubfolder'), array($testPath . '/subfolder'))->getFirstRecord();

        //this is not supported for folders!
        //$fmController->delete($subFolderNode->getId());

        // delete first newsubfolder, then testpath
        $fmController->deleteNodes(array($testPath . '/newsubfolder', $testPath));

        $modifications = Tinebase_Timemachine_ModificationLog::getInstance()->getReplicationModificationsByInstanceSeq($instance_seq);
        $fmModifications = $modifications->filter('record_type', 'Filemanager_Model_Node');
        $this->assertEquals($modifications->count(), $fmModifications->count(), 'other changes thatn to Filemanager_Model_Node detected');

        // rollback
        Tinebase_TransactionManager::getInstance()->rollBack();
        Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());

        // clean up file system... roll back only delete db entries!

        $notFound = false;
        try {
            $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath))->statpath);
        } catch (Tinebase_Exception_NotFound $tenf) {
            $notFound = true;
        }
        $this->assertTrue($notFound, 'roll back did not work...');

        // create first folder
        $mod = $fmModifications->getFirstRecord();
        $fmModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath))->statpath);

        // create second folder
        $mod = $fmModifications->getFirstRecord();
        $fmModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath . '/subfolder'))->statpath);

        // move subfolder to new name
        $mod = $fmModifications->getFirstRecord();
        $fmModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath . '/newsubfolder'))->statpath);
        $notFound = false;
        try {
            $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath . '/subfolder'))->statpath);
        } catch (Tinebase_Exception_NotFound $tenf) {
            $notFound = true;
        }
        $this->assertTrue($notFound, 'move did not work...');

        // copy it back to old name
        $mod = $fmModifications->getFirstRecord();
        $fmModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath . '/newsubfolder'))->statpath);
        $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath . '/subfolder'))->statpath);

        // delete new subfolder
        $mod = $fmModifications->getFirstRecord();
        $fmModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath . '/subfolder'))->statpath);
        $notFound = false;
        try {
            $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath . '/newsubfolder'))->statpath);
        } catch (Tinebase_Exception_NotFound $tenf) {
            $notFound = true;
        }
        $this->assertTrue($notFound, 'delete did not work...');

        // delete new folder
        $mod = $fmModifications->getFirstRecord();
        $fmModifications->removeRecord($mod);
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->applyReplicationModLogs(new Tinebase_Record_RecordSet('Tinebase_Model_ModificationLog', array($mod)));
        $this->assertTrue($result, 'applyReplactionModLogs failed');
        $notFound = false;
        try {
            $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath . '/subfolder'))->statpath);
        } catch (Tinebase_Exception_NotFound $tenf) {
            $notFound = true;
        }
        $this->assertTrue($notFound, 'delete did not work...');
        $notFound = false;
        try {
            $filesystem->stat(Tinebase_Model_Tree_Node_Path::createFromPath($fmController->addBasePath($testPath))->statpath);
        } catch (Tinebase_Exception_NotFound $tenf) {
            $notFound = true;
        }
        $this->assertTrue($notFound, 'delete did not work...');
    }
}
