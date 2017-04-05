<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2010-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for Tinebase_FileSystem
 */
class Tinebase_FileSystemTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var array test objects
     */
    protected $objects = array();

    /**
     * @var Tinebase_FileSystem
     */
    protected $_controller;
    
    /**
     * Backend
     *
     * @var Filemanager_Backend_Node
     */
    protected $_backend;

    protected $_oldModLog;
    protected $_oldIndexContent;

    protected $_rmDir = array();

    protected $_transactionId = null;

    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Tine 2.0 filesystem tests');
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
        if (empty(Tinebase_Core::getConfig()->filesdir)) {
            $this->markTestSkipped('filesystem base path not found');
        }

        $this->_rmDir = array();
        $this->_oldModLog = Tinebase_Core::getConfig()->{Tinebase_Config::FILESYSTEM}->{Tinebase_Config::FILESYSTEM_MODLOGACTIVE};
        $this->_oldIndexContent = Tinebase_Core::getConfig()->{Tinebase_Config::FILESYSTEM}->{Tinebase_Config::FILESYSTEM_INDEX_CONTENT};

        $this->_transactionId = Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());
        
        $this->_controller = new Tinebase_FileSystem();
        $this->_basePath   = '/' . Tinebase_Application::getInstance()->getApplicationByName('Tinebase')->getId() . '/folders/' . Tinebase_Model_Container::TYPE_SHARED;
        
        $this->_controller->initializeApplication(Tinebase_Application::getInstance()->getApplicationByName('Tinebase'));
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
        $fsConfig = Tinebase_Core::getConfig()->get(Tinebase_Config::FILESYSTEM);
        $fsConfig->{Tinebase_Config::FILESYSTEM_MODLOGACTIVE} = $this->_oldModLog;
        $fsConfig->{Tinebase_Config::FILESYSTEM_INDEX_CONTENT} = $this->_oldIndexContent;
        Tinebase_Core::getConfig()->set(Tinebase_Config::FILESYSTEM, $fsConfig);
        Tinebase_FileSystem::getInstance()->resetBackends();

        Tinebase_TransactionManager::getInstance()->rollBack();
        Tinebase_FileSystem::getInstance()->clearStatCache();

        if (!empty($this->_rmDir)) {
            try {
                foreach($this->_rmDir as $rmDir) {
                    Tinebase_FileSystem::getInstance()->rmdir($rmDir, true);
                }
            } catch (Exception $e) {
            }
            Tinebase_FileSystem::getInstance()->clearStatCache();
        }

        Tinebase_FileSystem::getInstance()->clearDeletedFilesFromFilesystem();
    }
    
    public function testMkdir()
    {
        $basePathNode = $this->_controller->stat($this->_basePath);
        
        $testPath = $this->_basePath . '/PHPUNIT';
        $node = $this->_controller->mkdir($testPath);
        
        $this->assertInstanceOf('Tinebase_Model_Tree_Node', $node);
        $this->assertTrue($this->_controller->fileExists($testPath), 'path created by mkdir not found');
        $this->assertTrue($this->_controller->isDir($testPath),      'path created by mkdir is not a directory');
        $this->assertEquals(1, $node->revision);
        $this->assertNotEquals($basePathNode->hash, $this->_controller->stat($this->_basePath)->hash);
        
        return $testPath;
    }
    
    public function testDirectoryHashUpdate()
    {
        $testPath = $this->testMkdir();
        
        $basePathNode = $this->_controller->stat($testPath);
        
        $this->assertEquals(1, $basePathNode->revision);
        $this->assertNotEmpty($basePathNode->hash);
        
        $testNode = $this->_controller->mkdir($testPath . '/phpunit');
        
        $this->assertEquals(1, $testNode->revision);
        $this->assertNotEmpty($testNode->hash);
        $this->assertNotEquals($basePathNode->hash, $this->_controller->stat($testPath)->hash);
    }
    
    /**
     * test copying directory to existing directory
     */
    public function testCopyDirectoryToExistingDirectory()
    {
        $sourcePath      = $this->testMkdir();
        $destinationPath = $this->_basePath . '/TESTCOPY2';
        $this->_controller->mkdir($destinationPath);
        
        $createdNode = $this->_controller->copy($sourcePath, $destinationPath);
        
        $this->assertNotEquals($this->_controller->stat($sourcePath)->getId(), $createdNode->getId());
        $this->assertEquals(Tinebase_Model_Tree_Node::TYPE_FOLDER, $createdNode->type);
        $this->assertEquals($this->_controller->stat($sourcePath)->name, $createdNode->name);
        $this->assertTrue($this->_controller->fileExists($this->_basePath . '/TESTCOPY2/PHPUNIT'));
    }
    
    /**
     * test copying file to existing directory
     */
    public function testCopyFileToExistingDirectory()
    {
        $sourcePath      = $this->testCreateFile();
        $destinationPath = $this->_basePath . '/TESTCOPY2';
        $this->_controller->mkdir($destinationPath);
        
        $createdNode = $this->_controller->copy($sourcePath, $destinationPath);
        
        $this->assertNotEquals($this->_controller->stat($sourcePath)->getId(), $createdNode->getId());
        $this->assertEquals(Tinebase_Model_Tree_Node::TYPE_FILE, $createdNode->type);
        $this->assertEquals($this->_controller->stat($sourcePath)->name, $createdNode->name);
        $this->assertTrue($this->_controller->fileExists($this->_basePath . '/TESTCOPY2/' . basename($sourcePath)));
    }
    
    /**
     * test copying file to existing directory and change name
     */
    public function testCopyFileToExistingDirectoryAndChangeName()
    {
        $sourcePath      = $this->testCreateFile();
        $destinationPath = $this->_basePath . '/TESTCOPY2/phpunit2.txt';

        $this->_controller->mkdir($this->_basePath . '/TESTCOPY2');
        
        $createdNode = $this->_controller->copy($sourcePath, $destinationPath);
        
        $this->assertNotEquals($this->_controller->stat($sourcePath)->getId(), $createdNode->getId());
        $this->assertEquals(Tinebase_Model_Tree_Node::TYPE_FILE, $createdNode->type);
        $this->assertEquals(basename($destinationPath), $createdNode->name);
        $this->assertTrue($this->_controller->fileExists($this->_basePath . '/TESTCOPY2/' . basename($destinationPath)));
    }
    
    /**
     * test copying directory to existing directory
     */
    public function testCopySourceAndDestinationTheSame()
    {
        $sourcePath      = $this->testCreateFile();
        $destinationPath = $this->testMkdir();
        
        $this->setExpectedException('Tinebase_Exception_UnexpectedValue');

        $createdNode = $this->_controller->copy($sourcePath, $destinationPath);
    }
    
    public function testRename()
    {
        $testPath = $this->testMkdir();
        $this->testCreateFile();
    
        $testPath2 = $testPath . '/RENAMED';
        Tinebase_FileSystem::getInstance()->mkdir($testPath2);
    
        Tinebase_FileSystem::getInstance()->rename($testPath . '/phpunit.txt', $testPath2 . '/phpunit2.txt');
    
        $nameOfChildren = Tinebase_FileSystem::getInstance()->scandir($testPath)->name;
        $this->assertFalse(in_array('phpunit.txt', $nameOfChildren));

        $nameOfChildren = Tinebase_FileSystem::getInstance()->scandir($testPath2)->name;
        $this->assertTrue(in_array('phpunit2.txt', $nameOfChildren));
    }
    
    public function testRmdir()
    {
        $testPath = $this->testMkdir();
    
        $result = $this->_controller->rmdir($testPath);
    
        $this->assertTrue($result,                                    'wrong result for rmdir command');
        $this->assertFalse($this->_controller->fileExists($testPath), 'failed to delete directory');
    }
    
    public function testScandir()
    {
        $this->testMkdir();
        
        $children = $this->_controller->scanDir($this->_basePath)->name;
        
        $this->assertTrue(in_array('PHPUNIT', $children));
    }
    
    public function testStat()
    {
        $this->testCreateFile();
    
        $node = $this->_controller->stat($this->_basePath . '/PHPUNIT/phpunit.txt');
    
        $this->assertEquals(Tinebase_Model_Tree_FileObject::TYPE_FILE, $node->type);
        $this->assertEquals('phpunit.txt', $node->name);
        $this->assertEquals(7, $node->size);
    }
    
    /**
     * test for isDir with existing directory 
     */
    public function testIsDir()
    {
        $this->testMkdir();
        
        $result = $this->_controller->isDir($this->_basePath . '/PHPUNIT');
        
        $this->assertTrue($result);

        $result = $this->_controller->isFile($this->_basePath . '/PHPUNIT');
    
        $this->assertFalse($result);
    }
    
    /**
     * test for isDir with non existing directory
     */
    public function testIsDirNotExisting()
    {
        $result = $this->_controller->isDir($this->_basePath . '/PHPUNITNotExisting');
        
        $this->assertFalse($result);
    }
    
    public function testCreateFile()
    {
        $testDir  = $this->testMkdir();
        $testFile = 'phpunit.txt';
        $testPath = $testDir . '/' . $testFile;

        $basePathNode = $this->_controller->stat($testDir);
        $this->assertEquals(1, $basePathNode->revision);

        $handle = $this->_controller->fopen($testPath, 'x');
        
        $this->assertEquals('resource', gettype($handle), 'opening file failed');
        
        $written = fwrite($handle, 'phpunit');
        
        $this->assertEquals(7, $written);
        
        $this->_controller->fclose($handle);

        $children = $this->_controller->scanDir($testDir)->name;

        $updatedBasePathNode = $this->_controller->stat($testDir);

        $this->assertContains($testFile, $children);
        $this->assertEquals(1, $updatedBasePathNode->revision);
        $this->assertNotEquals($basePathNode->hash, $updatedBasePathNode->hash);
        
        return $testPath;
    }

    public function testModLogAndIndexContent()
    {
        $this->_rmDir[] = $this->_basePath . '/PHPUNIT';

        $fsConfig = Tinebase_Core::getConfig()->get(Tinebase_Config::FILESYSTEM);
        $fsConfig->{Tinebase_Config::FILESYSTEM_MODLOGACTIVE} = true;
        $fsConfig->{Tinebase_Config::FILESYSTEM_INDEX_CONTENT} = true;
        Tinebase_Core::getConfig()->set(Tinebase_Config::FILESYSTEM, $fsConfig);
        $this->_controller->resetBackends();

        $testPath = $this->testCreateFile();

        $node = $this->_controller->stat($testPath);
        $this->assertEquals(7, $node->size);
        $this->assertEquals(7, $node->revision_size);

        Tinebase_TransactionManager::getInstance()->commitTransaction($this->_transactionId);

        $this->_transactionId = Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());

        $filter = new Tinebase_Model_Tree_Node_Filter(array(
            array('field' => 'id',          'operator' => 'equals',     'value' => $node->getId()),
            array('field' => 'content',     'operator' => 'contains',   'value' => 'phpunit'),
        ));
        $result = $this->_controller->search($filter);
        $this->assertEquals(1, $result->count(), 'didn\'t find file');


        $filter = new Tinebase_Model_Tree_Node_Filter(array(
            array('field' => 'id',          'operator' => 'equals',     'value' => $node->getId()),
            array('field' => 'content',     'operator' => 'contains',   'value' => 'shooo'),
        ));
        $result = $this->_controller->search($filter);
        $this->assertEquals(0, $result->count(), 'did find file where non should be found');


        $handle = $this->_controller->fopen($testPath, 'w');
        $written = fwrite($handle, 'abcde');
        $this->assertEquals(5, $written);
        $this->_controller->fclose($handle);

        $node = $this->_controller->stat($testPath);
        $this->assertEquals(5, $node->size);
        $this->assertEquals(12, $node->revision_size);


        Tinebase_TransactionManager::getInstance()->commitTransaction($this->_transactionId);

        $filter = new Tinebase_Model_Tree_Node_Filter(array(
            array('field' => 'id',          'operator' => 'equals',     'value' => $node->getId()),
            array('field' => 'content',     'operator' => 'contains',   'value' => 'abcde'),
        ));
        $result = $this->_controller->search($filter);
        $this->assertEquals(1, $result->count(), 'didn\'t find file');


        $filter = new Tinebase_Model_Tree_Node_Filter(array(
            array('field' => 'id',          'operator' => 'equals',     'value' => $node->getId()),
            array('field' => 'content',     'operator' => 'contains',   'value' => 'phpunit'),
        ));
        $result = $this->_controller->search($filter);
        $this->assertEquals(0, $result->count(), 'did find file where non should be found');
    }
    
    /**
     * test for isDir with existing directory
     */
    public function testIsFile()
    {
        $this->testCreateFile();
    
        $result = $this->_controller->isFile($this->_basePath . '/PHPUNIT/phpunit.txt');
    
        $this->assertTrue($result);

        $result = $this->_controller->isDir($this->_basePath . '/PHPUNIT/phpunit.txt');
    
        $this->assertFalse($result);
    }
    
    public function testOpenFile()
    {
        $this->testCreateFile();
        
        $handle = $this->_controller->fopen($this->_basePath . '/PHPUNIT/phpunit.txt', 'r');
        
        $this->assertEquals('phpunit', stream_get_contents($handle), 'file content mismatch');
        
        $this->_controller->fclose($handle);
    }
    
    public function testDeleteFile()
    {
        $this->testCreateFile();
        
        $this->_controller->unlink($this->_basePath . '/PHPUNIT/phpunit.txt');
        
        $children = $this->_controller->scanDir($this->_basePath . '/PHPUNIT')->name;
        
        $this->assertTrue(!in_array('phpunit.txt', $children));
    }
    
    public function testGetFileSize()
    {
        $this->testCreateFile();
        
        $filesize = $this->_controller->filesize($this->_basePath . '/PHPUNIT/phpunit.txt');
        
        $this->assertEquals(7, $filesize);
    }
    
    /**
     * test get content type
     */
    public function testGetContentType()
    {
        $this->testCreateFile();
        
        $contentType = $this->_controller->getContentType($this->_basePath . '/PHPUNIT/phpunit.txt');
        
        // finfo_open() for content type detection is only available in php versions >= 5.3.0'
        $expectedContentType = (version_compare(PHP_VERSION, '5.3.0', '>=') && function_exists('finfo_open')) ? 'text/plain' : 'application/octet-stream';
        
        $this->assertEquals($expectedContentType, $contentType);
    }
    
    public function testGetMTime()
    {
        $now = Tinebase_DateTime::now()->getTimestamp();
        
        $this->testCreateFile();
        
        $timestamp = $this->_controller->getMTime($this->_basePath . '/PHPUNIT/phpunit.txt');
        
        $this->assertGreaterThanOrEqual(sprintf('%u', $now), sprintf('%u', $timestamp));
    }
    
    public function testGetEtag()
    {
        $this->testCreateFile();
        
        $node = $this->_controller->stat($this->_basePath . '/PHPUNIT/phpunit.txt');
        
        $etag = $this->_controller->getETag($this->_basePath . '/PHPUNIT/phpunit.txt');
        
        $this->assertEquals($node->hash, $etag);
    }
    
    /**
     * @return Filemanager_Model_Directory
     */
    public static function getTestRecord()
    {
        $object  = new Tinebase_Model_Tree_Node(array(
            'name'     => 'PHPUnit test node',
        ), true);
        
        return $object;
    }
}

