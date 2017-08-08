<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Frontend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2012-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * class to handle containers in WebDAV tree
 *
 * @package     Tinebase
 * @subpackage  Frontend
 * 
 * @TODO extend from Tinebase_Frontend_WebDAV_Directory 
 *       and remove Tinebase_WebDav_Container_Abstract
 */
class Tinebase_Frontend_WebDAV_Container extends Tinebase_WebDav_Container_Abstract
{
    protected $_applicationName = 'Tinebase';
    
    protected $_model = 'File';
    
    protected $_suffix = null;
    
    /**
     * webdav file class
     * 
     * @var string
     */
    protected $_fileClass = 'Tinebase_Frontend_WebDAV_File';

    /**
     * webdav directory class
     * 
     * @var string
     */
    protected $_directoryClass = 'Tinebase_Frontend_WebDAV_Directory';

    /**
     * constructor
     * 
     * @param  Tinebase_Model_Tree_Node    $_container
     * @param  boolean                     $_useIdAsName
     */
    public function __construct($_container, $_useIdAsName = false)
    {
        parent::__construct($_container, $_useIdAsName);
        
        $this->_path = Tinebase_FileSystem::getInstance()->getPathOfNode($this->_container, /* as string */ true);
        
        // make sure filesystem path exists
        try {
            Tinebase_FileSystem::getInstance()->stat($this->_path);
        } catch (Tinebase_Exception_NotFound $tenf) {
            Tinebase_FileSystem::getInstance()->mkdir($this->_path);
        }
    }
    
    /**
     * Creates a new subdirectory
     *
     * @param string $name
     * @throws Sabre\DAV\Exception\Forbidden
     * @return void
     */
    public function createDirectory($name)
    {
        Tinebase_Frontend_WebDAV_Node::checkForbiddenFile($name);
        
        if (!Tinebase_Core::getUser()->hasGrant($this->_getContainer(), Tinebase_Model_Grants::GRANT_ADD)) {
            throw new Sabre\DAV\Exception\Forbidden('Forbidden to create folder: ' . $name);
        }
    
        $path = $this->_path . '/' . $name;
    
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' create directory: ' . $path);
    
        Tinebase_FileSystem::getInstance()->mkdir($path);
    }
    
    /**
     * Creates a new file in the directory
     *
     * @param string $name Name of the file
     * @param resource $data Initial payload, passed as a readable stream resource.
     * @throws Sabre\DAV\Exception\Forbidden
     * @return void
     */
    public function createFile($name, $data = null)
    {
        Tinebase_Frontend_WebDAV_Node::checkForbiddenFile($name);
        
        if (!Tinebase_Core::getUser()->hasGrant($this->_getContainer(), Tinebase_Model_Grants::GRANT_ADD)) {
            throw new Sabre\DAV\Exception\Forbidden('Forbidden to create file: ' . $this->_path . '/' . $name);
        }
        
        // OwnCloud chunked file upload
        if (isset($_SERVER['HTTP_OC_CHUNKED']) && is_resource($data)) {
            $completeFile = Tinebase_Frontend_WebDAV_Directory::handleOwnCloudChunkedFileUpload($name, $data);
            
            if (! $completeFile instanceof Tinebase_Model_TempFile) {
                return null;
            }
            
            $name = $completeFile->name;
            $data = fopen($completeFile->path, 'r');
            
            if ($this->childExists($name)) {
                return $this->getChild($name)->put($data);
            }
        }
        
        $path = $this->_path . '/' . $name;
    
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE))
            Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' PATH: ' . $path);
    
        if (!$handle = Tinebase_FileSystem::getInstance()->fopen($path, 'x')) {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to create file (filename file://' . $path . ')');
        }
    
        if (is_resource($data)) {
            stream_copy_to_stream($data, $handle);
        }
    
        Tinebase_FileSystem::getInstance()->fclose($handle);
        
        return '"' . Tinebase_FileSystem::getInstance()->getETag($path) . '"';
    }
    
    /**
     * Deleted the current container
     *
     * @throws Sabre\DAV\Exception\Forbidden
     * @return void
     */
    public function delete()
    {
        if (!Tinebase_Core::getUser()->hasGrant($this->_getContainer(), Tinebase_Model_Grants::GRANT_DELETE)) {
            throw new Sabre\DAV\Exception\Forbidden('Forbidden to delete directory: ' . $this->_path);
        }
    
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG))
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' delete directory: ' . $this->_path);
    
        foreach ($this->getChildren() as $child) {
            $child->delete();
        }
    
        if (!Tinebase_FileSystem::getInstance()->rmdir($this->_path, /* $recursive */ true)) {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to delete node');
        }
    }
    
    public function getChild($name)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) 
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' path: ' . $this->_path . '/' . $name);
        
        Tinebase_Frontend_WebDAV_Node::checkForbiddenFile($name);
        
        try {
            $childNode = Tinebase_FileSystem::getInstance()->stat($this->_path . '/' . $name);
            if (!Tinebase_Core::getUser()->hasGrant($childNode, Tinebase_Model_Grants::GRANT_READ)) {
                throw new Sabre\DAV\Exception\Forbidden('You do not have access');
            }
        } catch (Tinebase_Exception_NotFound $tenf) {
            throw new Sabre\DAV\Exception\NotFound('file not found: ' . $this->_path . '/' . $name);
        }
        
        if ($childNode->type == Tinebase_Model_Tree_FileObject::TYPE_FOLDER) {
            return new $this->_directoryClass($this->_path . '/' . $name);
        } else {
            return new $this->_fileClass($this->_path . '/' . $name);
        }
    }
    
    /**
     * Returns an array with all the child nodes
     *
     * @return Sabre\DAV\INode[]
     */
    public function getChildren()
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) 
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' path: ' . $this->_path);
        
        $children = array();
        
        // Loop through the directory, and create objects for each node
        foreach (Tinebase_FileSystem::getInstance()->scanDir($this->_path) as $node) {
            if (Tinebase_Core::getUser()->hasGrant($node, Tinebase_Model_Grants::GRANT_READ)) {
                $children[] = $this->getChild($node->name);
            }
        }
        
        return $children;
    }
    
    /**
     * return etag
     * 
     * @return string
     */
    public function getETag()
    {
        return '"' . Tinebase_FileSystem::getInstance()->stat($this->_path)->hash . '"';
    }
    
    /**
     * Renames the node
     * 
     * @throws Sabre\DAV\Exception\Forbidden
     * @param string $name The new name
     * @return void
     */
    public function setName($name) 
    {
        Tinebase_Frontend_WebDAV_Node::checkForbiddenFile($name);
        
        if (!Tinebase_Core::getUser()->hasGrant($this->_getContainer(), Tinebase_Model_Grants::GRANT_EDIT)) {
            throw new Sabre\DAV\Exception\Forbidden('Forbidden to rename file: ' . $this->_path);
        }
        
        $this->_getContainer()->name = $name;
        Tinebase_FileSystem::getInstance()->update($this->_getContainer());
    }
    
    /**
     * return container for given path
     * 
     * @return Tinebase_Model_Tree_Node
     */
    protected function _getContainer()
    {
        return $this->_container;
    }
}
