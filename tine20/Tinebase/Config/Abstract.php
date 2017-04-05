<?php
/**
 * @package     Tinebase
 * @subpackage  Config
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2016 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */

/**
 * base for config classes
 * 
 * @package     Tinebase
 * @subpackage  Config
 * 
 * @todo support protected function interceptor for get property: _get<PropertyName>(&$data)
 * @todo support protected function interceptor for set property: _set<PropertyName>(&$data)
 * @todo update db to json encode all configs
 * @todo support array collections definitions
 */
abstract class Tinebase_Config_Abstract implements Tinebase_Config_Interface
{
    /**
     * object config type
     * 
     * @var string
     */
    const TYPE_OBJECT = 'object';

    /**
     * integer config type
     * 
     * @var string
     */
    const TYPE_INT = 'int';
    
    /**
     * boolean config type
     * 
     * @var string
     */
    const TYPE_BOOL = 'bool';
    
    /**
     * string config type
     * 
     * @var string
     */
    const TYPE_STRING = 'string';
    
    /**
     * float config type
     * 
     * @var string
     */
    const TYPE_FLOAT = 'float';
    
    /**
     * dateTime config type
     * 
     * @var string
     */
    const TYPE_DATETIME = 'dateTime';

    /**
     * keyField config type
     *
     * @var string
     */
    const TYPE_KEYFIELD = 'keyField';

    /**
     * array config type
     *
     * @var string
     */
    const TYPE_ARRAY = 'array';

    /**
     * keyFieldConfig config type
     * 
     * @var string
     */
    const TYPE_KEYFIELD_CONFIG = 'keyFieldConfig';
    
    /**
     * config key for enabled features / feature switch
     *
     * @var string
     */
    const ENABLED_FEATURES = 'features';
    
    /**
     * application name this config belongs to
     *
     * @var string
     */
    protected $_appName;
    
    /**
     * config file data.
     * 
     * @var array
     */
    private static $_configFileData;
    
    /**
     * config database backend
     * 
     * @var Tinebase_Backend_Sql
     */
    private static $_backend;
    
    /**
     * application config class cache (name => config record)
     * 
     * @var array
     */
    protected $_cachedApplicationConfig = NULL;

    protected $_mergedConfigCache = array();

    /**
     * server classes
     *
     * @var array
     */
    protected static $_serverPlugins = array();
    
    /**
     * get config object for application
     * 
     * @param string $applicationName
     * @return Tinebase_Config_Abstract
     */
    public static function factory($applicationName)
    {
        if ($applicationName === 'Tinebase') {
            $config = Tinebase_Core::getConfig();
            // NOTE: this is a Zend_Config object in the Setup
            if ($config instanceof Tinebase_Config_Abstract) {
                return $config;
            }
        }
        
        $configClassName = $applicationName . '_Config';
        if (@class_exists($configClassName)) {
            $config = call_user_func(array($configClassName, 'getInstance'));
        } else {
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ 
                . ' Application ' . $applicationName . ' has no config class.');
            $config = NULL;
        }
        
        return $config;
    }
    
    /**
     * retrieve a value and return $default if there is no element set.
     *
     * @param  string $name
     * @param  mixed  $default
     * @return mixed
     */
    public function get($name, $default = NULL)
    {
        if (isset($this->_mergedConfigCache[$name]) || array_key_exists($name, $this->_mergedConfigCache)) {
            if (!isset($this->_mergedConfigCache[$name]) && null !== $default) {
                return $default;
            }
            return $this->_mergedConfigCache[$name];
        }

        $fileConfigArray = null;
        $dbConfigArray = null;
        $dbAvailable = Tinebase_Core::hasDb();

        // NOTE: we return here (or in the default handling) if db is not available. That is to prevent db lookup when db is not yet setup
        $configFileSection = $this->getConfigFileSection($name);
        if (null !== $configFileSection) {
            $fileConfigArray = $configFileSection[$name];
            if (false === $dbAvailable) {
                return $this->_rawToConfig($fileConfigArray, $name);
            }
        }
        
        if (true === $dbAvailable && null !== ($config = $this->_loadConfig($name))) {
            $dbConfigArray = json_decode($config->value, TRUE);
            // @todo JSON encode all config data via update script!
            if (null === $dbConfigArray && strtolower($config->value) !== '{null}') $dbConfigArray = $config->value;
        }

        $data = null;
        if (null === $fileConfigArray && null === $dbConfigArray) {
            if ($default !== null) {
                return $default;
            }

            $data = $this->_getDefault($name);
        } else {

            if (null === $fileConfigArray) {
                $fileConfigArray = array();
            } elseif(!is_array($fileConfigArray)) {
                $data = $fileConfigArray;
            }

            if (null === $dbConfigArray) {
                $dbConfigArray = array();
            } elseif(!is_array($dbConfigArray) && null === $data) {
                if (count($fileConfigArray) > 0) {
                    $dbConfigArray = array();
                } else {
                    $data = $dbConfigArray;
                }
            }

            if (null === $data) {
                $data = array_replace_recursive($dbConfigArray, $fileConfigArray);
            }
        }

        $this->_mergedConfigCache[$name] = $this->_rawToConfig($data, $name);

        return $this->_mergedConfigCache[$name];
    }
    
    /**
     * get config default
     * - checks if application config.inc.php is available for defaults first
     * - checks definition default second
     * 
     * @param string $name
     * @return mixed
     */
    protected function _getDefault($name)
    {
        $default = null;
        $definition = self::getDefinition($name);
        
        $appDefaultConfig = $this->_getAppDefaultsConfigFileData();
        if (isset($appDefaultConfig[$name])) {
            $default = $appDefaultConfig[$name];
        } else if (null !== $definition) {
            if (isset($definition['default']) || array_key_exists('default', $definition)) {
                $default = $definition['default'];
            } elseif (isset($definition['type']) && isset($definition['class']) && $definition['type'] === self::TYPE_OBJECT) {
                $default = array();
            }
        }

        return $default;
    }
    
    /**
     * store a config value
     *
     * if you store a value here, it will be valid for the current process and be persisted in the db, BUT
     * it maybe overwritten by a config file entry. So other process that merge the config from db and config
     * file again may not get the value you set here.
     *
     * @TODO validate config (rawToString?)
     *
     * @param  string   $_name      config name
     * @param  mixed    $_value     config value
     * @return void
     */
    public function set($_name, $_value)
    {
        if (null === $_value) {
            $this->delete($_name);
            if (isset($this->_mergedConfigCache[$_name]) || array_key_exists($_name, $this->_mergedConfigCache)) {
                unset($this->_mergedConfigCache[$_name]);
            }
            return;
        }

        $configRecord = new Tinebase_Model_Config(array(
            "application_id"    => Tinebase_Application::getInstance()->getApplicationByName($this->_appName)->getId(),
            "name"              => $_name,
            "value"             => json_encode($_value),
        ));
        
        $this->_saveConfig($configRecord);

        $this->_mergedConfigCache[$_name] = $this->_rawToConfig($_value, $_name);
    }
    
    /**
     * delete a config from database
     * 
     * @param  string   $_name
     * @return void
     */
    public function delete($_name)
    {
        $config = $this->_loadConfig($_name);
        if ($config) {
            $this->_getBackend()->delete($config->getId());
            $this->clearCache();
        }
    }
    
    /**
     * delete all config for a application
     *
     * @param  string   $_applicationId
     * @return integer  number of deleted configs
     * 
     * @todo remove param as this should be known?
     */
    public function deleteConfigByApplicationId($_applicationId)
    {
        $count = $this->_getBackend()->deleteByProperty($_applicationId, 'application_id');
        $this->clearCache(array("id" => $_applicationId));
        
        return $count;
    }
    
    /**
     * Magic function so that $obj->value will work.
     *
     * @param string $_name
     * @return mixed
     */
    public function __get($_name)
    {
        return $this->get($_name);
    }
    
    /**
     * Magic function so that $obj->configName = configValue will work.
     *
     * @param  string   $_name      config name
     * @param  mixed    $_value     config value
     * @return void
     */
    public function __set($_name, $_value)
    {
        $this->set($_name, $_value);
    }
    
    /**
     * checks if a config name is set
     * isset means that the config key is present either in config file or in db
     * 
     * @param  string $_name
     * @return bool
     */
    public function __isset($_name)
    {
        // NOTE: we can't test more precise here due to cacheing
        $value = $this->get($_name, Tinebase_Model_Config::NOTSET);
        
        return $value !== Tinebase_Model_Config::NOTSET;
    }
    
    /**
     * returns data from central config.inc.php file
     * 
     * @return array
     */
    protected function _getConfigFileData()
    {
        if (! self::$_configFileData) {
            /** @noinspection PhpIncludeInspection */
            self::$_configFileData = include('config.inc.php');
            
            if (self::$_configFileData === false) {
                die('Central configuration file config.inc.php not found in includepath: ' . get_include_path() . "\n");
            }
            
            if (isset(self::$_configFileData['confdfolder'])) {
                $tmpDir = Tinebase_Core::guessTempDir(self::$_configFileData);
                $cachedConfigFile = $tmpDir . DIRECTORY_SEPARATOR . 'cachedConfig.inc.php';

                if (file_exists($cachedConfigFile)) {
                    /** @noinspection PhpIncludeInspection */
                    $cachedConfigData = include($cachedConfigFile);
                } else {
                    $cachedConfigData = false;
                }
                
                if ($this->_doCreateCachedConfig($cachedConfigData)) {
                    $this->_createCachedConfig($tmpDir);
                } else {
                    self::$_configFileData = $cachedConfigData;
                }
            }
        }
        
        return self::$_configFileData;
    }

    /**
     * returns true if a new cached config file should be created
     *
     * @param $cachedConfigData
     * @return bool
     */
    protected function _doCreateCachedConfig($cachedConfigData)
    {
        return (
               false === $cachedConfigData
            || (defined('TINE20_BUILDTYPE') && TINE20_BUILDTYPE == 'DEVELOPMENT')
            || (defined('TINE20_BUILDTYPE') && TINE20_BUILDTYPE == 'DEBUG')
            || $cachedConfigData['ttlstamp'] < time()
        );
    }
    
    /**
     * composes config files from conf.d and saves array to tmp file
     *
     * @param string $tmpDir
     */
    protected function _createCachedConfig($tmpDir)
    {
        $confdFolder = self::$_configFileData['confdfolder'];

        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Creating new cached config file in ' . $tmpDir);

        if (! is_readable($confdFolder)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                . ' can\'t open conf.d folder "' . $confdFolder . '"');
            return;
        }

        $dh = opendir($confdFolder);

        if ($dh === false) {
            if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                . ' opendir() failed on folder "' . $confdFolder . '"');
            return;
        }

        while (false !== ($direntry = readdir($dh))) {
            if (strpos($direntry, '.inc.php') === (strlen($direntry) - 8)) {
                // TODO do lint!?! php -l $confdFolder . DIRECTORY_SEPARATOR . $direntry
                /** @noinspection PhpIncludeInspection */
                $tmpArray = include($confdFolder . DIRECTORY_SEPARATOR . $direntry);
                if (false !== $tmpArray) {
                    foreach ($tmpArray as $key => $value) {
                        self::$_configFileData[$key] = $value;
                    }
                }
            }
        }
        closedir($dh);

        $ttl = 60;
        if (isset(self::$_configFileData['composeConfigTTL'])) {
            $ttl = (int) self::$_configFileData['composeConfigTTL'];
            if ($ttl < 1) {
                if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                    . ' composeConfigTTL needs to be an integer > 0, current value: "'
                    . print_r(self::$_configFileData['composeConfigTTL'],true) . '"');
                $ttl = 60;
            }
        }
        self::$_configFileData['ttlstamp'] = time() + $ttl;
        
        $filename = $tmpDir . DIRECTORY_SEPARATOR . 'cachedConfig.inc.php';
        $filenameTmp = $filename . uniqid('tine20', true);
        $fh = fopen($filenameTmp, 'w');
        if (false === $fh) {
            if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                    . ' can\'t create cached composed config file "' .$filename );
        } else {
            
            fputs($fh, "<?php\n\nreturn ");
            fputs($fh, var_export(self::$_configFileData, true));
            fputs($fh, ';');
            fclose($fh);

            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Wrote config to file ' . $filenameTmp);
            
            if (false === rename($filenameTmp, $filename) ) {
                if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                    . ' can\'t rename "' . $filenameTmp . '" to "' . $filename . '"' );
            }

            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Renamed to file ' . $filename);
        }
    }
    
    /**
     * returns data from application specific config.inc.php file
     *
     * @return array
     */
    protected function _getAppDefaultsConfigFileData()
    {
        $cacheId = $this->_appName;
        try {
            $configData = Tinebase_Cache_PerRequest::getInstance()->load(__CLASS__, __METHOD__, $cacheId);
        } catch (Tinebase_Exception_NotFound $tenf) {

            $configFilename = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . $this->_appName . DIRECTORY_SEPARATOR . 'config.inc.php';

            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Looking for defaults config.inc.php at ' . $configFilename);
            if (file_exists($configFilename)) {
                /** @noinspection PhpIncludeInspection */
                $configData = include($configFilename);
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Found default config.inc.php for app ' . $this->_appName);
                if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                    . ' ' . print_r($configData, true));
            } else {
                $configData = array();
            }
            Tinebase_Cache_PerRequest::getInstance()->save(__CLASS__, __METHOD__, $cacheId, $configData);
        }
        
        return $configData;
    }
    
    /**
     * get config file section where config identified by name is in
     * 
     * @param  string $name
     * @return array
     */
    public function getConfigFileSection($name)
    {
        $configFileData = $this->_getConfigFileData();
        
        // appName section overwrites global section in config file
        // TODO: this needs improvement -> it is currently not allowed to have configs with the same names in
        //       an Application and Tinebase as this leads to strange/unpredictable results here ...
        return (isset($configFileData[$this->_appName]) || array_key_exists($this->_appName, $configFileData)) && (isset($configFileData[$this->_appName][$name]) || array_key_exists($name, $configFileData[$this->_appName])) ? $configFileData[$this->_appName] :
              ((isset($configFileData[$name]) || array_key_exists($name, $configFileData)) ? $configFileData : NULL);
    }
    
    /**
     * load a config record from database
     * 
     * @param  string                   $_name
     * @return Tinebase_Model_Config|NULL
     */
    protected function _loadConfig($_name)
    {
        if ($this->_cachedApplicationConfig === NULL) {
            $this->_loadAllAppConfigsInCache();
        }
        $result = (isset($this->_cachedApplicationConfig[$_name])) ? $this->_cachedApplicationConfig[$_name] :  NULL;
        
        return $result;
    }

    /**
    * fill class cache with all config records for this app
    */
    protected function _loadAllAppConfigsInCache()
    {
        if (empty($this->_appName)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' appName not set');
            $this->_cachedApplicationConfig = array();
        }

        if (! Tinebase_Application::getInstance()->getInstance()->isInstalled('Tinebase')) {
            $this->_cachedApplicationConfig = array();
            return;
        }
        
        $cache = Tinebase_Core::getCache();
        if (!is_object($cache)) {
           Tinebase_Core::setupCache();
           $cache = Tinebase_Core::getCache();
        }
        
        if (Tinebase_Core::get(Tinebase_Core::SHAREDCACHE)) {
            if ($cachedApplicationConfig = $cache->load('cachedAppConfig_' . $this->_appName)) {
                $this->_cachedApplicationConfig = $cachedApplicationConfig;
                return;
            }
        }
        
        try {
            $applicationId = Tinebase_Model_Application::convertApplicationIdToInt($this->_appName);
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Loading all configs for app ' . $this->_appName);

            $filter = new Tinebase_Model_ConfigFilter(array(
                array('field' => 'application_id', 'operator' => 'equals', 'value' => $applicationId),
            ));
            $allConfigs = $this->_getBackend()->search($filter);
        } catch (Zend_Db_Exception $zdae) {
            // DB might not exist or tables are not created, yet
            Tinebase_Exception::log($zdae);
            $this->_cachedApplicationConfig = array();
            return;
        }

        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Found ' . count($allConfigs) . ' configs.');
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($allConfigs->toArray(), TRUE));
        
        foreach ($allConfigs as $config) {
            $this->_cachedApplicationConfig[$config->name] = $config;
        }
        
        if (Tinebase_Core::get(Tinebase_Core::SHAREDCACHE)) {
            $cache->save($this->_cachedApplicationConfig, 'cachedAppConfig_' . $this->_appName);
        }
    }
    
    /**
     * store a config record in database
     * 
     * @param   Tinebase_Model_Config $_config record to save
     * @return  Tinebase_Model_Config
     * 
     * @todo only allow to save records for this app ($this->_appName)
     */
    protected function _saveConfig(Tinebase_Model_Config $_config)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Setting config ' . $_config->name);
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' ' . print_r($_config->value, true));
        
        $config = $this->_loadConfig($_config->name);
        
        if ($config) {
            $config->value = $_config->value;
            try {
                $result = $this->_getBackend()->update($config);
            } catch (Tinebase_Exception_NotFound $tenf) {
                // config might be deleted but cache has not been cleaned
                $result = $this->_getBackend()->create($_config);
            }
        } else {
            $result = $this->_getBackend()->create($_config);
        }
        
        $this->clearCache();
        
        return $result;
    }

    /**
     * clear the cache
     * @param   array $appFilter
     */
    public function clearCache($appFilter = null)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Clearing config cache');

        if (Tinebase_Core::get(Tinebase_Core::SHAREDCACHE)) {
            if (isset($appFilter)) {
                list($key, $value) = each($appFilter);
                $appName = $key === 'name' ? $value : Tinebase_Application::getInstance()->getApplicationById($value)->name;
            } else {
                $appName = $this->_appName;
            }
            Tinebase_Core::getCache()->remove('cachedAppConfig_' . $appName);
        }

        Tinebase_Cache_PerRequest::getInstance()->reset('Tinebase_Config_Abstract');

        $cachedConfigFile = Tinebase_Core::guessTempDir() . DIRECTORY_SEPARATOR . 'cachedConfig.inc.php';
        if (file_exists($cachedConfigFile)) {
            unlink($cachedConfigFile);
        }

        // reset class caches last because they would be filled again by Tinebase_Core::guessTempDir()
        self::$_configFileData = null;
        $this->_cachedApplicationConfig = null;
        $this->_mergedConfigCache = array();
    }
    
    /**
     * returns config database backend
     * 
     * @return Tinebase_Backend_Sql
     */
    protected function _getBackend()
    {
        if (! self::$_backend) {
            self::$_backend = new Tinebase_Backend_Sql(array(
                'modelName' => 'Tinebase_Model_Config', 
                'tableName' => 'config',
            ));
        }
        
        return self::$_backend;
    }

    /**
     * converts raw data to config values of defined type
     *
     * @param   mixed     $_rawData
     * @param   string    $_name
     * @return  mixed
     */
    protected function _rawToConfig($_rawData, $_name)
    {
        return static::rawToConfig($_rawData, $this, $_name, self::getDefinition($_name), $this->_appName);
    }

    /**
     * converts raw data to config values of defined type
     * 
     * @TODO support array contents conversion
     * @TODO support interceptors
     * 
     * @param   mixed     $_rawData
     * @param   object    $parent
     * @param   string    $parentKey
     * @param   array     $definition
     * @param   string    $appName
     * @return  mixed
     */
    public static function rawToConfig($_rawData, $parent, $parentKey, $definition, $appName)
    {
        if (null === $_rawData) {
            return $_rawData;
        }

        // TODO make definition mandatory => should be an error
        if (!is_array($definition) || !isset($definition['type'])) {
            return is_array($_rawData) ? new Tinebase_Config_Struct($_rawData) : $_rawData;
        }
        if ($definition['type'] === self::TYPE_OBJECT && isset($definition['class']) && @class_exists($definition['class'])) {
            if (is_object($_rawData) && $_rawData instanceof $definition['class']) {
                return $_rawData;
            }
            return new $definition['class'](is_array($_rawData) ? $_rawData : array(), $parent, $parentKey,
                isset($definition['content']) ? $definition['content'] : null, $appName);
        }

        switch ($definition['type']) {
            case self::TYPE_INT:        return (int) $_rawData;
            case self::TYPE_BOOL:       return $_rawData === "true" || (bool) (int) $_rawData;
            case self::TYPE_STRING:     return (string) $_rawData;
            case self::TYPE_FLOAT:      return (float) $_rawData;
            case self::TYPE_ARRAY:      return (array) $_rawData;
            case self::TYPE_DATETIME:   return new DateTime($_rawData);
            case self::TYPE_KEYFIELD_CONFIG:
                if (is_object($_rawData) && $_rawData instanceof Tinebase_Config_KeyField) {
                    return $_rawData;
                }
                $options = (isset($definition['options']) || array_key_exists('options', $definition)) ? (array) $definition['options'] : array();
                $options['appName'] = $appName;
                return Tinebase_Config_KeyField::create($_rawData, $options);

            // TODO this should be an error
            default:                    return is_array($_rawData) ? new Tinebase_Config_Struct($_rawData, $parent, $parentKey) : $_rawData;
        }
    }
    
    /**
     * get definition of given property
     * 
     * @param   string  $_name
     * @return  array
     */
    public function getDefinition($_name)
    {
        $properties = static::getProperties();
        
        return (isset($properties[$_name]) || array_key_exists($_name, $properties)) ? $properties[$_name] : NULL;
    }
    
    /**
     * Get list of server classes
     *
     * @return array
     */
    public static function getServerPlugins()
    {
        return static::$_serverPlugins;
    }
    
    /**
     * check if config system is ready
     * 
     * @todo check db setup
     * @return bool
     */
    public static function isReady()
    {
        $configFile = @file_get_contents('config.inc.php', FILE_USE_INCLUDE_PATH);
        
        return !! $configFile;
    }

    /**
     * returns true if a certain feature is enabled
     * 
     * @param string $featureName
     * @return boolean
     */
    public function featureEnabled($featureName)
    {
        $cacheId = $this->_appName;
        try {
            $features = Tinebase_Cache_PerRequest::getInstance()->load(__CLASS__, __METHOD__, $cacheId);
        } catch (Tinebase_Exception_NotFound $tenf) {
            $features = $this->get(self::ENABLED_FEATURES);
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Features config of app ' . $this->_appName . ': '
                . print_r($features->toArray(), true));
            Tinebase_Cache_PerRequest::getInstance()->save(__CLASS__, __METHOD__, $cacheId, $features);
        }

        if (isset($features->{$featureName})) {
            if (Tinebase_Config::FEATURE_SEARCH_PATH === $featureName && 'Tinebase' === $this->_appName &&
                !Setup_Backend_Factory::factory()->supports('mysql >= 5.6.4')) {
                return false;
            }
            return $features->{$featureName};
        }

        return false;
    }
}
