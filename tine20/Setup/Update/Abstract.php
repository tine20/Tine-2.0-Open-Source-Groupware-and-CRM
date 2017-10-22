<?php
/**
 * Tine 2.0
 * 
 * @package     Setup
 * @subpackage  Update
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Matthias Greiling <m.greiling@metaways.de>
 */

/**
 * Common class for a Tine 2.0 Update
 * 
 * @package     Setup
 * @subpackage  Update
 */
class Setup_Update_Abstract
{
    /**
     * backend for databse handling and extended database queries
     *
     * @var Setup_Backend_Mysql
     */
    protected $_backend;
    
    /**
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_db;

    /**
     * @var null|boolean
     */
    protected $_isReplicationSlave = null;

    /**
     * @var null|boolean
     */
    protected $_isReplicationMaster = null;

    /** 
     * the constructor
     *
     * @param Setup_Backend_Interface $_backend
     */
    public function __construct($_backend)
    {
        $this->_backend = $_backend;
        $this->_db = Tinebase_Core::getDb();
    }
    
    /**
     * get version number of a given application 
     * version is stored in database table "applications"
     *
     * @param string $_application
     * @return string version number major.minor release 
     */
    public function getApplicationVersion($_application)
    {
        return static::getAppVersion($_application);
    }

    /**
     * get version number of a given application
     * version is stored in database table "applications"
     *
     * @param string $_application
     * @return string version number major.minor release
     */
    public static function getAppVersion($_application)
    {
        $db = Tinebase_Core::getDb();
        $select = $db->select()
                ->from(SQL_TABLE_PREFIX . 'applications')
                ->where($db->quoteIdentifier('name') . ' = ?', $_application);

        $stmt = $select->query();
        $version = $stmt->fetchAll();
        
        return $version[0]['version'];
    }

    /**
     * set version number of a given application 
     * version is stored in database table "applications"
     *
     * @param string $_applicationName
     * @param string $_version new version number
     * @return Tinebase_Model_Application
     */    
    public function setApplicationVersion($_applicationName, $_version)
    {
        $application = Tinebase_Application::getInstance()->getApplicationByName($_applicationName);
        $application->version = $_version;
        
        return Tinebase_Application::getInstance()->updateApplication($application);
    }
    
    /**
     * get version number of a given table
     * version is stored in database table "applications_tables"
     *
     * @param string $_tableName
     * @return int version number 
     */
    public function getTableVersion($_tableName)
    {
        $select = $this->_db->select()
                ->from(SQL_TABLE_PREFIX . 'application_tables')
                ->where(    $this->_db->quoteIdentifier('name') . ' = ?', $_tableName)
                ->orWhere(  $this->_db->quoteIdentifier('name') . ' = ?', SQL_TABLE_PREFIX . $_tableName);

        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ .
            ' ' . $select->__toString());

        $stmt = $select->query();
        $rows = $stmt->fetchAll();

        $result = (count($rows) > 0 && isset($rows[0]['version'])) ? $rows[0]['version'] : 0;
        
        return $result;
    }
    
    /**
     * set version number of a given table
     * version is stored in database table "applications_tables"
     *
     * @param string $_tableName
     * @param int|string $_version
     * @param boolean $_createIfNotExist
     * @param string $_application
     * @return void
     * @throws Setup_Exception_NotFound
     */     
    public function setTableVersion($_tableName, $_version, $_createIfNotExist = TRUE, $_application = 'Tinebase')
    {
        if ($this->getTableVersion($_tableName) == 0) {
            if ($_createIfNotExist) {
                Tinebase_Application::getInstance()->addApplicationTable(
                    Tinebase_Application::getInstance()->getApplicationByName($_application), 
                    $_tableName,
                    $_version
                );
            } else {
                throw new Setup_Exception_NotFound('Table ' . $_tableName . ' not found in application tables or previous version number invalid.');
            }
        } else {
            $applicationsTables = new Tinebase_Db_Table(array('name' =>  SQL_TABLE_PREFIX . 'application_tables'));
            $where  = array(
                $this->_db->quoteInto($this->_db->quoteIdentifier('name') . ' = ?', $_tableName),
            );
            $applicationsTables->update(array('version' => $_version), $where);
        }
    }
    
    /**
     * set version number of a given table
     * version is stored in database table "applications_tables"
     *
     * @param string $_tableName
     */  
    public function increaseTableVersion($_tableName)
    {
        $currentVersion = $this->getTableVersion($_tableName);

        $version = ++$currentVersion;
        
        $applicationsTables = new Tinebase_Db_Table(array('name' =>  SQL_TABLE_PREFIX . 'application_tables'));
        $where  = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier('name') . ' = ?', $_tableName),
        );
        $applicationsTables->update(array('version' => $version), $where);
    }
    
    /**
     * compares version numbers of given table and given number
     *
     * @param  string $_tableName
     * @param  int $_version number
     * @throws Setup_Exception
     */     
    public function validateTableVersion($_tableName, $_version)
    {
        $currentVersion = $this->getTableVersion($_tableName);
        if($_version != $currentVersion) {
            throw new Setup_Exception("Wrong table version for $_tableName. expected $_version got $currentVersion");
        }
    }
    
    /**
     * create new table and add it to application tables
     * 
     * @param string $_tableName
     * @param Setup_Backend_Schema_Table_Abstract $_table
     * @param string $_application
     * @param int $_version
     * @return boolean
     */
    public function createTable($_tableName, Setup_Backend_Schema_Table_Abstract $_table, $_application = 'Tinebase', $_version = 1)
    {
        $app = Tinebase_Application::getInstance()->getApplicationByName($_application);
        Tinebase_Application::getInstance()->removeApplicationTable($app, $_tableName);
        
        if (false === $this->_backend->createTable($_table)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Creation of table ' . $_tableName . ' gracefully failed');
            return false;
        }
        
        Tinebase_Application::getInstance()->addApplicationTable($app, $_tableName, $_version);
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Created new table ' . $_tableName);

        return true;
    }
    
    /**
     * rename table in applications table
     *
     * @param string $_oldTableName
     * @param string $_newTableName
     */  
    public function renameTable($_oldTableName, $_newTableName)
    {
        $this->_backend->renameTable($_oldTableName, $_newTableName);
        $this->renameTableInAppTables($_oldTableName, $_newTableName);
    }

    /**
     * @param $_oldTableName
     * @param $_newTableName
     * @return int
     */
    public function renameTableInAppTables($_oldTableName, $_newTableName)
    {
        $applicationsTables = new Tinebase_Db_Table(array('name' =>  SQL_TABLE_PREFIX . 'application_tables'));
        $where  = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier('name') . ' = ?', $_oldTableName),
        );
        $result = $applicationsTables->update(array('name' => $_newTableName), $where);
        return $result;
    }
    
    /**
     * drop table
     *
     * @param string $_tableName
     * @param string $_application
     */  
    public function dropTable($_tableName, $_application = 'Tinebase')
    {
        Tinebase_Application::getInstance()->removeApplicationTable(Tinebase_Application::getInstance()->getApplicationByName($_application), $_tableName);
        $this->_backend->dropTable($_tableName);
    }
    
    /**
     * prompts for a username to set as active user on performing updates. this must be an admin user.
     * the user account will be returned. this method can be called by cli only, so a exception will 
     * be thrown if not running on cli
     * 
     * @throws Tinebase_Exception
     * @return Tinebase_Model_FullUser
     */
    public function promptForUsername()
    {
        if (php_sapi_name() == 'cli') {
            
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                . ' Prompting for username on CLI');
            
            $userFound = null;
            $userAccount = null;
            
            do {
                try {
                    if ($userFound === FALSE) {
                        echo PHP_EOL;
                        echo 'The user could not be found!' . PHP_EOL . PHP_EOL;
                    }
                    
                    $user = Tinebase_Server_Cli::promptInput('Please enter an admin username to perform updates ');
                    $userAccount = Tinebase_User::getInstance()->getFullUserByLoginName($user);
                    
                    if (! $userAccount->hasRight('Tinebase', Tinebase_Acl_Rights::ADMIN)) {
                        $userFound = NULL;
                        echo PHP_EOL;
                        echo 'The user "' . $user . '" could be found, but this is not an admin user!' . PHP_EOL . PHP_EOL;
                    } else {
                        Tinebase_Core::set(Tinebase_Core::USER, $userAccount);
                        $userFound = TRUE;
                    }
                    
                } catch (Tinebase_Exception_NotFound $e) {
                    $userFound = FALSE;
                }
                
            } while (! $userFound);
            
        } else {
            throw new Setup_Exception_PromptUser('no CLI call');
        }
        
        return $userAccount;
    }

    /**
     * get db adapter
     * 
     * @return Zend_Db_Adapter_Abstract
     */
    public function getDb()
    {
        return $this->_db;
    }
    
    /**
     * Search for text fields that contain a string longer as a specific length and truncate it to this length
     * 
     * @param string $table
     * @param string $field
     * @param int $length
     */
    public function shortenTextValues($table, $field, $length)
    {
        $select = $this->_db->select()
            ->from(array($table => SQL_TABLE_PREFIX . $table), array($field))
            ->where("CHAR_LENGTH(" . $this->_db->quoteIdentifier($field) . ") > ?", $length);
        
        $stmt = $this->_db->query($select);
        $results = $stmt->fetchAll();
        $stmt->closeCursor();
        
        foreach ($results as $result) {
            $where = array(
                array($this->_db->quoteIdentifier($field) . ' = ?' => $result[$field])
            );
            
            $newContent = array(
                $field => iconv_substr($result[$field], 0 , $length, 'UTF-8')
            );
            
            try {
                $this->_db->update(SQL_TABLE_PREFIX . $table, $newContent, $where);
                Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Field was shortend: ' . print_r($result, true));
            } catch (Tinebase_Exception_Record_Validation $terv) {
                Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Failed to shorten field: ' . print_r($result, true));
                Tinebase_Exception::log($terv);
            }
        }
    }
    
    /**
     * truncate text fields to a specific length
     * Array needs to contain the table name, field name, and a config option for "<notnull>" ("true", "false")
     * or use "null" to set default to NULL
     * 
     * @param array $columns
     * @param int $length
     */
    public function truncateTextColumn($columns, $length)
    {
        foreach ($columns as $table => $fields) {
            foreach ($fields as $field => $config) {
                try {
                    $this->shortenTextValues($table, $field, $length);
                    if (isset($config)) {
                        $config = ($config == 'null' ? '<default>NULL</default>': '<notnull>' . $config . '</notnull>');
                    }
                    $declaration = new Setup_Backend_Schema_Field_Xml('
                        <field>
                            <name>' . $field . '</name>
                            <type>text</type>
                            <length>' . $length . '</length>'
                            . $config .
                        '</field>
                    ');
                    
                    $this->_backend->alterCol($table, $declaration);
                } catch (Zend_Db_Statement_Exception $zdse) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                        . ' Could not truncate text column ' . $field . ' in table ' . $table);
                    Tinebase_Exception::log($zdse);
                }
            }
        }
    }

    protected function _addModlogFields($table)
    {
        $fields = array('<field>
                <name>created_by</name>
                <type>text</type>
                <length>40</length>
            </field>','
            <field>
                <name>creation_time</name>
                <type>datetime</type>
            </field> ','
            <field>
                <name>last_modified_by</name>
                <type>text</type>
                <length>40</length>
            </field>','
            <field>
                <name>last_modified_time</name>
                <type>datetime</type>
            </field>','
            <field>
                <name>is_deleted</name>
                <type>boolean</type>
                <default>false</default>
            </field>','
            <field>
                <name>deleted_by</name>
                <type>text</type>
                <length>40</length>
            </field>','
            <field>
                <name>deleted_time</name>
                <type>datetime</type>
            </field>','
            <field>
                <name>seq</name>
                <type>integer</type>
                <notnull>true</notnull>
                <default>0</default>
            </field>');
        
        foreach ($fields as $field) {
            $declaration = new Setup_Backend_Schema_Field_Xml($field);
            try {
                $this->_backend->addCol($table, $declaration);
            } catch (Zend_Db_Statement_Exception $zdse) {
                Tinebase_Exception::log($zdse);
            }
        }
    }

    /**
     * try to get user for setup tasks from config
     *
     * @return Tinebase_Model_FullUser
     */
    static public function getSetupFromConfigOrCreateOnTheFly()
    {
        try {
            $setupId = Tinebase_Config::getInstance()->get(Tinebase_Config::SETUPUSERID);
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Setting user with id ' . $setupId . ' as setupuser.');
            /** @noinspection PhpUndefinedMethodInspection */
            $setupUser = Tinebase_User::getInstance()->getUserByPropertyFromSqlBackend('accountId', $setupId, 'Tinebase_Model_FullUser');
        } catch (Tinebase_Exception_NotFound $tenf) {
            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ' . $tenf->getMessage());

            $setupUser = Tinebase_User::createSystemUser('setupuser');
            if ($setupUser) {
                Tinebase_Config::getInstance()->set(Tinebase_Config::SETUPUSERID, null);
                Tinebase_Config::getInstance()->set(Tinebase_Config::SETUPUSERID, $setupUser->getId());
            }
        }

        return $setupUser;
    }

    /**
     * update schema of modelconfig enabled app
     *
     * @param string $appName
     * @param array $modelNames
     * @return boolean success
     * @throws Setup_Exception_NotFound
     */
    public function updateSchema($appName, $modelNames)
    {
        if (count($modelNames) === 0) {
            return false;
        }

        if (! Setup_Core::isDoctrineAvailable()) {

            if (Tinebase_Core::isLogLevel(Zend_Log::CRIT)) Tinebase_Core::getLogger()->crit(__METHOD__ . '::' . __LINE__ .
                ' No doctrine ORM available -> disabling app ' . $appName);

            Tinebase_Application::getInstance()->setApplicationState(
                array(Tinebase_Application::getInstance()->getApplicationByName($appName)->getId()),
                Tinebase_Application::DISABLED
            );
            return false;
        }

        $updateRequired = false;
        $setNewVersions = array();
        /** @var Tinebase_Record_Abstract $modelName */
        foreach ($modelNames as $modelName) {
            $modelConfig = $modelName::getConfiguration();
            $tableName = Tinebase_Helper::array_value('name', $modelConfig->getTable());
            $currentVersion = $this->getTableVersion($tableName);
            $schemaVersion = $modelConfig->getVersion();
            if ($currentVersion < $schemaVersion) {
                $updateRequired = true;
                $setNewVersions[$tableName] = $schemaVersion;
            }
        }

        if ($updateRequired) {
            Setup_SchemaTool::updateSchema($appName, $modelNames);

            foreach($setNewVersions as $table => $version) {
                $this->setTableVersion($table, $version);
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isReplicationSlave()
    {
        if (null !== $this->_isReplicationSlave) {
            return $this->_isReplicationSlave;
        }
        $slaveConfiguration = Tinebase_Config::getInstance()->{Tinebase_Config::REPLICATION_SLAVE};
        $tine20Url = $slaveConfiguration->{Tinebase_Config::MASTER_URL};
        $tine20LoginName = $slaveConfiguration->{Tinebase_Config::MASTER_USERNAME};
        $tine20Password = $slaveConfiguration->{Tinebase_Config::MASTER_PASSWORD};

        // check if we are a replication slave
        if (empty($tine20Url) || empty($tine20LoginName) || empty($tine20Password)) {
            $this->_isReplicationMaster = true;
            return ($this->_isReplicationSlave = false);
        }

        $this->_isReplicationMaster = false;
        return ($this->_isReplicationSlave = true);
    }

    /**
     * @return bool
     */
    public function isReplicationMaster()
    {
        if (null !== $this->_isReplicationMaster) {
            return $this->_isReplicationMaster;
        }

        return ($this->_isReplicationMaster = ! $this->isReplicationSlave());
    }
}
