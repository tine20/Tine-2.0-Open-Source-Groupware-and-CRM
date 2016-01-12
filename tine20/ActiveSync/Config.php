<?php
/**
 * @package     ActiveSync
 * @subpackage  Config
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2012 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * ActiveSync config class
 * 
 * @package     ActiveSync
 * @subpackage  Config
 */
class ActiveSync_Config extends Tinebase_Config_Abstract
{
    /**
     * fields for contact record duplicate check
     * 
     * @var string
     */
    const DEFAULT_POLICY = 'defaultPolicy';

    /**
     * DISABLE_ACCESS_LOG
     *
     * @var string
     */
    const DISABLE_ACCESS_LOG = 'disableaccesslog';

    /**
     * MAX_FILTER_TYPE_EMAIL
     * 
     * @var string
     */
    const MAX_FILTER_TYPE_EMAIL = 'maxfiltertypeemail';

    /**
     * MAX_FILTER_TYPE_CALENDAR
     * 
     * @var string
     */
    const MAX_FILTER_TYPE_CALENDAR = 'maxfiltertypecalendar';

    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Definition::$_properties
     */
    protected static $_properties = array(
        self::DEFAULT_POLICY => array(
        //_('Default policy for new devices')
            'label'                 => 'Default policy for new devices',
        //_('Enter the id of the policy to apply to newly created devices.')
            'description'           => 'Enter the id of the policy to apply to newly created devices.',
            'type'                  => 'string',
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => TRUE,
            'setBySetupModule'      => FALSE,
            'default'               => null,
        ),
        self::DISABLE_ACCESS_LOG => array(
        //_('Disable Access Log')
            'label'                 => 'Disable Access Log creation',
        //_('Disable ActiveSync Access Log creation.')
            'description'           => 'Disable ActiveSync Access Log creation.',
            'type'                  => Tinebase_Config_Abstract::TYPE_BOOL,
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => FALSE,
            'setBySetupModule'      => TRUE,
            'default'               => FALSE,
        ),
    self::MAX_FILTER_TYPE_EMAIL => array(
        //_('Filter timeslot for emails')
            'label'                 => 'Filter timeslot for emails',
        //_('For how long in the past the emails should be synchronized.')
            'description'           => 'For how long in the past the emails should be synchronized.',
            'type'                  => Tinebase_Config_Abstract::TYPE_INT,
            // @todo options is not used yet (only for TYPE_KEYFIELD_CONFIG configs),
            //  but this is helpful to see which values are possible here
            'options'               => array(
                Syncroton_Command_Sync::FILTER_NOTHING,
                Syncroton_Command_Sync::FILTER_6_MONTHS_BACK,
                Syncroton_Command_Sync::FILTER_3_MONTHS_BACK,
                Syncroton_Command_Sync::FILTER_1_MONTH_BACK,
                Syncroton_Command_Sync::FILTER_2_WEEKS_BACK,
                Syncroton_Command_Sync::FILTER_1_WEEK_BACK,
                Syncroton_Command_Sync::FILTER_3_DAYS_BACK,
                Syncroton_Command_Sync::FILTER_1_DAY_BACK
            ),
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => FALSE,
            'setBySetupModule'      => TRUE,
            'default'               => Syncroton_Command_Sync::FILTER_NOTHING,
        ),
        self::MAX_FILTER_TYPE_CALENDAR => array(
        //_('Filter timeslot for events')
            'label'                 => 'Filter timeslot for events',
        //_('For how long in the past the events should be synchronized.')
            'description'           => 'For how long in the past the events should be synchronized.',
            'type'                  => Tinebase_Config_Abstract::TYPE_INT,
            // @todo options is not used yet (only for TYPE_KEYFIELD_CONFIG configs),
            //  but this is helpful to see which values are possible here
            'options'               => array(
                Syncroton_Command_Sync::FILTER_6_MONTHS_BACK,
                Syncroton_Command_Sync::FILTER_3_MONTHS_BACK,
                Syncroton_Command_Sync::FILTER_1_MONTH_BACK,
                Syncroton_Command_Sync::FILTER_2_WEEKS_BACK
            ),
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => FALSE,
            'setBySetupModule'      => TRUE,
            'default'               => Syncroton_Command_Sync::FILTER_6_MONTHS_BACK,
        ),
    );
    
    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Abstract::$_appName
     */
    protected $_appName = 'ActiveSync';
    
    /**
     * holds the instance of the singleton
     *
     * @var Tinebase_Config
     */
    private static $_instance = NULL;
    
    /**
     * server classes
     *
     * @var array
     */
    protected static $_serverPlugins = array(
        'ActiveSync_Server_Plugin' => 50
    );
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() {}
    
    /**
     * don't clone. Use the singleton.
     */
    private function __clone() {}
    
    /**
     * Returns instance of ActiveSync_Config
     *
     * @return ActiveSync_Config
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
