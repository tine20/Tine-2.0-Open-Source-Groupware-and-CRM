<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tinebase
 * @subpackage  Account
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2010-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * 
 * @todo make testLoginAndLogout work (needs to run in separate process)
 */

/**
 * Test helper
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for Tinebase_Controller
 */
class Tinebase_ControllerTest extends PHPUnit_Framework_TestCase
{
    /**
     * controller instance
     * 
     * @var Tinebase_Controller
     */
    protected $_instance = NULL;
    
    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        $this->_instance = Tinebase_Controller::getInstance();
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
        Tinebase_Config::getInstance()->maintenanceMode = 0;
    }
    
    /**
     * testMaintenanceModeLoginFail
     */
    public function testMaintenanceModeLoginFail()
    {
        if (Tinebase_User::getConfiguredBackend() === Tinebase_User::LDAP) {
            $this->markTestSkipped('FIXME: Does not work with LDAP backend (full test suite run)');
        }

        Tinebase_Config::getInstance()->maintenanceMode = 1;

        try {
            $this->_instance->login(
                'sclever',
                Tinebase_Helper::array_value('password', TestServer::getInstance()->getTestCredentials()),
                new \Zend\Http\PhpEnvironment\Request()
            );
            $this->fail('expected maintenance mode exception');
        } catch (Tinebase_Exception_MaintenanceMode $temm) {
            $this->assertEquals('Installation is in maintenance mode. Please try again later', $temm->getMessage());
        }
    }

    /**
     * testCleanupCache
     */
    public function testCleanupCache()
    {
        $this->_instance->cleanupCache(Zend_Cache::CLEANING_MODE_ALL);
        
        $cache = Tinebase_Core::getCache();
        $oldLifetime = $cache->getOption('lifetime');
        $cache->setLifetime(1);
        $cacheId = Tinebase_Helper::convertCacheId('testCleanupCache');
        $cache->save('value', $cacheId);
        sleep(3);
        
        // cleanup with CLEANING_MODE_OLD
        $this->_instance->cleanupCache();
        $cache->setLifetime($oldLifetime);
        
        $this->assertFalse($cache->load($cacheId));
        
        // check for cache files
        $config = Tinebase_Core::getConfig();
        
        if ($config->caching && $config->caching->backend == 'File' && $config->caching->path) {
            $cacheFile = $this->_lookForCacheFile($config->caching->path);
            $this->assertEquals(NULL, $cacheFile, 'found cache file: ' . $cacheFile);
        }
    }
    
    /**
     * look for cache files
     * 
     * @param string $_path
     * @param boolean $_firstLevel
     * @return string|NULL
     */
    protected function _lookForCacheFile($_path, $_firstLevel = TRUE)
    {
        foreach (new DirectoryIterator($_path) as $item) {
            if ($item->isDir() && preg_match('/^zend_cache/', $item->getFileName())) {
                //echo 'scanning ' . $item->getFileName();
                if ($this->_lookForCacheFile($item->getPathname(), FALSE)) {
                    // file found in subdir
                    return $item->getPathname();
                }
            } else if ($item->isFile() && ! $_firstLevel) {
                // file found
                return $item->getPathname();
            }
        }
        
        return NULL;
    }
}
