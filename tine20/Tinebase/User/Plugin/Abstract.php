<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  User
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2010-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * abstract class for user plugins
 * 
 * @package Tinebase
 * @subpackage User
 */
abstract class Tinebase_User_Plugin_Abstract implements Tinebase_User_Plugin_SqlInterface
{
    /**
    * @var Zend_Db_Adapter_Abstract
    */
    protected $_db = NULL;
    
    /**
     * @var Tinebase_Backend_Sql_Command_Interface
     */
    protected $_dbCommand;

    /**
     * email user config
     *
     * @var array
     */
    protected $_config = array();

    /**
     * inspect data used to create user
     * 
     * @param Tinebase_Model_FullUser  $_addedUser
     * @param Tinebase_Model_FullUser  $_newUserProperties
     */
    public function inspectAddUser(Tinebase_Model_FullUser $_addedUser, Tinebase_Model_FullUser $_newUserProperties)
    {
        $this->inspectUpdateUser($_addedUser, $_newUserProperties);
    }
    
    /**
     * inspect data used to update user
     * 
     * @param Tinebase_Model_FullUser  $_updatedUser
     * @param Tinebase_Model_FullUser  $_newUserProperties
     */
    public function inspectUpdateUser(Tinebase_Model_FullUser $_updatedUser, Tinebase_Model_FullUser $_newUserProperties)
    {
        if (! isset($_newUserProperties->imapUser) && ! isset($_newUserProperties->smtpUser)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' No email properties found!');
            return;
        }
        
        if ($this->_userExists($_updatedUser) === true) {
            $this->_updateUser($_updatedUser, $_newUserProperties);
        } else {
            $this->_addUser($_updatedUser, $_newUserProperties);
        }
    }

    /**
     * inspect get user by property
     *
     * @param Tinebase_Model_User  $_user  the user object
     */
    public function inspectGetUserByProperty(Tinebase_Model_User $_user)
    {
        // do nothing
    }

    /**
     * update/set email user password
     *
     * @param  string  $_userId
     * @param  string  $_password
     * @param  bool    $_encrypt encrypt password
     */
    public function inspectSetPassword($_userId, $_password, $_encrypt = TRUE)
    {
        // do nothing
    }

    /**
     * delete user by id
     *
     * @param   Tinebase_Model_FullUser $_user
     */
    public function inspectDeleteUser(Tinebase_Model_FullUser $_user)
    {
        // do nothing
    }

    /**
     * adds email properties for a new user
     * 
     * @param  Tinebase_Model_FullUser  $_addedUser
     * @param  Tinebase_Model_FullUser  $_newUserProperties
     */
    abstract protected function _addUser(Tinebase_Model_FullUser $_addedUser, Tinebase_Model_FullUser $_newUserProperties);
    
    /**
     * Check if we should append domain name or not
     *
     * @param  string $_userName
     * @return string
     */
    protected function _appendDomain($_userName)
    {
        $domainConfigKey = ($this instanceof Tinebase_EmailUser_Imap_Interface) ? 'domain' : 'primarydomain';
        
        if (!empty($this->_config[$domainConfigKey])) {
            $domain = '@' . $this->_config[$domainConfigKey];
            if (strpos($_userName, $domain) === FALSE) {
                $_userName .= $domain;
            }
        }
        
        return $_userName;
    }
    
    /**
     * set database
     */
    protected function _getDb()
    {
        $mailDbConfig = $this->_config[$this->_subconfigKey];
        $mailDbConfig['adapter'] = !empty($mailDbConfig['adapter']) ?
            strtolower($mailDbConfig['adapter']) :
            strtolower($this->_config['adapter']);
        
        $tine20DbConfig = Tinebase_Core::getDb()->getConfig();
        $tine20DbConfig['adapter'] = strtolower(str_replace('Tinebase_Backend_Sql_Adapter_', '', get_class(Tinebase_Core::getDb())));
        
        if ($mailDbConfig['adapter']  == $tine20DbConfig['adapter'] &&
            $mailDbConfig['host']     == $tine20DbConfig['host']    &&
            $mailDbConfig['dbname']   == $tine20DbConfig['dbname']  &&
            $mailDbConfig['username'] == $tine20DbConfig['username']
        ) {
            $this->_db = Tinebase_Core::getDb();
        } else {
            $dbConfig = array_intersect_key($mailDbConfig, array_flip(array('adapter', 'host', 'dbname', 'username', 'password', 'port')));
            $this->_db = Tinebase_Core::createAndConfigureDbAdapter($dbConfig);
        }
    }
    
    /**
     * get email user name depending on config
     *
     * @param Tinebase_Model_FullUser $user
     * @param $alternativeLoginName
     * @return string
     */
    protected function _getEmailUserName(Tinebase_Model_FullUser $user, $alternativeLoginName = null)
    {
        if (isset($this->_config['useEmailAsUsername']) && $this->_config['useEmailAsUsername']) {
            $emailUsername = $user->accountEmailAddress;
        } else if (isset($this->_config['instanceName']) && ! empty($this->_config['instanceName'])) {
            $emailUsername = $user->getId() . '@' . $this->_config['instanceName'];
        } else if (isset($this->_config['domain']) && $this->_config['domain'] !== null) {
            $emailUsername = $this->_appendDomain($user->accountLoginName);
        } else if ($alternativeLoginName !== null) {
            $emailUsername = $emailAddress;
        } else {
            $emailUsername = $user->accountLoginName;
        }

        return $emailUsername;
    }
    
    /**
     * get database object
     * 
     * @return Zend_Db_Adapter_Abstract
     */
    public function getDb()
    {
        return $this->_db;
    }
    
    /**
     * generate salt for password scheme
     * 
     * @param $_scheme
     * @return string
     */
    protected function _salt($_scheme)
    {
        // create a salt that ensures crypt creates an sha2 hash
        $base64_alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ'
            .'abcdefghijklmnopqrstuvwxyz0123456789+/';

        $salt = '';
        for($i=0; $i<16; $i++){
            $salt .= $base64_alphabet[rand(0,63)];
        }
        
        switch ($_scheme) {
            case 'SSHA256':
                $salt = '$5$' . $salt . '$';
                break;
                
            case 'SSHA512':
                $salt = '$6$' . $salt . '$';
                break;
                
            case 'MD5-CRYPT':
            default:
                $salt = crypt($_scheme);
                break;
        }

        return $salt;
    }
    
    /**
     * updates email properties for an existing user
     * 
     * @param  Tinebase_Model_FullUser  $_updatedUser
     * @param  Tinebase_Model_FullUser  $_newUserProperties
     */
    abstract protected function _updateUser(Tinebase_Model_FullUser $_updatedUser, Tinebase_Model_FullUser $_newUserProperties);
    
    /**
     * check if user exists already in plugin user table
     * 
     * @param Tinebase_Model_FullUser $_user
     */
    abstract protected function _userExists(Tinebase_Model_FullUser $_user);
}
