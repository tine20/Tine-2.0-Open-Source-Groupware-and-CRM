<?php
/**
 * @package     Crm
 * @subpackage  Config
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2012-2015 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * Crm config class
 * 
 * @package     Crm
 * @subpackage  Config
 */
class Crm_Config extends Tinebase_Config_Abstract
{
    /**
     * lead states available
     *
     * @var string
     */
    const LEAD_STATES = 'leadstates';

    /**
     * lead sources available
     *
     * @var string
     */
    const LEAD_SOURCES = 'leadsources';

    /**
     * lead types available
     *
     * @var string
     */
    const LEAD_TYPES = 'leadtypes';

    /**
     * lead import feature
     *
     * @var string
     */
    const FEATURE_LEAD_IMPORT = 'featureLeadImport';

    /**
     * lead import auto task
     *
     * @var string
     */
    const LEAD_IMPORT_AUTOTASK = 'leadImportAutoTask';

    /**
     * lead import notification mail
     *
     * @var string
     */
    const LEAD_IMPORT_NOTIFICATION = 'leadImportNotification';

    /**
     * fields for lead record duplicate check
     *
     * @var string
     */
    const LEAD_DUP_FIELDS = 'leadDupFields';

    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Definition::$_properties
     */
    protected static $_properties = array(
        self::LEAD_STATES => array(
            //_('Lead States Available')
            'label'                 => 'Lead States Available',
            //_('Possible lead status, with their associated turnover probabilities. If a status is flagged, leads with this status are treated as closed.')
            'description'           => 'Possible lead status, with their associated turnover probabilities. If a status is flagged, leads with this status are treated as closed.',
            'type'                  => Tinebase_Config_Abstract::TYPE_KEYFIELD_CONFIG,
            'options'               => array('recordModel' => 'Crm_Model_LeadState'),
            'clientRegistryInclude' => TRUE,
            'setByAdminModule'      => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 1, 'value' => 'open',                  'probability' => 0,     'endslead' => 0), //_('open')
                    array('id' => 2, 'value' => 'contacted',             'probability' => 10,    'endslead' => 0), //_('contacted')
                    array('id' => 3, 'value' => 'waiting for feedback',  'probability' => 30,    'endslead' => 0), //_('waiting for feedback')
                    array('id' => 4, 'value' => 'quote sent',            'probability' => 50,    'endslead' => 0), //_('quote sent')
                    array('id' => 5, 'value' => 'accepted',              'probability' => 100,   'endslead' => 1), //_('accepted')
                    array('id' => 6, 'value' => 'lost',                  'probability' => 0,     'endslead' => 1), //_('lost')
                ),
                'default' => 1
            )
        ),

        self::LEAD_SOURCES => array(
            //_('Lead Sources Available')
            'label'                 => 'Lead Sources Available',
            //_('Possible lead sources. If a source is flagged as archived, leads of this source are treated as closed.')
            'description'           => '\'Possible lead sources. If a source is flagged as archived, leads of this source are treated as closed.',
            'type'                  => Tinebase_Config_Abstract::TYPE_KEYFIELD_CONFIG,
            'options'               => array('recordModel' => 'Crm_Model_LeadSource'),
            'clientRegistryInclude' => TRUE,
            'setByAdminModule'      => TRUE,
            'default'               => array (
                'records' => array(
                    array('id' => 1, 'value' => 'Market'),
                    array('id' => 2, 'value' => 'Email'),
                    array('id' => 3, 'value' => 'Telephone'),
                    array('id' => 4, 'value' => 'Website'),
                ),
                'default' => 1
            )
        ),

        self::LEAD_TYPES => array(
            //_('Lead Types Available')
            'label'                 => 'Lead Types Available',
            //_('Possible lead types.')
            'description'           => 'Possible lead types.',
            'type'                  => Tinebase_Config_Abstract::TYPE_KEYFIELD_CONFIG,
//            'options'               => array('recordModel' => 'Crm_Model_LeadType'),
            'clientRegistryInclude' => TRUE,
            'setByAdminModule'      => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 1, 'value' => 'Customer'),   //_('Customer')
                    array('id' => 2, 'value' => 'Partner'),    //_('Partner')
                    array('id' => 3, 'value' => 'Reseller'),   //_('Reseller')
                ),
                'default' => 1
            )
        ),

        /**
         * enabled Crm features
         */
        self::ENABLED_FEATURES => array(
            //_('Enabled Features')
            'label'                 => 'Enabled Features',
            //_('Enabled Features in Crm Application.')
            'description'           => 'Enabled Features in Crm Application.',
            'type'                  => 'object',
            'class'                 => 'Tinebase_Config_Struct',
            'clientRegistryInclude' => TRUE,
            'content'               => array(
                self::FEATURE_LEAD_IMPORT => array(
                    'label'         => 'Lead Import', //_('Lead Import')
                    'description'   => 'Lead Import',
                ),
            ),
            'default'               => array(
                self::FEATURE_LEAD_IMPORT => true,
            ),
        ),
        self::LEAD_IMPORT_AUTOTASK => array(
            //_('Add new task on lead import')
            'label'                 => 'Add new task on lead import',
            //_('Automatically creates a task for the responsible person if a new lead is imported.')
            'description'           => 'Automatically creates a task for the responsible person if a new lead is imported.',
            'type'                  => 'boolean',
            'clientRegistryInclude' => false,
            'default'               => false,
        ),
        self::LEAD_IMPORT_NOTIFICATION => array(
            //_('Send notification email on lead import')
            'label'                 => 'Send notification email on lead import',
            //_('Sends an email to all responsible persons for the imported leads.')
            'description'           => 'Sends an email to all responsible persons for the imported leads.',
            'type'                  => 'boolean',
            'clientRegistryInclude' => false,
            'default'               => false,
        ),
        self::LEAD_DUP_FIELDS => array(
            //_('Lead duplicate check fields')
            'label'                 => 'Lead duplicate check fields',
            //_('These fields are checked when a new lead is created. If a record with the same data in the fields is found, a duplicate exception is thrown.')
            'description'           => 'These fields are checked when a new lead is created. If a record with the same data in the fields is found, a duplicate exception is thrown.',
            'type'                  => 'array',
            'contents'              => 'array',
            'clientRegistryInclude' => TRUE,
            'default'               => array('lead_name'),
        ),
    );
    
    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Abstract::$_appName
     */
    protected $_appName = 'Crm';
    
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
    private function __construct() {}
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __clone() {}
    
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
