<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tests
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2013-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */

/**
 * Test helper
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Abstract test class
 * 
 * @package     Tests
 *
 * TODO separation of concerns: split into multiple classes/traits with cleanup / fixture / ... functionality
 */
abstract class TestCase extends PHPUnit_Framework_TestCase
{
    /**
     * transaction id if test is wrapped in an transaction
     * 
     * @var string
     */
    protected $_transactionId = null;
    
    /**
     * usernames to be deleted (in sync backend)
     * 
     * @var array
     */
    protected $_usernamesToDelete = array();
    
    /**
     * groups (ID) to be deleted (in sync backend)
     * 
     * @var array
     */
    protected $_groupIdsToDelete = array();
    
    /**
     * remove group members, too when deleting groups
     * 
     * @var boolean
     */
    protected $_removeGroupMembers = true;
    
    /**
     * invalidate roles cache
     * 
     * @var boolean
     */
    protected $_invalidateRolesCache = false;
    
    /**
     * test personas
     * 
     * @var array
     */
    protected $_personas = [];
    
    /**
     * unit in test
     *
     * @var Object
     */
    protected $_uit = null;
    
    /**
     * the test user
     *
     * @var Tinebase_Model_FullUser
     */
    protected $_originalTestUser;
    
    /**
     * the mailer
     * 
     * @var Zend_Mail_Transport_Abstract
     */
    protected static $_mailer = null;

    /**
     * db lock ids to be released
     *
     * @var array
     */
    protected $_releaseDBLockIds = array();

    /**
     * customfields that should be deleted later
     *
     * @var array
     */
    protected $_customfieldIdsToDelete = array();

    /**
     * set up tests
     */
    protected function setUp()
    {
        foreach ($this->_customfieldIdsToDelete as $cfd) {
            Tinebase_CustomField::getInstance()->deleteCustomField($cfd);
        }

        $this->_transactionId = Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());
        
        Addressbook_Controller_Contact::getInstance()->setGeoDataForContacts(false);

        if (Zend_Registry::isRegistered('personas')) {
            $this->_personas = Zend_Registry::get('personas');
        }
        
        $this->_originalTestUser = Tinebase_Core::getUser();
    }
    
    /**
     * tear down tests
     */
    protected function tearDown()
    {
        if (in_array(Tinebase_User::getConfiguredBackend(), array(Tinebase_User::LDAP, Tinebase_User::ACTIVEDIRECTORY))) {
            $this->_deleteUsers();
            $this->_deleteGroups();
        }
        if ($this->_transactionId) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))
                Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Rolling back test transaction');
            Tinebase_TransactionManager::getInstance()->rollBack();
        }
        
        Addressbook_Controller_Contact::getInstance()->setGeoDataForContacts(true);

        if ($this->_originalTestUser instanceof Tinebase_Model_User) {
            Tinebase_Core::set(Tinebase_Core::USER, $this->_originalTestUser);
        }
        
        if ($this->_invalidateRolesCache) {
            Tinebase_Acl_Roles::getInstance()->resetClassCache();
        }

        Tinebase_Cache_PerRequest::getInstance()->reset();

        $this->_releaseDBLocks();
    }

    /**
     * release db locks
     */
    protected function _releaseDBLocks()
    {
        foreach ($this->_releaseDBLockIds as $lockId) {
            Tinebase_Lock::releaseDBSessionLock($lockId);
        }

        $this->_releaseDBLockIds = array();
    }

    /**
     * tear down after test class
     */
    public static function tearDownAfterClass()
    {
        Tinebase_Core::getDbProfiling();
    }
    
    /**
     * test needs transaction
     */
    protected function _testNeedsTransaction()
    {
        if ($this->_transactionId) {
            Tinebase_TransactionManager::getInstance()->commitTransaction($this->_transactionId);
            $this->_transactionId = null;
        }
    }
    
    /**
     * get tag
     *
     * @param string $tagType
     * @param string $tagName
     * @param array $contexts
     * @return Tinebase_Model_Tag
     */
    protected function _getTag($tagType = Tinebase_Model_Tag::TYPE_SHARED, $tagName = NULL, $contexts = NULL)
    {
        if ($tagName) {
            try {
                $tag = Tinebase_Tags::getInstance()->getTagByName($tagName);
                return $tag;
            } catch (Tinebase_Exception_NotFound $tenf) {
            }
        } else {
            $tagName = Tinebase_Record_Abstract::generateUID();
        }
    
        $targ = array(
            'type'          => $tagType,
            'name'          => $tagName,
            'description'   => 'testTagDescription',
            'color'         => '#009B31',
        );
    
        if ($contexts) {
            $targ['contexts'] = $contexts;
        }
    
        return new Tinebase_Model_Tag($targ);
    }
    
    /**
     * delete groups and their members
     * 
     * - also deletes groups and users in sync backends
     */
    protected function _deleteGroups()
    {
        if (! is_array($this->_groupIdsToDelete)) {
            return;
        }

        foreach ($this->_groupIdsToDelete as $groupId) {
            if ($this->_removeGroupMembers) {
                foreach (Tinebase_Group::getInstance()->getGroupMembers($groupId) as $userId) {
                    try {
                        Tinebase_User::getInstance()->deleteUser($userId);
                    } catch (Exception $e) {
                        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                            . ' error while deleting user: ' . $e->getMessage());
                    }
                }
            }
            try {
                Tinebase_Group::getInstance()->deleteGroups($groupId);
            } catch (Exception $e) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' error while deleting group: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * delete users
     */
    protected function _deleteUsers()
    {
        foreach ($this->_usernamesToDelete as $username) {
            try {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Trying to delete user: ' . $username);

                Tinebase_User::getInstance()->deleteUser(Tinebase_User::getInstance()->getUserByLoginName($username));
            } catch (Exception $e) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Error while deleting user: ' . $e->getMessage());
            }
        }
    }

    /**
     * removes records and their relations
     *
     * @param Tinebase_Record_RecordSet $records
     * @param array $modelsToDelete
     * @param array $typesToDelete
     */
    protected function _deleteRecordRelations($records, $modelsToDelete = array(), $typesToDelete = array())
    {
        $controller = Tinebase_Core::getApplicationInstance($records->getRecordClassName());

        if (! method_exists($controller, 'deleteLinkedRelations')) {
            return;
        }

        foreach ($records as $record) {
            $controller->deleteLinkedRelations($record, $modelsToDelete, $typesToDelete);
        }
    }

    /**
     * get personal container
     * 
     * @param string $applicationName
     * @param Tinebase_Model_User $user
     * @return Tinebase_Model_Container
     */
    protected function _getPersonalContainer($applicationName, $user = null)
    {
        if ($user === null) {
            $user = Tinebase_Core::getUser();
        }

        /** @var Tinebase_Model_Container $personalContainer */
        $personalContainer = Tinebase_Container::getInstance()->getPersonalContainer(
            $user,
            $applicationName, 
            $user,
            Tinebase_Model_Grants::GRANT_EDIT
        )->getFirstRecord();

        if (! $personalContainer) {
            throw new Tinebase_Exception_UnexpectedValue('no personal container found!');
        }

        return $personalContainer;
    }
    
    /**
     * get test container
     * 
     * @param string $applicationName
     * @param string $model
     * @return Tinebase_Model_Container
     */
    protected function _getTestContainer($applicationName, $model = null)
    {
        return Tinebase_Container::getInstance()->addContainer(new Tinebase_Model_Container(array(
            'name'           => 'PHPUnit ' . $model .' container',
            'type'           => Tinebase_Model_Container::TYPE_PERSONAL,
            'owner_id'       => Tinebase_Core::getUser(),
            'backend'        => 'Sql',
            'application_id' => Tinebase_Application::getInstance()->getApplicationByName($applicationName)->getId(),
            'model'          => $model,
        ), true));
    }
    
    /**
     * get test mail domain
     * 
     * @return string
     */
    protected function _getMailDomain()
    {
        return TestServer::getPrimaryMailDomain();
    }
    
    /**
     * get test user email address
     * 
     * @return string test user email address
     */
    protected function _getEmailAddress()
    {
        $testConfig = TestServer::getInstance()->getConfig();
        return ($testConfig->email) ? $testConfig->email : Tinebase_Core::getUser()->accountEmailAddress;
    }
    
    /**
     * lazy init of uit
     * 
     * @return Object
     * @throws Exception
     * 
     * @todo fix ide object class detection for completions
     */
    protected function _getUit()
    {
        if ($this->_uit === null) {
            $uitClass = preg_replace('/Tests{0,1}$/', '', get_class($this));
            if (@method_exists($uitClass, 'getInstance')) {
                $this->_uit = call_user_func($uitClass . '::getInstance');
            } else if (@class_exists($uitClass)) {
                $this->_uit = new $uitClass();
            } else {
                // use generic json frontend
                if ($pos = strpos($uitClass, '_')) {
                    $appName = substr($uitClass, 0, $pos);
                    $this->_uit = new Tinebase_Frontend_Json_Generic($appName);
                } else {
                    throw new Exception('could not find class ' . $uitClass);
                }
            }
        }
        
        return $this->_uit;
    }
    
    /**
     * get messages
     * 
     * @return array
     */
    public static function getMessages()
    {
        // make sure messages are sent if queue is activated
        if (isset(Tinebase_Core::getConfig()->actionqueue)) {
            Tinebase_ActionQueue::getInstance()->processQueue();
        }

        /** @noinspection PhpUndefinedMethodInspection */
        return self::getMailer()->getMessages();
    }
    
    /**
     * get mailer
     * 
     * @return Zend_Mail_Transport_Abstract
     */
    public static function getMailer()
    {
        if (! self::$_mailer) {
            self::$_mailer = Tinebase_Smtp::getDefaultTransport();
        }
        
        return self::$_mailer;
    }
    
    /**
     * flush mailer (send all remaining mails first)
     */
    public static function flushMailer()
    {
        // make sure all messages are sent if queue is activated
        if (isset(Tinebase_Core::getConfig()->actionqueue)) {
            Tinebase_ActionQueue::getInstance()->processQueue();
        }

        /** @noinspection PhpUndefinedMethodInspection */
        self::getMailer()->flush();
    }
    
    /**
     * returns the content.xml of an ods document
     * 
     * @param string $filename
     * @return SimpleXMLElement
     */
    protected function _getContentXML($filename)
    {
        $zipHandler = zip_open($filename);
        
        do {
            $entry = zip_read($zipHandler);
        } while ($entry && zip_entry_name($entry) != "content.xml");
        
        // open entry
        zip_entry_open($zipHandler, $entry, "r");
        
        // read entry
        $entryContent = zip_entry_read($entry, zip_entry_filesize($entry));
        
        $xml = simplexml_load_string($entryContent);
        zip_close($zipHandler);
        
        return $xml;
    }
    
    /**
     * get test temp file
     * 
     * @return Tinebase_Model_TempFile
     */
    protected function _getTempFile()
    {
        $tempFileBackend = new Tinebase_TempFile();
        $tempFile = $tempFileBackend->createTempFile(dirname(__FILE__) . '/Filemanager/files/test.txt');
        return $tempFile;
    }
    
    /**
     * remove right in all users roles
     * 
     * @param string $applicationName
     * @param string $rightToRemove
     * @param boolean $removeAdminRight
     * @return array original role rights by role id
     */
    protected function _removeRoleRight($applicationName, $rightToRemove, $removeAdminRight = true)
    {
        $app = Tinebase_Application::getInstance()->getApplicationByName($applicationName);
        $rolesOfUser = Tinebase_Acl_Roles::getInstance()->getRoleMemberships(Tinebase_Core::getUser()->getId());
        $this->_invalidateRolesCache = true;

        $roleRights = array();
        foreach ($rolesOfUser as $roleId) {
            $roleRights[$roleId] = $rights = Tinebase_Acl_Roles::getInstance()->getRoleRights($roleId);
            foreach ($rights as $idx => $right) {
                if ($right['application_id'] === $app->getId() && ($right['right'] === $rightToRemove || (
                    true === $removeAdminRight && $right['right'] === Tinebase_Acl_Rights_Abstract::ADMIN))) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                        . ' Removing right ' . $right['right'] . ' from app ' . $applicationName . ' in role (id) ' . $roleId);
                    unset($rights[$idx]);
                }
            }
            Tinebase_Acl_Roles::getInstance()->setRoleRights($roleId, $rights);
        }
        
        return $roleRights;
    }
    
    /**
     * set grants for a persona and the current user
     * 
     * @param integer $containerId
     * @param string $persona
     * @param boolean $personaAdminGrant
     * @param boolean $userAdminGrant
     */
    protected function _setPersonaGrantsForTestContainer($containerId, $persona, $personaAdminGrant = false, $userAdminGrant = true)
    {
        $grants = new Tinebase_Record_RecordSet('Tinebase_Model_Grants', array(array(
            'account_id'    => $this->_personas[$persona]->getId(),
            'account_type'  => 'user',
            Tinebase_Model_Grants::GRANT_READ     => true,
            Tinebase_Model_Grants::GRANT_ADD      => true,
            Tinebase_Model_Grants::GRANT_EDIT     => true,
            Tinebase_Model_Grants::GRANT_DELETE   => true,
            Tinebase_Model_Grants::GRANT_ADMIN    => $personaAdminGrant,
        ), array(
            'account_id'    => Tinebase_Core::getUser()->getId(),
            'account_type'  => 'user',
            Tinebase_Model_Grants::GRANT_READ     => true,
            Tinebase_Model_Grants::GRANT_ADD      => true,
            Tinebase_Model_Grants::GRANT_EDIT     => true,
            Tinebase_Model_Grants::GRANT_DELETE   => true,
            Tinebase_Model_Grants::GRANT_ADMIN    => $userAdminGrant,
        )));
        
        Tinebase_Container::getInstance()->setGrants($containerId, $grants, TRUE);
    }

    /**
     * set current user
     *
     * @param $user
     * @throws Tinebase_Exception_InvalidArgument
     */
    protected function _setUser($user)
    {
        Tinebase_Core::set(Tinebase_Core::USER, $user);
    }

    /**
     * call handle cli function with params
     *
     * @param string $command
     * @param array $_params
     * @return string
     */
    protected function _cliHelper($command, $_params)
    {
        $opts = new Zend_Console_Getopt(array($command => $command));
        $opts->setArguments($_params);
        ob_start();
        $this->_cli->handle($opts, false);
        $out = ob_get_clean();
        return $out;
    }


    /**
     * test record json api
     *
     * @param string $modelName
     * @param string $nameField
     * @param string $descriptionField
     * @param bool $delete
     * @param array $recordData
     * @param bool $description
     * @return array
     * @throws Exception
     */
    protected function _testSimpleRecordApi(
        $modelName,
        $nameField = 'name',
        $descriptionField = 'description',
        $delete = true,
        $recordData = [],
        $description = true
    ) {
        $uit = $this->_getUit();
        if (!$uit instanceof Tinebase_Frontend_Json_Abstract) {
            throw new Exception('only allowed for json frontend tests suites');
        }

        $newRecord = array(
            $nameField => 'my test ' . $modelName
        );

        if ($description) {
            $newRecord[$descriptionField] = 'my test description';
        }

        $classParts = explode('_', get_called_class());
        $realModelName = $classParts[0] . '_Model_' . $modelName;
        /** @var Tinebase_Record_Abstract $realModelName */
        $configuration = $realModelName::getConfiguration();

        $savedRecord = call_user_func(array($uit, 'save' . $modelName), array_merge($newRecord, $recordData));
        $this->assertEquals('my test ' . $modelName, $savedRecord[$nameField], print_r($savedRecord, true));
        if (null !== $configuration && $configuration->modlogActive) {
            $this->assertTrue(isset($savedRecord['created_by']['accountId']), 'created_by not present: ' .
                print_r($savedRecord));
            $this->assertEquals(Tinebase_Core::getUser()->getId(), $savedRecord['created_by']['accountId'],
                'created_by has wrong value: ' . print_r($savedRecord));
        }

        // Update description if record has
        if ($description) {
            $savedRecord[$descriptionField] = 'my updated description';
            $updatedRecord = call_user_func(array($uit, 'save' . $modelName), $savedRecord);
            $this->assertEquals('my updated description', $updatedRecord[$descriptionField]);
        }

        if (!$description) {
            $updatedRecord = $savedRecord;
        }

        // update name as well!
        $updatedRecord[$nameField] = 'my updated namefield';
        $updatedRecord = call_user_func(array($uit, 'save' . $modelName), $updatedRecord);
        $this->assertEquals('my updated namefield', $updatedRecord[$nameField]);

        $filter = array(array('field' => 'id', 'operator' => 'equals', 'value' => $updatedRecord['id']));
        $result = call_user_func(array($uit, 'search' . $modelName . 's'), $filter, array());
        $this->assertEquals(1, $result['totalcount']);

        if (null !== $configuration && $configuration->modlogActive) {
            $this->assertTrue(isset($result['results'][0]['last_modified_by']['accountId']),
                'last_modified_by not present: ' . print_r($result));
            $this->assertEquals(Tinebase_Core::getUser()->getId(), $result['results'][0]['last_modified_by']['accountId'],
                'last_modified_by has wrong value: ' . print_r($result));
        }

        if ($delete) {
            call_user_func(array($uit, 'delete' . $modelName . 's'), array($updatedRecord['id']));
            try {
                call_user_func(array($uit, 'get' . $modelName), $updatedRecord['id']);
                $this->fail('should delete Record');
            } catch (Tinebase_Exception_NotFound $tenf) {
                $this->assertTrue($tenf instanceof Tinebase_Exception_NotFound);
            }
        }

        return $updatedRecord;
    }

    /**
     * returns true if main db adapter is postgresql
     *
     * @return bool
     */
    protected function _dbIsPgsql()
    {
        $db = Tinebase_Core::getDb();
        return ($db instanceof Zend_Db_Adapter_Pdo_Pgsql);
    }

    /**
     * get custom field record
     *
     * @param string $name
     * @param string $model
     * @param string $type
     * @return Tinebase_Model_CustomField_Config
     *
     * TODO use a single array as param that is merged with the defaults
     */
    protected function _createCustomField($name = 'YomiName', $model = 'Addressbook_Model_Contact', $type = 'string')
    {
        $application = substr($model, 0, strpos($model, '_'));
        $cfData = new Tinebase_Model_CustomField_Config(array(
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName($application)->getId(),
            'name'              => $name,
            'model'             => $model,
            'definition'        => array(
                'label' => Tinebase_Record_Abstract::generateUID(),
                'type'  => $type,
                'recordConfig' => $type === 'record'
                    ? array('value' => array('records' => 'Tine.Addressbook.Model.Contact'))
                    : null,
                'uiconfig' => array(
                    'xtype'  => Tinebase_Record_Abstract::generateUID(),
                    'length' => 10,
                    'group'  => 'unittest',
                    'order'  => 100,
                )
            )
        ));

        try {
            $result = Tinebase_CustomField::getInstance()->addCustomField($cfData);
            $this->_customfieldIdsToDelete[] = $result->getId();
        } catch (Zend_Db_Statement_Exception $zdse) {
            // customfield already exists
            $cfs = Tinebase_CustomField::getInstance()->getCustomFieldsForApplication($application);
            $result = $cfs->filter('name', $name)->getFirstRecord();
        }

        return $result;
    }

    /**
     * returns a test user object
     *
     * @return Tinebase_Model_FullUser
     */
    public static function getTestUser()
    {
        $emailDomain = TestServer::getPrimaryMailDomain();

        $user  = new Tinebase_Model_FullUser(array(
            'accountLoginName'      => 'tine20phpunituser',
            'accountStatus'         => 'enabled',
            'accountExpires'        => NULL,
            'accountPrimaryGroup'   => Tinebase_Group::getInstance()->getDefaultGroup()->id,
            'accountLastName'       => 'Tine 2.0',
            'accountFirstName'      => 'PHPUnit User',
            'accountEmailAddress'   => 'phpunit@' . $emailDomain,
        ));

        return $user;
    }

    /**
     * return persona/testuser (stat)path
     *
     * @param string|Tinebase_Model_User $persona
     * @param string $appName
     * @return string
     */
    protected function _getPersonalPath($persona = 'sclever', $appName = 'Filemanager')
    {
        if ($persona instanceof Tinebase_Model_User) {
            $userId = $persona->getId();
        } elseif (is_string($persona)) {
            $userId = $this->_personas[$persona]->getId();
        } else {
            $userId = '';
        }
        return Tinebase_FileSystem::getInstance()->getApplicationBasePath(
            $appName,
            Tinebase_FileSystem::FOLDER_TYPE_PERSONAL
        ) . '/' . $userId;
    }
}
