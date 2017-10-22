<?php
/**
 * convert functions for records from/to json (array) format
 * 
 * @package     Tinebase
 * @subpackage  Convert
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2011-2016 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * convert functions for records from/to json (array) format
 *
 * @package     Tinebase
 * @subpackage  Convert
 */
class Tinebase_Convert_Json implements Tinebase_Convert_Interface
{
    /**
     * converts external format to Tinebase_Record_Abstract
     *
     * @param  mixed $_blob the input data to parse
     * @param  Tinebase_Record_Abstract $_record update existing record
     * @return Tinebase_Record_Abstract
     * @throws Tinebase_Exception_NotImplemented
     */
    public function toTine20Model($_blob, Tinebase_Record_Abstract $_record = NULL)
    {
        throw new Tinebase_Exception_NotImplemented('From json to record is not implemented yet');
    }
    
    /**
     * converts Tinebase_Record_Abstract to external format
     * 
     * @param  Tinebase_Record_Abstract $_record
     * @return mixed
     */
    public function fromTine20Model(Tinebase_Record_Abstract $_record)
    {
        if (! $_record) {
            return array();
        }
        
        // for resolving we'll use recordset
        /** @var Tinebase_Record_Interface $recordClassName */
        $recordClassName = get_class($_record);
        $records = new Tinebase_Record_RecordSet($recordClassName, array($_record));
        $modelConfiguration = $recordClassName::getConfiguration();

        return $this->_fromTine20RecordSet($records, $modelConfiguration, false);
    }

    /**
     * converts Tinebase_Record_RecordSet to external format
     *
     * @param Tinebase_Record_RecordSet|Tinebase_Record_Abstract  $_records
     * @param Tinebase_Model_Filter_FilterGroup $_filter
     * @param Tinebase_Model_Pagination $_pagination
     *
     * @return mixed
     */
    public function fromTine20RecordSet(Tinebase_Record_RecordSet $_records = NULL, /** @noinspection PhpUnusedParameterInspection */
                                        $_filter = NULL, /** @noinspection PhpUnusedParameterInspection */ $_pagination = NULL)
    {
        if (! $_records || count($_records) == 0) {
            return array();
        }

        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Processing ' . count($_records) . ' records ...');

        // find out if there is a modelConfiguration
        /** @var Tinebase_Record_Interface $ownRecordClass */
        $ownRecordClass = $_records->getRecordClassName();
        $config = $ownRecordClass::getConfiguration();

        return $this->_fromTine20RecordSet($_records, $config, true);
    }

    /**
     * @param Tinebase_Record_RecordSet $_records
     * @param $config
     * @param bool $multiple
     * @return array
     */
    protected function _fromTine20RecordSet(Tinebase_Record_RecordSet $_records, $config, $multiple)
    {
        $this->_resolveBeforeToArray($_records, $config, $multiple);

        $_records->setTimezone(Tinebase_Core::getUserTimezone());
        $_records->convertDates = true;

        if (false === $multiple) {
            $result = $_records->getFirstRecord()->toArray();
        } else {
            $result = $_records->toArray();
        }

        // resolve all virtual fields after converting to array, so we can add these properties "virtually"
        $result = $this->_resolveAfterToArray($result, $config, $multiple);

        return $result;
    }

    /**
     * resolves single record fields (Tinebase_ModelConfiguration._recordsFields)
     * 
     * @param Tinebase_Record_RecordSet $_records the records
     * @param Tinebase_ModelConfiguration $modelConfig
     */
    protected function _resolveSingleRecordFields(Tinebase_Record_RecordSet $_records, Tinebase_ModelConfiguration $modelConfig = NULL)
    {
        if (! $modelConfig) {
            return;
        }
        
        $resolveFields = $modelConfig->recordFields;
        
        if ($resolveFields && is_array($resolveFields)) {
            // don't search twice if the same recordClass gets resolved on multiple fields
            $resolveRecords = array();
            foreach ($resolveFields as $fieldKey => $fieldConfig) {
                $resolveRecords[$fieldConfig['config']['recordClassName']][] = $fieldKey;
            }
            
            foreach ($resolveRecords as $foreignRecordClassName => $fields) {
                $foreignIds = array();
                $foreignRecordsArray = array();
                $fields = (array) $fields;
                foreach ($fields as $field) {
                    $idsForField = $_records->{$field};
                    foreach ($idsForField as $key => $value) {
                        if ($value instanceof Tinebase_Record_Abstract) {
                            $foreignRecordsArray[$value->getId()] = $value;
                        } else {
                            if ($value && !isset($foreignRecordsArray[$value])) {
                                $foreignIds[$value] = $value;
                            }
                        }
                    }
                }
                
                if (empty($foreignIds) && empty($foreignRecordsArray)) {
                    continue;
                }
                
                $cfg = $resolveFields[$fields[0]];

                if ($cfg['type'] === 'user') {
                    $foreignRecords = Tinebase_User::getInstance()->getMultiple($foreignIds);
                } else if ($cfg['type'] === 'container') {
                    // TODO: resolve recursive records of records better in controller
                    // TODO: resolve containers
                    if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                        . ' No handling for container foreign records implemented');

                    //$foreignRecords = new Tinebase_Record_RecordSet('Tinebase_Model_Container');
                    // $foreignRecords->addRecord(Tinebase_Container::getInstance()->get(XXX));
                    continue;
                } else {
                    try {
                        $foreignRecords = $this->_getForeignRecords($foreignIds, $foreignRecordClassName);
                    } catch (Tinebase_Exception_AccessDenied $tead) {
                        if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ 
                            . ' No right to access application of record ' . $foreignRecordClassName);
                        continue;
                    }
                }

                foreach ($foreignRecordsArray as $id => $rec) {
                    if ($foreignRecords->getById($id) === false) {
                        if ($cfg['type'] === 'user' && get_class($rec) === Tinebase_Model_User::class) {
                            $rec = new Tinebase_Model_FullUser($rec->toArray(), true);
                        }
                        $foreignRecords->addRecord($rec);
                    }
                }
                
                if ($foreignRecords->count() === 0) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' No matching foreign records found (' . $foreignRecordClassName . ')');
                    continue;
                }
                $foreignRecords->setTimezone(Tinebase_Core::getUserTimezone());
                $foreignRecords->convertDates = true;
                Tinebase_Frontend_Json_Abstract::resolveContainersAndTags($foreignRecords, $modelConfig);
                $fr = $foreignRecords->getFirstRecord();
                if ($fr && $fr->has('notes')) {
                    Tinebase_Notes::getInstance()->getMultipleNotesOfRecords($foreignRecords);
                }

                $this->_mapForeignRecords($_records, $fields, $foreignRecords);
            }
        }
    }

    protected function _getForeignRecords($foreignIds, $foreignRecordClassName)
    {
        $controller = Tinebase_Core::getApplicationInstance($foreignRecordClassName);
        $foreignRecords = $controller->getMultiple($foreignIds);
        return $foreignRecords;
    }

    /**
     * @param $records
     * @param $fields
     * @param Tinebase_Record_RecordSet $foreignRecords
     */
    protected function _mapForeignRecords($records, $fields, $foreignRecords)
    {
        foreach ($records as $record) {
            foreach ($fields as $field) {
                $foreignId = $record->{$field};
                if (is_scalar($foreignId)) {
                    $idx = $foreignRecords->getIndexById($foreignId);
                    if (isset($idx) && $idx !== FALSE) {
                        $record->{$field} = $foreignRecords[$idx];
                    } else {
                        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                            . ' No matching foreign record found for id: ' . $foreignId);
                    }
                }
            }
        }
    }
    
    /**
     * resolves multiple records (fallback)
     * 
     * @deprecated use Tinebase_ModelConfiguration to configure your models, so this won't be used anymore 
     * @param Tinebase_Record_RecordSet $records the records
     * @param array $resolveFields
     */
    public static function resolveMultipleIdFields($records, $resolveFields = NULL)
    {
        if (! $records instanceof Tinebase_Record_RecordSet || !$records->count()) {
            return;
        }

        /** @var Tinebase_Record_Interface $ownRecordClass */
        $ownRecordClass = $records->getRecordClassName();
        if ($resolveFields === NULL) {
            $resolveFields = $ownRecordClass::getResolveForeignIdFields();
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Resolving ' . $ownRecordClass . ' fields: ' . print_r($resolveFields, TRUE));
        
        foreach ((array) $resolveFields as $foreignRecordClassName => $fields) {
            if ($foreignRecordClassName === 'recursive') {
                foreach ($fields as $field => $model) {
                    foreach ($records->$field as $subRecords) {
                        self::resolveMultipleIdFields($subRecords);
                    }
                }
            } else {
                self::_resolveForeignIdFields($records, $foreignRecordClassName, (array) $fields);
            }
        }
    }
    
    /**
     * resolve foreign fields for records like user ids to users, etc.
     * 
     * @param Tinebase_Record_RecordSet $records
     * @param string $foreignRecordClassName
     * @param array $fields
     */
    protected static function _resolveForeignIdFields($records, $foreignRecordClassName, $fields)
    {
        $options = (isset($fields['options']) || array_key_exists('options', $fields)) ? $fields['options'] : array();
        $fields = (isset($fields['fields']) || array_key_exists('fields', $fields)) ? $fields['fields'] : $fields;
        
        $foreignIds = array();
        foreach ($fields as $field) {
            $foreignIds = array_unique(array_merge($foreignIds, $records->{$field}));
        }
        
        try {
            $controller = Tinebase_Core::getApplicationInstance($foreignRecordClassName);
        } catch (Tinebase_Exception_AccessDenied $tead) {
            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ 
                . ' No right to access application of record ' . $foreignRecordClassName);
            return;
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Fetching ' . $foreignRecordClassName . ' by id: ' . print_r($foreignIds, TRUE));
        
        if ((isset($options['ignoreAcl']) || array_key_exists('ignoreAcl', $options)) && $options['ignoreAcl']) {
            // @todo make sure that second param of getMultiple() is $ignoreAcl
            $foreignRecords = $controller->getMultiple($foreignIds, TRUE);
        } else {
            $foreignRecords = $controller->getMultiple($foreignIds);
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Foreign records found: ' . print_r($foreignRecords->toArray(), TRUE));
        
        if (count($foreignRecords) === 0) {
            return;
        }
        
        foreach ($records as $record) {
            foreach ($fields as $field) {
                if (is_scalar($record->{$field})) {
                    $idx = $foreignRecords->getIndexById($record->{$field});
                    if (isset($idx) && $idx !== FALSE) {
                        $record->{$field} = $foreignRecords[$idx];
                    } else {
                        switch ($foreignRecordClassName) {
                            case 'Tinebase_Model_User':
                            case 'Tinebase_Model_FullUser':
                                $record->{$field} = Tinebase_User::getInstance()->getNonExistentUser();
                                break;
                            default:
                                // skip
                        }
                    }
                }
            }
        }
    }
    
    /**
     * resolve multiple record fields (Tinebase_ModelConfiguration._recordsFields)
     * 
     * @param Tinebase_Record_RecordSet $_records
     * @param Tinebase_ModelConfiguration $modelConfiguration
     * @param boolean $multiple
     * @throws Tinebase_Exception_UnexpectedValue
     */
    protected function _resolveMultipleRecordFields(Tinebase_Record_RecordSet $_records, $modelConfiguration = NULL, $multiple = false)
    {
        if (! $modelConfiguration || (! $_records->count())) {
            return;
        }
        
        if (! ($resolveFields = $modelConfiguration->recordsFields)) {
            return;
        }
        
        $ownIds = $_records->{$modelConfiguration->idProperty};
        
        // iterate fields to resolve
        foreach ($resolveFields as $fieldKey => $c) {
            $config = $c['config'];
            
            // resolve records, if omitOnSearch is definitively set to FALSE (by default they won't be resolved on search)
            if ($multiple && !(isset($config['omitOnSearch']) && $config['omitOnSearch'] === FALSE)) {
                continue;
            }

            if (! isset($config['controllerClassName'])) {
                throw new Tinebase_Exception_UnexpectedValue('Controller class name needed');
            }

            // fetch the fields by the refIfField
            /** @var Tinebase_Controller_Record_Interface|Tinebase_Controller_SearchInterface $controller */
            /** @noinspection PhpUndefinedMethodInspection */
            $controller = $config['controllerClassName']::getInstance();
            $filterName = $config['filterClassName'];
            
            $filterArray = array();
            
            // addFilters can be added and must be added if the same model resides in more than one records fields
            if (isset($config['addFilters']) && is_array($config['addFilters'])) {
                $filterArray = $config['addFilters'];
            }

            /** @var Tinebase_Model_Filter_FilterGroup $filter */
            $filter = new $filterName($filterArray);
            $filter->addFilter(new Tinebase_Model_Filter_Id(array('field' => $config['refIdField'], 'operator' => 'in', 'value' => $ownIds)));
            
            $paging = NULL;
            if (isset($config['paging']) && is_array($config['paging'])) {
                $paging = new Tinebase_Model_Pagination($config['paging']);
            }
            
            $foreignRecords = $controller->search($filter, $paging);
            /** @var Tinebase_Record_Interface $foreignRecordClass */
            $foreignRecordClass = $foreignRecords->getRecordClassName();
            $foreignRecordModelConfiguration = $foreignRecordClass::getConfiguration();
            
            $foreignRecords->setTimezone(Tinebase_Core::getUserTimezone());
            $foreignRecords->convertDates = true;
            
            $fr = $foreignRecords->getFirstRecord();

            // @todo: resolve alarms?
            // @todo: use parts parameter?
            if ($foreignRecordModelConfiguration->resolveRelated && $fr) {
                if ($fr->has('notes')) {
                    Tinebase_Notes::getInstance()->getMultipleNotesOfRecords($foreignRecords);
                }
                if ($fr->has('tags')) {
                    Tinebase_Tags::getInstance()->getMultipleTagsOfRecords($foreignRecords);
                }
                if ($fr->has('relations')) {
                    $relations = Tinebase_Relations::getInstance()->getMultipleRelations($foreignRecordClass, 'Sql', $foreignRecords->{$fr->getIdProperty()} );
                    $foreignRecords->setByIndices('relations', $relations);
                }
                if ($fr->has('customfields')) {
                    Tinebase_CustomField::getInstance()->resolveMultipleCustomfields($foreignRecords);
                }
                if ($fr->has('attachments') && Tinebase_Core::isFilesystemAvailable()) {
                    Tinebase_FileSystem_RecordAttachments::getInstance()->getMultipleAttachmentsOfRecords($foreignRecords);
                }
            }
            
            if ($foreignRecords->count() > 0) {
                /** @var Tinebase_Record_Interface $record */
                foreach ($_records as $record) {
                    $filtered = $foreignRecords->filter($config['refIdField'], $record->getId())->toArray();
                    $filtered = $this->_resolveAfterToArray($filtered, $foreignRecordModelConfiguration, TRUE);
                    $record->{$fieldKey} = $filtered;
                }
                
            } else {
                $_records->{$fieldKey} = NULL;
            }
        }
        
    }

    /**
     * resolves virtual fields, if a function has been defined in the field definition
     *
     * @param array $resultSet
     * @param Tinebase_ModelConfiguration $modelConfiguration
     * @param boolean $multiple
     * @return array
     */
    protected function _resolveVirtualFields($resultSet, $modelConfiguration = NULL, $multiple = false)
    {
        if (! $modelConfiguration || ! ($virtualFields = $modelConfiguration->virtualFields)) {
            return $resultSet;
        }
        
        if ($modelConfiguration->resolveVFGlobally === TRUE) {

            /** @var Tinebase_Controller_Record_Interface $controller */
            $controller = $modelConfiguration->getControllerInstance();
            
            if ($multiple) {
                return $controller->resolveMultipleVirtualFields($resultSet);
            }
            return $controller->resolveVirtualFields($resultSet);
        }
        
        foreach ($virtualFields as $field) {
            // resolve virtual relation record from relations property
            if (isset($field['type']) && $field['type'] == 'relation') {

                // tODO probably move this array nesting to the top of the function, check 'function' todo below though
                if ($multiple) {
                    $tmp = &$resultSet;
                } else {
                    $tmp = array(&$resultSet);
                }
                foreach($tmp as &$rS) {
                    $fc = $field['config'];
                    if (isset($rS['relations']) && (is_array($rS['relations']))) {
                        foreach ($rS['relations'] as $relation) {
                            if (($relation['type'] == $fc['type']) && ($relation['related_model'] == ($fc['appName'] . '_Model_' . $fc['modelName']))) {
                                $rS[$field['key']] = $relation['related_record'];
                            }
                        }
                    }
                }
            // resolve virtual field by function
                // TODO multiple is not passed along and not part of the if condition! this is bad I guess this is dead code?
            } else if ((isset($field['function']) || array_key_exists('function', $field))) {
                if (is_array($field['function'])) {
                    if (count($field['function']) > 1) { // static method call
                        $class  = $field['function'][0];
                        $method = $field['function'][1];
                        $resultSet = $class::$method($resultSet);

                    } else { // use key as classname and value as method name
                        $ks = array_keys($field['function']);
                        $class  = array_pop($ks);
                        $vs = array_values($field['function']);
                        $method = array_pop($vs);
                        $class = $class::getInstance();
                        
                        $resultSet = $class->$method($resultSet);
                        
                    }
                // if no array has been given, this should be a function name
                } else {
                    $resolveFunction = $field['function'];
                    $resultSet = $resolveFunction($resultSet);
                }
            }
        }
        
        return $resultSet;
    }
    
    /**
     * resolves child records before converting the record set to an array
     * 
     * @param Tinebase_Record_RecordSet $records
     * @param Tinebase_ModelConfiguration $modelConfiguration
     * @param boolean $multiple
     */
    protected function _resolveBeforeToArray($records, $modelConfiguration, $multiple = false)
    {
        Tinebase_Frontend_Json_Abstract::resolveContainersAndTags($records, $modelConfiguration);

        self::resolveAttachmentImage($records);
        
        self::resolveMultipleIdFields($records);
        
        // use modern record resolving, if the model was configured using Tinebase_ModelConfiguration
        // at first, resolve all single record fields
        if ($modelConfiguration) {
            $this->_resolveSingleRecordFields($records, $modelConfiguration);
        
            // resolve all multiple records fields
            $this->_resolveMultipleRecordFields($records, $modelConfiguration, $multiple);
        }
    }

    /**
     * adds image property with image url like this:
     *  'index.php?method=Tinebase.getImage&application=Tinebase&location=vfs&id=e4b7de34e229672c0d5e22be0912779441e6e051'
     *
     * @param $records
     */
    static public function resolveAttachmentImage($records)
    {
        // get all images from attachments and set 'image' properties

        // TODO find an additional condition to better detect the attachments that should be the record image(s)

        /** @var Tinebase_Record_Interface $record */
        foreach ($records as $record) {
            if ($record->has('image') && $record->attachments instanceof Tinebase_Record_RecordSet) {
                foreach ($record->attachments as $attachment) {
                    if (in_array($attachment->contenttype, Tinebase_ImageHelper::getSupportedImageMimeTypes())) {
                        $record->image = Tinebase_Model_Image::getImageUrl('Tinebase', $attachment->getId(), 'vfs');
                        break;
                    }
                }
            }
        }
    }

    /**
     * resolves child records after converting the record set to an array
     *
     * @param array $result
     * @param Tinebase_ModelConfiguration $modelConfiguration
     * @param boolean $multiple
     *
     * @return array
     */
    protected function _resolveAfterToArray($result, $modelConfiguration, $multiple = false)
    {
        $result = $this->_resolveVirtualFields($result, $modelConfiguration, $multiple);
        return $result;
    }
}
