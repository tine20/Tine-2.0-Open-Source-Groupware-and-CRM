<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Log
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * 
 */
/**
 * class Tinebase_Log
 * 
 * @package Tinebase
 * @subpackage Log
 *
 * @method void trace(string $msg)
 * @method void debug(string $msg)
 * @method void info(string $msg)
 * @method void warn(string $msg)
 * @method void err(string $msg)
 */
class Tinebase_Log extends Zend_Log
{
    /**
     * 
     * @var Tinebase_Log_Formatter
     */
    protected $_formatter;

    /**
     * Keeps flipped priorities from _priorities array in Zend_Log
     * @var array
     */
    protected $_flippedPriorities;

    /**
     * Stores timezone information
     * @var string
     */
    protected $_tz = NULL;

    /**
     * Class constructor.  Create a new logger
     *
     * @param Zend_Log_Writer_Abstract|null  $writer  default writer
     */
    public function __construct(Zend_Log_Writer_Abstract $writer = null)
    {
        parent::__construct($writer);
        $this->_flippedPriorities = array_flip($this->_priorities);
    }

    /**
     * adds a priority in flippedPriorities array
     * @param string  $name
     * @param integer $priority
     * @return void
     */
    public function addPriority($name, $priority)
    {
        parent::addPriority($name, $priority);
        $this->_flippedPriorities[strtoupper($name)] = $priority;
    }

    /**
     * Checks the priority and calls respective log is its it can be called
     * @param string $method
     * @param string $params
     */
    public function __call($method, $params)
    {
        $priority = strtoupper($method);
        if(isset($this->_flippedPriorities[$priority])) {
            if(Tinebase_Core::isLogLevel($this->_flippedPriorities[$priority])) {
                parent::__call($method, $params);
            }
        }
    }

    /**
     * add strings to replace in log output (passwords for example)
     * 
     * @param string $search
     * @param string $replace
     */
    public function addReplacement($search, $replace = '********')
    {
        $this->getFormatter()->addReplacement($search, $replace);
    }
    
    /**
     * 
     * @return Tinebase_Log_Formatter
     */
    public function getFormatter()
    {
        if (!$this->_formatter instanceof Tinebase_Log_Formatter) {
            $this->_formatter = new Tinebase_Log_Formatter();
        }
        
        return $this->_formatter;
    }
    
    /**
     * add new log writer defined by a config object/array
     * 
     * @param Tinebase_Config_Struct|Zend_Config|array $loggerConfig
     * 
     * @throws Tinebase_Exception_NotFound
     */
    public function addWriterByConfig($loggerConfig)
    {
        $loggerConfig = ($loggerConfig instanceof Tinebase_Config_Struct || $loggerConfig instanceof Zend_Config)
            ? $loggerConfig : new Tinebase_Config_Struct($loggerConfig);

        if (isset($loggerConfig->active) && $loggerConfig->active == false) {
            // writer deactivated
            return;
        }

        if (empty($loggerConfig->filename)) {
            throw new Tinebase_Exception_NotFound('filename missing in logger config');
        }

        $filename = $loggerConfig->filename;
        $writer = new Zend_Log_Writer_Stream($filename);
        
        $writer->setFormatter($this->getFormatter());

        $priority = ($loggerConfig->priority) ? (int)$loggerConfig->priority : Zend_Log::EMERG;
        $filter = new Zend_Log_Filter_Priority($priority);
        $writer->addFilter($filter);

        // add more filters here
        if (isset($loggerConfig->filter->user)) {
            $writer->addFilter(new Tinebase_Log_Filter_User($loggerConfig->filter->user));
        }
        if (isset($loggerConfig->filter->message)) {
            $writer->addFilter(new Zend_Log_Filter_Message($loggerConfig->filter->message));
        }
        
        $this->addWriter($writer);
    }
    
    /**
     * get max log priority
     * 
     * @param Tinebase_Config_Struct|Zend_Config $loggerConfig
     * @return integer
     */
    public static function getMaxLogLevel($loggerConfig)
    {
        $logLevel = $loggerConfig && $loggerConfig->priority ? (int)$loggerConfig->priority : Zend_Log::EMERG;
        if ($loggerConfig && $loggerConfig->additionalWriters) {
            foreach ($loggerConfig->additionalWriters as $writerConfig) {
                $writerConfig = ($writerConfig instanceof Tinebase_Config_Struct || $writerConfig instanceof Zend_Config)
                    ? $writerConfig : new Tinebase_Config_Struct($writerConfig);
                if ($writerConfig->priority && $writerConfig->priority > $logLevel) {
                    $logLevel = (int) $writerConfig->priority;
                }
            }
        }
        return $logLevel;
    }

    /**
     * Log a message at a priority
     *
     * @param  string   $message   Message to log
     * @param  integer  $priority  Priority of message
     * @param  mixed    $extras    Extra information to log in event
     * @return void
     * @throws Zend_Log_Exception
     */
    public function log($message, $priority, $extras = null)
    {
        if ($this->_tz){
            $oldTZ = date_default_timezone_get();
            date_default_timezone_set($this->_tz);
            parent::log($message, $priority, $extras);
            date_default_timezone_set($oldTZ);
        }
        else
            parent::log($message, $priority, $extras);
    }

    /**
     * Sets the timezone for the log output
     *
     * @param  string   $_tz   valid PHP timezone or NULL for UTC
     * @return void
     */
    public function setTimezone($_tz)
    {
        $this->_tz = $_tz;
    }

    /**
     * Returns the timezone for the log output
     *
     * @return  string   $_tz   valid PHP timezone or NULL for UTC
     */
    public function getTimezone()
    {
        return($this->_tz);
    }
}
