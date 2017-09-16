<?php
/**
 * Tine 2.0
 * @package     Tinebase
 * @subpackage  Frontend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2008-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * cli server
 *
 * This class handles all requests from cli scripts
 *
 * @package     Tinebase
 * @subpackage  Frontend
 */
class Tinebase_Frontend_Cli extends Tinebase_Frontend_Cli_Abstract
{
    /**
     * the internal name of the application
     *
     * @var string
     */
    protected $_applicationName = 'Tinebase';

    /**
     * needed by demo data fns
     *
     * @var array
     */
    protected $_applicationsToWorkOn = array();

    /**
     * @param Zend_Console_Getopt $_opts
     * @return boolean success
     */
    public function increaseReplicationMasterId($opts)
    {
        if (!$this->_checkAdminRight()) {
            return -1;
        }

        Tinebase_Timemachine_ModificationLog::getInstance()->increaseReplicationMasterId();

        return true;
    }

    /**
     * @param Zend_Console_Getopt $_opts
     * @return boolean success
     */
    public function readModifictionLogFromMaster($opts)
    {
        if (!$this->_checkAdminRight()) {
            return -1;
        }

        Tinebase_Timemachine_ModificationLog::getInstance()->readModificationLogFromMaster();

        return true;
    }

    /**
     * rebuildPaths
     *
    * @param Zend_Console_Getopt $_opts
    * @return integer success
    */
    public function rebuildPaths($opts)
    {
        if (! $this->_checkAdminRight()) {
            return -1;
        }

        $result = Tinebase_Controller::getInstance()->rebuildPaths();

        return $result ? true : -1;
    }

    /**
     * forces containers that support sync token to resync via WebDAV sync tokens
     *
     * this will cause 2 BadRequest responses to sync token requests
     * the first one as soon as the client notices that something changed and sends a sync token request
     * eventually the client receives a false sync token (as we increased content sequence, but we dont have a content history entry)
     * eventually not (if something really changed in the calendar in the meantime)
     *
     * in case the client got a fake sync token, the clients next sync token request (once something really changed) will fail again
     * after something really changed valid sync tokens will be handed out again
     *
     * @param Zend_Console_Getopt $_opts
     */
    public function forceSyncTokenResync($_opts)
    {
        $args = $this->_parseArgs($_opts, array());
        $container = Tinebase_Container::getInstance();

        if (isset($args['userIds'])) {
            $args['userIds'] = !is_array($args['userIds']) ? array($args['userIds']) : $args['userIds'];
            $args['containerIds'] = $container->search(new Tinebase_Model_ContainerFilter(array(
                array('field' => 'owner_id', 'operator' => 'in', 'value' => $args['userIds'])
            )))->getId();
        }

        if (isset($args['containerIds'])) {
            $resultStr = '';

            if (!is_array($args['containerIds'])) {
                $args['containerIds'] = array($args['containerIds']);
            }

            $db = Tinebase_Core::getDb();


            $contentBackend = $container->getContentBackend();
            foreach($args['containerIds'] as $id) {
                $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($db);

                $containerData = $container->get($id);
                $recordsBackend = Tinebase_Core::getApplicationInstance($containerData->model)->getBackend();
                if (method_exists($recordsBackend, 'increaseSeqsForContainerId')) {
                    $recordsBackend->increaseSeqsForContainerId($id);

                    $container->increaseContentSequence($id);
                    $resultStr .= ($resultStr !== '' ? ', ' : '') . $id . '(' . $contentBackend->deleteByProperty($id,
                            'container_id') . ')';
                }
                Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
            }

            echo "\nDeleted containers(num content history records): " . $resultStr . "\n";
        }
    }

    /**
     * clean timemachine_modlog for records that have been pruned (not deleted!)
     */
    public function cleanModlog()
    {
        if (! $this->_checkAdminRight()) {
            return FALSE;
        }

        $deleted = Tinebase_Timemachine_ModificationLog::getInstance()->clean();

        echo "\ndeleted $deleted modlogs records\n";
    }

    /**
     * clean relations, set relation to deleted if at least one of the ends has been set to deleted or pruned
     */
    public function cleanRelations()
    {
        if (! $this->_checkAdminRight()) {
            return FALSE;
        }

        $relations = Tinebase_Relations::getInstance();
        $filter = new Tinebase_Model_Filter_FilterGroup();
        $pagination = new Tinebase_Model_Pagination();
        $pagination->limit = 10000;
        $pagination->sort = 'id';

        $totalCount = 0;
        $date = Tinebase_DateTime::now()->subYear(1);

        while ( ($recordSet = $relations->search($filter, $pagination)) && $recordSet->count() > 0 ) {
            $filter = new Tinebase_Model_Filter_FilterGroup();
            $pagination->start += $pagination->limit;
            $models = array();

            foreach($recordSet as $relation) {
                $models[$relation->own_model][$relation->own_id][] = $relation->id;
                $models[$relation->related_model][$relation->related_id][] = $relation->id;
            }
            foreach ($models as $model => &$ids) {
                $doAll = false;

                try {
                    $app = Tinebase_Core::getApplicationInstance($model, '', true);
                } catch (Tinebase_Exception_NotFound $tenf) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::INFO))
                        Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' model: ' . $model . ' no application found for it');
                    $doAll = true;
                }
                if (!$doAll) {
                    if ($app instanceof Tinebase_Container)
                    {
                        $backend = $app;
                    } else {
                        if (!$app instanceof Tinebase_Controller_Record_Abstract) {
                            if (Tinebase_Core::isLogLevel(Zend_Log::INFO))
                                Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' model: ' . $model . ' controller: ' . get_class($app) . ' not an instance of Tinebase_Controller_Record_Abstract');
                            continue;
                        }

                        $backend = $app->getBackend();
                    }
                    if (!$backend instanceof Tinebase_Backend_Interface) {
                        if (Tinebase_Core::isLogLevel(Zend_Log::INFO))
                            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' model: ' . $model . ' backend: ' . get_class($backend) . ' not an instance of Tinebase_Backend_Interface');
                        continue;
                    }
                    $record = new $model(null, true);

                    $modelFilter = $model . 'Filter';
                    $idFilter = new $modelFilter(array(), '', array('ignoreAcl' => true));
                    $idFilter->addFilter(new Tinebase_Model_Filter_Id(array(
                        'field' => $record->getIdProperty(), 'operator' => 'in', 'value' => array_keys($ids)
                    )));


                    $existingIds = $backend->search($idFilter, null, true);

                    if (!is_array($existingIds)) {
                        throw new Exception('search for model: ' . $model . ' returned not an array!');
                    }
                    foreach ($existingIds as $id) {
                        unset($ids[$id]);
                    }
                }

                if ( count($ids) > 0 ) {
                    $toDelete = array();
                    foreach ($ids as $idArrays) {
                        foreach ($idArrays as $id) {
                            $toDelete[$id] = true;
                        }
                    }

                    $toDelete = array_keys($toDelete);

                    foreach($toDelete as $id) {
                        if ( $recordSet->getById($id)->creation_time && $recordSet->getById($id)->creation_time->isLater($date) ) {
                            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' relation is about to get deleted that is younger than 1 year: ' . print_r($recordSet->getById($id)->toArray(false), true));
                        }
                    }

                    $relations->delete($toDelete);
                    $totalCount += count($toDelete);
                }
            }
        }

        $message = 'Deleted ' . $totalCount . ' relations in total';
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO))
            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' ' . $message);
        echo $message . "\n";
    }

    /**
     * authentication
     *
     * @param string $_username
     * @param string $_password
     */
    public function authenticate($_username, $_password)
    {
        $authResult = Tinebase_Auth::getInstance()->authenticate($_username, $_password);
        
        if ($authResult->isValid()) {
            $accountsController = Tinebase_User::getInstance();
            try {
                $account = $accountsController->getFullUserByLoginName($authResult->getIdentity());
            } catch (Tinebase_Exception_NotFound $e) {
                echo 'account ' . $authResult->getIdentity() . ' not found in account storage'."\n";
                exit();
            }
            
            Tinebase_Core::set('currentAccount', $account);

            $ipAddress = '127.0.0.1';
            $account->setLoginTime($ipAddress);

            Tinebase_AccessLog::getInstance()->create(new Tinebase_Model_AccessLog(array(
                'sessionid'     => 'cli call',
                'login_name'    => $authResult->getIdentity(),
                'ip'            => $ipAddress,
                'li'            => Tinebase_DateTime::now()->get(Tinebase_Record_Abstract::ISO8601LONG),
                'lo'            => Tinebase_DateTime::now()->get(Tinebase_Record_Abstract::ISO8601LONG),
                'result'        => $authResult->getCode(),
                'account_id'    => Tinebase_Core::getUser()->getId(),
                'clienttype'    => 'TineCli',
            )));
            
        } else {
            echo "Wrong username and/or password.\n";
            exit();
        }
    }
    
    /**
     * handle request (call -ApplicationName-_Cli.-MethodName- or -ApplicationName-_Cli.getHelp)
     *
     * @param Zend_Console_Getopt $_opts
     * @return boolean success
     */
    public function handle($_opts)
    {
        list($application, $method) = explode('.', $_opts->method);
        $class = $application . '_Frontend_Cli';
        
        if (@class_exists($class)) {
            $object = new $class;
            if ($_opts->info) {
                $result = $object->getHelp();
            } else if (method_exists($object, $method)) {
                $result = call_user_func(array($object, $method), $_opts);
            } else {
                $result = FALSE;
                echo "Method $method not found.\n";
            }
        } else {
            echo "Class $class does not exist.\n";
            $result = FALSE;
        }
        
        return $result;
    }

    /**
     * trigger async events (for example via cronjob)
     *
     * @param Zend_Console_Getopt $_opts
     * @return boolean success
     */
    public function triggerAsyncEvents($_opts)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Triggering async events from CLI.');

        $freeLock = $this->_aquireMultiServerLock(__CLASS__ . '::' . __FUNCTION__ . '::' . Tinebase_Core::getTinebaseId());
        if (! $freeLock) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                .' Job already running.');
            return false;
        }
        
        $userController = Tinebase_User::getInstance();

        try {
            $cronuser = $userController->getFullUserByLoginName($_opts->username);
        } catch (Tinebase_Exception_NotFound $tenf) {
            $cronuser = $this->_getCronuserFromConfigOrCreateOnTheFly();
        }
        Tinebase_Core::set(Tinebase_Core::USER, $cronuser);
        
        $scheduler = Tinebase_Core::getScheduler();
        $responses = $scheduler->run();
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .' ' . print_r(array_keys($responses), TRUE));
        
        $responseString = ($responses) ? implode(',', array_keys($responses)) : 'NULL';
        echo "Tine 2.0 scheduler run (" . $responseString . ") complete.\n";
        
        return true;
    }

    /**
     * process given queue job
     *  --jobId the queue job id to execute
     *
     * @param Zend_Console_Getopt $_opts
     * @return bool success
     * @throws Tinebase_Exception_InvalidArgument
     */
    public function executeQueueJob($_opts)
    {
        try {
            $cronuser = Tinebase_User::getInstance()->getFullUserByLoginName($_opts->username);
        } catch (Tinebase_Exception_NotFound $tenf) {
            $cronuser = $this->_getCronuserFromConfigOrCreateOnTheFly();
        }
        
        Tinebase_Core::set(Tinebase_Core::USER, $cronuser);
        
        $args = $_opts->getRemainingArgs();
        $jobId = preg_replace('/^jobId=/', '', $args[0]);
        
        if (! $jobId) {
            throw new Tinebase_Exception_InvalidArgument('mandatory parameter "jobId" is missing');
        }

        $actionQueue = Tinebase_ActionQueue::getInstance();
        $job = $actionQueue->receive($jobId);

        if (isset($job['account_id'])) {
            Tinebase_Core::set(Tinebase_Core::USER, Tinebase_User::getInstance()->getFullUserById($job['account_id']));
        }

        $result = $actionQueue->executeAction($job);
        
        return false !== $result;
    }
    
    /**
     * clear table as defined in arguments
     * can clear the following tables:
     * - credential_cache
     * - access_log
     * - async_job
     * - temp_files
     * 
     * if param date is given (date=2010-09-17), all records before this date are deleted (if the table has a date field)
     * 
     * @param $_opts
     * @return boolean success
     */
    public function clearTable(Zend_Console_Getopt $_opts)
    {
        if (! $this->_checkAdminRight()) {
            return FALSE;
        }
        
        $args = $this->_parseArgs($_opts, array('tables'), 'tables');
        $dateString = (isset($args['date']) || array_key_exists('date', $args)) ? $args['date'] : NULL;

        $db = Tinebase_Core::getDb();
        foreach ($args['tables'] as $table) {
            switch ($table) {
                case 'access_log':
                    $date = ($dateString) ? new Tinebase_DateTime($dateString) : NULL;
                    Tinebase_AccessLog::getInstance()->clearTable($date);
                    break;
                case 'async_job':
                    $where = ($dateString) ? array(
                        $db->quoteInto($db->quoteIdentifier('end_time') . ' < ?', $dateString)
                    ) : array();
                    $where[] = $db->quoteInto($db->quoteIdentifier('status') . ' < ?', 'success');
                    
                    echo "\nRemoving all successful async_job entries " . ($dateString ? "before $dateString " : "") . "...";
                    $deleteCount = $db->delete(SQL_TABLE_PREFIX . $table, $where);
                    echo "\nRemoved $deleteCount records.";
                    break;
                case 'credential_cache':
                    Tinebase_Auth_CredentialCache::getInstance()->clearCacheTable($dateString);
                    break;
                case 'temp_files':
                    Tinebase_TempFile::getInstance()->clearTableAndTempdir($dateString);
                    break;
                default:
                    echo 'Table ' . $table . " not supported or argument missing.\n";
            }
            echo "\nCleared table $table.";
        }
        echo "\n\n";
        
        return TRUE;
    }
    
    /**
     * purge deleted records
     * 
     * if param date is given (for example: date=2010-09-17), all records before this date are deleted (if the table has a date field)
     * if table names are given, purge only records from this tables
     * 
     * @param $_opts
     * @return boolean success
     *
     * TODO move purge logic to applications, purge Tinebase tables at the end
     */
    public function purgeDeletedRecords(Zend_Console_Getopt $_opts)
    {
        if (! $this->_checkAdminRight()) {
            return FALSE;
        }

        $args = $this->_parseArgs($_opts, array(), 'tables');
        $doEverything = false;

        if (! (isset($args['tables']) || array_key_exists('tables', $args)) || empty($args['tables'])) {
            echo "No tables given.\nPurging records from all tables!\n";
            $args['tables'] = $this->_getAllApplicationTables();
            $doEverything = true;
        }
        
        $db = Tinebase_Core::getDb();
        
        if ((isset($args['date']) || array_key_exists('date', $args))) {
            echo "\nRemoving all deleted entries before {$args['date']} ...";
            $where = array(
                $db->quoteInto($db->quoteIdentifier('deleted_time') . ' < ?', $args['date'])
            );
        } else {
            echo "\nRemoving all deleted entries ...";
            $where = array();
        }
        $where[] = $db->quoteInto($db->quoteIdentifier('is_deleted') . ' = ?', 1);

        $orderedTables = $this->_orderTables($args['tables']);
        $this->_purgeTables($orderedTables, $where);

        if ($doEverything) {
            echo "\nCleaning relations...";
            $this->cleanRelations();

            echo "\nCleaning modlog...";
            $this->cleanModlog();

            echo "\nCleaning customfields...";
            $this->cleanCustomfields();

            echo "\nCleaning notes...";
            $this->cleanNotes();
        }

        echo "\n\n";
        
        return TRUE;
    }

    /**
     * cleanNotes: removes notes of records that have been deleted
     */
    public function cleanNotes()
    {
        if (! $this->_checkAdminRight()) {
            return FALSE;
        }

        $notesController = Tinebase_Notes::getInstance();
        $notes = $notesController->getAllNotes();
        $controllers = array();
        $models = array();
        $deleteIds = array();

        /** @var Tinebase_Model_Note $note */
        foreach ($notes as $note) {
            if (!isset($controllers[$note->record_model])) {
                if (strpos($note->record_model, 'Tinebase') === 0) {
                    continue;
                }
                try {
                    $controllers[$note->record_model] = Tinebase_Core::getApplicationInstance($note->record_model);
                } catch(Tinebase_Exception_AccessDenied $e) {
                    // TODO log
                    continue;
                } catch(Tinebase_Exception_NotFound $tenf) {
                    $deleteIds[] = $note->getId();
                    continue;
                }
                $oldACLCheckValue = $controllers[$note->record_model]->doContainerACLChecks(false);
                $models[$note->record_model] = array(
                    0 => new $note->record_model(),
                    1 => ($note->record_model !== 'Filemanager_Model_Node' ? class_exists($note->record_model . 'Filter') : false),
                    2 => $note->record_model . 'Filter',
                    3 => $oldACLCheckValue
                );
            }
            $controller = $controllers[$note->record_model];
            $model = $models[$note->record_model];

            if ($model[1]) {
                $filter = new $model[2](array(
                    array('field' => $model[0]->getIdProperty(), 'operator' => 'equals', 'value' => $note->record_id)
                ));
                if ($model[0]->has('is_deleted')) {
                    $filter->addFilter(new Tinebase_Model_Filter_Int(array('field' => 'is_deleted', 'operator' => 'notnull', 'value' => NULL)));
                }
                $result = $controller->searchCount($filter);

                if (is_bool($result) || (is_string($result) && $result === ((string)intval($result)))) {
                    $result = (int)$result;
                }

                if (!is_int($result)) {
                    if (is_array($result) && isset($result['totalcount'])) {
                        $result = (int)$result['totalcount'];
                    } elseif(is_array($result) && isset($result['count'])) {
                        $result = (int)$result['count'];
                    } else {
                        // todo log
                        // dummy line, remove!
                        $result = 1;
                    }
                }

                if ($result === 0) {
                    $deleteIds[] = $note->getId();
                }
            } else {
                try {
                    $controller->get($note->record_id, null, false, true);
                } catch(Tinebase_Exception_NotFound $tenf) {
                    $deleteIds[] = $note->getId();
                }
            }
        }

        if (count($deleteIds) > 0) {
            $notesController->purgeNotes($deleteIds);
        }

        foreach($controllers as $model => $controller) {
            $controller->doContainerACLChecks($models[$model][3]);
        }

        echo "\ndeleted " . count($deleteIds) . " notes\n";
    }

    /**
     * cleanCustomfields
     */
    public function cleanCustomfields()
    {
        if (! $this->_checkAdminRight()) {
            return FALSE;
        }

        $customFieldController = Tinebase_CustomField::getInstance();
        $customFieldConfigs = $customFieldController->searchConfig();
        $deleteCount = 0;

        /** @var Tinebase_Model_CustomField_Config $customFieldConfig */
        foreach($customFieldConfigs as $customFieldConfig) {
            $deleteAll = false;
            try {
                $controller = Tinebase_Core::getApplicationInstance($customFieldConfig->model);

                $oldACLCheckValue = $controller->doContainerACLChecks(false);
                if ($customFieldConfig->model !== 'Filemanager_Model_Node') {
                    $filterClass = $customFieldConfig->model . 'Filter';
                } else {
                    $filterClass = 'ClassThatDoesNotExist';
                }
            } catch(Tinebase_Exception_AccessDenied $e) {
                // TODO log
                continue;
            } catch(Tinebase_Exception_NotFound $tenf) {
                $deleteAll = true;
            }



            $filter = new Tinebase_Model_CustomField_ValueFilter(array(
                array('field' => 'customfield_id', 'operator' => 'equals', 'value' => $customFieldConfig->id)
            ));
            $customFieldValues = $customFieldController->search($filter);
            $deleteIds = array();

            if (true === $deleteAll) {
                $deleteIds = $customFieldValues->getId();
            } elseif (class_exists($filterClass)) {
                $model = new $customFieldConfig->model();
                /** @var Tinebase_Model_CustomField_Value $customFieldValue */
                foreach ($customFieldValues as $customFieldValue) {
                    $filter = new $filterClass(array(
                        array('field' => $model->getIdProperty(), 'operator' => 'equals', 'value' => $customFieldValue->record_id)
                    ));
                    if ($model->has('is_deleted')) {
                        $filter->addFilter(new Tinebase_Model_Filter_Int(array('field' => 'is_deleted', 'operator' => 'notnull', 'value' => NULL)));
                    }

                    $result = $controller->searchCount($filter);

                    if (is_bool($result) || (is_string($result) && $result === ((string)intval($result)))) {
                        $result = (int)$result;
                    }

                    if (!is_int($result)) {
                        if (is_array($result) && isset($result['totalcount'])) {
                            $result = (int)$result['totalcount'];
                        } elseif(is_array($result) && isset($result['count'])) {
                            $result = (int)$result['count'];
                        } else {
                            // todo log
                            // dummy line, remove!
                            $result = 1;
                        }
                    }

                    if ($result === 0) {
                        $deleteIds[] = $customFieldValue->getId();
                    }
                }
            } else {
                /** @var Tinebase_Model_CustomField_Value $customFieldValue */
                foreach ($customFieldValues as $customFieldValue) {
                    try {
                        $controller->get($customFieldValue->record_id, null, false, true);
                    } catch(Tinebase_Exception_NotFound $tenf) {
                        $deleteIds[] = $customFieldValue->getId();
                    }
                }
            }

            if (count($deleteIds) > 0) {
                $customFieldController->deleteCustomFieldValue($deleteIds);
                $deleteCount += count($deleteIds);
            }

            if (true !== $deleteAll) {
                $controller->doContainerACLChecks($oldACLCheckValue);
            }
        }

        echo "\ndeleted " . $deleteCount . " customfield values\n";
    }
    
    /**
     * get all app tables
     * 
     * @return array
     */
    protected function _getAllApplicationTables()
    {
        $result = array();
        
        $enabledApplications = Tinebase_Application::getInstance()->getApplicationsByState(Tinebase_Application::ENABLED);
        foreach ($enabledApplications as $application) {
            $result = array_merge($result, Tinebase_Application::getInstance()->getApplicationTables($application));
        }
        
        return $result;
    }

    /**
     * order tables for purging deleted records in a defined order
     *
     * @param array $tables
     * @return array
     *
     * TODO could be improved by using usort
     */
    protected function _orderTables($tables)
    {
        // tags should be deleted first
        // containers should be deleted last

        $orderedTables = array();
        $lastTables = array();
        foreach($tables as $table) {
            switch ($table) {
                case 'container':
                    $lastTables[] = $table;
                    break;
                case 'tags':
                    array_unshift($orderedTables, $table);
                    break;
                default:
                    $orderedTables[] = $table;
            }
        }
        $orderedTables = array_merge($orderedTables, $lastTables);

        return $orderedTables;
    }

    /**
     * purge tables
     *
     * @param $orderedTables
     * @param $where
     */
    protected function _purgeTables($orderedTables, $where)
    {
        foreach ($orderedTables as $table) {
            try {
                $schema = Tinebase_Db_Table::getTableDescriptionFromCache(SQL_TABLE_PREFIX . $table);
            } catch (Zend_Db_Statement_Exception $zdse) {
                echo "\nCould not get schema (" . $zdse->getMessage() . "). Skipping table $table";
                continue;
            }
            if (!(isset($schema['is_deleted']) || array_key_exists('is_deleted', $schema)) || !(isset($schema['deleted_time']) || array_key_exists('deleted_time', $schema))) {
                continue;
            }

            $deleteCount = 0;
            try {
                $deleteCount = Tinebase_Core::getDb()->delete(SQL_TABLE_PREFIX . $table, $where);
            } catch (Zend_Db_Statement_Exception $zdse) {
                echo "\nFailed to purge deleted records for table $table. " . $zdse->getMessage();
            }
            if ($deleteCount > 0) {
                echo "\nCleared table $table (deleted $deleteCount records).";
            }
            // TODO this should only be echoed with --verbose or written to the logs
            else {
                echo "\nNothing to purge from $table";
            }
        }
    }

    /**
     * add new customfield config
     *
     * example:
     * $ php tine20.php --method=Tinebase.addCustomfield -- \
         application="Addressbook" model="Addressbook_Model_Contact" name="datefield" \
         definition='{"label":"Date","type":"datetime", "uiconfig": {"group":"Dates", "order": 30}}'
     * @see Tinebase_Model_CustomField_Config for full list
     *
     * @param $_opts
     * @return boolean success
     */
    public function addCustomfield(Zend_Console_Getopt $_opts)
    {
        if (! $this->_checkAdminRight()) {
            return FALSE;
        }
        
        // parse args
        $args = $_opts->getRemainingArgs();
        $data = array();
        foreach ($args as $idx => $arg) {
            list($key, $value) = explode('=', $arg);
            if ($key == 'application') {
                $key = 'application_id';
                $value = Tinebase_Application::getInstance()->getApplicationByName($value)->getId();
            }
            $data[$key] = $value;
        }
        
        $customfieldConfig = new Tinebase_Model_CustomField_Config($data);
        $cf = Tinebase_CustomField::getInstance()->addCustomField($customfieldConfig);

        echo "\nCreated customfield: ";
        print_r($cf->toArray());
        echo "\n";
        
        return TRUE;
    }
    
    /**
     * nagios monitoring for tine 2.0 database connection
     * 
     * @return integer
     * @see http://nagiosplug.sourceforge.net/developer-guidelines.html#PLUGOUTPUT
     */
    public function monitoringCheckDB()
    {
        $message = 'DB CONNECTION FAIL';
        try {
            if (! Setup_Core::isRegistered(Setup_Core::CONFIG)) {
                Setup_Core::setupConfig();
            }
            if (! Setup_Core::isRegistered(Setup_Core::LOGGER)) {
                Setup_Core::setupLogger();
            }
            $time_start = microtime(true);
            $dbcheck = Setup_Core::setupDatabaseConnection();
            $time = (microtime(true) - $time_start) * 1000;
        } catch (Exception $e) {
            $message .= ': ' . $e->getMessage();
            $dbcheck = FALSE;
        }
        
        if ($dbcheck) {
            echo "DB CONNECTION OK | connecttime={$time}ms;;;;\n";
            return 0;
        } 
        
        echo $message . "\n";
        return 2;
    }
    
    /**
     * nagios monitoring for tine 2.0 config file
     * 
     * @return integer
     * @see http://nagiosplug.sourceforge.net/developer-guidelines.html#PLUGOUTPUT
     */
    public function monitoringCheckConfig()
    {
        $message = 'CONFIG FAIL';
        $configcheck = FALSE;
        
        $configfile = Setup_Core::getConfigFilePath();
        if ($configfile) {
            $configfile = escapeshellcmd($configfile);
            if (preg_match('/^win/i', PHP_OS)) {
                exec("php -l $configfile 2> NUL", $error, $code);
            } else {
                exec("php -l $configfile 2> /dev/null", $error, $code);
            }
            if ($code == 0) {
                $configcheck = TRUE;
            } else {
                $message .= ': CONFIG FILE SYNTAX ERROR';
            }
        } else {
            $message .= ': CONFIG FILE MISSING';
        }
        
        if ($configcheck) {
            echo "CONFIG FILE OK\n";
            return 0;
        } else {
            echo $message . "\n";
            return 2;
        }
    }
    
    /**
    * nagios monitoring for tine 2.0 async cronjob run
    *
    * @return integer
    * 
    * @see http://nagiosplug.sourceforge.net/developer-guidelines.html#PLUGOUTPUT
    * @see 0008038: monitoringCheckCron -> check if cron did run in the last hour
    */
    public function monitoringCheckCron()
    {
        $message = 'CRON FAIL';

        try {
            $lastJob = Tinebase_AsyncJob::getInstance()->getLastJob('Tinebase_Event_Async_Minutely');
            
            if ($lastJob === NULL) {
                $message .= ': NO LAST JOB FOUND';
                $result = 1;
            } else {
                if ($lastJob->end_time instanceof Tinebase_DateTime) {
                    $duration = $lastJob->end_time->getTimestamp() - $lastJob->start_time->getTimestamp();
                    $valueString = ' | duration=' . $duration . 's;;;;';
                    $valueString .= ' end=' . $lastJob->end_time->getIso() . ';;;;';
                } else {
                    $valueString = '';
                }
                
                if ($lastJob->status === Tinebase_Model_AsyncJob::STATUS_RUNNING && Tinebase_DateTime::now()->isLater($lastJob->end_time)) {
                    $message .= ': LAST JOB TOOK TOO LONG';
                    $result = 1;
                } else if ($lastJob->status === Tinebase_Model_AsyncJob::STATUS_FAILURE) {
                    $message .= ': LAST JOB FAILED';
                    $result = 1;
                } else if (Tinebase_DateTime::now()->isLater($lastJob->start_time->addHour(1))) {
                    $message .= ': NO JOB IN THE LAST HOUR';
                    $result = 1;
                } else {
                    $message = 'CRON OK';
                    $result = 0;
                }
                $message .= $valueString;
            }
        } catch (Exception $e) {
            $message .= ': ' . $e->getMessage();
            $result = 2;
        }
        
        echo $message . "\n";
        return $result;
    }
    
    /**
     * nagios monitoring for tine 2.0 logins during the last 5 mins
     * 
     * @return number
     * 
     * @todo allow to configure timeslot
     */
    public function monitoringLoginNumber()
    {
        $message = 'LOGINS';
        $result  = 0;
        
        try {
            $filter = new Tinebase_Model_AccessLogFilter(array(
                array('field' => 'li', 'operator' => 'after', 'value' => Tinebase_DateTime::now()->subMinute(5))
            ));
            $accesslogs = Tinebase_AccessLog::getInstance()->search($filter, NULL, FALSE, TRUE);
            $valueString = ' | count=' . count($accesslogs) . ';;;;';
            $message .= ' OK' . $valueString;
        } catch (Exception $e) {
            $message .= ' FAIL: ' . $e->getMessage();
            $result = 2;
        }
        
        echo $message . "\n";
        return $result;
    }

    /**
     * nagios monitoring for tine 2.0 active users
     *
     * @return number
     *
     * @todo allow to configure timeslot / currently the active users of the last month are returned
     */
    public function monitoringActiveUsers()
    {
        $message = 'ACTIVE USERS';
        $result  = 0;

        try {
            $userCount = Tinebase_User::getInstance()->getActiveUserCount();
            $valueString = ' | count=' . $userCount . ';;;;';
            $message .= ' OK' . $valueString;
        } catch (Exception $e) {
            $message .= ' FAIL: ' . $e->getMessage();
            $result = 2;
        }

        echo $message . "\n";
        return $result;
    }

    /**
     * undo changes to records defined by certain criteria (user, date, fields, ...)
     * 
     * example: $ php tine20.php --username pschuele --method Tinebase.undo -d 
     *   -- record_type=Addressbook_Model_Contact modification_time=2013-05-08 modification_account=3263
     * 
     * @param Zend_Console_Getopt $opts
     */
    public function undo(Zend_Console_Getopt $opts)
    {
        if (! $this->_checkAdminRight()) {
            return FALSE;
        }
        
        $data = $this->_parseArgs($opts, array('modification_time'));
        
        // build filter from params
        $filterData = array();
        $allowedFilters = array(
            'record_type',
            'modification_time',
            'modification_account',
            'record_id'
        );
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFilters)) {
                $operator = ($key === 'modification_time') ? 'within' : 'equals';
                $filterData[] = array('field' => $key, 'operator' => $operator, 'value' => $value);
            }
        }
        $filter = new Tinebase_Model_ModificationLogFilter($filterData);
        
        $dryrun = $opts->d;
        $overwrite = (isset($data['overwrite']) && $data['overwrite']) ? TRUE : FALSE;
        $result = Tinebase_Timemachine_ModificationLog::getInstance()->undo($filter, $overwrite, $dryrun, (isset($data['modified_attribute'])?$data['modified_attribute']:null));
        
        if (! $dryrun) {
            echo 'Reverted ' . $result['totalcount'] . " change(s)\n";
        } else {
            echo "Dry run\n";
            echo 'Would revert ' . $result['totalcount'] . " change(s):\n";
            foreach ($result['undoneModlogs'] as $modlog) {
                $modifiedAttribute = $modlog->modified_attribute;
                if (!empty($modifiedAttribute)) {
                    echo 'id ' . $modlog->record_id . ' [' . $modifiedAttribute . ']: ' . $modlog->new_value . ' -> ' . $modlog->old_value . PHP_EOL;
                } else {
                    if ($modlog->change_type === Tinebase_Timemachine_ModificationLog::CREATED) {
                        echo 'id ' . $modlog->record_id . ' DELETE' . PHP_EOL;
                    } elseif ($modlog->change_type === Tinebase_Timemachine_ModificationLog::DELETED) {
                        echo 'id ' . $modlog->record_id . ' UNDELETE' . PHP_EOL;
                    } else {
                        $diff = new Tinebase_Record_Diff(json_decode($modlog->new_value));
                        foreach($diff->diff as $key => $val) {
                            echo 'id ' . $modlog->record_id . ' [' . $key . ']: ' . $val . ' -> ' . $diff->oldData[$key] . PHP_EOL;
                        }
                    }
                }
            }
        }
        echo 'Failcount: ' . $result['failcount'] . "\n";
        return 0;
    }
    
    /**
     * creates demo data for all applications
     * accepts same arguments as Tinebase_Frontend_Cli_Abstract::createDemoData
     * and the additional argument "skipAdmin" to force no user/group/role creation
     * 
     * @param Zend_Console_Getopt $_opts
     */
    public function createAllDemoData($_opts)
    {
        if (! $this->_checkAdminRight()) {
            return FALSE;
        }
        
        // fetch all applications and check if required are installed, otherwise remove app from array
        $applications = Tinebase_Application::getInstance()->getApplicationsByState(Tinebase_Application::ENABLED)->name;
        foreach($applications as $appName) {
            echo 'Searching for DemoData in application "' . $appName . '"...' . PHP_EOL;
            $className = $appName.'_Setup_DemoData';
            if (class_exists($className)) {
                echo 'DemoData in application "' . $appName . '" found!' . PHP_EOL;
                $required = $className::getRequiredApplications();
                foreach($required as $requiredApplication) {
                    if (! Tinebase_Helper::in_array_case($applications, $requiredApplication)) {
                        echo 'Creating DemoData for Application ' . $appName . ' is impossible, because application "' . $requiredApplication . '" is not installed.' . PHP_EOL;
                        continue 2;
                    }
                }
                $this->_applicationsToWorkOn[$appName] = array('appName' => $appName, 'required' => $required);
            } else {
                echo 'DemoData in application "' . $appName . '" not found.' . PHP_EOL . PHP_EOL;
            }
        }
        unset($applications);
        
        foreach($this->_applicationsToWorkOn as $app => $cfg) {
            $this->_createDemoDataRecursive($app, $cfg, $_opts);
        }

        return 0;
    }
    
    /**
     * creates demo data and calls itself if there are required apps
     * 
     * @param string $app
     * @param array $cfg
     * @param Zend_Console_Getopt $opts
     */
    protected function _createDemoDataRecursive($app, $cfg, $opts)
    {
        if (isset($cfg['required']) && is_array($cfg['required'])) {
            foreach($cfg['required'] as $requiredApp) {
                $this->_createDemoDataRecursive($requiredApp, $this->_applicationsToWorkOn[$requiredApp], $opts);
            }
        }
        
        $className = $app . '_Frontend_Cli';
        
        $classNameDD = $app . '_Setup_DemoData';
        
        if (class_exists($className)) {
            if (! $classNameDD::hasBeenRun()) {
                echo 'Creating DemoData in application "' . $app . '"...' . PHP_EOL;
                $class = new $className();
                $class->createDemoData($opts, FALSE);
            } else {
                echo 'DemoData for ' . $app . ' has been run already, skipping...' . PHP_EOL;
            }
        } else {
            echo 'Could not found ' . $className . ', so DemoData for application "' . $app . '" could not be created!';
        }
    }
    
    /**
     * clears deleted files from filesystem + database
     *
     * @return int
     */
    public function clearDeletedFiles()
    {
        if (! $this->_checkAdminRight()) {
            return -1;
        }
        
        $this->_addOutputLogWriter();
        
        Tinebase_FileSystem::getInstance()->clearDeletedFiles();

        return 0;
    }

    /**
     * recalculates the revision sizes and then the folder sizes
     *
     * @return int
     */
    public function fileSystemSizeRecalculation()
    {
        if (! $this->_checkAdminRight()) {
            return -1;
        }

        Tinebase_FileSystem::getInstance()->recalculateRevisionSize();

        Tinebase_FileSystem::getInstance()->recalculateFolderSize();

        return 0;
    }

    /**
     * checks if there are not yet indexed file objects and adds them to the index synchronously
     * that means this can be very time consuming
     *
     * @return int
     */
    public function fileSystemCheckIndexing()
    {

        if (! $this->_checkAdminRight()) {
            return -1;
        }

        Tinebase_FileSystem::getInstance()->checkIndexing();

        return 0;
    }

    /**
     * repair a table
     * 
     * @param Zend_Console_Getopt $opts
     * 
     * @todo add more tables
     */
    public function repairTable($opts)
    {
        if (! $this->_checkAdminRight()) {
            return FALSE;
        }
        
        $this->_addOutputLogWriter();
        
        $data = $this->_parseArgs($opts, array('table'));
        
        switch ($data['table']) {
            case 'importexport_definition':
                Tinebase_ImportExportDefinition::getInstance()->repairTable();
                $result = 0;
                break;
            default:
                if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                    . ' No repair script found for ' . $data['table']);
                $result = 1;
        }
        
        exit($result);
    }

    /**
     * transfer relations
     * 
     * @param Zend_Console_Getopt $opts
     */
    public function transferRelations($opts)
    {
        if (! $this->_checkAdminRight()) {
            return FALSE;
        }
        
        $this->_addOutputLogWriter();
        
        try {
            $args = $this->_parseArgs($opts, array('oldId', 'newId', 'model'));
        } catch (Tinebase_Exception_InvalidArgument $e) {
            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) {
                Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Parameters "oldId", "newId" and "model" are required!');
            }
            exit(1);
        }
        
        $skippedEntries = Tinebase_Relations::getInstance()->transferRelations($args['oldId'], $args['newId'], $args['model']);

        if (! empty($skippedEntries) && Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) {
            Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ' . count($skippedEntries) . ' entries has been skipped:');
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) {
            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' The operation has been terminated successfully.');
        }

        return 0;
    }

    /**
     * repair function for persistent filters (favorites) without grants: this adds default grants for those filters.
     *
     * @return int
     */
    public function setDefaultGrantsOfPersistentFilters()
    {
        if (! $this->_checkAdminRight()) {
            return -1;
        }

        $this->_addOutputLogWriter(6);

        // get all persistent filters without grants
        // TODO this could be enhanced by allowing to set default grants for other filters, too
        Tinebase_PersistentFilter::getInstance()->doContainerACLChecks(false);
        $filters = Tinebase_PersistentFilter::getInstance()->search(new Tinebase_Model_PersistentFilterFilter(array(),'', array('ignoreAcl' => true)));
        $filtersWithoutGrants = 0;

        foreach ($filters as $filter) {
            if (count($filter->grants) == 0) {
                // update to set default grants
                $filter = Tinebase_PersistentFilter::getInstance()->update($filter);
                $filtersWithoutGrants++;

                if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) {
                    Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                        . ' Updated filter: ' . print_r($filter->toArray(), true));
                }
            }
        }

        if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) {
            Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                . ' Set default grants for ' . $filtersWithoutGrants . ' filters'
                . ' (checked ' . count($filters) . ' in total).');
        }

        return 0;
    }

    /**
     *
     *
     * @return int
     */
    public function repairContainerOwner()
    {
        if (! $this->_checkAdminRight()) {
            return 2;
        }

        $this->_addOutputLogWriter(6);
        Tinebase_Container::getInstance()->setContainerOwners();

        return 0;
    }

    /**
     * show user report (number of enabled, disabled, ... users)
     *
     * TODO add system user count
     * TODO use twig?
     */
    public function userReport()
    {
        if (! $this->_checkAdminRight()) {
            return 2;
        }

        $translation = Tinebase_Translation::getTranslation('Tinebase');

        $userStatus = array(
            'total' => array(),
            Tinebase_Model_User::ACCOUNT_STATUS_ENABLED => array(/* 'showUserNames' => true, 'showClients' => true */),
            Tinebase_Model_User::ACCOUNT_STATUS_DISABLED => array(),
            Tinebase_Model_User::ACCOUNT_STATUS_BLOCKED => array(),
            Tinebase_Model_User::ACCOUNT_STATUS_EXPIRED => array(),
            //'system' => array(),
            'lastmonth' => array('lastMonths' => 1, 'showUserNames' => true, 'showClients' => true),
            'last 3 months' => array('lastMonths' => 3),
        );

        foreach ($userStatus as $status => $options) {
            switch ($status) {
                case 'lastmonth':
                case 'last 3 months':
                    $userCount = Tinebase_User::getInstance()->getActiveUserCount($options['lastMonths']);
                    $text = $translation->_("Number of distinct users") . " (" . $status . "): " . $userCount . "\n";
                    break;
                case 'system':
                    $text = "TODO add me\n";
                    break;
                default:
                    $userCount = Tinebase_User::getInstance()->getUserCount($status);
                    $text = $translation->_("Number of users") . " (" . $status . "): " . $userCount . "\n";
            }
            echo $text;

            if (isset($options['showUserNames']) && $options['showUserNames']
                && in_array($status, array('lastmonth', 'last 3 months'))
                && isset($options['lastMonths'])
            ) {
                // TODO allow this for other status
                echo $translation->_("  User Accounts:\n");
                $userIds = Tinebase_User::getInstance()->getActiveUserIds($options['lastMonths']);
                foreach ($userIds as $userId) {
                    $user = Tinebase_User::getInstance()->getUserByProperty('accountId', $userId, 'Tinebase_Model_FullUser');
                    echo "  * " . $user->accountLoginName . ' / ' . $user->accountDisplayName . "\n";
                    if (isset($options['showClients']) && $options['showClients']) {
                        $userClients = Tinebase_AccessLog::getInstance()->getUserClients($user, $options['lastMonths']);
                        echo "    Clients: \n";
                        foreach ($userClients as $client) {
                            echo "     - $client\n";
                        }
                        echo "\n";
                    }
                }
            }
            echo "\n";
        }

        return 0;
    }

    public function createFullTextIndex()
    {
        if (! $this->_checkAdminRight()) {
            return -1;
        }

        $setupBackend = Setup_Backend_Factory::factory();
        if (!$setupBackend->supports('mysql >= 5.6.4')) {
            return -2;
        }

        $failures = array();
        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>note</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>note</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('addressbook', $declaration);
        } catch (Exception $e) {
            $failures[] = 'addressbook';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('cal_events', $declaration);
        } catch (Exception $e) {
            $failures[] = 'cal_events';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('metacrm_lead', $declaration);
        } catch (Exception $e) {
            $failures[] = 'metacrm_lead';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('events_event', $declaration);
        } catch (Exception $e) {
            $failures[] = 'events_event';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('projects_project', $declaration);
        } catch (Exception $e) {
            $failures[] = 'projects_project';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('sales_contracts', $declaration);
        } catch (Exception $e) {
            $failures[] = 'sales_contracts';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('sales_products', $declaration);
        } catch (Exception $e) {
            $failures[] = 'sales_products';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('sales_customers', $declaration);
        } catch (Exception $e) {
            $failures[] = 'sales_customers';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('sales_suppliers', $declaration);
        } catch (Exception $e) {
            $failures[] = 'sales_suppliers';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('sales_purchase_invoices', $declaration);
        } catch (Exception $e) {
            $failures[] = 'sales_purchase_invoices';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('sales_sales_invoices', $declaration);
        } catch (Exception $e) {
            $failures[] = 'sales_sales_invoices';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('sales_offers', $declaration);
        } catch (Exception $e) {
            $failures[] = 'sales_offers';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('sales_order_conf', $declaration);
        } catch (Exception $e) {
            $failures[] = 'sales_order_conf';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('tasks', $declaration);
        } catch (Exception $e) {
            $failures[] = 'tasks';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('timetracker_timesheet', $declaration);
        } catch (Exception $e) {
            $failures[] = 'timetracker_timesheet';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('timetracker_timeaccount', $declaration);
        } catch (Exception $e) {
            $failures[] = 'timetracker_timeaccount';
        }

        try {
            if ($setupBackend->tableExists('path')) {
                $declaration = new Setup_Backend_Schema_Index_Xml('
                    <index>
                        <name>path</name>
                        <fulltext>true</fulltext>
                        <field>
                            <name>path</name>
                        </field>
                    </index>
                ');
                $setupBackend->addIndex('path', $declaration);
            }
        } catch (Exception $e) {
            $failures[] = 'path';
        }

        try {
            if ($setupBackend->tableExists('path')) {
                $declaration = new Setup_Backend_Schema_Index_Xml('
                    <index>
                        <name>shadow_path</name>
                        <fulltext>true</fulltext>
                        <field>
                            <name>shadow_path</name>
                        </field>
                    </index>
                ');
                $setupBackend->addIndex('path', $declaration);
            }
        } catch (Exception $e) {
            $failures[] = 'shadow_path';
        }

        try {
            if (!$setupBackend->tableExists('path')) {
                $declaration = new Setup_Backend_Schema_Table_Xml('<table>
                    <name>path</name>
                    <version>2</version>
                    <requirements>
                        <required>mysql >= 5.6.4</required>
                    </requirements>
                    <declaration>
                        <field>
                            <name>id</name>
                            <type>text</type>
                            <length>40</length>
                            <notnull>true</notnull>
                        </field>
                        <field>
                            <name>path</name>
                            <type>text</type>
                            <length>65535</length>
                            <notnull>true</notnull>
                        </field>
                        <field>
                            <name>shadow_path</name>
                            <type>text</type>
                            <length>65535</length>
                            <notnull>true</notnull>
                        </field>
                        <field>
                            <name>creation_time</name>
                            <type>datetime</type>
                        </field>
                        <index>
                            <name>id</name>
                            <primary>true</primary>
                            <field>
                                <name>id</name>
                            </field>
                        </index>
                        <index>
                        <name>path</name>
                            <fulltext>true</fulltext>
                            <field>
                                <name>path</name>
                            </field>
                        </index>
                        <index>
                            <name>shadow_path</name>
                            <fulltext>true</fulltext>
                            <field>
                                <name>shadow_path</name>
                            </field>
                        </index>
                    </declaration>
                </table>');

                $tmp = new Setup_Update_Abstract($setupBackend);
                $tmp->createTable('path', $declaration, 'Tinebase', 2);

                $setupUser = Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly();
                if ($setupUser) {
                    Tinebase_Core::set(Tinebase_Core::USER, $setupUser);
                    Tinebase_Controller::getInstance()->rebuildPaths();
                } else {
                    if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) {
                        Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                            . ' Could not find valid setupuser. Skipping rebuildPaths: you might need to run this manually.');
                    }
                }
            }
        } catch (Exception $e) {
            $failures[] = 'create path';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>text_data</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>text_data</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('external_fulltext', $declaration);
        } catch (Exception $e) {
            $failures[] = 'external_fulltext';
        }

        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('tree_fileobjects', $declaration);
        } catch (Exception $e) {
            $failures[] = 'tree_fileobjects';
        }

        try {
            try {
                $setupBackend->dropIndex('tags', 'description');
            } catch (Exception $e) {
                // Ignore, if there is no index, we can just go on and create one.
            }
            $declaration = new Setup_Backend_Schema_Index_Xml('
                <index>
                    <name>description</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>description</name>
                    </field>
                </index>
            ');
            $setupBackend->addIndex('tags', $declaration);
        } catch (Exception $e) {
            $failures[] = 'tags';
        }

        if (count($failures) > 0) {
            echo PHP_EOL . 'failures: ' . join(' ', $failures);
        }

        echo PHP_EOL . 'done' . PHP_EOL . PHP_EOL;

        return 0;
    }

    public function updateAllAccountsWithAccountEmail()
    {
        if (! $this->_checkAdminRight()) {
            return -1;
        }

        $userController = Tinebase_User::getInstance();
        $emailUser = Tinebase_EmailUser::getInstance();
        $allowedDomains = Tinebase_EmailUser::getAllowedDomains();
        /** @var Tinebase_Model_FullUser $user */
        foreach ($userController->getFullUsers() as $user) {
            $emailUser->inspectGetUserByProperty($user);
            if (! empty($user->accountEmailAddress)) {
                list($userPart, $domainPart) = explode('@', $user->accountEmailAddress);
                if (count($allowedDomains) > 0 && ! in_array($domainPart, $allowedDomains)) {
                    $newEmailAddress = $userPart . '@' . $allowedDomains[0];
                    if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                        . ' Setting new email address for user to comply with allowed domains: ' . $newEmailAddress);
                    $user->accountEmailAddress = $newEmailAddress;
                }
                $userController->updateUser($user);
            }
        }

        return 0;
    }

    public function waitForActionQueueToEmpty()
    {
        $actionQueue = Tinebase_ActionQueue::getInstance();
        if (!$actionQueue->hasAsyncBackend()) {
            return 0;
        }

        $startTime = time();
        while ($actionQueue->getQueueSize() > 0 && time() - $startTime < 300) {
            usleep(1000);
        }

        return $actionQueue->getQueueSize();
    }
}
