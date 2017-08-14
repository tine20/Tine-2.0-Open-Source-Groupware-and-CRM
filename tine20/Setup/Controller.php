<?php
/**
 * Tine 2.0
 *
 * @package     Setup
 * @subpackage  Controller
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2008-2016 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 * @todo        move $this->_db calls to backend class
 */

/**
 * php helpers
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'Tinebase' . DIRECTORY_SEPARATOR . 'Helper.php';

/**
 * class to handle setup of Tine 2.0
 *
 * @package     Setup
 * @subpackage  Controller
 */
class Setup_Controller
{
    /**
     * holds the instance of the singleton
     *
     * @var Setup_Controller
     */
    private static $_instance = NULL;
    
    /**
     * setup backend
     *
     * @var Setup_Backend_Interface
     */
    protected $_backend = NULL;
    
    /**
     * the directory where applications are located
     *
     * @var string
     */
    protected $_baseDir;
    
    /**
     * the email configs to get/set
     *
     * @var array
     */
    protected $_emailConfigKeys = array();
    
    /**
     * number of updated apps
     * 
     * @var integer
     */
    protected $_updatedApplications = 0;

    const MAX_DB_PREFIX_LENGTH = 10;

    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() {}
    
    /**
     * url to Tine 2.0 wiki
     *
     * @var string
     */
    protected $_helperLink = ' <a href="http://wiki.tine20.org/Admins/Install_Howto" target="_blank">Check the Tine 2.0 wiki for support.</a>';

    /**
     * the singleton pattern
     *
     * @return Setup_Controller
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Setup_Controller;
        }
        
        return self::$_instance;
    }

    /**
     * the constructor
     *
     */
    protected function __construct()
    {
        // setup actions could take quite a while we try to set max execution time to unlimited
        Setup_Core::setExecutionLifeTime(0);
        
        if (!defined('MAXLOOPCOUNT')) {
            define('MAXLOOPCOUNT', 50);
        }
        
        $this->_baseDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR;
        
        if (Setup_Core::get(Setup_Core::CHECKDB)) {
            $this->_db = Setup_Core::getDb();
            $this->_backend = Setup_Backend_Factory::factory();
        } else {
            $this->_db = NULL;
        }
        
        $this->_emailConfigKeys = array(
            'imap'  => Tinebase_Config::IMAP,
            'smtp'  => Tinebase_Config::SMTP,
            'sieve' => Tinebase_Config::SIEVE,
        );
    }

    /**
     * check system/php requirements (env + ext check)
     *
     * @return array
     *
     * @todo add message to results array
     */
    public function checkRequirements()
    {
        $envCheck = $this->environmentCheck();
        
        $databaseCheck = $this->checkDatabase();
        
        $extCheck = new Setup_ExtCheck(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'essentials.xml');
        $extResult = $extCheck->getData();

        $result = array(
            'success' => ($envCheck['success'] && $databaseCheck['success'] && $extResult['success']),
            'results' => array_merge($envCheck['result'], $databaseCheck['result'], $extResult['result']),
        );

        $result['totalcount'] = count($result['results']);
        
        return $result;
    }
    
    /**
     * check which database extensions are available
     *
     * @return array
     */
    public function checkDatabase()
    {
        $result = array(
            'result'  => array(),
            'success' => false
        );
        
        $loadedExtensions = get_loaded_extensions();
        
        if (! in_array('PDO', $loadedExtensions)) {
            $result['result'][] = array(
                'key'       => 'Database',
                'value'     => FALSE,
                'message'   => "PDO extension not found."  . $this->_helperLink
            );
            
            return $result;
        }
        
        // check mysql requirements
        $missingMysqlExtensions = array_diff(array('pdo_mysql'), $loadedExtensions);
        
        // check pgsql requirements
        $missingPgsqlExtensions = array_diff(array('pgsql', 'pdo_pgsql'), $loadedExtensions);
        
        // check oracle requirements
        $missingOracleExtensions = array_diff(array('oci8'), $loadedExtensions);

        if (! empty($missingMysqlExtensions) && ! empty($missingPgsqlExtensions) && ! empty($missingOracleExtensions)) {
            $result['result'][] = array(
                'key'       => 'Database',
                'value'     => FALSE,
                'message'   => 'Database extensions missing. For MySQL install: ' . implode(', ', $missingMysqlExtensions) . 
                               ' For Oracle install: ' . implode(', ', $missingOracleExtensions) . 
                               ' For PostgreSQL install: ' . implode(', ', $missingPgsqlExtensions) .
                               $this->_helperLink
            );
            
            return $result;
        }
        
        $result['result'][] = array(
            'key'       => 'Database',
            'value'     => TRUE,
            'message'   => 'Support for following databases enabled: ' . 
                           (empty($missingMysqlExtensions) ? 'MySQL' : '') . ' ' .
                           (empty($missingOracleExtensions) ? 'Oracle' : '') . ' ' .
                           (empty($missingPgsqlExtensions) ? 'PostgreSQL' : '') . ' '
        );
        $result['success'] = TRUE;
        
        return $result;
    }
    
    /**
     * Check if tableprefix is longer than 6 charcters
     *
     * @return boolean
     */
    public function checkDatabasePrefix()
    {
        $config = Setup_Core::get(Setup_Core::CONFIG);
        if (isset($config->database->tableprefix) && strlen($config->database->tableprefix) > self::MAX_DB_PREFIX_LENGTH) {
            if (Setup_Core::isLogLevel(Zend_Log::ERR)) Setup_Core::getLogger()->error(__METHOD__ . '::' . __LINE__
                . ' Tableprefix: "' . $config->database->tableprefix . '" is longer than ' . self::MAX_DB_PREFIX_LENGTH
                . '  characters! Please check your configuration.');
            return false;
        }
        return true;
    }
    
    /**
     * Check if logger is properly configured (or not configured at all)
     *
     * @return boolean
     */
    public function checkConfigLogger()
    {
        $config = Setup_Core::get(Setup_Core::CONFIG);
        if (!isset($config->logger) || !$config->logger->active) {
            return true;
        } else {
            return (
                isset($config->logger->filename)
                && (
                    file_exists($config->logger->filename) && is_writable($config->logger->filename)
                    || is_writable(dirname($config->logger->filename))
                )
            );
        }
    }
    
    /**
     * Check if caching is properly configured (or not configured at all)
     *
     * @return boolean
     */
    public function checkConfigCaching()
    {
        $result = FALSE;
        
        $config = Setup_Core::get(Setup_Core::CONFIG);
        
        if (! isset($config->caching) || !$config->caching->active) {
            $result = TRUE;
            
        } else if (! isset($config->caching->backend) || ucfirst($config->caching->backend) === 'File') {
            $result = $this->checkDir('path', 'caching', FALSE);
            
        } else if (ucfirst($config->caching->backend) === 'Redis') {
            $result = $this->_checkRedisConnect(isset($config->caching->redis) ? $config->caching->redis->toArray() : array());
            
        } else if (ucfirst($config->caching->backend) === 'Memcached') {
            $result = $this->_checkMemcacheConnect(isset($config->caching->memcached) ? $config->caching->memcached->toArray() : array());
            
        }
        
        return $result;
    }
    
    /**
     * checks redis extension and connection
     * 
     * @param array $config
     * @return boolean
     */
    protected function _checkRedisConnect($config)
    {
        if (! extension_loaded('redis')) {
            Setup_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' redis extension not loaded');
            return FALSE;
        }
        $redis = new Redis;
        $host = isset($config['host']) ? $config['host'] : 'localhost';
        $port = isset($config['port']) ? $config['port'] : 6379;
        
        $result = $redis->connect($host, $port);
        if ($result) {
            $redis->close();
        } else {
            Setup_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Could not connect to redis server at ' . $host . ':' . $port);
        }
        
        return $result;
    }
    
    /**
     * checks memcached extension and connection
     * 
     * @param array $config
     * @return boolean
     */
    protected function _checkMemcacheConnect($config)
    {
        if (! extension_loaded('memcache')) {
            Setup_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' memcache extension not loaded');
            return FALSE;
        }
        $memcache = new Memcache;
        $host = isset($config['host']) ? $config['host'] : 'localhost';
        $port = isset($config['port']) ? $config['port'] : 11211;
        $result = $memcache->connect($host, $port);
        
        return $result;
    }
    
    /**
     * Check if queue is properly configured (or not configured at all)
     *
     * @return boolean
     */
    public function checkConfigQueue()
    {
        $config = Setup_Core::get(Setup_Core::CONFIG);
        if (! isset($config->actionqueue) || ! $config->actionqueue->active) {
            $result = TRUE;
        } else {
            $result = $this->_checkRedisConnect($config->actionqueue->toArray());
        }
        
        return $result;
    }
    
    /**
     * check config session
     * 
     * @return boolean
     */
    public function checkConfigSession()
    {
        $result = FALSE;
        $config = Setup_Core::get(Setup_Core::CONFIG);
        if (! isset($config->session) || !$config->session->active) {
            return TRUE;
        } else if (ucfirst($config->session->backend) === 'File') {
            return $this->checkDir('path', 'session', FALSE);
        } else if (ucfirst($config->session->backend) === 'Redis') {
            $result = $this->_checkRedisConnect($config->session->toArray());
        }
        
        return $result;
    }
    
    /**
     * checks if path in config is writable
     *
     * @param string $_name
     * @param string $_group
     * @return boolean
     */
    public function checkDir($_name, $_group = NULL, $allowEmptyPath = TRUE)
    {
        $config = $this->getConfigData();
        if ($_group !== NULL && (isset($config[$_group]) || array_key_exists($_group, $config))) {
            $config = $config[$_group];
        }
        
        $path = (isset($config[$_name]) || array_key_exists($_name, $config)) ? $config[$_name] : false;
        if (empty($path)) {
            return $allowEmptyPath;
        } else {
            return @is_writable($path);
        }
    }
    
    /**
     * get list of applications as found in the filesystem
     *
     * @param boolean $getInstalled applications, too
     * @return array appName => setupXML
     */
    public function getInstallableApplications($getInstalled = false)
    {
        // create Tinebase tables first
        $applications = $getInstalled || ! $this->isInstalled('Tinebase')
            ? array('Tinebase' => $this->getSetupXml('Tinebase'))
            : array();
        
        try {
            $dirIterator = new DirectoryIterator($this->_baseDir);
        } catch (Exception $e) {
            Setup_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Could not open base dir: ' . $this->_baseDir);
            throw new Tinebase_Exception_AccessDenied('Could not open Tine 2.0 root directory.');
        }
        
        foreach ($dirIterator as $item) {
            $appName = $item->getFileName();
            if ($appName{0} != '.' && $appName != 'Tinebase' && $item->isDir()) {
                $fileName = $this->_baseDir . $appName . '/Setup/setup.xml' ;
                if (file_exists($fileName) && ($getInstalled || ! $this->isInstalled($appName))) {
                    $applications[$appName] = $this->getSetupXml($appName);
                }
            }
        }
        
        return $applications;
    }
    
    /**
     * updates installed applications. does nothing if no applications are installed
     *
     * @param Tinebase_Record_RecordSet $_applications
     * @return  array   messages
     */
    public function updateApplications(Tinebase_Record_RecordSet $_applications = null)
    {
        if (null === ($user = Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly())) {
            throw new Tinebase_Exception('could not create setup user');
        }
        Tinebase_Core::set(Tinebase_Core::USER, $user);

        if ($_applications === null) {
            $_applications = Tinebase_Application::getInstance()->getApplications();
        }

        // we need to clone here because we would taint the app cache otherwise
        $applications = clone($_applications);

        $this->_updatedApplications = 0;
        $smallestMajorVersion = NULL;
        $biggestMajorVersion = NULL;
        
        //find smallest major version
        foreach ($applications as $application) {
            if ($smallestMajorVersion === NULL || $application->getMajorVersion() < $smallestMajorVersion) {
                $smallestMajorVersion = $application->getMajorVersion();
            }
            if ($biggestMajorVersion === NULL || $application->getMajorVersion() > $biggestMajorVersion) {
                $biggestMajorVersion = $application->getMajorVersion();
            }
        }
        
        $messages = array();
        
        // update tinebase first (to biggest major version)
        $tinebase = Tinebase_Application::getInstance()->getApplicationByName('Tinebase');
        if ($idx = $applications->getIndexById($tinebase->getId())) {
            unset($applications[$idx]);
        }

        list($major, $minor) = explode('.', $this->getSetupXml('Tinebase')->version[0]);
        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Updating Tinebase to version ' . $major . '.' . $minor);

        for ($majorVersion = $tinebase->getMajorVersion(); $majorVersion <= $major; $majorVersion++) {
            $messages = array_merge($messages, $this->updateApplication($tinebase, $majorVersion));
        }

        // update the rest
        for ($majorVersion = $smallestMajorVersion; $majorVersion <= $biggestMajorVersion; $majorVersion++) {
            foreach ($applications as $application) {
                if ($application->getMajorVersion() <= $majorVersion) {
                    $messages = array_merge($messages, $this->updateApplication($application, $majorVersion));
                }
            }
        }
        
        return array(
            'messages' => $messages,
            'updated'  => $this->_updatedApplications,
        );
    }    
    
    /**
     * load the setup.xml file and returns a simplexml object
     *
     * @param string $_applicationName name of the application
     * @return SimpleXMLElement
     */
    public function getSetupXml($_applicationName)
    {
        $setupXML = $this->_baseDir . ucfirst($_applicationName) . '/Setup/setup.xml';

        if (!file_exists($setupXML)) {
            throw new Setup_Exception_NotFound(ucfirst($_applicationName)
                . '/Setup/setup.xml not found. If application got renamed or deleted, re-run setup.php.');
        }
        
        $xml = simplexml_load_file($setupXML);

        return $xml;
    }
    
    /**
     * check update
     *
     * @param   Tinebase_Model_Application $_application
     * @throws  Setup_Exception
     */
    public function checkUpdate(Tinebase_Model_Application $_application)
    {
        $xmlTables = $this->getSetupXml($_application->name);
        if(isset($xmlTables->tables)) {
            foreach ($xmlTables->tables[0] as $tableXML) {
                $table = Setup_Backend_Schema_Table_Factory::factory('Xml', $tableXML);
                if (true == $this->_backend->tableExists($table->name)) {
                    try {
                        $this->_backend->checkTable($table);
                    } catch (Setup_Exception $e) {
                        Setup_Core::getLogger()->error(__METHOD__ . '::' . __LINE__ . " Checking table failed with message '{$e->getMessage()}'");
                    }
                } else {
                    throw new Setup_Exception('Table ' . $table->name . ' for application' . $_application->name . " does not exist. \n<strong>Update broken</strong>");
                }
            }
        }
    }
    
    /**
     * update installed application
     *
     * @param   Tinebase_Model_Application    $_application
     * @param   string    $_majorVersion
     * @return  array   messages
     * @throws  Setup_Exception if current app version is too high
     */
    public function updateApplication(Tinebase_Model_Application $_application, $_majorVersion)
    {
        $setupXml = $this->getSetupXml($_application->name);
        $messages = array();
        
        switch (version_compare($_application->version, $setupXml->version)) {
            case -1:
                $message = "Executing updates for " . $_application->name . " (starting at " . $_application->version . ")";
                
                $messages[] = $message;
                Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' ' . $message);

                $version = $_application->getMajorAndMinorVersion();
                $minor = $version['minor'];
                
                $className = ucfirst($_application->name) . '_Setup_Update_Release' . $_majorVersion;
                if(! class_exists($className)) {
                    $nextMajorRelease = ($_majorVersion + 1) . ".0";
                    Setup_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                        . " Update class {$className} does not exists, skipping release {$_majorVersion} for app "
                        . "{$_application->name} and increasing version to $nextMajorRelease"
                    );
                    $_application->version = $nextMajorRelease;
                    Tinebase_Application::getInstance()->updateApplication($_application);

                } else {
                    $update = new $className($this->_backend);
                
                    $classMethods = get_class_methods($update);
              
                    // we must do at least one update
                    do {
                        $functionName = 'update_' . $minor;
                        
                        try {
                            $db = Setup_Core::getDb();
                            $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($db);
                        
                            Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                                . ' Updating ' . $_application->name . ' - ' . $functionName
                            );
                            
                            $update->$functionName();
                        
                            Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
                
                        } catch (Exception $e) {
                            Tinebase_TransactionManager::getInstance()->rollBack();
                            Setup_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . $e->getMessage());
                            Setup_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . $e->getTraceAsString());
                            throw $e;
                        }
                            
                        $minor++;
                    } while(array_search('update_' . $minor, $classMethods) !== false);
                }
                
                $messages[] = "<strong> Updated " . $_application->name . " successfully to " .  $_majorVersion . '.' . $minor . "</strong>";
                
                // update app version
                $updatedApp = Tinebase_Application::getInstance()->getApplicationById($_application->getId());
                $_application->version = $updatedApp->version;
                Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Updated ' . $_application->name . " successfully to " .  $_application->version);
                $this->_updatedApplications++;
                
                break;
                
            case 0:
                Setup_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' No update needed for ' . $_application->name);
                break;
                
            case 1:
                throw new Setup_Exception('Current application version is higher than version from setup.xml: '
                    . $_application->version . ' > ' . $setupXml->version
                );
                break;
        }
        
        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Clearing cache after update ...');
        $this->_enableCaching();
        Tinebase_Core::getCache()->clean(Zend_Cache::CLEANING_MODE_ALL);
        
        return $messages;
    }

    /**
     * checks if update is required
     *
     * @return boolean
     */
    public function updateNeeded($_application)
    {
        try {
            $setupXml = $this->getSetupXml($_application->name);
        } catch (Setup_Exception_NotFound $senf) {
            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' ' . $senf->getMessage() . ' Disabling application "' . $_application->name . '".');
            Tinebase_Application::getInstance()->setApplicationState(array($_application->getId()), Tinebase_Application::DISABLED);
            return false;
        }
        
        $updateNeeded = version_compare($_application->version, $setupXml->version);
        
        if($updateNeeded === -1) {
            return true;
        }
        
        return false;
    }
    
    /**
     * search for installed and installable applications
     *
     * @return array
     */
    public function searchApplications()
    {
        // get installable apps
        $installable = $this->getInstallableApplications(/* $getInstalled */ true);
        
        // get installed apps
        if (Setup_Core::get(Setup_Core::CHECKDB)) {
            try {
                $installed = Tinebase_Application::getInstance()->getApplications(NULL, 'id')->toArray();
                
                // merge to create result array
                $applications = array();
                foreach ($installed as $application) {
                    
                    if (! (isset($installable[$application['name']]) || array_key_exists($application['name'], $installable))) {
                        Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' App ' . $application['name'] . ' does not exist any more.');
                        continue;
                    }
                    
                    $depends = (array) $installable[$application['name']]->depends;
                    if (isset($depends['application'])) {
                        $depends = implode(', ', (array) $depends['application']);
                    }
                    
                    $application['current_version'] = (string) $installable[$application['name']]->version;
                    $application['install_status'] = (version_compare($application['version'], $application['current_version']) === -1) ? 'updateable' : 'uptodate';
                    $application['depends'] = $depends;
                    $applications[] = $application;
                    unset($installable[$application['name']]);
                }
            } catch (Zend_Db_Statement_Exception $zse) {
                // no tables exist
            }
        }
        
        foreach ($installable as $name => $setupXML) {
            $depends = (array) $setupXML->depends;
            if (isset($depends['application'])) {
                $depends = implode(', ', (array) $depends['application']);
            }
            
            $applications[] = array(
                'name'              => $name,
                'current_version'   => (string) $setupXML->version,
                'install_status'    => 'uninstalled',
                'depends'           => $depends,
            );
        }
        
        return array(
            'results'       => $applications,
            'totalcount'    => count($applications)
        );
    }

    /**
     * checks if setup is required
     *
     * @return boolean
     */
    public function setupRequired()
    {
        $result = FALSE;
        
        // check if applications table exists / only if db available
        if (Setup_Core::isRegistered(Setup_Core::DB)) {
            try {
                $applicationTable = Setup_Core::getDb()->describeTable(SQL_TABLE_PREFIX . 'applications');
                if (empty($applicationTable)) {
                    Setup_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Applications table empty');
                    $result = TRUE;
                }
            } catch (Zend_Db_Statement_Exception $zdse) {
                Setup_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ' . $zdse->getMessage());
                $result = TRUE;
            } catch (Zend_Db_Adapter_Exception $zdae) {
                Setup_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ' . $zdae->getMessage());
                $result = TRUE;
            }
        }
        
        return $result;
    }
    
    /**
     * do php.ini environment check
     *
     * @return array
     */
    public function environmentCheck()
    {
        $result = array();
        $message = array();
        $success = TRUE;
        
        
        
        // check php environment
        $requiredIniSettings = array(
            'magic_quotes_sybase'  => 0,
            'magic_quotes_gpc'     => 0,
            'magic_quotes_runtime' => 0,
            'mbstring.func_overload' => 0,
            'eaccelerator.enable' => 0,
            'memory_limit' => '48M'
        );
        
        foreach ($requiredIniSettings as $variable => $newValue) {
            $oldValue = ini_get($variable);
            
            if ($variable == 'memory_limit') {
                $required = Tinebase_Helper::convertToBytes($newValue);
                $set = Tinebase_Helper::convertToBytes($oldValue);
                
                if ( $set < $required) {
                    $result[] = array(
                        'key'       => $variable,
                        'value'     => FALSE,
                        'message'   => "You need to set $variable equal or greater than $required (now: $set)." . $this->_helperLink
                    );
                    $success = FALSE;
                }

            } elseif ($oldValue != $newValue) {
                if (ini_set($variable, $newValue) === false) {
                    $result[] = array(
                        'key'       => $variable,
                        'value'     => FALSE,
                        'message'   => "You need to set $variable from $oldValue to $newValue."  . $this->_helperLink
                    );
                    $success = FALSE;
                }
            } else {
                $result[] = array(
                    'key'       => $variable,
                    'value'     => TRUE,
                    'message'   => ''
                );
            }
        }
        
        return array(
            'result'        => $result,
            'success'       => $success,
        );
    }
    
    /**
     * get config file default values
     *
     * @return array
     */
    public function getConfigDefaults()
    {
        $defaultPath = Setup_Core::guessTempDir();
        
        $result = array(
            'database' => array(
                'host'  => 'localhost',
                'dbname' => 'tine20',
                'username' => 'tine20',
                'password' => '',
                'adapter' => 'pdo_mysql',
                'tableprefix' => 'tine20_',
                'port'          => 3306
            ),
            'logger' => array(
                'filename' => $defaultPath . DIRECTORY_SEPARATOR . 'tine20.log',
                'priority' => '5'
            ),
            'caching' => array(
               'active' => 1,
               'lifetime' => 3600,
               'backend' => 'File',
               'path' => $defaultPath,
            ),
            'tmpdir' => $defaultPath,
            'session' => array(
                'path'      => Tinebase_Session::getSessionDir(),
                'liftime'   => 86400,
            ),
        );
        
        return $result;
    }

    /**
     * get config file values
     *
     * @return array
     */
    public function getConfigData()
    {
        $configArray = Setup_Core::get(Setup_Core::CONFIG)->toArray();
        
        #####################################
        # LEGACY/COMPATIBILITY:
        # (1) had to rename session.save_path key to sessiondir because otherwise the
        # generic save config method would interpret the "_" as array key/value seperator
        # (2) moved session config to subgroup 'session'
        if (empty($configArray['session']) || empty($configArray['session']['path'])) {
            foreach (array('session.save_path', 'sessiondir') as $deprecatedSessionDir) {
                $sessionDir = (isset($configArray[$deprecatedSessionDir]) || array_key_exists($deprecatedSessionDir, $configArray)) ? $configArray[$deprecatedSessionDir] : '';
                if (! empty($sessionDir)) {
                    if (empty($configArray['session'])) {
                        $configArray['session'] = array();
                    }
                    $configArray['session']['path'] = $sessionDir;
                    Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . " config.inc.php key '{$deprecatedSessionDir}' should be renamed to 'path' and moved to 'session' group.");
                }
            }
        }
        #####################################
        
        return $configArray;
    }
    
    /**
     * save data to config file
     *
     * @param array   $_data
     * @param boolean $_merge
     */
    public function saveConfigData($_data, $_merge = TRUE)
    {
        if (!empty($_data['setupuser']['password']) && !Setup_Auth::isMd5($_data['setupuser']['password'])) {
            $password = $_data['setupuser']['password'];
            $_data['setupuser']['password'] = md5($_data['setupuser']['password']);
        }
        if (Setup_Core::configFileExists() && !Setup_Core::configFileWritable()) {
            throw new Setup_Exception('Config File is not writeable.');
        }
        
        if (Setup_Core::configFileExists()) {
            $doLogin = FALSE;
            $filename = Setup_Core::getConfigFilePath();
        } else {
            $doLogin = TRUE;
            $filename = dirname(__FILE__) . '/../config.inc.php';
        }
        
        $config = $this->writeConfigToFile($_data, $_merge, $filename);
        
        Setup_Core::set(Setup_Core::CONFIG, $config);
        
        Setup_Core::setupLogger();
        
        if ($doLogin && isset($password)) {
            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Create session for setup user ' . $_data['setupuser']['username']);
            $this->login($_data['setupuser']['username'], $password);
        }
    }
    
    /**
     * write config to a file
     *
     * @param array $_data
     * @param boolean $_merge
     * @param string $_filename
     * @return Zend_Config
     */
    public function writeConfigToFile($_data, $_merge, $_filename)
    {
        // merge config data and active config
        if ($_merge) {
            $activeConfig = Setup_Core::get(Setup_Core::CONFIG);
            $config = new Zend_Config($activeConfig->toArray(), true);
            $config->merge(new Zend_Config($_data));
        } else {
            $config = new Zend_Config($_data);
        }
        
        // write to file
        Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Updating config.inc.php');
        $writer = new Zend_Config_Writer_Array(array(
            'config'   => $config,
            'filename' => $_filename,
        ));
        $writer->write();
        
        return $config;
    }
    
    /**
     * load authentication data
     *
     * @return array
     */
    public function loadAuthenticationData()
    {
        return array(
            'authentication'    => $this->_getAuthProviderData(),
            'accounts'          => $this->_getAccountsStorageData(),
            'redirectSettings'  => $this->_getRedirectSettings(),
            'password'          => $this->_getPasswordSettings(),
            'saveusername'      => $this->_getReuseUsernameSettings()
        );
    }
    
    /**
     * Update authentication data
     *
     * Needs Tinebase tables to store the data, therefore
     * installs Tinebase if it is not already installed
     *
     * @param array $_authenticationData
     *
     * @return bool
     */
    public function saveAuthentication($_authenticationData)
    {
        if ($this->isInstalled('Tinebase')) {
            // NOTE: Tinebase_Setup_Initialiser calls this function again so
            //       we come to this point on initial installation _and_ update
            $this->_updateAuthentication($_authenticationData);
        } else {
            $installationOptions = array('authenticationData' => $_authenticationData);
            $this->installApplications(array('Tinebase'), $installationOptions);
        }
    }

    /**
     * Save {@param $_authenticationData} to config file
     *
     * @param array $_authenticationData [hash containing settings for authentication and accountsStorage]
     * @return void
     */
    protected function _updateAuthentication($_authenticationData)
    {
        // this is a dangerous TRACE as there might be passwords in here!
        //if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($_authenticationData, TRUE));

        $this->_enableCaching();
        
        if (isset($_authenticationData['authentication'])) {
            $this->_updateAuthenticationProvider($_authenticationData['authentication']);
        }
        
        if (isset($_authenticationData['accounts'])) {
            $this->_updateAccountsStorage($_authenticationData['accounts']);
        }
        
        if (isset($_authenticationData['redirectSettings'])) {
            $this->_updateRedirectSettings($_authenticationData['redirectSettings']);
        }
        
        if (isset($_authenticationData['password'])) {
            $this->_updatePasswordSettings($_authenticationData['password']);
        }
        
        if (isset($_authenticationData['saveusername'])) {
            $this->_updateReuseUsername($_authenticationData['saveusername']);
        }
        
        if (isset($_authenticationData['acceptedTermsVersion'])) {
            $this->saveAcceptedTerms($_authenticationData['acceptedTermsVersion']);
        }
    }
    
    /**
     * enable caching to make sure cache gets cleaned if config options change
     */
    protected function _enableCaching()
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Activate caching backend if available ...');
        
        Tinebase_Core::setupCache();
    }
    
    /**
     * Update authentication provider
     *
     * @param array $_data
     * @return void
     */
    protected function _updateAuthenticationProvider($_data)
    {
        Tinebase_Auth::setBackendType($_data['backend']);
        $config = (isset($_data[$_data['backend']])) ? $_data[$_data['backend']] : $_data;
        
        $excludeKeys = array('adminLoginName', 'adminPassword', 'adminPasswordConfirmation');
        foreach ($excludeKeys as $key) {
            if ((isset($config[$key]) || array_key_exists($key, $config))) {
                unset($config[$key]);
            }
        }
        
        Tinebase_Auth::setBackendConfiguration($config, null, true);
        Tinebase_Auth::saveBackendConfiguration();
    }
    
    /**
     * Update accountsStorage
     *
     * @param array $_data
     * @return void
     */
    protected function _updateAccountsStorage($_data)
    {
        $originalBackend = Tinebase_User::getConfiguredBackend();
        $newBackend = $_data['backend'];
        
        Tinebase_User::setBackendType($_data['backend']);
        $config = (isset($_data[$_data['backend']])) ? $_data[$_data['backend']] : $_data;
        Tinebase_User::setBackendConfiguration($config, null, true);
        Tinebase_User::saveBackendConfiguration();
        
        if ($originalBackend != $newBackend && $this->isInstalled('Addressbook') && $originalBackend == Tinebase_User::SQL) {
            Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Switching from $originalBackend to $newBackend account storage");
            try {
                $db = Setup_Core::getDb();
                $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($db);
                $this->_migrateFromSqlAccountsStorage();
                Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
        
            } catch (Exception $e) {
                Tinebase_TransactionManager::getInstance()->rollBack();
                Setup_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . $e->getMessage());
                Setup_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . $e->getTraceAsString());
                
                Tinebase_User::setBackendType($originalBackend);
                Tinebase_User::saveBackendConfiguration();
                
                throw $e;
            }
        }
    }
    
    /**
     * migrate from SQL account storage to another one (for example LDAP)
     * - deletes all users, groups and roles because they will be
     *   imported from new accounts storage backend
     */
    protected function _migrateFromSqlAccountsStorage()
    {
        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Deleting all user accounts, groups, roles and rights');
        Tinebase_User::factory(Tinebase_User::SQL)->deleteAllUsers();
        
        $contactSQLBackend = new Addressbook_Backend_Sql();
        $allUserContactIds = $contactSQLBackend->search(new Addressbook_Model_ContactFilter(array('type' => 'user')), null, true);
        if (count($allUserContactIds) > 0) {
            $contactSQLBackend->delete($allUserContactIds);
        }
        
        
        Tinebase_Group::factory(Tinebase_Group::SQL)->deleteAllGroups();
        $listsSQLBackend = new Addressbook_Backend_List();
        $allGroupListIds = $listsSQLBackend->search(new Addressbook_Model_ListFilter(array('type' => 'group')), null, true);
        if (count($allGroupListIds) > 0) {
            $listsSQLBackend->delete($allGroupListIds);
        }

        $roles = Tinebase_Acl_Roles::getInstance();
        $roles->deleteAllRoles();
        
        // import users (from new backend) / create initial users (SQL)
        Tinebase_User::syncUsers(array('syncContactData' => TRUE));
        
        $roles->createInitialRoles();
        $applications = Tinebase_Application::getInstance()->getApplications(NULL, 'id');
        foreach ($applications as $application) {
             Setup_Initialize::initializeApplicationRights($application);
        }
    }
    
    /**
     * Update redirect settings
     *
     * @param array $_data
     * @return void
     */
    protected function _updateRedirectSettings($_data)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($_data, 1));
        $keys = array(Tinebase_Config::REDIRECTURL, Tinebase_Config::REDIRECTALWAYS, Tinebase_Config::REDIRECTTOREFERRER);
        foreach ($keys as $key) {
            if ((isset($_data[$key]) || array_key_exists($key, $_data))) {
                if (strlen($_data[$key]) === 0) {
                    Tinebase_Config::getInstance()->delete($key);
                } else {
                    Tinebase_Config::getInstance()->set($key, $_data[$key]);
                }
            }
        }
    }

        /**
     * update pw settings
     * 
     * @param array $data
     */
    protected function _updatePasswordSettings($data)
    {
        foreach ($data as $config => $value) {
            Tinebase_Config::getInstance()->set($config, $value);
        }
    }
    
    /**
     * update pw settings
     * 
     * @param array $data
     */
    protected function _updateReuseUsername($data)
    {
        foreach ($data as $config => $value) {
            Tinebase_Config::getInstance()->set($config, $value);
        }
    }
    
    /**
     *
     * get auth provider data
     *
     * @return array
     *
     * @todo get this from config table instead of file!
     */
    protected function _getAuthProviderData()
    {
        $result = Tinebase_Auth::getBackendConfigurationWithDefaults(Setup_Core::get(Setup_Core::CHECKDB));
        $result['backend'] = (Setup_Core::get(Setup_Core::CHECKDB)) ? Tinebase_Auth::getConfiguredBackend() : Tinebase_Auth::SQL;

        return $result;
    }
    
    /**
     * get Accounts storage data
     *
     * @return array
     */
    protected function _getAccountsStorageData()
    {
        $result = Tinebase_User::getBackendConfigurationWithDefaults(Setup_Core::get(Setup_Core::CHECKDB));
        $result['backend'] = (Setup_Core::get(Setup_Core::CHECKDB)) ? Tinebase_User::getConfiguredBackend() : Tinebase_User::SQL;

        return $result;
    }
    
    /**
     * Get redirect Settings from config table.
     * If Tinebase is not installed, default values will be returned.
     *
     * @return array
     */
    protected function _getRedirectSettings()
    {
        $return = array(
              Tinebase_Config::REDIRECTURL => '',
              Tinebase_Config::REDIRECTTOREFERRER => '0'
        );
        if (Setup_Core::get(Setup_Core::CHECKDB) && $this->isInstalled('Tinebase')) {
            $return[Tinebase_Config::REDIRECTURL] = Tinebase_Config::getInstance()->get(Tinebase_Config::REDIRECTURL, '');
            $return[Tinebase_Config::REDIRECTTOREFERRER] = Tinebase_Config::getInstance()->get(Tinebase_Config::REDIRECTTOREFERRER, '');
        }
        return $return;
    }

    /**
     * get password settings
     * 
     * @return array
     * 
     * @todo should use generic mechanism to fetch setup related configs
     */
    protected function _getPasswordSettings()
    {
        $configs = array(
            Tinebase_Config::PASSWORD_CHANGE                     => 1,
            Tinebase_Config::PASSWORD_POLICY_ACTIVE              => 0,
            Tinebase_Config::PASSWORD_POLICY_ONLYASCII           => 0,
            Tinebase_Config::PASSWORD_POLICY_MIN_LENGTH          => 0,
            Tinebase_Config::PASSWORD_POLICY_MIN_WORD_CHARS      => 0,
            Tinebase_Config::PASSWORD_POLICY_MIN_UPPERCASE_CHARS => 0,
            Tinebase_Config::PASSWORD_POLICY_MIN_SPECIAL_CHARS   => 0,
            Tinebase_Config::PASSWORD_POLICY_MIN_NUMBERS         => 0,
            Tinebase_Config::PASSWORD_POLICY_CHANGE_AFTER        => 0,
        );

        $result = array();
        $tinebaseInstalled = $this->isInstalled('Tinebase');
        foreach ($configs as $config => $default) {
            $result[$config] = ($tinebaseInstalled) ? Tinebase_Config::getInstance()->get($config, $default) : $default;
        }
        
        return $result;
    }
    
    /**
     * get Reuse Username to login textbox
     * 
     * @return array
     * 
     * @todo should use generic mechanism to fetch setup related configs
     */
    protected function _getReuseUsernameSettings()
    {
        $configs = array(
            Tinebase_Config::REUSEUSERNAME_SAVEUSERNAME         => 0,
        );

        $result = array();
        $tinebaseInstalled = $this->isInstalled('Tinebase');
        foreach ($configs as $config => $default) {
            $result[$config] = ($tinebaseInstalled) ? Tinebase_Config::getInstance()->get($config, $default) : $default;
        }
        
        return $result;
    }
    
    /**
     * get email config
     *
     * @return array
     */
    public function getEmailConfig()
    {
        $result = array();
        
        foreach ($this->_emailConfigKeys as $configName => $configKey) {
            $config = Tinebase_Config::getInstance()->get($configKey, new Tinebase_Config_Struct(array()))->toArray();
            if (! empty($config) && ! isset($config['active'])) {
                $config['active'] = TRUE;
            }
            $result[$configName] = $config;
        }
        
        return $result;
    }
    
    /**
     * save email config
     *
     * @param array $_data
     * @return void
     */
    public function saveEmailConfig($_data)
    {
        // this is a dangerous TRACE as there might be passwords in here!
        //if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($_data, TRUE));
        
        $this->_enableCaching();
        
        foreach ($this->_emailConfigKeys as $configName => $configKey) {
            if ((isset($_data[$configName]) || array_key_exists($configName, $_data))) {
                // fetch current config first and preserve all values that aren't in $_data array
                $currentConfig = Tinebase_Config::getInstance()->get($configKey, new Tinebase_Config_Struct(array()))->toArray();
                $newConfig = array_merge($_data[$configName], array_diff_key($currentConfig, $_data[$configName]));
                Tinebase_Config::getInstance()->set($configKey, $newConfig);
            }
        }
    }
    
    /**
     * returns all email config keys
     *
     * @return array
     */
    public function getEmailConfigKeys()
    {
        return $this->_emailConfigKeys;
    }
    
    /**
     * get accepted terms config
     *
     * @return integer
     */
    public function getAcceptedTerms()
    {
        return Tinebase_Config::getInstance()->get(Tinebase_Config::ACCEPTEDTERMSVERSION, 0);
    }
    
    /**
     * save acceptedTermsVersion
     *
     * @param $_data
     * @return void
     */
    public function saveAcceptedTerms($_data)
    {
        Tinebase_Config::getInstance()->set(Tinebase_Config::ACCEPTEDTERMSVERSION, $_data);
    }
    
    /**
     * save config option in db
     *
     * @param string $key
     * @param string|array $value
     * @param string $applicationName
     * @return void
     */
    public function setConfigOption($key, $value, $applicationName = 'Tinebase')
    {
        $config = Tinebase_Config_Abstract::factory($applicationName);
        
        if ($config) {
            $config->set($key, $value);
        }
    }
    
    /**
     * create new setup user session
     *
     * @param   string $_username
     * @param   string $_password
     * @return  bool
     */
    public function login($_username, $_password)
    {
        $setupAuth = new Setup_Auth($_username, $_password);
        $authResult = Zend_Auth::getInstance()->authenticate($setupAuth);
        
        if ($authResult->isValid()) {
            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Valid credentials, setting username in session and registry.');
            Tinebase_Session::regenerateId();
            
            Setup_Core::set(Setup_Core::USER, $_username);
            Setup_Session::getSessionNamespace()->setupuser = $_username;
            return true;
            
        } else {
            Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Invalid credentials! ' . print_r($authResult->getMessages(), TRUE));
            Tinebase_Session::expireSessionCookie();
            sleep(2);
            return false;
        }
    }
    
    /**
     * destroy session
     *
     * @return void
     */
    public function logout()
    {
        $_SESSION = array();
        
        Tinebase_Session::destroyAndRemoveCookie();
    }
    
    /**
     * install list of applications
     *
     * @param array $_applications list of application names
     * @param array | optional $_options
     * @return void
     */
    public function installApplications($_applications, $_options = null)
    {
        $this->_clearCache();
        
        // check requirements for initial install / add required apps to list
        if (! $this->isInstalled('Tinebase')) {
    
            $minimumRequirements = array('Addressbook', 'Tinebase', 'Admin');
            
            foreach ($minimumRequirements as $requiredApp) {
                if (!in_array($requiredApp, $_applications) && !$this->isInstalled($requiredApp)) {
                    // Addressbook has to be installed with Tinebase for initial data (user contact)
                    Setup_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                        . ' ' . $requiredApp . ' has to be installed first (adding it to list).'
                    );
                    $_applications[] = $requiredApp;
                }
            }
        } else {
            $setupUser = Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly();
            if ($setupUser && ! Tinebase_Core::getUser() instanceof Tinebase_Model_User) {
                Tinebase_Core::set(Tinebase_Core::USER, $setupUser);
            }
        }
        
        // get xml and sort apps first
        $applications = array();
        foreach ($_applications as $applicationName) {
            if ($this->isInstalled($applicationName)) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . " skipping installation of application {$applicationName} because it is already installed");
            } else {
                $applications[$applicationName] = $this->getSetupXml($applicationName);
            }
        }
        $applications = $this->_sortInstallableApplications($applications);
        
        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Installing applications: ' . print_r(array_keys($applications), true));
        
        foreach ($applications as $name => $xml) {
            if (! $xml) {
                Setup_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' Could not install application ' . $name);
            } else {
                $this->_installApplication($xml, $_options);
            }
        }
    }

    /**
     * install tine from dump file
     *
     * @param $options
     * @throws Setup_Exception
     * @return boolean
     */
    public function installFromDump($options)
    {
        $this->_clearCache();

        if ($this->isInstalled('Tinebase')) {
            Setup_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Tinebase is already installed.');
            return false;
        }

        $mysqlBackupFile = null;
        if (isset($options['backupDir'])) {
            $mysqlBackupFile = $options['backupDir'] . '/tine20_mysql.sql.bz2';
        } else if (isset($options['backupUrl'])) {
            // download files first and put them in temp dir
            $tempDir = Tinebase_Core::getTempDir();
            foreach (array(
                         array('file' => 'tine20_config.tar.bz2', 'param' => 'config'),
                         array('file' => 'tine20_mysql.sql.bz2', 'param' => 'db'),
                         array('file' => 'tine20_files.tar.bz2', 'param' => 'files')
                    ) as $download) {
                if (isset($options[$download['param']])) {
                    $fileUrl = $options['backupUrl'] . '/' . $download['file'];
                        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Downloading ' . $fileUrl);
                    $targetFile = $tempDir . DIRECTORY_SEPARATOR . $download['file'];
                    if ($download['param'] === 'db') {
                        $mysqlBackupFile = $targetFile;
                    }
                    file_put_contents(
                        $targetFile,
                        fopen($fileUrl, 'r')
                    );
                }
            }
            $options['backupDir'] = $tempDir;
        } else {
            throw new Setup_Exception("backupDir or backupUrl param required");
        }

        if (! $mysqlBackupFile || ! file_exists($mysqlBackupFile)) {
            throw new Setup_Exception("$mysqlBackupFile not found");
        }

        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Installing from dump ' . $mysqlBackupFile);

        $this->_replaceTinebaseidInDump($mysqlBackupFile);
        $this->restore($options);

        $setupUser = Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly();
        if ($setupUser && ! Tinebase_Core::getUser() instanceof Tinebase_Model_User) {
            Tinebase_Core::set(Tinebase_Core::USER, $setupUser);
        }

        // set the replication master id
        $tinebase = Tinebase_Application::getInstance()->getApplicationByName('Tinebase');
        $state = $tinebase->state;
        if (!is_array($state)) {
            $state = array();
        }
        $state[Tinebase_Model_Application::STATE_REPLICATION_MASTER_ID] = Tinebase_Timemachine_ModificationLog::getInstance()->getMaxInstanceSeq();
        $tinebase->state = $state;
        Tinebase_Application::getInstance()->updateApplication($tinebase);

        $this->updateApplications();

        return true;
    }

    /**
     * replace old Tinebase ID in dump to make sure we have a unique installation ID
     *
     * TODO: think about moving the Tinebase ID (and more info) to a metadata.json file in the backup zip
     *
     * @param $mysqlBackupFile
     * @throws Setup_Exception
     */
    protected function _replaceTinebaseidInDump($mysqlBackupFile)
    {
        // fetch old Tinebase ID
        $cmd = "bzcat $mysqlBackupFile | grep \",'Tinebase','enabled'\"";
        $result = exec($cmd);
        if (! preg_match("/'([0-9a-f]+)','Tinebase'/", $result, $matches)) {
            throw new Setup_Exception('could not find Tinebase ID in dump');
        }
        $oldTinebaseId = $matches[1];
        Setup_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Replacing old Tinebase id: ' . $oldTinebaseId);

        $cmd = "bzcat $mysqlBackupFile | sed s/"
            . $oldTinebaseId . '/'
            . Tinebase_Record_Abstract::generateUID() . "/g | " // g for global!
            . "bzip2 > " . $mysqlBackupFile . '.tmp';

        Setup_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $cmd);

        exec($cmd);
        copy($mysqlBackupFile . '.tmp', $mysqlBackupFile);
        unlink($mysqlBackupFile . '.tmp');
    }

    /**
     * delete list of applications
     *
     * @param array $_applications list of application names
     */
    public function uninstallApplications($_applications)
    {
        if (null === ($user = Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly())) {
            throw new Tinebase_Exception('could not create setup user');
        }
        Tinebase_Core::set(Tinebase_Core::USER, $user);

        $this->_clearCache();

        $installedApps = Tinebase_Application::getInstance()->getApplications();
        
        // uninstall all apps if tinebase ist going to be uninstalled
        if (count($installedApps) !== count($_applications) && in_array('Tinebase', $_applications)) {
            $_applications = $installedApps->name;
        }
        
        // deactivate foreign key check if all installed apps should be uninstalled
        $deactivatedForeignKeyCheck = false;
        if (count($installedApps) == count($_applications) && get_class($this->_backend) == 'Setup_Backend_Mysql') {
            $this->_backend->setForeignKeyChecks(0);
            $deactivatedForeignKeyCheck = true;
        }

        // get xml and sort apps first
        $applications = array();
        foreach ($_applications as $applicationName) {
            try {
                $applications[$applicationName] = $this->getSetupXml($applicationName);
            } catch (Setup_Exception_NotFound $senf) {
                // application setup.xml not found
                Tinebase_Exception::log($senf);
                $applications[$applicationName] = null;
            }
        }
        $applications = $this->_sortUninstallableApplications($applications);

        foreach ($applications as $name => $xml) {
            $app = Tinebase_Application::getInstance()->getApplicationByName($name);
            $this->_uninstallApplication($app);
        }

        if (true === $deactivatedForeignKeyCheck) {
            $this->_backend->setForeignKeyChecks(1);
        }
    }
    
    /**
     * install given application
     *
     * @param  SimpleXMLElement $_xml
     * @param  array | optional $_options
     * @return void
     * @throws Tinebase_Exception_Backend_Database
     */
    protected function _installApplication(SimpleXMLElement $_xml, $_options = null)
    {
        if ($this->_backend === NULL) {
            throw new Tinebase_Exception_Backend_Database('Need configured and working database backend for install.');
        }
        
        if (!$this->checkDatabasePrefix()) {
            throw new Tinebase_Exception_Backend_Database('Tableprefix is too long');
        }
        
        try {
            if (Setup_Core::isLogLevel(Zend_Log::INFO)) Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Installing application: ' . $_xml->name);

            $createdTables = array();

            // traditional xml declaration
            if (isset($_xml->tables)) {
                foreach ($_xml->tables[0] as $tableXML) {
                    $table = Setup_Backend_Schema_Table_Factory::factory('Xml', $tableXML);
                    if ($this->_createTable($table) !== true) {
                        // table was gracefully not created, maybe due to missing requirements, just continue
                        continue;
                    }
                    $createdTables[] = $table;
                }
            }

            // do we have modelconfig + doctrine
            else {
                $application = Setup_Core::getApplicationInstance($_xml->name, '', true);
                $models = $application->getModels(true /* MCv2only */);

                if (count($models) > 0) {
                    // create tables using doctrine 2
                    Setup_SchemaTool::createSchema($_xml->name, $models);

                    // adopt to old workflow
                    foreach ($models as $model) {
                        $modelConfiguration = $model::getConfiguration();
                        $createdTables[] = (object)array(
                            'name' => Tinebase_Helper::array_value('name', $modelConfiguration->getTable()),
                            'version' => $modelConfiguration->getVersion(),
                        );
                    }
                }
            }
    
            $application = new Tinebase_Model_Application(array(
                'name'      => (string)$_xml->name,
                'status'    => $_xml->status ? (string)$_xml->status : Tinebase_Application::ENABLED,
                'order'     => $_xml->order ? (string)$_xml->order : 99,
                'version'   => (string)$_xml->version
            ));

            $application = Tinebase_Application::getInstance()->addApplication($application);
            
            // keep track of tables belonging to this application
            foreach ($createdTables as $table) {
                Tinebase_Application::getInstance()->addApplicationTable($application, (string) $table->name, (int) $table->version);
            }
            
            // insert default records
            if (isset($_xml->defaultRecords)) {
                foreach ($_xml->defaultRecords[0] as $record) {
                    $this->_backend->execInsertStatement($record);
                }
            }
            
            Setup_Initialize::initialize($application, $_options);

            // look for import definitions and put them into the db
            $this->createImportExportDefinitions($application);
        } catch (Exception $e) {
            Tinebase_Exception::log($e, /* suppress trace */ false);
            throw $e;
        }
    }

    protected function _createTable($table)
    {
        if (Setup_Core::isLogLevel(Zend_Log::DEBUG)) Setup_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Creating table: ' . $table->name);

        try {
            $result = $this->_backend->createTable($table);
        } catch (Zend_Db_Statement_Exception $zdse) {
            throw new Tinebase_Exception_Backend_Database('Could not create table: ' . $zdse->getMessage());
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw new Tinebase_Exception_Backend_Database('Could not create table: ' . $zdae->getMessage());
        }

        return $result;
    }

    /**
     * look for export & import definitions and put them into the db
     *
     * @param Tinebase_Model_Application $_application
     */
    public function createImportExportDefinitions($_application)
    {
        foreach (array('Import', 'Export') as $type) {
            $path =
                $this->_baseDir . $_application->name .
                DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . 'definitions';
    
            if (file_exists($path)) {
                foreach (new DirectoryIterator($path) as $item) {
                    $filename = $path . DIRECTORY_SEPARATOR . $item->getFileName();
                    if (preg_match("/\.xml/", $filename)) {
                        try {
                            Tinebase_ImportExportDefinition::getInstance()->updateOrCreateFromFilename($filename, $_application);
                        } catch (Exception $e) {
                            Setup_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                                . ' Not installing import/export definion from file: ' . $filename
                                . ' / Error message: ' . $e->getMessage());
                        }
                    }
                }
            }

            $path =
                $this->_baseDir . $_application->name .
                DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . 'templates';

            if (file_exists($path)) {
                $fileSystem = Tinebase_FileSystem::getInstance();

                $basepath = $fileSystem->getApplicationBasePath(
                    'Tinebase',
                    Tinebase_FileSystem::FOLDER_TYPE_SHARED
                ) . '/' . strtolower($type);

                if (false === $fileSystem->isDir($basepath)) {
                    $fileSystem->createAclNode($basepath);
                }

                $templateAppPath = Tinebase_Model_Tree_Node_Path::createFromPath($basepath . '/templates/' . $_application->name);

                if (! $fileSystem->isDir($templateAppPath->statpath)) {
                    $fileSystem->mkdir($templateAppPath->statpath);
                }

                foreach (new DirectoryIterator($path) as $item) {
                    if (!$item->isFile()) {
                        continue;
                    }
                    if (false === ($content = file_get_contents($item->getPathname()))) {
                        Setup_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                            . ' Could not import template: ' . $item->getPathname());
                        continue;
                    }
                    if (false === ($file = $fileSystem->fopen($templateAppPath->statpath . '/' . $item->getFileName(), 'w'))) {
                        Setup_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                            . ' could not open ' . $templateAppPath->statpath . '/' . $item->getFileName() . ' for writting');
                        continue;
                    }
                    fwrite($file, $content);
                    if (true !== $fileSystem->fclose($file)) {
                        Setup_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                            . ' write to ' . $templateAppPath->statpath . '/' . $item->getFileName() . ' did not succeed');
                        continue;
                    }
                }
            }
        }
    }
    
    /**
     * uninstall app
     *
     * @param Tinebase_Model_Application $_application
     * @throws Setup_Exception
     */
    protected function _uninstallApplication(Tinebase_Model_Application $_application, $uninstallAll = false)
    {
        if ($this->_backend === null) {
            throw new Setup_Exception('No setup backend available');
        }
        
        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Uninstall ' . $_application);
        try {
            $applicationTables = Tinebase_Application::getInstance()->getApplicationTables($_application);
        } catch (Zend_Db_Statement_Exception $zdse) {
            Setup_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . " " . $zdse);
            throw new Setup_Exception('Could not uninstall ' . $_application . ' (you might need to remove the tables by yourself): ' . $zdse->getMessage());
        }
        $disabledFK = FALSE;
        $db = Tinebase_Core::getDb();
        
        do {
            $oldCount = count($applicationTables);

            if ($_application->name == 'Tinebase') {
                $installedApplications = Tinebase_Application::getInstance()->getApplications(NULL, 'id');
                if (count($installedApplications) !== 1) {
                    Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Installed apps: ' . print_r($installedApplications->name, true));
                    throw new Setup_Exception_Dependency('Failed to uninstall application "Tinebase" because of dependencies to other installed applications.');
                }
            }

            foreach ($applicationTables as $key => $table) {
                Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Remove table: $table");
                
                try {
                    // drop foreign keys which point to current table first
                    $foreignKeys = $this->_backend->getExistingForeignKeys($table);
                    foreach ($foreignKeys as $foreignKey) {
                        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . 
                            " Drop index: " . $foreignKey['table_name'] . ' => ' . $foreignKey['constraint_name']);
                        $this->_backend->dropForeignKey($foreignKey['table_name'], $foreignKey['constraint_name']);
                    }
                    
                    // drop table
                    $this->_backend->dropTable($table);
                    
                    if ($_application->name != 'Tinebase') {
                        Tinebase_Application::getInstance()->removeApplicationTable($_application, $table);
                    }
                    
                    unset($applicationTables[$key]);
                    
                } catch (Zend_Db_Statement_Exception $e) {
                    // we need to catch exceptions here, as we don't want to break here, as a table
                    // might still have some foreign keys
                    // this works with mysql only
                    $message = $e->getMessage();
                    Setup_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . " Could not drop table $table - " . $message);
                    
                    // remove app table if table not found in db
                    if (preg_match('/SQLSTATE\[42S02\]: Base table or view not found/', $message) && $_application->name != 'Tinebase') {
                        Tinebase_Application::getInstance()->removeApplicationTable($_application, $table);
                        unset($applicationTables[$key]);
                    } else {
                        Setup_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . " Disabling foreign key checks ... ");
                        if ($db instanceof Zend_Db_Adapter_Pdo_Mysql) {
                            $db->query("SET FOREIGN_KEY_CHECKS=0");
                        }
                        $disabledFK = TRUE;
                    }
                }
            }
            
            if ($oldCount > 0 && count($applicationTables) == $oldCount) {
                throw new Setup_Exception('dead lock detected oldCount: ' . $oldCount);
            }
        } while (count($applicationTables) > 0);
        
        if ($disabledFK) {
            if ($db instanceof Zend_Db_Adapter_Pdo_Mysql) {
                Setup_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . " Enabling foreign key checks again... ");
                $db->query("SET FOREIGN_KEY_CHECKS=1");
            }
        }
        
        if ($_application->name != 'Tinebase') {
            if (!$uninstallAll) {
                Tinebase_Relations::getInstance()->removeApplication($_application->name);

                Tinebase_Timemachine_ModificationLog::getInstance()->removeApplication($_application);

                // delete containers, config options and other data for app
                Tinebase_Application::getInstance()->removeApplicationAuxiliaryData($_application);
            }
            
            // remove application from table of installed applications
            Tinebase_Application::getInstance()->deleteApplication($_application);
        }

        Setup_Uninitialize::uninitialize($_application);

        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Removed app: " . $_application->name);
    }

    /**
     * sort applications by checking dependencies
     *
     * @param array $_applications
     * @return array
     */
    protected function _sortInstallableApplications($_applications)
    {
        $result = array();
        
        // begin with Tinebase, Admin and Addressbook
        $alwaysOnTop = array('Tinebase', 'Admin', 'Addressbook');
        foreach ($alwaysOnTop as $app) {
            if (isset($_applications[$app])) {
                $result[$app] = $_applications[$app];
                unset($_applications[$app]);
            }
        }
        
        // get all apps to install ($name => $dependencies)
        $appsToSort = array();
        foreach($_applications as $name => $xml) {
            $depends = (array) $xml->depends;
            if (isset($depends['application'])) {
                if ($depends['application'] == 'Tinebase') {
                    $appsToSort[$name] = array();
                    
                } else {
                    $depends['application'] = (array) $depends['application'];
                    
                    foreach ($depends['application'] as $app) {
                        // don't add tinebase (all apps depend on tinebase)
                        if ($app != 'Tinebase') {
                            $appsToSort[$name][] = $app;
                        }
                    }
                }
            } else {
                $appsToSort[$name] = array();
            }
        }
        
        //Setup_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($appsToSort, true));
        
        // re-sort apps
        $count = 0;
        while (count($appsToSort) > 0 && $count < MAXLOOPCOUNT) {
            
            foreach($appsToSort as $name => $depends) {

                if (empty($depends)) {
                    // no dependencies left -> copy app to result set
                    $result[$name] = $_applications[$name];
                    unset($appsToSort[$name]);
                } else {
                    foreach ($depends as $key => $dependingAppName) {
                        if (in_array($dependingAppName, array_keys($result)) || $this->isInstalled($dependingAppName)) {
                            // remove from depending apps because it is already in result set
                            unset($appsToSort[$name][$key]);
                        }
                    }
                }
            }
            $count++;
        }
        
        if ($count == MAXLOOPCOUNT) {
            Setup_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ .
                " Some Applications could not be installed because of (cyclic?) dependencies: " . print_r(array_keys($appsToSort), TRUE));
        }
        
        return $result;
    }

    /**
     * sort applications by checking dependencies
     *
     * @param array $_applications
     * @return array
     */
    protected function _sortUninstallableApplications($_applications)
    {
        $result = array();
        
        // get all apps to uninstall ($name => $dependencies)
        $appsToSort = array();
        foreach($_applications as $name => $xml) {
            if ($name !== 'Tinebase') {
                $depends = $xml ? (array) $xml->depends : array();
                if (isset($depends['application'])) {
                    if ($depends['application'] == 'Tinebase') {
                        $appsToSort[$name] = array();
                        
                    } else {
                        $depends['application'] = (array) $depends['application'];
                        
                        foreach ($depends['application'] as $app) {
                            // don't add tinebase (all apps depend on tinebase)
                            if ($app != 'Tinebase') {
                                $appsToSort[$name][] = $app;
                            }
                        }
                    }
                } else {
                    $appsToSort[$name] = array();
                }
            }
        }
        
        // re-sort apps
        $count = 0;
        while (count($appsToSort) > 0 && $count < MAXLOOPCOUNT) {

            foreach($appsToSort as $name => $depends) {
                //Setup_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " - $count $name - " . print_r($depends, true));
                
                // don't uninstall if another app depends on this one
                $otherAppDepends = FALSE;
                foreach($appsToSort as $innerName => $innerDepends) {
                    if(in_array($name, $innerDepends)) {
                        $otherAppDepends = TRUE;
                        break;
                    }
                }
                
                // add it to results
                if (!$otherAppDepends) {
                    $result[$name] = $_applications[$name];
                    unset($appsToSort[$name]);
                }
            }
            $count++;
        }
        
        if ($count == MAXLOOPCOUNT) {
            Setup_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ .
                " Some Applications could not be uninstalled because of (cyclic?) dependencies: " . print_r(array_keys($appsToSort), TRUE));
        }

        // Tinebase is uninstalled last
        if (isset($_applications['Tinebase'])) {
            $result['Tinebase'] = $_applications['Tinebase'];
            unset($_applications['Tinebase']);
        }
        
        return $result;
    }
    
    /**
     * check if an application is installed
     *
     * @param string $appname
     * @return boolean
     */
    public function isInstalled($appname)
    {
        try {
            $result = Tinebase_Application::getInstance()->isInstalled($appname);
        } catch (Exception $e) {
            Setup_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Application ' . $appname . ' is not installed.');
            Setup_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . $e);
            $result = FALSE;
        }
        
        return $result;
    }
    
    /**
     * clear cache
     *
     * @return void
     */
    protected function _clearCache()
    {
        // setup cache (via tinebase because it is disabled in setup by default)
        Tinebase_Core::setupCache(TRUE);
        
        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Clearing cache ...');
        
        // clear cache
        $cache = Setup_Core::getCache()->clean(Zend_Cache::CLEANING_MODE_ALL);

        Tinebase_Application::getInstance()->resetClassCache();
        Tinebase_Cache_PerRequest::getInstance()->reset();

        // deactivate cache again
        Tinebase_Core::setupCache(FALSE);
    }

    /**
     * returns TRUE if filesystem is available
     * 
     * @return boolean
     */
    public function isFilesystemAvailable()
    {
        if ($this->_isFileSystemAvailable === null) {
            try {
                $session = Tinebase_Session::getSessionNamespace();

                if (isset($session->filesystemAvailable)) {
                    $this->_isFileSystemAvailable = $session->filesystemAvailable;

                    return $this->_isFileSystemAvailable;
                }
            } catch (Zend_Session_Exception $zse) {
                $session = null;
            }

            $this->_isFileSystemAvailable = (!empty(Tinebase_Core::getConfig()->filesdir) && is_writeable(Tinebase_Core::getConfig()->filesdir));

            if ($session instanceof Zend_Session_Namespace) {
                if (Tinebase_Session::isWritable()) {
                    $session->filesystemAvailable = $this->_isFileSystemAvailable;
                }
            }

            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                . ' Filesystem available: ' . ($this->_isFileSystemAvailable ? 'yes' : 'no'));
        }

        return $this->_isFileSystemAvailable;
    }

    /**
     * backup
     *
     * @param $options array(
     *      'backupDir'  => string // where to store the backup
     *      'noTimestamp => bool   // don't append timestamp to backup dir
     *      'config'     => bool   // backup config
     *      'db'         => bool   // backup database
     *      'files'      => bool   // backup files
     *    )
     */
    public function backup($options)
    {
        $config = Setup_Core::getConfig();

        $backupDir = isset($options['backupDir']) ? $options['backupDir'] : $config->backupDir;
        if (! $backupDir) {
            throw new Exception('backupDir not configured');
        }

        if (! isset($options['noTimestamp'])) {
            $backupDir .= '/' . date_create('now', new DateTimeZone('UTC'))->format('Y-m-d-H-i-s');
        }

        if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) {
            throw new Exception("$backupDir could  not be created");
        }

        if (isset($options['config']) && $options['config']) {
            $configFile = stream_resolve_include_path('config.inc.php');
            $configDir = dirname($configFile);

            $files = file_exists("$configDir/index.php") ? 'config.inc.php' : '.';
            `cd $configDir; tar cjf $backupDir/tine20_config.tar.bz2 $files`;
        }

        if (isset($options['db']) && $options['db']) {
            if (! $this->_backend) {
                throw new Exception('db not configured, cannot backup');
            }

            $backupOptions = array(
                'backupDir'         => $backupDir,
                'structTables'      => $this->_getBackupStructureOnlyTables(),
            );

            $this->_backend->backup($backupOptions);
        }

        $filesDir = isset($config->filesdir) ? $config->filesdir : false;
        if ($options['files'] && $filesDir) {
            `cd $filesDir; tar cjf $backupDir/tine20_files.tar.bz2 .`;
        }
    }

    /**
     * returns an array of all tables of all applications that should only backup the structure
     *
     * @return array
     * @throws Setup_Exception_NotFound
     */
    protected function _getBackupStructureOnlyTables()
    {
        $tables = array();

        // find tables that only backup structure
        $applications = Tinebase_Application::getInstance()->getApplications();

        /**
         * @var $application Tinebase_Model_Application
         */
        foreach($applications as $application) {
            $tableDef = $this->getSetupXml($application->name);
            $structOnlys = $tableDef->xpath('//table/backupStructureOnly[text()="true"]');

            foreach($structOnlys as $structOnly) {
                $tableName = $structOnly->xpath('./../name/text()');
                $tables[] = SQL_TABLE_PREFIX . $tableName[0];
            }
        }

        return $tables;
    }

    /**
     * restore
     *
     * @param $options array(
     *      'backupDir'  => string // location of backup to restore
     *      'config'     => bool   // restore config
     *      'db'         => bool   // restore database
     *      'files'      => bool   // restore files
     *    )
     *
     * @param $options
     * @throws Setup_Exception
     */
    public function restore($options)
    {
        if (! isset($options['backupDir'])) {
            throw new Setup_Exception("you need to specify the backupDir");
        }

        if (isset($options['config']) && $options['config']) {
            $configBackupFile = $options['backupDir']. '/tine20_config.tar.bz2';
            if (! file_exists($configBackupFile)) {
                throw new Setup_Exception("$configBackupFile not found");
            }

            $configDir = isset($options['configDir']) ? $options['configDir'] : false;
            if (!$configDir) {
                $configFile = stream_resolve_include_path('config.inc.php');
                if (!$configFile) {
                    throw new Setup_Exception("can't detect configDir, please use configDir option");
                }
                $configDir = dirname($configFile);
            }

            `cd $configDir; tar xf $configBackupFile`;
        }

        Setup_Core::setupConfig();
        $config = Setup_Core::getConfig();

        if (isset($options['db']) && $options['db']) {
            $this->_backend->restore($options['backupDir']);
        }

        $filesDir = isset($config->filesdir) ? $config->filesdir : false;
        if (isset($options['files']) && $options['files']) {
            $dir = $options['backupDir'];
            $filesBackupFile = $dir . '/tine20_files.tar.bz2';
            if (! file_exists($filesBackupFile)) {
                throw new Setup_Exception("$filesBackupFile not found");
            }

            `cd $filesDir; tar xf $filesBackupFile`;
        }
    }

    public function compareSchema($options)
    {
        if (! isset($options['otherdb'])) {
            throw new Exception("you need to specify the otherdb");
        }

        return Setup_SchemaTool::compareSchema($options['otherdb']);
    }
}
