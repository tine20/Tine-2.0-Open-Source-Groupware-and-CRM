<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tinebase
 * @subpackage  User
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Tinebase_User_EmailUser_Imap_LdapDbmailSchemaTest::main');
}

/**
 * Test class for Tinebase_Group
 */
class Tinebase_User_EmailUser_Imap_LdapDbmailSchemaTest extends PHPUnit_Framework_TestCase
{
    /**
     * ldap group backend
     *
     * @var Tinebase_User_LDAP
     */
    protected $_backend = NULL;
        
    /**
     * @var array test objects
     */
    protected $objects = array();
    
    protected $_config;

    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Tinebase_User_SqlTest');
        PHPUnit_TextUI_TestRunner::run($suite);
    }

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        if (Tinebase_User::getConfiguredBackend() !== Tinebase_User::LDAP) {
            $this->markTestSkipped('LDAP backend not enabled');
        }
        
        $this->_backend = Tinebase_User::factory(Tinebase_User::LDAP);
        
        if (!array_key_exists('Tinebase_EmailUser_Imap_LdapDbmailSchema', $this->_backend->getPlugins())) {
            $this->markTestSkipped('Dbmail LDAP plugin not enabled');
        }
        
        $this->_config = Tinebase_Config::getInstance()->get(Tinebase_Config::IMAP, new Tinebase_Config_Struct())->toArray();
        
        $this->objects['users'] = array();
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
        foreach ($this->objects['users'] as $user) {
            $this->_backend->deleteUser($user);
        }
    }
    
    /**
     * try to add an user
     * 
     * @return Tinebase_Model_FullUser
     */
    public function testAddUser()
    {
        $user = TestCase::getTestUser();
        $user->imapUser = new Tinebase_Model_EmailUser(array(
            'emailMailQuota' => 1000
        ));
        
        $testUser = $this->_backend->addUser($user);
        $this->objects['users']['testUser'] = $testUser;

        #var_dump($testUser->toArray());
        #var_dump($this->_config);
        
        $this->assertEquals(array(
            'emailUID'         => (empty($this->_config['domain'])) ? $user->accountLoginName : $user->accountLoginName . '@' . $this->_config['domain'],
            'emailGID'         => sprintf("%u", crc32(Tinebase_Application::getInstance()->getApplicationByName('Tinebase')->getId())),
            'emailMailQuota'   => 1000,
            'emailForwardOnly' => 0
        ), $testUser->imapUser->toArray());
        
        return $user;
    }
    
    /**
     * try to update an user
     *
     */
    public function testUpdateUser()
    {
        $user = $this->testAddUser();
        $user->accountEmailAddress = null;
        
        $testUser = $this->_backend->updateUser($user);
        
        #var_dump($testUser->toArray());
        
        $this->assertEquals(array(
            'emailMailQuota'   => 500,
            'emailForwardOnly' => 0
        ), $testUser->imapUser->toArray());
    }
    
    /**
     * try to enable an account
     *
     */
    public function testSetStatus()
    {
        $user = $this->testAddUser();

        $this->_backend->setStatus($user, Tinebase_User::STATUS_DISABLED);
        
        $testUser = $this->_backend->getUserById($user, 'Tinebase_Model_FullUser');
        
        $this->assertEquals(Tinebase_User::STATUS_DISABLED, $testUser->accountStatus);
        
        $this->_backend->setStatus($user, Tinebase_User::STATUS_ENABLED);
        
        $testUser = $this->_backend->getUserById($user, 'Tinebase_Model_FullUser');
        
        $this->assertEquals(Tinebase_User::STATUS_ENABLED, $testUser->accountStatus);
    }
    
}        
    

if (PHPUnit_MAIN_METHOD == 'Tinebase_User_EmailUser_Imap_LdapDbmailSchemaTest::main') {
    Tinebase_Group_SqlTest::main();
}
