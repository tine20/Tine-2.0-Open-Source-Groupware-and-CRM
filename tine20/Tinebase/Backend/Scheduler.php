<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Backend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2017-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Paul Mehrer <p.mehrer@metaways.de>
 *
 */

/**
 * Tinebase Scheduler backend
 *
 * @package     Tinebase
 * @subpackage  Backend
 */
class Tinebase_Backend_Scheduler extends Tinebase_Backend_Sql
{
    const TABLE_NAME = 'scheduler_task';

    public function __construct()
    {
        parent::__construct([
            'modelName'     => Tinebase_Model_SchedulerTask::class,
            'tableName'     => self::TABLE_NAME,
            'modlogActive'  => false
        ]);
        $this->_additionalColumns = ['server_time' => new Zend_Db_Expr('NOW()')];
    }

    /**
     * @param string $_name
     * @return bool
     */
    public function hasTask($_name)
    {
        $select = $this->_getSelect()->where($this->_db->quoteInto($this->_db->quoteIdentifier('name') . ' = ?',
            $_name));

        $stmt = $this->_db->query($select);
        $result = !empty($stmt->fetchAll());
        $stmt->closeCursor();

        return $result;
    }

    /**
     * @return Tinebase_Model_SchedulerTask|null
     */
    public function getDueTask()
    {
        // the "random" order is here so permanently failing tasks can not block the other tasks from being executed
        $orders = [
            'next_run ' . Zend_Db_Select::SQL_ASC,
            'next_run ' . Zend_Db_Select::SQL_DESC,
            'id ' . Zend_Db_Select::SQL_ASC,
            'id ' . Zend_Db_Select::SQL_DESC,
            'name ' . Zend_Db_Select::SQL_ASC,
            'name ' . Zend_Db_Select::SQL_DESC,
            'failure_count ' . Zend_Db_Select::SQL_ASC,
            'failure_count ' . Zend_Db_Select::SQL_DESC,
        ];
        $select = $this->_getSelect()->where($this->_db->quoteIdentifier('next_run') . ' <= NOW() AND ' .
            $this->_db->quoteIdentifier('lock_id') . ' IS NULL')->forUpdate(true)
            ->order($orders[rand(0, count($orders) - 1)])->limit(1);

        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetch();
        $stmt->closeCursor();

        if (!$queryResult) {
            return null;
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->_rawDataToRecord($queryResult);
    }

    /**
     * @param Tinebase_Model_SchedulerTask $task
     * @return bool
     */
    public function markTaskRunning(Tinebase_Model_SchedulerTask $task)
    {
        $lockId = __METHOD__ . '::' . Tinebase_Record_Abstract::generateUID();
        if (true !== Tinebase_Core::acquireMultiServerLock($lockId)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::ERR)) Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ .
                ' could not acquire multi server lock: ' . $lockId);
            return false;
        }
        $task->lock_id = $lockId;

        return 1 === $this->_db->update($this->_tablePrefix . $this->_tableName, array('lock_id' => $lockId),
            $this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' = ?', $task->getId()));
    }

    public function cleanZombieTasks()
    {
        $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($this->_db);
        $select = $this->_getSelect()->where($this->_db->quoteIdentifier('lock_id') . ' IS NOT NULL')->forUpdate(true);

        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetchAll();
        $stmt->closeCursor();

        foreach ($queryResult as $task) {
            if (true === Tinebase_Core::acquireMultiServerLock($task['lock_id'])) {
                if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' .
                    __LINE__ . ' cleaning up zombie scheduler task: ' . print_r($task, true));
                if (1 !== $this->_db->update($this->_tablePrefix . $this->_tableName, array('lock_id' => null),
                        $this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' = ?', $task['id'])) &&
                        Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' .
                    __LINE__ . ' update zombie scheduler task failed');
                Tinebase_Core::releaseMultiServerLock($task['lock_id']);
            }
        }

        Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
    }

    /**
     * @return Tinebase_Model_SchedulerTask|null
     */
    public function getLastRun()
    {
        $select = $this->_getSelect()->order('last_run ' . Zend_Db_Select::SQL_DESC)->limit(1);

        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetch();
        $stmt->closeCursor();

        if (!$queryResult) {
            return null;
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->_rawDataToRecord($queryResult);
    }
}