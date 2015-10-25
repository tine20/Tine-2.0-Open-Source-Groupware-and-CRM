<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2010-2015 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */

/**
 * Test class for Tinebase_Frontend_Cli
 */
class Tinebase_Frontend_CliTest extends TestCase
{
    /**
     * Backend
     *
     * @var Tinebase_Frontend_Cli
     */
    protected $_cli;
    
    /**
     * test user
     * 
     * @var Tinebase_Model_FullUser
     */
    protected $_testUser;
    
    /**
     * user plugins, need to be reset after triggerAsyncEvents run
     * 
     * @var array
     */
    protected $_userPlugins = array();
    
    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        parent::setUp();
        
        $this->_cli = new Tinebase_Frontend_Cli();
        $this->_testUser = Tinebase_Core::getUser();
        $this->_userPlugins = Tinebase_User::getInstance()->getPlugins();
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
        // need to be reset after triggerAsyncEvents run (singleton ...)
        Tinebase_User::getInstance()->unregisterAllPlugins();
        Tinebase_User::getInstance()->registerPlugins($this->_userPlugins);
        
        $currentUser = Tinebase_Core::getUser();
        if ($currentUser->accountLoginName !== $this->_testUser->accountLoginName) {
            Tinebase_Core::set(Tinebase_Core::USER, $this->_testUser);
        }
        
        parent::tearDown();
    }
    
    /**
     * test to clear accesslog table
     */
    public function testClearTableAccessLogWithDate()
    {
        $accessLogsBefore = Admin_Controller_AccessLog::getInstance()->search();
        $opts = $this->_getOpts('access_log');
        
        ob_start();
        $this->_cli->clearTable($opts);
        // TODO check $out
        $out = ob_get_clean();
        
        $accessLogsAfter = Admin_Controller_AccessLog::getInstance()->search();
        $this->assertGreaterThan(count($accessLogsAfter), count($accessLogsBefore));
        $this->assertEquals(0, count($accessLogsAfter));
    }
    
    /**
     * get options
     * 
     * @param string $_table
     * @return Zend_Console_Getopt
     */
    protected function _getOpts($_table = NULL)
    {
        $opts = new Zend_Console_Getopt('abp:');
        $tomorrow = Tinebase_DateTime::now()->addDay(1)->toString('Y-m-d');
        $params = array('date=' . $tomorrow);
        if ($_table !== NULL) {
            $params[] = $_table;
        }
        $opts->setArguments($params);
        
        return $opts;
    }

    /**
     * test purge deleted records
     */
    public function testPurgeDeletedRecordsAddressbook()
    {
        $opts = $this->_getOpts('addressbook');
        $deletedRecord = $this->_addAndDeleteContact();
        
        ob_start();
        $this->_cli->purgeDeletedRecords($opts);
        $out = ob_get_clean();
        
        $this->assertContains('Removing all deleted entries before', $out);
        $this->assertContains('Cleared table addressbook (deleted ', $out);

        $contactBackend = Addressbook_Backend_Factory::factory(Addressbook_Backend_Factory::SQL);
        $this->setExpectedException('Tinebase_Exception_NotFound');
        $contactBackend->get($deletedRecord->getId(), TRUE);
    }

    /**
     * test purge deleted records
     */
    public function testPurgeDeletedRecordsAllTables()
    {
        $opts = $this->_getOpts();
        $deletedContact = $this->_addAndDeleteContact();
        $deletedLead = $this->_addAndDeleteLead();
        
        ob_start();
        $this->_cli->purgeDeletedRecords($opts);
        $out = ob_get_clean();
        
        $this->assertContains('Removing all deleted entries before', $out);
        $this->assertContains('Cleared table addressbook (deleted ', $out);
        $this->assertContains('Cleared table metacrm_lead (deleted ', $out);

        $contactBackend = Addressbook_Backend_Factory::factory(Addressbook_Backend_Factory::SQL);
        $contacts = $contactBackend->getMultipleByProperty($deletedContact->getId(), 'id', TRUE);
        $this->assertEquals(0, count($contacts));

        $leadsBackend = new Crm_Backend_Lead();
        $leads = $leadsBackend->getMultipleByProperty($deletedLead->getId(), 'id', TRUE);
        $this->assertEquals(0, count($leads));
    }
    
    /**
     * creates and deletes a contact + returns the deleted record
     * 
     * @return Addressbook_Model_Contact
     */
    protected function _addAndDeleteContact()
    {
        $newContact = new Addressbook_Model_Contact(array(
            'n_family'          => 'PHPUNIT',
            'container_id'      => Addressbook_Controller_Contact::getInstance()->getDefaultAddressbook()->getId(),
            'tel_cell_private'  => '+49TELCELLPRIVATE',
        ));
        $newContact = Addressbook_Controller_Contact::getInstance()->create($newContact);
        Addressbook_Controller_Contact::getInstance()->delete($newContact->getId());
        
        return $newContact;
    }

    /**
     * creates and deletes a lead + returns the deleted record
     * 
     * @return Crm_Model_Lead
     */
    protected function _addAndDeleteLead()
    {
        $newLead = new Crm_Model_Lead(array(
            'lead_name'     => 'PHPUNIT Lead',
            'container_id'  => Tinebase_Container::getInstance()->getDefaultContainer('Crm')->getId(),
            'leadstate_id'  => 1,
            'leadtype_id'   => 1,
            'leadsource_id' => 1,
            'start'         => Tinebase_DateTime::now(),
        ));
        $newLead = Crm_Controller_Lead::getInstance()->create($newLead);
        Crm_Controller_Lead::getInstance()->delete($newLead->getId());
        
        return $newLead;
    }
    
    /**
     * test trigger events
     */
    public function testTriggerAsyncEvents()
    {
        $opts = new Zend_Console_Getopt('abp:');
        $opts->setArguments(array());
        $this->_usernamesToDelete[] = 'cronuser';
        $this->_releaseDBLockIds[] = 'Tinebase_Frontend_Cli::triggerAsyncEvents';

        ob_start();
        $this->_cli->triggerAsyncEvents($opts);
        $out = ob_get_clean();
        
        $userPlugins = Tinebase_User::getInstance()->getPlugins();
        $this->assertEquals(0, count($userPlugins), 'got user plugins: ' . print_r($userPlugins, true));
        
        $cronuserId = Tinebase_Config::getInstance()->get(Tinebase_Config::CRONUSERID);
        $this->assertTrue(! empty($cronuserId), 'got empty cronuser id');
        $cronuser = Tinebase_User::getInstance()->getFullUserById($cronuserId);
        $this->assertEquals('cronuser', $cronuser->accountLoginName);
        $adminGroup = Tinebase_Group::getInstance()->getDefaultAdminGroup();
        
        $this->assertEquals($adminGroup->getId(), $cronuser->accountPrimaryGroup);
        $this->assertContains('Tine 2.0 scheduler run', $out, $out);
    }

    /**
     * testMonitoringCheckDB
     * 
     * NOTE deactivated this test as it might affect other tests
     * 
     * @todo fix this test / make cli method testable
     */
    public function _testMonitoringCheckDB()
    {
        ob_start();
        $result = $this->_cli->monitoringCheckDB();
        $out = ob_get_clean();
        
        $this->assertEquals("DB CONNECTION OK\n", $out);
        $this->assertEquals(0, $result);
    }

    /**
     * testMonitoringCheckConfig
     */
    public function testMonitoringCheckConfig()
    {
        ob_start();
        $result = $this->_cli->monitoringCheckConfig();
        $out = ob_get_clean();
        
        $this->assertEquals("CONFIG FILE OK\n", $out);
        $this->assertEquals(0, $result);
    }

    /**
     * testMonitoringCheckCron
     */
    public function testMonitoringCheckCron()
    {
        ob_start();
        $result = $this->_cli->monitoringCheckCron();
        $out = ob_get_clean();
        
        $lastJob = Tinebase_AsyncJob::getInstance()->getLastJob('Tinebase_Event_Async_Minutely');
        if ($lastJob) {
            $this->assertContains('CRON OK', $out);
            $this->assertEquals(0, $result);
        } else {
            $this->assertEquals("CRON FAIL: NO LAST JOB FOUND\n", $out);
            $this->assertEquals(1, $result);
        }
    }

    /**
     * testMonitoringLoginNumber
     */
    public function testMonitoringLoginNumber()
    {
        ob_start();
        $result = $this->_cli->monitoringLoginNumber();
        $out = ob_get_clean();
        $this->assertEquals(0, $result);

        preg_match('/LOGINS OK \| count=(\d+);;;;/', $out, $matches);
        $this->assertGreaterThan(1, count($matches));
        $this->assertGreaterThanOrEqual(0, $matches[1]);
    }

    /**
     * testMonitoringActiveUsers
     *
     * TODO generalize monitoring tests
     */
    public function testMonitoringActiveUsers()
    {
        ob_start();
        $result = $this->_cli->monitoringActiveUsers();
        $out = ob_get_clean();
        $this->assertEquals(0, $result);

        preg_match('/ACTIVE USERS OK \| count=(\d+);;;;/', $out, $matches);
        $this->assertGreaterThan(1, count($matches));
        $this->assertGreaterThanOrEqual(1, $matches[1], 'at least unittest user should have logged in once');
    }
}
