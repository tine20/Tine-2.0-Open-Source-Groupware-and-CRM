<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tinebase
 * @subpackage  Container
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2008-2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Tinebase_Frontend_Json_ContainerTest::main');
}

/**
 * Test class for Tinebase_Group
 */
class Tinebase_Frontend_Json_ContainerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Tinebase_Frontend_Json_Container
     */
    protected $_backend = NULL;
    
    /**
     * @var array test objects
     */
    protected $objects = array();

    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Tinebase_Frontend_Json_ContainerTest');
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
        $this->_backend = new Tinebase_Frontend_Json_Container();
        
        try {
            $container = Tinebase_Container::getInstance()->getContainerByName('Addressbook', 'Tine 2.0 Unittest', Tinebase_Model_Container::TYPE_PERSONAL, Tinebase_Core::getUser());
            Tinebase_Container::getInstance()->deleteContainer($container);
        } catch (Exception $e) {
            // do nothing
        }
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
    
    }
    
    /**
     * try to add an account
     *
     */
    public function testAddContainer()
    {
        $container = $this->_backend->addContainer('Addressbook', 'Tine 2.0 Unittest', Tinebase_Model_Container::TYPE_PERSONAL);

        $this->assertEquals('Tine 2.0 Unittest', $container['name']);
        $this->assertEquals('Addressbook_Model_Contact', $container['model']);
        $this->assertTrue($container['account_grants'][Tinebase_Model_Grants::GRANT_ADMIN]);

        Tinebase_Container::getInstance()->deleteContainer($container['id']);
    }
        
    /**
     * try to add an account
     *
     */
    public function testDeleteContainer()
    {
        $container = $this->_backend->addContainer('Addressbook', 'Tine 2.0 Unittest', Tinebase_Model_Container::TYPE_PERSONAL);

        $this->assertEquals('Tine 2.0 Unittest', $container['name']);
        $this->assertEquals('Addressbook_Model_Contact', $container['model']);
        $this->_backend->deleteContainer($container['id']);
        
        $this->setExpectedException('Tinebase_Exception_NotFound');
        
        $container = Tinebase_Container::getInstance()->getContainerById($container['id']);
        
    }
        
    /**
     * try to add an account
     *
     */
    public function testRenameContainer()
    {
        $container = $this->_backend->addContainer('Addressbook', 'Tine 2.0 Unittest', Tinebase_Model_Container::TYPE_PERSONAL);

        $this->assertEquals('Tine 2.0 Unittest', $container['name']);

        
        $container = $this->_backend->renameContainer($container['id'], 'Tine 2.0 Unittest renamed');

        $this->assertEquals('Tine 2.0 Unittest renamed', $container['name']);
        $this->assertEquals('Addressbook_Model_Contact', $container['model']);

        $this->_backend->deleteContainer($container['id']);
        
        $this->setExpectedException('Tinebase_Exception_NotFound');
        
        $container = Tinebase_Container::getInstance()->getContainerById($container['id']);
    }
    
    /**
     * try to add an account
     *
     */
    public function testGetContainerGrants()
    {
        $container = $this->_backend->addContainer('Addressbook', 'Tine 2.0 Unittest', Tinebase_Model_Container::TYPE_PERSONAL);

        $this->assertEquals('Tine 2.0 Unittest', $container['name']);


        $grants = $this->_backend->getContainerGrants($container['id']);

        $this->assertEquals(1, $grants['totalcount']);
        $this->assertTrue($grants['results'][0]["readGrant"]);
        $this->assertEquals(Zend_Registry::get('currentAccount')->getId(), $grants['results'][0]["account_id"]);

        $this->_backend->deleteContainer($container['id']);

        $this->setExpectedException('Tinebase_Exception_NotFound');

        $container = Tinebase_Container::getInstance()->getContainerById($container['id']);
    }
            
    /**
     * try to set container grants
     *
     */
    public function testSetContainerGrants()
    {
        $container = $this->_backend->addContainer('Addressbook', 'Tine 2.0 Unittest', Tinebase_Model_Container::TYPE_PERSONAL);

        $this->assertEquals('Tine 2.0 Unittest', $container['name']);
        
        $newGrants = array(
            array(
                'account_id'     => Zend_Registry::get('currentAccount')->getId(),
                'account_type'   => 'user',
                //'account_name'   => 'not used',
                Tinebase_Model_Grants::GRANT_READ      => true,
                Tinebase_Model_Grants::GRANT_ADD       => true,
                Tinebase_Model_Grants::GRANT_EDIT      => true,
                Tinebase_Model_Grants::GRANT_DELETE    => false,
                Tinebase_Model_Grants::GRANT_ADMIN     => true
            )
        );
        
        $grants = $this->_backend->setContainerGrants($container['id'], $newGrants);
        
        $this->assertEquals(1, count($grants['results']));
        $this->assertFalse($grants['results'][0]["deleteGrant"]);
        $this->assertTrue($grants['results'][0]["adminGrant"]);
        

        $this->_backend->deleteContainer($container['id']);

        $this->setExpectedException('Tinebase_Exception_NotFound');

        $container = Tinebase_Container::getInstance()->getContainerById($container['id']);
    }
    
    /**
     * try to search containers
     *
     */
    public function testSearchContainers()
    {
        $container = $this->_backend->addContainer('Addressbook', 'Winter', Tinebase_Model_Container::TYPE_PERSONAL);
        $this->assertEquals('Winter', $container['name']);
        
        $filter = array(array(
                'field'     => 'query',
                'operator'     => 'contains',
                'value'     => 'Winter'
        ),array('field' => 'application_id', 'operator' => 'equals', 'value' =>
            Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId()));
        $paging = array(
                'start'    => 0,
                'limit'    => 1
        );
        
        $result = $this->_backend->searchContainers($filter, $paging);

        $this->assertGreaterThan(0, $result['totalcount']);
        $this->assertEquals($container['name'], $result['results'][0]['name']);
        $this->assertTrue(isset($result['results'][0]['account_grants']['readGrant']), 'account_grants missing');
        $this->assertTrue(isset($result['results'][0]['ownerContact']['email']), 'ownerContact missing');
    }
}
    

if (PHPUnit_MAIN_METHOD == 'Tinebase_Frontend_Json_ContainerTest::main') {
    Tinebase_Frontend_Json_ContainerTest::main();
}
