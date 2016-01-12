<?php
/**
 * @package     HumanResources
 * @subpackage  Config
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Alexander Stintzing <a.stintzing@metaways.de>
 * @copyright   Copyright (c) 2012-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * HumanResources config class
 *
 * @package     HumanResources
 * @subpackage  Config
 */
class HumanResources_Config extends Tinebase_Config_Abstract
{
    /**
     * FreeTime Type
     * @var string
     */
    const FREETIME_TYPE = 'freetimeType';

    /**
     * Vacation Status
     * @var string
     */
    const VACATION_STATUS = 'vacationStatus';

    /**
     * Sickness Status
     * @var string
     */
    const SICKNESS_STATUS = 'sicknessStatus';
    
    /**
     * Default Feast Calendar (used for tailoring datepicker)
     * @var string
     */
    const DEFAULT_FEAST_CALENDAR = 'defaultFeastCalendar';
    
    /**
     * Defines the date when vacation booked from last year can't be taken anymore
     * 
     * @var string
     */
    const VACATION_EXPIRES = 'vacationExpires';
    
    /**
     * types for extra free times
     * 
     * @var string
     */
    const EXTRA_FREETIME_TYPE = 'extraFreetimeType';
    
    /**
     * id of (filsystem) container for vacation templates
     *
     * @var string
     */
    const REPORT_TEMPLATES_CONTAINER_ID = 'reportTemplatesContainerId';
    
    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Definition::$_properties
     */
    protected static $_properties = array(
        self::FREETIME_TYPE => array(
            //_('Freetime Type')
            'label'                 => 'Freetime Type',
            //_('Possible free time definitions')
            'description'           => 'Possible free time definitions',
            'type'                  => 'keyFieldConfig',
            'options'               => array('recordModel' => 'HumanResources_Model_FreeTimeType'),
            'clientRegistryInclude' => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 'SICKNESS',             'value' => 'Sickness',           'icon' => 'images/oxygen/16x16/actions/book.png',  'system' => TRUE),  //_('Sickness')
                    array('id' => 'VACATION',             'value' => 'Vacation',           'icon' => 'images/oxygen/16x16/actions/book2.png', 'system' => TRUE),  //_('Vacation')
                ),
                'default' => 'VACATION'
            )
        ),
        self::VACATION_STATUS => array(
            //_('Vacation Status')
            'label'                 => 'Vacation Status',
            //_('Possible vacation status definitions')
            'description'           => 'Possible vacation status definitions',
            'type'                  => 'keyFieldConfig',
            'options'               => array('recordModel' => 'HumanResources_Model_FreeTimeStatus'),
            'clientRegistryInclude' => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 'REQUESTED',  'value' => 'Requested',  'icon' => 'images/oxygen/16x16/actions/mail-mark-unread-new.png', 'system' => TRUE),  //_('Requested')
                    array('id' => 'IN-PROCESS', 'value' => 'In process', 'icon' => 'images/oxygen/16x16/actions/view-refresh.png',         'system' => TRUE),  //_('In process')
                    array('id' => 'ACCEPTED',   'value' => 'Accepted',   'icon' => 'images/oxygen/16x16/actions/ok.png', 'system' => TRUE),  //_('Accepted')
                    array('id' => 'DECLINED',   'value' => 'Declined',   'icon' => 'images/oxygen/16x16/actions/dialog-cancel.png', 'system' => TRUE),  //_('Declined')

                ),
                'default' => 'REQUESTED'
            )
        ),
        self::SICKNESS_STATUS => array(
            //_('Sickness Status')
            'label'                 => 'Sickness Status',
            //_('Possible sickness status definitions')
            'description'           => 'Possible sickness status definitions',
            'type'                  => 'keyFieldConfig',
            'options'               => array('recordModel' => 'HumanResources_Model_FreeTimeStatus'),
            'clientRegistryInclude' => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 'EXCUSED',   'value' => 'Excused',   'icon' => 'images/oxygen/16x16/actions/smiley.png', 'system' => TRUE),  //_('Excused')
                    array('id' => 'UNEXCUSED', 'value' => 'Unexcused', 'icon' => 'images/oxygen/16x16/actions/tools-report-bug.png', 'system' => TRUE),  //_('Unexcused')

                ),
                'default' => 'EXCUSED'
            )
        ),
        self::DEFAULT_FEAST_CALENDAR => array(
            // _('Default Feast Calendar')
            'label'                 => 'Default Feast Calendar',
            // _('Here you can define the default feast calendar used to set feast days and other free days in datepicker')
            'description'           => 'Here you can define the default feast calendar used to set feast days and other free days in datepicker',
            'type'                  => 'container',
            'clientRegistryInclude' => TRUE,
            'setByAdminModule'      => TRUE,
        ),
        self::EXTRA_FREETIME_TYPE => array(
            //_('Extra freetime type')
            'label'                 => 'Extra freetime type',
            //_('Possible extra free time definitions')
            'description'           => 'Possible extra free time definitions',
            'type'                  => 'keyFieldConfig',
            'options'               => array('recordModel' => 'HumanResources_Model_ExtraFreeTimeType'),
            'clientRegistryInclude' => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 'PAYED',     'value' => 'Payed',     'icon' => NULL, 'system' => TRUE),  //_('Payed')
                    array('id' => 'NOT_PAYED', 'value' => 'Not payed', 'icon' => NULL, 'system' => TRUE),  //_('Not payed')
                ),
                'default' => 'PAYED'
            )
        ),
        self::VACATION_EXPIRES => array(
            // _('Vacation expires')
            'label'                 => 'Vacation expires',
            // _('Here you can define the day, when the vacation days taken from last year expires, the format is MM-DD.')
            'description'           => 'Here you can define the day, when the vacation days taken from last year expires, the format is MM-DD.',
            'type'                  => 'string',
            'clientRegistryInclude' => TRUE,
            'setByAdminModule'      => TRUE,
            'default' => '03-15'
        ),
        self::REPORT_TEMPLATES_CONTAINER_ID => array(
        //_('Report Templates Container ID')
            'label'                 => 'Report Templates Container ID',
            'description'           => 'Report Templates Container ID',
            'type'                  => Tinebase_Config_Abstract::TYPE_STRING,
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => FALSE,
            'setBySetupModule'      => FALSE,
        ),
    );

    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Abstract::$_appName
     */
    protected $_appName = 'HumanResources';

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
    
    /**
     * returns the date of vacation expiration for the given year 
     * or for the current year, if no year is given or null, if no expiration is defined
     * 
     * @param string $year
     * @return Tinebase_DateTime|NULL
     */
    public function getVacationExpirationDate($year)
    {
        if (! $year) {
            $year = Tinebase_DateTime::now()->format('Y');
        }
        
        $expires = self::getInstance()->get(self::VACATION_EXPIRES, 0);
        
        if ($expires != 0) {
            $split = explode('-', $expires);
            $date = Tinebase_DateTime::now();
            $date->setDate($year, intval($split[0]), intval($split[1]));
        } else {
            return null;
        }
    }
}
