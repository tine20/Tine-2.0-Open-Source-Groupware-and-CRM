<?php
/**
 * Tine 2.0
 *
 * @package     MailFiler
 * @subpackage  Frontend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2010-2014 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * backend class for Zend_Json_Server
 *
 * This class handles all Json requests for the MailFiler application
 *
 * @package     MailFiler
 * @subpackage  Frontend
 */
class MailFiler_Frontend_Json extends Tinebase_Frontend_Json_Abstract
{
    /**
     * app name
     * 
     * @var string
     */
    protected $_applicationName = 'MailFiler';
    
    /**
     * search file/directory nodes
     *
     * @param  array $filter
     * @param  array $paging
     * @return array
     */
    public function searchNodes($filter, $paging)
    {
        $this->_paginationModel = 'MailFiler_Model_Node';
        try {
            $result = $this->_search($filter, $paging, MailFiler_Controller_Node::getInstance(),
                'MailFiler_Model_NodeFilter');
            $this->_removeAppIdFromPathFilter($result);

            return $result;
        } finally {
            $this->_paginationModel = null;
        }
    }
    
    /**
     * remove app id (base path) from filter
     * 
     * @param array $_result
     * 
     * @todo is this really needed? perhaps we can set the correct path in Tinebase_Model_Tree_Node_PathFilter::toArray
     */
    protected function _removeAppIdFromPathFilter(&$_result)
    {
        $app = Tinebase_Application::getInstance()->getApplicationByName($this->_applicationName);
        
        foreach ($_result['filter'] as $idx => &$filter) {
            if ($filter['field'] === 'path') {
                if (is_array($filter['value'])) {
                    $filter['value']['path'] = Tinebase_Model_Tree_Node_Path::removeAppIdFromPath($filter['value']['path'], $app);
                } else {
                    $filter['value'] = Tinebase_Model_Tree_Node_Path::removeAppIdFromPath($filter['value'], $app);
                }
            }
        }
    }

    /**
     * create node
     * 
     * @param array $filename
     * @param string $type directory or file
     * @param string $tempFileId
     * @param boolean $forceOverwrite
     * @return array
     */
    public function createNode($filename, $type, $tempFileId = array(), $forceOverwrite = false)
    {
        $nodes = MailFiler_Controller_Node::getInstance()->createNodes((array)$filename, $type, (array)$tempFileId, $forceOverwrite);
        $result = (count($nodes) === 0) ? array() : $this->_recordToJson($nodes->getFirstRecord());
        
        return $result;
    }

    /**
     * create nodes
     * 
     * @param string|array $filenames
     * @param string $type directory or file
     * @param string|array $tempFileIds
     * @param boolean $forceOverwrite
     * @return array
     */
    public function createNodes($filenames, $type, $tempFileIds = array(), $forceOverwrite = false)
    {
        $nodes = MailFiler_Controller_Node::getInstance()->createNodes((array)$filenames, $type, (array)$tempFileIds, $forceOverwrite);

        return $this->_multipleRecordsToJson($nodes);
    }
    
    /**
     * copy node(s)
     * 
     * @param string|array $sourceFilenames string->single file, array->multiple
     * @param string|array $destinationFilenames string->singlefile OR directory, array->multiple files
     * @param boolean $forceOverwrite
     * @return array
     */
    public function copyNodes($sourceFilenames, $destinationFilenames, $forceOverwrite)
    {
        $nodes = MailFiler_Controller_Node::getInstance()->copyNodes((array)$sourceFilenames, $destinationFilenames, $forceOverwrite);

        return $this->_multipleRecordsToJson($nodes);
    }

    /**
     * move node(s)
     * 
     * @param string|array $sourceFilenames string->single file, array->multiple
     * @param string|array $destinationFilenames string->singlefile OR directory, array->multiple files
     * @param boolean $forceOverwrite
     * @return array
     */
    public function moveNodes($sourceFilenames, $destinationFilenames, $forceOverwrite)
    {
        $nodes = MailFiler_Controller_Node::getInstance()->moveNodes((array)$sourceFilenames, $destinationFilenames, $forceOverwrite);

        return $this->_multipleRecordsToJson($nodes);
    }

    /**
     * delete node(s)
     * 
     * @param string|array $filenames string->single file, array->multiple
     * @return array
     */
    public function deleteNodes($filenames)
    {
        MailFiler_Controller_Node::getInstance()->deleteNodes((array)$filenames);
        
        return array(
            'status'    => 'success'
        );
    }

    /**
     * returns the node record
     * @param string $id
     * @return array
     */
    public function getNode($id)
    {
        return $this->_get($id, MailFiler_Controller_Node::getInstance());
    }
    
    /**
     * save node
     * save node here in json fe just updates meta info (name, description, relations, customfields, tags, notes),
     * if record already exists (after it had been uploaded)
     * @param array with record data 
     * @return array
     */
    public function saveNode($recordData)
    {
        if((isset($recordData['created_by']) || array_key_exists('created_by', $recordData))) {
            return $this->_save($recordData, MailFiler_Controller_Node::getInstance(), 'Node');
        } else {    // on upload complete
            return $recordData;
        }
    }
    
    /**
     * Search for records matching given arguments
     *
     * @param  array $filter
     * @param  array $paging
     * @return array
     */
    public function searchDownloadLinks($filter, $paging)
    {
        return $this->_search($filter, $paging, MailFiler_Controller_DownloadLink::getInstance(), 'MailFiler_Model_DownloadLinkFilter');
    }
    
    /**
     * Return a single record
     *
     * @param   string $id
     * @return  array record data
     */
    public function getDownloadLink($id)
    {
        return $this->_get($id, MailFiler_Controller_DownloadLink::getInstance());
    }
    
    /**
     * creates/updates a record
     *
     * @param  array $recordData
     * @return array created/updated record
     */
    public function saveDownloadLink($recordData)
    {
        return $this->_save($recordData, MailFiler_Controller_DownloadLink::getInstance(), 'DownloadLink');
    }
    
    /**
     * deletes existing records
     *
     * @param  array $ids
     * @return array
     */
    public function deleteDownloadLinks($ids)
    {
        return $this->_delete($ids, MailFiler_Controller_DownloadLink::getInstance());
    }
}
