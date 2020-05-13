<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  EmailUser
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2015-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schuele <p.schuele@metaways.de>
 */

/**
 * plugin to handle smtp settings for dbmail ldap schema
 * 
 * @package    Tinebase
 * @subpackage EmailUser
 */
class Tinebase_EmailUser_Smtp_LdapDbmailSchema extends Tinebase_EmailUser_Ldap implements Tinebase_EmailUser_Smtp_Interface
{
    /**
     * dbmail config
     * 
     * @var array 
     */
    protected $_config = array(
        'emailGID' => null
    );
    
    /**
     * user properties mapping 
     * -> we need to use lowercase for ldap fields because ldap_fetch returns lowercase keys
     *
     * @var array
     */
    protected $_propertyMapping = array(
        'emailAddress'     => 'mail',
        'emailAliases'     => 'mailalternateaddress', 
        'emailForwards'    => 'mailforwardingaddress',
        'emailForwardOnly' => 'deliverymode'
    );
    
    /**
     * objectclasses required for users
     *
     * @var array
     */
    protected $_requiredObjectClass = array(
        'dbmailUser'
    );
    
    protected $_backendType = Tinebase_Config::SMTP;
    
    /**
     * the constructor
     */
    public function __construct(array $_options = array())
    {
        parent::__construct($_options);

        $this->_config['emailGID'] = sprintf("%u", crc32(Tinebase_Core::getTinebaseId()));

        // get email user backend config options (host, dbname, username, password, port)
        $emailConfig = Tinebase_Config::getInstance()->get(Tinebase_Config::SMTP, new Tinebase_Config_Struct())->toArray();

        // merge _config and email backend config
        $this->_config = array_merge($this->_config, $emailConfig);

        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($this->_config, TRUE));
    }
    
    /**
     * (non-PHPdoc)
     * @see Tinebase_EmailUser_Ldap::_user2Ldap()
     */
    protected function _user2Ldap(Tinebase_Model_FullUser $_user, array &$_ldapData, array &$_ldapEntry = array())
    {
        if (empty($_user->accountEmailAddress)) {
            foreach ($this->_propertyMapping as $ldapKeyName) {
                $_ldapData[$ldapKeyName] = array();
            }
            $_ldapData['accountStatus'] = array();
            $_ldapData['mailHost']      = array();
            
            $_ldapData['objectclass'] = array_unique(array_diff($_ldapData['objectclass'], $this->_requiredObjectClass));
            
        } else {
            parent::_user2Ldap($_user, $_ldapData, $_ldapEntry);
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . '  $ldapData: ' . print_r($_ldapData, true));
    }

    /**
     * check if user exists already in email backend user table
     *
     * @param  Tinebase_Model_FullUser  $_user
     * @return boolean
     *
     * TODO implement
     */
    public function emailAddressExists(Tinebase_Model_FullUser $_user)
    {
        return false;
    }
}  
