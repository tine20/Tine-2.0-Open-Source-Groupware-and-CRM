<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Alarm
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 */

/**
 * controller for alarms / reminder messages
 *
 * @package     Tinebase
 * @subpackage  Alarm
 */
class Tinebase_Alarm extends Tinebase_Controller_Record_Abstract
{
    /**
     * @var Tinebase_Backend_Sql
     */
    protected $_backend;
    
    /**
     * Model name
     *
     * @var string
     */
    protected $_modelName = Tinebase_Model_Alarm::class;
    
    /**
     * check for container ACLs?
     *
     * @var boolean
     */
    protected $_doContainerACLChecks = FALSE;
    
    /**
     * holds the instance of the singleton
     *
     * @var Tinebase_Alarm
     */
    private static $instance = NULL;
    
    /**
     * the constructor
     *
     */
    private function __construct()
    {
        $this->_backend = new Tinebase_Backend_Sql(array(
            'modelName' => $this->_modelName, 
            'tableName' => 'alarm',
        ));
    }
    
    /**
     * the singleton pattern
     *
     * @return Tinebase_Alarm
     */
    public static function getInstance() 
    {
        if (self::$instance === NULL) {
            self::$instance = new Tinebase_Alarm();
        }
        return self::$instance;
    }
    
    /**************************** public funcs *************************************/
    
    /**
     * send pending alarms
     *
     * @param mixed $_eventName
     * @return void
     * 
     * @todo sort alarms (by model/...)?
     * @todo what to do about Tinebase_Model_Alarm::STATUS_FAILURE alarms?
     */
    public function sendPendingAlarms($_eventName)
    {
        $eventName = (is_array($_eventName)) ? $_eventName['eventName'] : $_eventName;
        
        $job = Tinebase_AsyncJob::getInstance()->startJob($eventName);
        
        if (! $job) {
            return;
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' No ' . $eventName . ' is running. Starting new one.');
        
        try {
            // get all pending alarms
            $filter = new Tinebase_Model_AlarmFilter(array(
                array(
                    'field'     => 'alarm_time', 
                    'operator'  => 'before', 
                    'value'     => Tinebase_DateTime::now()->subMinute(1)->get(Tinebase_Record_Abstract::ISO8601LONG)
                ),
                array(
                    'field'     => 'sent_status', 
                    'operator'  => 'equals', 
                    'value'     => Tinebase_Model_Alarm::STATUS_PENDING // STATUS_FAILURE?
                ),
            ));
            
            $limit = Tinebase_Config::getInstance()->get(Tinebase_Config::ALARMS_EACH_JOB, 100);
            $pagination = ($limit > 0) ? new Tinebase_Model_Pagination(array(
                'limit' => $limit
            )) : null;
            
            $alarms = $this->_backend->search($filter, $pagination);
            
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
                ' Sending ' . count($alarms) . ' alarms (limit: ' . $limit . ').');
            
            // loop alarms and call sendAlarm in controllers
            foreach ($alarms as $alarm) {
                list($appName, , $itemName) = explode('_', $alarm->model);
                $appController = Tinebase_Core::getApplicationInstance($appName, $itemName);
            
                if ($appController instanceof Tinebase_Controller_Alarm_Interface) {
                
                    $alarm->sent_time = Tinebase_DateTime::now();
                
                    try {
                        // NOTE: we set the status here, so controller can adopt the status itself
                        $alarm->sent_status = Tinebase_Model_Alarm::STATUS_SUCCESS;
                        $appController->sendAlarm($alarm);
                    
                    } catch (Exception $e) {
                        Tinebase_Exception::log($e);
                        $alarm->sent_message = $e->getMessage();
                        $alarm->sent_status = Tinebase_Model_Alarm::STATUS_FAILURE;
                    } 
                
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' Updating alarm status: ' . $alarm->sent_status);
                
                    $this->update($alarm);
                }
            }
        
            $job = Tinebase_AsyncJob::getInstance()->finishJob($job);
        
        } catch (Exception $e) {
            // save new status 'failure'
            $job = Tinebase_AsyncJob::getInstance()->finishJob($job, Tinebase_Model_AsyncJob::STATUS_FAILURE, $e->getMessage());
            if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Job failed: ' . $e->getMessage());
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $e->getTraceAsString());
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Job ' . $eventName . ' finished.');
    }
    
    /**
     * get all alarms of given record(s) / adds record_id index to result set
     * 
     * @param  string $_model model to get alarms for
     * @param  string|array|Tinebase_Record_Interface|Tinebase_Record_RecordSet $_recordId record id(s) to get alarms for
     * @param  boolean $_onlyIds
     * @return Tinebase_Record_RecordSet|array of ids
     */
    public function getAlarmsOfRecord($_model, $_recordId, $_onlyIds = FALSE)
    {
        if ($_recordId instanceof Tinebase_Record_RecordSet) {
            $recordId = $_recordId->getArrayOfIds();
        } else if ($_recordId instanceof Tinebase_Record_Interface) {
            $recordId = $_recordId->getId();
        } else {
            $recordId = $_recordId;
        }
        
        //if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . "  model: '$_model' id:" . print_r((array)$recordId, true));
    
        $filter = new Tinebase_Model_AlarmFilter(array(
            array(
                'field'     => 'model', 
                'operator'  => 'equals', 
                'value'     => $_model
            ),
            array(
                'field'     => 'record_id', 
                'operator'  => 'in', 
                'value'     => (array)$recordId
            ),
        ));
        $result = $this->_backend->search($filter, NULL, $_onlyIds);
        
        // NOTE: Adding indices to empty recordsets breaks empty tests
        if (count($result) > 0 && $result instanceof Tinebase_Record_RecordSet) {
            $result->addIndices(array('record_id'));
        }
        
        return $result;
    }
    
    /**
     * set alarms of record
     *
     * @param Tinebase_Record_Interface $_record
     * @param string $_alarmsProperty
     * @return void
     */
    public function setAlarmsOfRecord(Tinebase_Record_Interface $_record, $_alarmsProperty = 'alarms')
    {
        $model = get_class($_record);
        $alarms = $_record->{$_alarmsProperty};
        
        $currentAlarms = $this->getAlarmsOfRecord($model, $_record);
        $diff = $currentAlarms->getMigration($alarms->getArrayOfIds());
        $this->_backend->delete($diff['toDeleteIds']);
        
        // create / update alarms
        foreach ($alarms as $alarm) {
            $id = $alarm->getId();
            
            if ($id) {
                $alarm = $this->_backend->update($alarm);
                
            } else {
                $alarm->record_id = $_record->getId();
                if (! $alarm->model) {
                    $alarm->model = $model;
                }
                $alarm = $this->_backend->create($alarm);
            }
        }
    }
    
    /**
     * delete all alarms of a given record(s)
     *
     * @param string $_model
     * @param string|array|Tinebase_Record_Interface|Tinebase_Record_RecordSet $_recordId
     * @return void
     */
    public function deleteAlarmsOfRecord($_model, $_recordId)
    {
        $ids = $this->getAlarmsOfRecord($_model, $_recordId, TRUE);
        $this->delete($ids);
    }
}
