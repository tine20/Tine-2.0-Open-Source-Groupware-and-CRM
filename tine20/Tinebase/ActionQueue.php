<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  ActionQueue
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2009-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * Action Queue
 * 
 * Method queue for deferred/async execution of Tine 2.0 application actions as defined 
 * in the application controllers 
 *
 * @package     Tinebase
 * @subpackage  ActionQueue
 *
 * @method int getQueueSize()
 * @method int waitForJob()
 * @method array receive(integer $jobId)
 * @method void delete(integer $jobId)
 * @method void reschedule(string $jobId)
 *
 */
 class Tinebase_ActionQueue implements Tinebase_Controller_Interface
 {
     const BACKEND_DIRECT = 'Direct';
     const BACKEND_REDIS  = 'Redis';
     
     /**
      * holds queue instance
      * 
      * @var Tinebase_ActionQueue_Backend_Interface
      */
     protected $_queue = NULL;
     
    /**
     * holds the instance of the singleton
     *
     * @var Tinebase_ActionQueue
     */
    private static $_instance = NULL;

    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() 
    {
    }
    
    /**
     * the singleton pattern
     *
     * @param string|null $_forceBackend
     * @return Tinebase_ActionQueue
     */
    public static function getInstance($_forceBackend = null)
    {
        if (self::$_instance === NULL || null !== $_forceBackend) {
            self::$_instance = new Tinebase_ActionQueue($_forceBackend);
        }
        
        return self::$_instance;
    }
    
    /**
     * destroy instance of this class
     */
    public static function destroyInstance()
    {
        self::$_instance = NULL;
    }
    
    /**
     * constructor
     *
     * @param string|null $_forceBackend
     */
    private function __construct($_forceBackend = null)
    {
        $options = null;
        $backend = null === $_forceBackend ? self::BACKEND_DIRECT : $_forceBackend;
        $config = Tinebase_Core::getConfig()->{Tinebase_Config::ACTIONQUEUE};

        /** @noinspection PhpUndefinedFieldInspection */
        if (null === $_forceBackend && $config && isset($config->{Tinebase_Config::ACTIONQUEUE_BACKEND}) && $config->{Tinebase_Config::ACTIONQUEUE_ACTIVE}) {
            /** @noinspection PhpUndefinedFieldInspection */
            $options = $config->toArray();
            
            $backend = (isset($options[Tinebase_Config::ACTIONQUEUE_BACKEND]) || array_key_exists(Tinebase_Config::ACTIONQUEUE_BACKEND, $options)) ? ucfirst(strtolower($options[Tinebase_Config::ACTIONQUEUE_BACKEND])) : $backend;
            unset($options[Tinebase_Config::ACTIONQUEUE_BACKEND]);
            unset($options[Tinebase_Config::ACTIONQUEUE_ACTIVE]);
        }
        
        $className = 'Tinebase_ActionQueue_Backend_' . $backend;
        
        if (!class_exists($className)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::ERR)) Tinebase_Core::getLogger()->err(
                __METHOD__ . '::' . __LINE__ . " Queue class name {$className} not found. Falling back to direct execution.");
            
            $className = Tinebase_ActionQueue_Backend_Direct::class;
        }
    
        $this->_queue = new $className($options); 

        if (! $this->_queue instanceof Tinebase_ActionQueue_Backend_Interface) {
            throw new Tinebase_Exception_UnexpectedValue('backend does not implement Tinebase_ActionQueue_Backend_Interface');
        }
    }

     /**
      * execute action defined in queue message
      *
      * @param  array $message action
      * @return mixed
      * @throws Tinebase_Exception_AccessDenied
      * @throws Tinebase_Exception_NotFound
      */
    public function executeAction($message)
    {
        if (! is_array($message) || ! (isset($message['action']) || array_key_exists('action', $message)) || strpos($message['action'], '.') === FALSE) {
            throw new Tinebase_Exception_NotFound('Could not execute action, invalid message/action param');
        }

        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(
            __LINE__ . '::' . __METHOD__ . " executing action: '{$message['action']}'");
        
        list($appName, $actionName) = explode('.', $message['action']);
        $controller = Tinebase_Core::getApplicationInstance($appName);
    
        if (! method_exists($controller, $actionName)) {
            throw new Tinebase_Exception_NotFound('Could not execute action, requested action does not exist');
        }
        
        return call_user_func_array(array($controller, $actionName), $message['params']);
    }
    
    /**
     * check if the backend is async
     *  
     * @return boolean true if queue backend is async
     */
    public function hasAsyncBackend()
    {
        return ! $this->_queue instanceof Tinebase_ActionQueue_Backend_Direct;
    }
    
    /**
     * process all jobs in queue
     */
    public function processQueue()
    {
        // loop over all jobs
        while(false !== ($jobId = $this->_queue->waitForJob())) {
            $job = $this->_queue->receive($jobId);
            
            $this->executeAction($job);
            
            $this->_queue->delete($jobId);
        }
    }

     /** @noinspection PhpDocSignatureInspection */
     /**
     * queues an action
     *
     * @param string $_action
     * @param mixed  $_arg1
     * @param mixed  $_arg2
     * ...
     * 
     * @return string the job id
     */
    public function queueAction()
    {
        $params = func_get_args();
        $action = array_shift($params);
        $user = Tinebase_Core::getUser();
        if (! is_object($user)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::ERR)) Tinebase_Core::getLogger()->err(
                __METHOD__ . '::' . __LINE__ . " Not Queueing action: '{$action}' because no valid user object found");
            return null;
        }

        $message = array(
            'action'     => $action,
            'account_id' => $user->getId(),
            'params'     => $params
        );
        
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(
            __METHOD__ . '::' . __LINE__ . " Queueing action: '{$action}'");
        
        return $this->_queue->send($message);
    }
    
    /**
     * resume processing of events
     */
    public function resumeEvents()
    {
    }
    
    /**
     * suspend processing of event
     */
    public function suspendEvents()
    {
    }

    /**
     * call function of queue backend
     * 
     * @param  string $name
     * @param  array  $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->_queue, $name), $arguments);
    }

     /**
      * returns the class name of the used queue implementation
      *
      * @return string
      */
    public function getBackendType()
    {
        return get_class($this->_queue);
    }
}
