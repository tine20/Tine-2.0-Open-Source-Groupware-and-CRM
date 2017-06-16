<?php
/**
 * @package     Calendar
 * @subpackage  Config
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2011-2014 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * calendar config class
 * 
 * @package     Calendar
 * @subpackage  Config
 */
class Calendar_Config extends Tinebase_Config_Abstract
{
    /**
     * Fixed Calendars
     * 
     * @var string
     */
    const FIXED_CALENDARS = 'fixedCalendars';
    
    /**
     * Attendee Status Available
     * 
     * @var string
     */
    const ATTENDEE_STATUS = 'attendeeStatus';
    
    /**
     * Attendee Roles Available
     * 
     * @var string
     */
    const ATTENDEE_ROLES = 'attendeeRoles';

    /**
     * Resource Types Available
     *
     * @var string
     */
    const RESOURCE_TYPES = 'resourceTypes';

    /**
     * FreeBusy Types Available
     *
     * @var string
     */
    const FREEBUSY_TYPES = 'freebusyTypes';

    /**
     * Crop days view
     *
     * @var string
     */
    const CROP_DAYS_VIEW = 'daysviewcroptime';

    /**
     * Days view mouse wheel increment
     *
     * @var integer
     */
    const DAYS_VIEW_MOUSE_WHEEL_INCREMENT = 'daysviewwheelincrement';
    
    /**
     * Allow events outside the definition created by the edit dialog
     * 
     * @var string
     */
    const CROP_DAYS_VIEW_ALLOW_ALL_EVENTS = 'daysviewallowallevents';

    /**
     * MAX_FILTER_PERIOD_CALDAV
     * 
     * @var string
     */
    const MAX_FILTER_PERIOD_CALDAV = 'maxfilterperiodcaldav';

    /**
     * MAX_FILTER_PERIOD_CALDAV_SYNCTOKEN
     *
     * @var string
     */
    const MAX_FILTER_PERIOD_CALDAV_SYNCTOKEN = 'maxfilterperiodcaldavsynctoken';

    /**
     * MAX_NOTIFICATION_PERIOD_FROM
     * 
     * @var string
     */
    const MAX_NOTIFICATION_PERIOD_FROM = 'maxnotificationperiodfrom';
    
    /**
     * MAX_JSON_DEFAULT_FILTER_PERIOD_FROM
     * 
     * @var string
     */
    const MAX_JSON_DEFAULT_FILTER_PERIOD_FROM = 'maxjsondefaultfilterperiodfrom';
    
    /**
     * MAX_JSON_DEFAULT_FILTER_PERIOD_UNTIL
     * 
     * @var string
     */
    const MAX_JSON_DEFAULT_FILTER_PERIOD_UNTIL = 'maxjsondefaultfilterperioduntil';
    
    /**
     * DISABLE_EXTERNAL_IMIP
     *
     * @var string
     */
    const DISABLE_EXTERNAL_IMIP = 'disableExternalImip';
    
    /**
     * SKIP_DOUBLE_EVENTS
     *
     * @var string
     */
    const SKIP_DOUBLE_EVENTS = 'skipdoubleevents';

    /**
     * Send attendee mails to users with edit permissions to the added resource
     */
    const RESOURCE_MAIL_FOR_EDITORS = 'resourcemailforeditors';

    /**
     * FEATURE_SPLIT_VIEW
     *
     * @var string
     */
    const FEATURE_SPLIT_VIEW = 'featureSplitView';

    /**
     * FEATURE_YEAR_VIEW
     *
     * @var string
     */
    const FEATURE_YEAR_VIEW = 'featureYearView';

    /**
     * FEATURE_EXTENDED_EVENT_CONTEXT_ACTIONS
     *
     * @var string
     */
    const FEATURE_EXTENDED_EVENT_CONTEXT_ACTIONS = 'featureExtendedEventContextActions';

    /**
     * FEATURE_COLOR_BY
     *
     * @var string
     */
    const FEATURE_COLOR_BY = 'featureColorBy';

    /**
     * EVENT_VIEW
     *
     * @var string
     */
    const EVENT_VIEW = 'eventView';
    
    /**
     * FEATURE_RECUR_EXCEPT
     *
     * @var string
     */
    const FEATURE_RECUR_EXCEPT = 'featureRecurExcept';

    /**
     * @var string
     */
    const TENTATIVE_NOTIFICATIONS = 'tentativeNotifications';

    /**
     * @var string
     */
    const TENTATIVE_NOTIFICATIONS_ENABLED = 'enabled';

    /**
     * @var string
     */
    const TENTATIVE_NOTIFICATIONS_DAYS = 'days';

    /**
     * @var string
     */
    const TENTATIVE_NOTIFICATIONS_FILTER = 'filter';

    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Definition::$_properties
     */
    protected static $_properties = array(
        self::FIXED_CALENDARS => array(
            //_('Fixed Calendars')
            'label'                 => 'Fixed Calendars',
            //_('Calendars always selected regardless of all filter parameters. A valid use case might be to force the display of an certain holiday calendar.')
            'description'           => 'Calendars always selected regardless of all filter parameters. A valid use case might be to force the display of an certain holiday calendar.',
            'type'                  => 'array',
            'contents'              => 'string', // in fact this are ids of Tinebase_Model_Container of app Calendar and we might what to have te ui to autocreate pickers panel here? x-type? -> later
            'clientRegistryInclude' => TRUE
        ),
        self::CROP_DAYS_VIEW => array(
                                   //_('Crop Days')
            'label'                 => 'Crop Days',
                                   //_('Crop calendar view configured start and endtime.')
            'description'           => 'Crop calendar view configured start and endtime.',
            'type'                  => Tinebase_Config_Abstract::TYPE_BOOL,
            'clientRegistryInclude' => true,
            'setByAdminModule'      => false,
            'setBySetupModule'      => false,
            'default'               => false
        
        ),
        self::CROP_DAYS_VIEW_ALLOW_ALL_EVENTS => array(
                                   //_('Crop Days Limit Override')
            'label'                 => 'Crop Days Limit Override',
                                   //_('Allow events outside start and endtime.')
            'description'           => 'Allow events outside start and endtime.',
            'type'                  => Tinebase_Config_Abstract::TYPE_BOOL,
            'clientRegistryInclude' => true,
            'setByAdminModule'      => false,
            'setBySetupModule'      => false,
            'default'               => false
        
        ),
        self::DAYS_VIEW_MOUSE_WHEEL_INCREMENT => array(
                                    //_('Week View Mouse Wheel Increment')
            'label'                 => 'Week View Mouse Wheel Increment',
            //_('Crop calendar view configured start and endtime.')
            'description'           => 'Number of pixels to scroll per mouse wheel',
            'type'                  => Tinebase_Config_Abstract::TYPE_INT,
            'clientRegistryInclude' => true,
            'setByAdminModule'      => false,
            'setBySetupModule'      => false,
            'default'               => 50

        ),
        self::EVENT_VIEW => array(
            //_('Default View for Events')
            'label'                 => 'Default View for Events',
            //_('Default View for Events')
            'description'           => 'Default View for Events ("organizer" or "attendee")',
            'type'                  => Tinebase_Config_Abstract::TYPE_KEYFIELD,
            'options'               => array(
                'records' => array(
                    array('id' => 'attendee',  'value' => 'Attendee'), //_('Attendee')
                    array('id' => 'organizer', 'value' => 'Organizer'), //_('Organizer')
                ),
            ),
            'clientRegistryInclude' => true,
            'setByAdminModule'      => TRUE,
            'setBySetupModule'      => FALSE,
            'default'               => 'attendee',
        ),
        self::ATTENDEE_STATUS => array(
                                   //_('Attendee Status Available')
            'label'                 => 'Attendee Status Available',
                                   //_('Possible event attendee status. Please note that additional attendee status might impact other calendar systems on export or synchronisation.')
            'description'           => 'Possible event attendee status. Please note that additional attendee status might impact other calendar systems on export or synchronisation.',
            'type'                  => Tinebase_Config_Abstract::TYPE_KEYFIELD_CONFIG,
            'options'               => array('recordModel' => 'Calendar_Model_AttendeeStatus'),
            'clientRegistryInclude' => TRUE,
            'setByAdminModule'      => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 'NEEDS-ACTION', 'value' => 'No response', 'icon' => 'images/oxygen/16x16/actions/mail-mark-unread-new.png', 'system' => true), //_('No response')
                    array('id' => 'ACCEPTED',     'value' => 'Accepted',    'icon' => 'images/oxygen/16x16/actions/ok.png',                   'system' => true), //_('Accepted')
                    array('id' => 'DECLINED',     'value' => 'Declined',    'icon' => 'images/oxygen/16x16/actions/dialog-cancel.png',        'system' => true), //_('Declined')
                    array('id' => 'TENTATIVE',    'value' => 'Tentative',   'icon' => 'images/calendar-response-tentative.png',               'system' => true), //_('Tentative')
                ),
                'default' => 'NEEDS-ACTION'
            )
        ),
        self::ATTENDEE_ROLES => array(
                                   //_('Attendee Roles Available')
            'label'                 => 'Attendee Roles Available',
                                   //_('Possible event attendee roles. Please note that additional attendee roles might impact other calendar systems on export or synchronisation.')
            'description'           => 'Possible event attendee roles. Please note that additional attendee roles might impact other calendar systems on export or synchronisation.',
            'type'                  => Tinebase_Config_Abstract::TYPE_KEYFIELD_CONFIG,
            'options'               => array('recordModel' => 'Calendar_Model_AttendeeRole'),
            'clientRegistryInclude' => TRUE,
            'setByAdminModule'      => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 'REQ', 'value' => 'Required', 'system' => true), //_('Required')
                    array('id' => 'OPT', 'value' => 'Optional', 'system' => true), //_('Optional')
                ),
                'default' => 'REQ'
            )
        ),
        self::RESOURCE_TYPES => array(
            //_('Resource Types Available')
            'label'                 => 'Resource Types Available',
            //_('Possible resource types. Please note that additional free/busy types might impact other calendar systems on export or synchronisation.')
            'description'           => 'Possible resource types. Please note that additional free/busy types might impact other calendar systems on export or synchronisation.',
            'type'                  => Tinebase_Config_Abstract::TYPE_KEYFIELD_CONFIG,
            'clientRegistryInclude' => TRUE,
            'setByAdminModule'      => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => 'RESOURCE', 'value' => 'Resource', 'system' => true), //_('Resource')
                    array('id' => 'ROOM', 'value' => 'Room', 'system' => true), //_('Room')
                ),
                'default' => 'RESOURCE'
            )
        ),
        self::FREEBUSY_TYPES => array(
            //_('Free/Busy Types Available')
            'label'                 => 'Free/Busy Types Available',
            //_('Possible free/busy types. Please note that additional free/busy types might impact other calendar systems on export or synchronisation.')
            'description'           => 'Possible free/busy types. Please note that additional free/busy types might impact other calendar systems on export or synchronisation.',
            'type'                  => Tinebase_Config_Abstract::TYPE_KEYFIELD_CONFIG,
            'clientRegistryInclude' => TRUE,
            'setByAdminModule'      => TRUE,
            'default'               => array(
                'records' => array(
                    array('id' => Calendar_Model_FreeBusy::FREEBUSY_FREE, 'value' => 'Free', 'system' => true), //_('Free')
                    array('id' => Calendar_Model_FreeBusy::FREEBUSY_BUSY, 'value' => 'Busy', 'system' => true), //_('Busy')
                    array('id' => Calendar_Model_FreeBusy::FREEBUSY_BUSY_TENTATIVE, 'value' => 'Tentative', 'system' => true), //_('Tentative')
                    array('id' => Calendar_Model_FreeBusy::FREEBUSY_BUSY_UNAVAILABLE, 'value' => 'Unavailable', 'system' => true), //_('Unavailable')
                ),
                'default' => Calendar_Model_FreeBusy::FREEBUSY_BUSY
            )
        ),
        self::MAX_FILTER_PERIOD_CALDAV => array(
        //_('Filter timeslot for CalDAV events')
            'label'                 => 'Filter timeslot for events',
        //_('For how long in the past (in months) the events should be synchronized.')
            'description'           => 'For how long in the past (in months) the events should be synchronized.',
            'type'                  => Tinebase_Config_Abstract::TYPE_INT,
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => FALSE,
            'setBySetupModule'      => TRUE,
            'default'               => 2,
        ),
        self::MAX_FILTER_PERIOD_CALDAV_SYNCTOKEN => array(
            //_('Filter timeslot for CalDAV events with SyncToken')
            'label'                 => 'Filter timeslot for CalDAV events with SyncToken',
            //_('For how long in the past (in months) the events should be synchronized.')
            'description'           => 'For how long in the past (in months) the events should be synchronized.',
            'type'                  => Tinebase_Config_Abstract::TYPE_INT,
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => FALSE,
            'setBySetupModule'      => TRUE,
            'default'               => 100,
        ),
        self::MAX_NOTIFICATION_PERIOD_FROM => array(
        //_('Timeslot for event notifications')
            'label'                 => 'Timeslot for event notifications',
        //_('For how long in the past (in weeks) event notifications should be sent.')
            'description'           => 'For how long in the past (in weeks) event notifications should be sent.',
            'type'                  => Tinebase_Config_Abstract::TYPE_INT,
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => FALSE,
            'setBySetupModule'      => TRUE,
            'default'               => 1, // 1 week is default
        ),
        self::MAX_JSON_DEFAULT_FILTER_PERIOD_FROM => array(
        //_('Default filter period (from) for events fetched via JSON API')
            'label'                 => 'Default filter period (from) for events fetched via JSON API',
        //_('For how long in the past (in months) the events should be fetched.')
            'description'           => 'For how long in the past (in months) the events should be fetched.',
            'type'                  => Tinebase_Config_Abstract::TYPE_INT,
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => FALSE,
            'setBySetupModule'      => TRUE,
            'default'               => 0,
        ),
        self::MAX_JSON_DEFAULT_FILTER_PERIOD_UNTIL => array(
        //_('Default filter period (until) for events fetched via JSON API')
            'label'                 => 'Default filter period (until) for events fetched via JSON API',
        //_('For how long in the future (in months) the events should be fetched.')
            'description'           => 'For how long in the future (in months) the events should be fetched.',
            'type'                  => Tinebase_Config_Abstract::TYPE_INT,
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => FALSE,
            'setBySetupModule'      => TRUE,
            'default'               => 1,
        ),
        self::DISABLE_EXTERNAL_IMIP => array(
        //_('Disable iMIP for external organizers')
            'label'                 => 'Disable iMIP for external organizers',
        //_('Disable iMIP for external organizers')
            'description'           => 'Disable iMIP for external organizers',
            'type'                  => Tinebase_Config_Abstract::TYPE_BOOL,
            'clientRegistryInclude' => false,
            'setByAdminModule'      => true,
            'setBySetupModule'      => true,
            'default'               => false,
        ),
        self::SKIP_DOUBLE_EVENTS => array(
            //_('(CalDAV) Skip double events from personal or shared calendar')
            'label'                 => '(CalDAV) Skip double events from personal or shared calendar',
            //_('(CalDAV) Skip double events from personal or shared calendar ("personal" > Skip events from personal calendar or "shared" > Skip events from shared calendar)')
            'description'           => '(CalDAV) Skip double events from personal or shared calendar ("personal" > Skip events from personal calendar or "shared" > Skip events from shared calendar)',
            'type'                  => Tinebase_Config_Abstract::TYPE_STRING,
            'clientRegistryInclude' => FALSE,
            'setByAdminModule'      => FALSE,
            'setBySetupModule'      => FALSE,
            'default'               => '',
        ),
        self::RESOURCE_MAIL_FOR_EDITORS => array(
            //_('Send notifications to every user with edit permissions of the added resources')
            'label'                 => 'Send notifications to every user with edit permissions of the added resources',
            'description'           => 'Send notifications to every user with edit permissions of the added resources',
            'type'                  => Tinebase_Config_Abstract::TYPE_BOOL,
            'clientRegistryInclude' => false,
            'setBySetupModule'      => false,
            'setByAdminModule'      => true,
            'default'               => false
        ),
        self::ENABLED_FEATURES => array(
            //_('Enabled Features')
            'label'                 => 'Enabled Features',
            //_('Enabled Features in Calendar Application.')
            'description'           => 'Enabled Features in Calendar Application.',
            'type'                  => 'object',
            'class'                 => 'Tinebase_Config_Struct',
            'clientRegistryInclude' => TRUE,
            'content'               => array(
                self::FEATURE_SPLIT_VIEW => array(
                    'label'         => 'Calendar Split View', //_('Calendar Split View')
                    'description'   => 'Split day and week views by attendee', //_('Split day and week views by attendee')
                    'type'          => Tinebase_Config_Abstract::TYPE_BOOL,
                ),
                self::FEATURE_YEAR_VIEW => array(
                    'label'         => 'Calendar Year View', //_('Calendar Year View')
                    'description'   => 'Adds year view to Calendar', //_('Adds year view to Calendar')
                    'type'          => Tinebase_Config_Abstract::TYPE_BOOL,
                ),
                self::FEATURE_EXTENDED_EVENT_CONTEXT_ACTIONS => array(
                    'label'         => 'Calendar Extended Context Menu Actions', //_('Calendar Extended Context Menu Actions')
                    'description'   => 'Adds extended actions to event context menus', //_('Adds extended actions to event context menus')
                    'type'          => Tinebase_Config_Abstract::TYPE_BOOL,
                ),
                self::FEATURE_COLOR_BY => array(
                    'label'         => 'Color Events By', //_('Color Events By')
                    'description'   => 'Choose event color by different criteria', //_('Choose event color by different criteria')
                    'type'          => Tinebase_Config_Abstract::TYPE_BOOL,
                ),
                self::FEATURE_RECUR_EXCEPT => array(
                    'label'         => 'Recur Events Except', //_('Recur Events Except')
                    'description'   => 'Recur Events except on certain dates', //_('Recur Events except on certain dates')
                    'type'          => Tinebase_Config_Abstract::TYPE_BOOL,
                ),
            ),
            'default'               => array(
                self::FEATURE_SPLIT_VIEW                        => true,
                self::FEATURE_YEAR_VIEW                         => false,
                self::FEATURE_EXTENDED_EVENT_CONTEXT_ACTIONS    => true,
                self::FEATURE_COLOR_BY                          => true,
                self::FEATURE_RECUR_EXCEPT                      => false,
            ),
        ),
        self::TENTATIVE_NOTIFICATIONS => array(
            'label'                 => 'Send Tentative Notifications', //_('Send Tentative Notifications')
            'description'           => 'Send notifications to event organiziers of events that are tentative certain days before event is due', //_('Send notifications to event organiziers of events that are tentative certain days before event is due')
            'type'                  => 'object',
            'class'                 => 'Tinebase_Config_Struct',
            'clientRegistryInclude' => TRUE,
            'setBySetupModule'      => false,
            'setByAdminModule'      => true,
            'content'               => array(
                self::TENTATIVE_NOTIFICATIONS_ENABLED   => array(
                    'label'         => 'Enabled', //_('Enabled')
                    'description'   => 'Enabled', //_('Enabled')
                    'type'          => Tinebase_Config_Abstract::TYPE_BOOL,
                    'default'       => false,
                ),
                self::TENTATIVE_NOTIFICATIONS_DAYS      => array(
                    'label'         => 'Days Before Due Date', //_('Days Before Due Date')
                    'description'   => 'How many days before the events due date to start send notifications.', //_('How many days before the events due date to start send notifications.')
                    'type'          => Tinebase_Config_Abstract::TYPE_INT,
                    'default'       => 5,
                ),
                self::TENTATIVE_NOTIFICATIONS_FILTER    => array(
                    'label'         => 'Additional Filter', //_('Additional Filter')
                    'description'   => 'Additional filter to limit events notifications should be send for.', //_('Additional filter to limit events notifications should be send for.')
                    'type'          => Tinebase_Config_Abstract::TYPE_STRING,
                    'default'       => NULL,
                ),
            ),
            'default'               => array(),
        ),
    );
    
    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Abstract::$_appName
     */
    protected $_appName = 'Calendar';
    
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
