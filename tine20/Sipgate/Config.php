<?php
/**
 * @package     Sipgate
 * @subpackage  Config
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Alexander Stintzing <alex@stintzing.net>
 * @copyright   Copyright (c) 2012 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * Sipgate config class
 *
 * @package     Sipgate
 * @subpackage  Config
 */
class Sipgate_Config extends Tinebase_Config_Abstract
{
    /**
     * Connection Status
     * @var string
     */
    const CONNECTION_STATUS = 'connectionStatus';

    /**
     * Connection TOS
     * @var string
     */
    const CONNECTION_TOS = 'connectionTos';

    /**
     * Account type (shared/private)
     * @var string
     */
    const ACCOUNT_TYPE = 'accountType';
    
    /**
     * Sipgate account type (plus/team) - plus means also basic
     * @var string
     */
    const ACCOUNT_ACCOUNT_TYPE = 'accountAccountType';
    
    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Definition::$_properties
     */
    protected static $_properties = array(
        self::CONNECTION_STATUS => array(
            //_('Connection Status')
            'label'                 => 'Connection Status',
            //_('Possible connection states')
            'description'           => 'Possible connection states',
            'type'                  => 'keyFieldConfig',
            'options'               => array('recordModel' => 'Sipgate_Model_ConnectionStatus'),
            'clientRegistryInclude' => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 'accepted', 'value' => 'accepted', 'icon' => "../../images/../Sipgate/res/call_accepted.png", 'system' => true),  //_('accepted')
                    array('id' => 'outgoing', 'value' => 'outgoing', 'icon' => "../../images/../Sipgate/res/call_outgoing.png", 'system' => true),  //_('outgoing')
                    array('id' => 'missed',   'value' => 'missed',   'icon' => "../../images/../Sipgate/res/call_missed.png", 'system' => true),  //_('missed')
                ),
                'default' => 'accepted'
            )
        ),
        self::CONNECTION_TOS => array(
            //_('Connection TOS')
            'label'                 => 'Connection TOS',
            //_('Possible connection type of services')
            'description'           => 'Possible connection type of services',
            'type'                  => 'keyFieldConfig',
            'options'               => array('recordModel' => 'Sipgate_Model_ConnectionTos'),
            'clientRegistryInclude' => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 'voice', 'value' => 'Telephone Call',  'icon' => "../../images/oxygen/16x16/apps/kcall.png",   'system' => true),  //_('Telephone Call')
                    array('id' => 'fax',   'value' => 'Facsimile',       'icon' => "../../images/../Sipgate/res/16x16/kfax.png", 'system' => true),  //_('Facsimile')

                ),
                'default' => 'voice'
            )
        ),
        self::ACCOUNT_TYPE => array(
            //_('Account Type')
            'label'                 => 'Account Type',
            //_('Private Accounts are editable only for the creating user. Shared accounts are editable for users with "edit shared" rights')
            'description'           => 'Private Accounts are editable only for the creating user. Shared accounts are editable for users with "edit shared" rights',
            'type'                  => 'keyFieldConfig',
            'options'               => array('recordModel' => 'Sipgate_Model_AccountType'),
            'clientRegistryInclude' => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 'plus', 'value' => 'basic/plus', 'icon' => "../../images/oxygen/16x16/places/user-identity.png", 'system' => true),  //_('basic/plus')
                    array('id' => 'team', 'value' => 'team',       'icon' => "../../images/oxygen/16x16/apps/system-users.png",    'system' => true),  //_('team')
                ),
                'default' => 'shared'
            )
        ),
        self::ACCOUNT_ACCOUNT_TYPE => array(
            //_('Sipgate Account Type')
            'label'                 => 'Sipgate Account Type',
            //_('The type of your account as defined in the contract')
            'description'           => 'The type of your account as defined in the contract',
            'type'                  => 'keyFieldConfig',
            'options'               => array('recordModel' => 'Sipgate_Model_AccountAccountType'),
            'clientRegistryInclude' => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 'private', 'value' => 'private', 'icon' => "../../images/oxygen/16x16/places/user-identity.png", 'system' => true),  //_('private')
                    array('id' => 'shared',     'value' => 'shared',   'icon' => "../../images/oxygen/16x16/apps/system-users.png",    'system' => true),  //_('shared')

                ),
                'default' => 'plus'
            )
        ),
    );

    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Abstract::$_appName
     */
    protected $_appName = 'Sipgate';

    /**
     * holds the instance of the singleton
     *
     * @var Tinebase_Config
     */
    private static $_instance = NULL;

    /**
     * the constructor
     *
     * don't use the constructor. use the singleton
     */
    private function __construct() {
    }

    /**
     * the constructor
     *
     * don't use the constructor. use the singleton
     */
    private function __clone() {
    }

    /**
     * Returns instance of Tinebase_Config
     *
     * @return Tinebase_Config
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Abstract::getProperties()
     */
    public static function getProperties()
    {
        return self::$_properties;
    }
}
