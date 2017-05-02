<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Filesystem
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2013-2016 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 */

/**
 * filesystem attachments for records
 *
 * @package     Tinebase
 * @subpackage  Filesystem
 */
class Tinebase_FileSystem_RecordAttachments
{
    /**
     * filesystem controller
     * 
     * @var Tinebase_FileSystem
     */
    protected $_fsController = NULL;
    
    /**
     * holds the instance of the singleton
     *
     * @var Tinebase_FileSystem_RecordAttachments
     */
    private static $_instance = NULL;
    
    /**
     * the constructor
     */
    public function __construct() 
    {
        $this->_fsController  = Tinebase_FileSystem::getInstance();
    }
    
    /**
     * the singleton pattern
     *
     * @return Tinebase_FileSystem_RecordAttachments
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Tinebase_FileSystem_RecordAttachments();
        }
        
        return self::$_instance;
    }
    
    /**
     * fetch all file attachments of a record
     * 
     * @param Tinebase_Record_Interface $record
     * @return Tinebase_Record_RecordSet of Tinebase_Model_Tree_Node
     */
    public function getRecordAttachments(Tinebase_Record_Interface $record)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
            ' Fetching attachments of ' . get_class($record) . ' record with id ' . $record->getId() . ' ...');
        
        $parentPath = $this->getRecordAttachmentPath($record);
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ .
            ' Looking in path ' . $parentPath);
        
        try {
            $record->attachments = $this->_fsController->scanDir($parentPath);
        } catch (Tinebase_Exception_NotFound $tenf) {
            $record->attachments = new Tinebase_Record_RecordSet('Tinebase_Model_Tree_Node');
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG) && count($record->attachments) > 0) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
            ' Found ' . count($record->attachments) . ' attachment(s).');
        
        return $record->attachments;
    }
    
    /**
     * fetches attachments for multiple records at once
     * 
     * @param Tinebase_Record_RecordSet $records
     * 
     * @todo maybe this should be improved
     */
    public function getMultipleAttachmentsOfRecords($records)
    {
        if ($records instanceof Tinebase_Record_Abstract) {
            $records = new Tinebase_Record_RecordSet(get_class($records), array($records));
        }

        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
            ' Fetching attachments for ' . count($records) . ' record(s)');
        
        $parentNodes       = new Tinebase_Record_RecordSet('Tinebase_Model_Tree_Node');
        $recordNodeMapping = array();
        $typeMap           = array();
        
        foreach ($records as $record) {
            $typeMap[get_class($record)][] = $record->getId();
            
            $record->attachments = new Tinebase_Record_RecordSet('Tinebase_Model_Tree_Node');
        }
        
        foreach ($typeMap as $className => $recordIds) {
            $classPathName = $this->_fsController->getApplicationBasePath($record->getApplication(), Tinebase_FileSystem::FOLDER_TYPE_RECORDS) 
                          . '/' . $className;
            
            // top folder for record attachments
            try {
                $classPathNode = $this->_fsController->stat($classPathName);
            } catch (Tinebase_Exception_NotFound $tenf) {
                continue;
            }
            
            // subfolders for all records attachments
            $searchFilter = new Tinebase_Model_Tree_Node_Filter(array(
                array(
                    'field'     => 'parent_id',
                    'operator'  => 'equals',
                    'value'     => $classPathNode->getId()
                ),
                array(
                    'field'     => 'name',
                    'operator'  => 'in',
                    'value'     => $recordIds
                )
            ), Tinebase_Model_Filter_FilterGroup::CONDITION_AND, array('ignoreAcl' => true));
            $recordNodes = $this->_fsController->searchNodes($searchFilter);
            if ($recordNodes->count() === 0) {
                // nothing to be done 
                continue;
            }
            foreach ($recordNodes as $recordNode) {
                $recordNodeMapping[$recordNode->getId()] = $recordNode->name;
            }
            
            // get attachments
            $attachmentNodes = $this->_fsController->getTreeNodeChildren($recordNodes);
            
            // add attachments to records
            foreach ($attachmentNodes as $attachmentNode) {
                $record = $records->getById($recordNodeMapping[$attachmentNode->parent_id]);
                
                $record->attachments->addRecord($attachmentNode);
            }
        }
    }
    
    /**
     * set file attachments of a record
     * 
     * @param Tinebase_Record_Interface $record
     */
    public function setRecordAttachments(Tinebase_Record_Interface $record)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ .
            ' Record: ' . print_r($record->toArray(), TRUE));
        
        $currentAttachments = ($record->getId()) ? $this->getRecordAttachments(clone $record) : new Tinebase_Record_RecordSet('Tinebase_Model_Tree_Node');
        $attachmentsToSet = ($record->attachments instanceof Tinebase_Record_RecordSet) 
            ? $record->attachments
            : new Tinebase_Record_RecordSet('Tinebase_Model_Tree_Node', (array)$record->attachments, TRUE);
        
        $attachmentDiff = $currentAttachments->diff($attachmentsToSet);
        
        foreach ($attachmentDiff->added as $added) {
            try {
                $this->addRecordAttachment($record, $added->name, $added);
            } catch (Tinebase_Exception_InvalidArgument $teia) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
                    ' Could not add new attachment ' . print_r($added->toArray(), TRUE) . ' to record: ' . print_r($record->toArray(), TRUE));
                Tinebase_Exception::log($teia);
            } catch (Tinebase_Exception_NotFound $tenf) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
                    ' Could not add new attachment ' . print_r($added->toArray(), TRUE) . ' to record: ' . print_r($record->toArray(), TRUE));
                Tinebase_Exception::log($tenf);
            }
        }
        
        foreach ($attachmentDiff->removed as $removed) {
            $this->_fsController->deleteFileNode($removed);
        }
        
        foreach ($attachmentDiff->modified as $modified) {
            $this->_fsController->update($attachmentsToSet->getById($modified->getId()));
        }
    }
    
    /**
     * add attachement to record
     * 
     * @param  Tinebase_Record_Abstract $record
     * @param  string $name
     * @param  mixed $attachment
         @see Tinebase_FileSystem::copyTempfile
     * @return null|Tinebase_Model_Tree_Node
     */
    public function addRecordAttachment(Tinebase_Record_Abstract $record, $name, $attachment)
    {
        // only occurs via unittests
        if (!$name && isset($attachment->tempFile) && ! is_resource($attachment->tempFile)) {
            $attachment = Tinebase_TempFile::getInstance()->getTempFile($attachment->tempFile);
            $name = $attachment->name;
        }

        // If there is no tempfile, the attachment was added from the filemanager
        if ($attachment instanceof Tinebase_Model_Tree_Node && !isset($attachment->tempFile) && isset($attachment->path)) {
            return $this->addRecordAttachmentFromFilemanager($record, $attachment);
        }

        if ($attachment instanceof Tinebase_Model_Tree_Node && empty($name)) {
            $name = $attachment->name;
        }

        if (empty($name)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
                ' Could not evaluate attachment name.');
            return null;
        }
        
        $attachmentsDir = $this->getRecordAttachmentPath($record, TRUE);
        $attachmentPath = $attachmentsDir . '/' . $name;
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
            ' Creating new record attachment ' . $attachmentPath);
        if ($this->_fsController->fileExists($attachmentPath)) {
            throw new Tinebase_Exception_InvalidArgument('File already exists');
        }
        
        $this->_fsController->copyTempfile($attachment, $attachmentPath);
        
        $node = $this->_fsController->stat($attachmentPath);
        return $node;
    }

    /**
     * Add a filemanager attachment to a given record
     *
     * @param Tinebase_Record_Abstract $record
     * @param Tinebase_Model_Tree_Node $attachment
     * @return null|Tinebase_Model_Tree_Node
     */
    public function addRecordAttachmentFromFilemanager(Tinebase_Record_Abstract $record, Tinebase_Model_Tree_Node $attachment) {
        if (!$attachment->path || !$attachment->name) {
            return null;
        }

        $attachmentsDir = $this->getRecordAttachmentPath($record, true);
        $attachmentPath = $attachmentsDir . '/' . $attachment->name;

        $nodeController = Filemanager_Controller_Node::getInstance();
        $path = Tinebase_Model_Tree_Node_Path::createFromPath($nodeController->addBasePath($attachment->path));
        $attachment = $this->_fsController->copy($path->statpath, $attachmentPath);

        return $attachment;
    }
    
    /**
     * delete attachments of record
     * 
     * @param Tinebase_Record_Abstract $record
     */
    public function deleteRecordAttachments($record)
    {
        $attachments = ($record->attachments instanceof Tinebase_Record_RecordSet) ? $record->attachments : $this->getRecordAttachments($record);
        foreach ($attachments as $node) {
            $this->_fsController->deleteFileNode($node);
        }
    }
    
    /**
     * get path for record attachments
     * 
     * @param Tinebase_Record_Abstract $record
     * @param boolean $createDirIfNotExists
     * @throws Tinebase_Exception_InvalidArgument
     * @return string
     */
    public function getRecordAttachmentPath(Tinebase_Record_Abstract $record, $createDirIfNotExists = FALSE)
    {
        if (! $record->getId()) {
            throw new Tinebase_Exception_InvalidArgument('record needs an identifier');
        }
        
        $parentPath = $this->_fsController->getApplicationBasePath($record->getApplication(), Tinebase_FileSystem::FOLDER_TYPE_RECORDS);
        $recordPath = $parentPath . '/' . get_class($record) . '/' . $record->getId();
        if ($createDirIfNotExists && ! $this->_fsController->fileExists($recordPath)) {
            $this->_fsController->mkdir($recordPath);
        }
        
        return $recordPath;
    }
}
