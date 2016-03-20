<?php
/**
 * Tine 2.0
 * 
 * @package     Calendar
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2009-2014 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * Model of an event
 * 
 * Recuring Notes: 
 *  - deleted recurring exceptions are stored in exdate (array of datetimes)
 *  - modified recurring exceptions have their own event with recurid set the uid-dtstart
 *    of the originators event (@see RFC2445)
 *  - as id is unique, each modified recurring event has its own id
 *  - rrule is stored in RCF2445 format
 *  - the rrule_until is redundat to the rrule until property for fast queries
 *  - we don't use rrule count, they are converted to an until
 *  - like always in tine, we save all dates in UTC, but to correctly compute
 *    recurring events, we also save the timezone of the organizer
 *  - despite RFC2445 we have an expicit isAllDayEvent property
 * 
 * @package Calendar
 * @property Tinebase_Record_RecordSet alarms
 * @property Tinebase_DateTime creation_time
 * @property string is_all_day_event
 * @property string originator_tz
 * @property string seq
 * @property string uid
 * @property string etag
 * @property int container_id
 */
class Calendar_Model_Event extends Tinebase_Record_Abstract
{
    const TRANSP_TRANSP        = 'TRANSPARENT';
    const TRANSP_OPAQUE        = 'OPAQUE';
    
    const CLASS_PUBLIC         = 'PUBLIC';
    const CLASS_PRIVATE        = 'PRIVATE';
    //const CLASS_CONFIDENTIAL   = 'CONFIDENTIAL';
    
    const STATUS_CONFIRMED     = 'CONFIRMED';
    const STATUS_TENTATIVE     = 'TENTATIVE';
    const STATUS_CANCELED      = 'CANCELED';
    
    const RANGE_ALL           = 'ALL';
    const RANGE_THIS          = 'THIS';
    const RANGE_THISANDFUTURE = 'THISANDFUTURE';
    
    /**
     * key in $_validators/$_properties array for the filed which 
     * represents the identifier
     * 
     * @var string
     */
    protected $_identifier = 'id';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Calendar';
    
    /**
     * validators
     *
     * @var array
     */
    protected $_validators = array(
        // tine record fields
        'id'                   => array(Zend_Filter_Input::ALLOW_EMPTY => true,  /*'Alnum'*/),
        'container_id'         => array(Zend_Filter_Input::ALLOW_EMPTY => true,  'Int'  ),
        'created_by'           => array(Zend_Filter_Input::ALLOW_EMPTY => true,         ),
        'creation_time'        => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'last_modified_by'     => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'last_modified_time'   => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'is_deleted'           => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'deleted_time'         => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'deleted_by'           => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'seq'                  => array(Zend_Filter_Input::ALLOW_EMPTY => true,  'Int'  ),
        // calendar only fields
        'external_seq'         => array(Zend_Filter_Input::ALLOW_EMPTY => true,  'Int'  ), // external seq for caldav / imip update handling
        'dtend'                => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'transp'               => array(
            Zend_Filter_Input::ALLOW_EMPTY => true,
            array('InArray', array(self::TRANSP_OPAQUE, self::TRANSP_TRANSP))
        ),
        // ical common fields
        'class'                => array(
            Zend_Filter_Input::ALLOW_EMPTY => true,
            array('InArray', array(self::CLASS_PUBLIC, self::CLASS_PRIVATE, /*self::CLASS_CONFIDENTIAL*/))
        ),
        'description'          => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'geo'                  => array(Zend_Filter_Input::ALLOW_EMPTY => true, Zend_Filter_Input::DEFAULT_VALUE => NULL),
        'location'             => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'organizer'            => array(Zend_Filter_Input::ALLOW_EMPTY => false,        ),
        'priority'             => array(Zend_Filter_Input::ALLOW_EMPTY => true, 'Int'   ),
        'status'            => array(
            Zend_Filter_Input::ALLOW_EMPTY => true,
            array('InArray', array(self::STATUS_CONFIRMED, self::STATUS_TENTATIVE, self::STATUS_CANCELED))
        ),
        'summary'              => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'url'                  => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'uid'                  => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        'etag'                 => array(Zend_Filter_Input::ALLOW_EMPTY => true          ),
        // ical common fields with multiple appearance
        //'attach'                => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        'attendee'              => array(Zend_Filter_Input::ALLOW_EMPTY => true         ), // RecordSet of Calendar_Model_Attender
        'alarms'                => array(Zend_Filter_Input::ALLOW_EMPTY => true         ), // RecordSet of Tinebase_Model_Alarm
        'tags'                  => array(Zend_Filter_Input::ALLOW_EMPTY => true         ), // originally categories handled by Tinebase_Tags
        'notes'                 => array(Zend_Filter_Input::ALLOW_EMPTY => true         ), // originally comment handled by Tinebase_Notes
        'attachments'           => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        
        //'contact'               => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        //'related'               => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        //'resources'             => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        //'rstatus'               => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        // ical scheduleable interface fields
        'dtstart'               => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        'recurid'               => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        'base_event_id'         => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        // ical scheduleable interface fields with multiple appearance
        'exdate'                => array(Zend_Filter_Input::ALLOW_EMPTY => true         ), //  array of Tinebase_DateTimeTinebase_DateTime's
        //'exrule'                => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        //'rdate'                 => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        'rrule'                 => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        // calendar helper fields

        'is_all_day_event'      => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        'rrule_until'           => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        'rrule_constraints'     => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        'originator_tz'         => array(Zend_Filter_Input::ALLOW_EMPTY => true         ),
        'mute'                  => array(Zend_Filter_Input::ALLOW_EMPTY => true, Zend_Filter_Input::DEFAULT_VALUE => false      ),

        // relations
        'relations'             => array(Zend_Filter_Input::ALLOW_EMPTY => true, Zend_Filter_Input::DEFAULT_VALUE => NULL),
        'customfields'          => array(Zend_Filter_Input::ALLOW_EMPTY => true, Zend_Filter_Input::DEFAULT_VALUE => array()),
        
        // grant helper fields
        Tinebase_Model_Grants::GRANT_FREEBUSY => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        Tinebase_Model_Grants::GRANT_READ     => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        Tinebase_Model_Grants::GRANT_SYNC     => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        Tinebase_Model_Grants::GRANT_EXPORT   => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        Tinebase_Model_Grants::GRANT_EDIT     => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        Tinebase_Model_Grants::GRANT_DELETE   => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        Tinebase_Model_Grants::GRANT_PRIVATE  => array(Zend_Filter_Input::ALLOW_EMPTY => true),
    );
    
    /**
     * datetime fields
     *
     * @var array
     */
    protected $_datetimeFields = array(
        'creation_time', 
        'last_modified_time', 
        'deleted_time', 
        'completed', 
        'dtstart', 
        'dtend', 
        'exdate',
        //'rdate',
        'rrule_until',
    );
    
    /**
     * name of fields that should be omited from modlog
     *
     * @var array list of modlog omit fields
     */
    protected $_modlogOmitFields = array(
        Tinebase_Model_Grants::GRANT_READ,
        Tinebase_Model_Grants::GRANT_SYNC,
        Tinebase_Model_Grants::GRANT_EXPORT,
        Tinebase_Model_Grants::GRANT_EDIT,
        Tinebase_Model_Grants::GRANT_DELETE,
        Tinebase_Model_Grants::GRANT_PRIVATE,
    );
    
    /**
     * sets record related properties
     * 
     * @param string _name of property
     * @param mixed _value of property
     * @throws Tinebase_Exception_UnexpectedValue
     * @return void
     */
    public function __set($_name, $_value)
    {
        // ensure exdate as array
        if ($_name == 'exdate' && ! empty($_value) && ! is_array($_value) && ! $_value instanceof Tinebase_Record_RecordSet ) {
            $_value = array($_value);
        }
        
        if ($_name == 'attendee' && is_array($_value)) {
            $_value = new Tinebase_Record_RecordSet('Calendar_Model_Attender', $_value);
        }
        
        if ($_name == 'rrule' && is_string($_value) && ! empty($_value)) {
            // normalize rrule
            $_value = new Calendar_Model_Rrule($_value);
            $_value = (string) $_value;
        }
        parent::__set($_name, $_value);
    }
    
    /**
     * the constructor
     * it is needed because we have more validation fields in Calendars
     * 
     * @param mixed $_data
     * @param bool $bypassFilters sets {@see this->bypassFilters}
     * @param bool $convertDates sets {@see $this->convertDates}
     */
    public function __construct($_data = NULL, $_bypassFilters = false, $_convertDates = true)
    {
        $this->_filters['organizer'] = new Zend_Filter_Empty(NULL);
        
        parent::__construct($_data, $_bypassFilters, $_convertDates);
    }
    
    /**
     * (non-PHPdoc)
     * @see Tinebase_Record_Abstract::diff()
     */
    public function diff($record, $omitFields = array())
    {
        $checkRrule = false;
        if (! in_array('rrule', $omitFields)) {
            $omitFields[] = 'rrule';
            $checkRrule = true;
        }
        
        $diff = parent::diff($record, $omitFields);
        
        if ($checkRrule) {
            $ownRrule    = ! $this->rrule instanceof Calendar_Model_Rrule ? Calendar_Model_Rrule::getRruleFromString((string) $this->rrule) : $this->rrule;
            $recordRrule = ! $record->rrule instanceof Calendar_Model_Rrule ? Calendar_Model_Rrule::getRruleFromString($record->rrule) : $record->rrule;
            
            $rruleDiff = $ownRrule->diff($recordRrule);
            
            // don't take small ( < one day) rrule_until changes as diff
            if (
                    $ownRrule->until instanceof Tinebase_DateTime 
                    && (isset($rruleDiff->diff['until']) || array_key_exists('until', $rruleDiff->diff)) && $rruleDiff->diff['until'] instanceof Tinebase_DateTime
                    && abs($rruleDiff->diff['until']->getTimestamp() - $ownRrule->until->getTimestamp()) < 86400
            ){
                $rruleDiffArray = $rruleDiff->diff;
                unset($rruleDiffArray['until']);
                $rruleDiff->diff = $rruleDiffArray;
            }
            
            if (! empty($rruleDiff->diff)) {
                $diffArray = $diff->diff;
                $diffArray['rrule'] = $rruleDiff;
                
                $diff->diff = $diffArray;
            }
        }
        
        return $diff;
    }
    /**
     * add given attendee if not present under given conditions
     * 
     * @param Calendar_Model_Attender  $attendee
     * @param bool                     $ifOrganizer        only add attendee if he's organizer
     * @param bool                     $ifNoOtherAttendee  only add attendee if no other attendee are present
     * @param bool                     $personalOnly       only for personal containers
     * @return Calendar_Model_Attender asserted attendee
     */
    public function assertAttendee($attendee, $ifOrganizer = true, $ifNoOtherAttendee = false, $personalOnly = false)
    {
        if ($personalOnly) {
            try {
                $container = Tinebase_Container::getInstance()->getContainerById($this->container_id);
                if ($container->type != Tinebase_Model_Container::TYPE_PERSONAL) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(
                            __METHOD__ . '::' . __LINE__ . " not adding attendee as container is not personal.");
                    return;
                }
            } catch (Exception $e) {
                Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " cannot get container: $e");
            }
        }
        
        
        if ($ifNoOtherAttendee && $this->attendee instanceof Tinebase_Record_RecordSet && $this->attendee->count() > 0) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(
                    __METHOD__ . '::' . __LINE__ . " not adding attendee as other attendee are present.");
            return;
        }
        
        $assertionAttendee = Calendar_Model_Attender::getAttendee($this->attendee, $attendee);
        
        if (! $assertionAttendee) {
            if ($ifOrganizer && ! $this->isOrganizer($attendee)) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(
                    __METHOD__ . '::' . __LINE__ . " not adding attendee as he is not organizer.");
            }
            
            else {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(
                    __METHOD__ . '::' . __LINE__ . " adding attendee.");
                
                $assertionAttendee = new Calendar_Model_Attender(array(
                    'user_id'   => $attendee->user_id,
                    'user_type' => $attendee->user_type,
                    'status'    => Calendar_Model_Attender::STATUS_ACCEPTED,
                    'role'      => Calendar_Model_Attender::ROLE_REQUIRED
                ));
                
                if (! $this->attendee instanceof Tinebase_Record_RecordSet) {
                    $this->attendee = new Tinebase_Record_RecordSet('Calendar_Model_Attender');
                }
                $this->attendee->addRecord($assertionAttendee);
            }
        }
        
        return $assertionAttendee;
    }
    
    /**
     * returns the original dtstart of a recur series exception event 
     *  -> when the event should have started with no exception
     * 
     * @return Tinebase_DateTime
     */
    public function getOriginalDtStart()
    {
        $origianlDtStart = $this->dtstart instanceof stdClass ? clone $this->dtstart : $this->dtstart;
        
        if ($this->isRecurException()) {
            if ($this->recurid instanceof DateTime) {
                $origianlDtStart = clone $this->recurid;
            } else if (is_string($this->recurid)) {
                $origianlDtStartString = substr($this->recurid, -19);
                if (! Tinebase_DateTime::isDate($origianlDtStartString)) {
                    throw new Tinebase_Exception_InvalidArgument('recurid does not contain a valid original start date');
                }
                
                $origianlDtStart = new Tinebase_DateTime($origianlDtStartString, 'UTC');
            }
        }
        
        return $origianlDtStart;
    }
    
    /**
     * gets translated field name
     * 
     * NOTE: this has to be done explicitly as our field names are technically 
     *       and have no translations
     *       
     * @param string         $_field
     * @param Zend_Translate $_translation
     * @return string
     */
    public static function getTranslatedFieldName($_field, $_translation)
    {
        $t = $_translation;
        switch ($_field) {
            case 'dtstart':           return $t->_('Start');
            case 'dtend':             return $t->_('End');
            case 'transp':            return $t->_('Blocking');
            case 'class':             return $t->_('Classification');
            case 'description':       return $t->_('Description');
            case 'location':          return $t->_('Location');
            case 'organizer':         return $t->_('Organizer');
            case 'priority':          return $t->_('Priority');
            case 'status':            return $t->_('Status');
            case 'summary':           return $t->_('Summary');
            case 'url':               return $t->_('Url');
            case 'rrule':             return $t->_('Recurrance rule');
            case 'is_all_day_event':  return $t->_('Is all day event');
            case 'originator_tz':     return $t->_('Organizer timezone');
            default:                  return $_field;
        }
    }
    
    /**
     * gets translated value
     * 
     * NOTE: This is needed for values like Yes/No, Datetimes, etc.
     * 
     * @param  string           $_field
     * @param  mixed            $_value
     * @param  Zend_Translate   $_translation
     * @param  string           $_timezone
     * @return string
     */
    public static function getTranslatedValue($_field, $_value, $_translation, $_timezone)
    {
        if ($_value instanceof Tinebase_DateTime) {
            $locale = new Zend_Locale($_translation->getAdapter()->getLocale());
            return Tinebase_Translation::dateToStringInTzAndLocaleFormat($_value, $_timezone, $locale, 'datetime', true);
        }
        
        switch ($_field) {
            case 'transp':
                return $_value && $_value == Calendar_Model_Event::TRANSP_TRANSP ? $_translation->_('No') : $_translation->_('Yes');
            case 'organizer':
                if (! $_value instanceof Addressbook_Model_Contact) {
                    $organizer = Addressbook_Controller_Contact::getInstance()->getMultiple($_value, TRUE)->getFirstRecord();
                }
                return $organizer instanceof Addressbook_Model_Contact ? $organizer->n_fileas : '';
            case 'rrule':
                if ($_value) {
                    $rrule = $_value instanceof Calendar_Model_Rrule ? $_value : new Calendar_Model_Rrule($_value);
                    return $rrule->getTranslatedRule($_translation);
                }
            default:
                return $_value;
        }
    }
    
    /**
     * checks event for given grant
     * 
     * @param  string $_grant
     * @return bool
     */
    public function hasGrant($_grant)
    {
        $hasGrant = (isset($this->_properties[$_grant]) || array_key_exists($_grant, $this->_properties)) && (bool)$this->{$_grant};
        
        if ($this->class !== Calendar_Model_Event::CLASS_PUBLIC) {
            $hasGrant &= (
                // private grant
                $this->{Tinebase_Model_Grants::GRANT_PRIVATE} ||
                // I'm organizer
                Tinebase_Core::getUser()->contact_id == ($this->organizer instanceof Addressbook_Model_Contact ? $this->organizer->getId() : $this->organizer) ||
                // I'm attendee
                Calendar_Model_Attender::getOwnAttender($this->attendee)
            );
        }
        
        return $hasGrant;
    }
    
    /**
     * event is an exception of a recur event series
     * 
     * @return boolean
     */
    public function isRecurException()
    {
        return !!$this->recurid;
    }

    /**
     * event is non persistent recur instance
     * 
     * @return boolean
     */
    public function isRecurInstance()
    {
        return (boolean) preg_match('/^fakeid/', $this->getId());
    }
    
    /**
     * sets recurId of this model
     * 
     * @return string recurid which was set
     */
    public function setRecurId($baseEventId)
    {
        if (! ($this->uid && $this->dtstart)) {
            throw new Exception ('uid _and_ dtstart must be set to generate recurid');
        }
        
        // make sure we store recurid in utc
        $dtstart = $this->getOriginalDtStart();
        $dtstart->setTimezone('UTC');
        
        $this->recurid = $this->uid . '-' . $dtstart->get(Tinebase_Record_Abstract::ISO8601LONG);
        $this->base_event_id = $baseEventId;

        return $this->recurid;
    }
    
    /**
     * sets rrule until helper field
     *
     * @return void
     */
    public function setRruleUntil()
    {
        if (empty($this->rrule)) {
            $this->rrule_until = NULL;
        } else {
            $rrule = $this->rrule;
            if (! $this->rrule instanceof Calendar_Model_Rrule) {
                $rrule = new Calendar_Model_Rrule(array());
                $rrule->setFromString($this->rrule);
                $this->rrule = $rrule;
            }
            
            if (isset($rrule->count)) {
                $this->rrule_until = NULL;
                $exdates = $this->exdate;
                $this->exdate = NULL;
                
                $lastOccurrence = Calendar_Model_Rrule::computeNextOccurrence($this, new Tinebase_Record_RecordSet('Calendar_Model_Event'), $this->dtend, $rrule->count -1);
                $this->rrule_until = $lastOccurrence->dtend;
                $this->exdate = $exdates;
            } else {
                // set until to end of day in organizers timezone.
                // NOTE: this is in contrast to the iCal spec which says until should be the
                //       dtstart of the last occurence. But as the client with the name of the
                //       spec sets it to the end of the day, we do it also.
                if ($rrule->until instanceof Tinebase_DateTime && !$this->is_all_day_event) {
                    $rrule->until->setTimezone($this->originator_tz);
                    // NOTE: subSecond cause some clients send 00:00:00 for midnight
                    $rrule->until->subSecond(1)->setTime(23, 59, 59);
                    $rrule->until->setTimezone('UTC');
                }
                
                $this->rrule_until = $rrule->until;
            }
        }
        
        if ($this->rrule_until && $this->rrule_until->getTimeStamp() - $this->dtstart->getTimeStamp() < -1) {
            throw new Tinebase_Exception_Record_Validation('rrule until must not be before dtstart');
        }
    }
    
    /**
     * cleans up data to only contain freebusy infos
     * removes all fields except dtstart/dtend/id/modlog fields
     * 
     * @return boolean TRUE if cleanup took place
     */
    public function doFreeBusyCleanup()
    {
        if ($this->hasGrant(Tinebase_Model_Grants::GRANT_READ)) {
           return FALSE;
        }
        
        $this->_properties = array_intersect_key($this->_properties, array_flip(array(
            'id', 
            'dtstart', 
            'dtend',
            'transp',
            'seq',
            'uid',
            'is_all_day_event',
            'rrule',
            'rrule_until',
            'recurid',
            'exdate',
            'originator_tz',
            'attendee', // if we remove this, we need to adopt attendee resolveing
            'container_id',
            'created_by',
            'creation_time',
            'last_modified_by',
            'last_modified_time',
            'is_deleted',
            'deleted_time',
            'deleted_by',
        )));
        
        return TRUE;
    }
    
    /**
     * sets the record related properties from user generated input.
     * 
     * Input-filtering and validation by Zend_Filter_Input can enabled and disabled
     *
     * @param array $_data            the new data to set
     * @throws Tinebase_Exception_Record_Validation when content contains invalid or missing data
     */
    public function setFromArray(array $_data)
    {
        if (empty($_data['geo'])) {
            $_data['geo'] = NULL;
        }
        
        if (empty($_data['class'])) {
            $_data['class'] = self::CLASS_PUBLIC;
        }
        
        if (empty($_data['priority'])) {
            $_data['priority'] = NULL;
        }
        
        if (empty($_data['status'])) {
            $_data['status'] = self::STATUS_CONFIRMED;
        }
        
        if (isset($_data['container_id']) && is_array($_data['container_id'])) {
            $_data['container_id'] = $_data['container_id']['id'];
        }
        
        if (isset($_data['organizer']) && is_array($_data['organizer'])) {
            $_data['organizer'] = $_data['organizer']['id'];
        }
        
        if (isset($_data['attendee']) && is_array($_data['attendee'])) {
            $_data['attendee'] = new Tinebase_Record_RecordSet('Calendar_Model_Attender', $_data['attendee'], $this->bypassFilters, $this->convertDates);
        }
        
        if (isset($_data['rrule']) && ! empty($_data['rrule']) && ! $_data['rrule'] instanceof Calendar_Model_Rrule) {
            // rrule can be array or string
            $_data['rrule'] = new Calendar_Model_Rrule($_data['rrule'], $this->bypassFilters, $this->convertDates);
        }

        if (isset($_data['rrule_constraints']) && ! empty($_data['rrule_constraints']) && ! $_data['rrule_constraints'] instanceof Calendar_Model_EventFilter) {
            // rrule can be array or string
            $_data['rrule_constraints'] = new Calendar_Model_EventFilter($_data['rrule_constraints']);

        }

        if (isset($_data['alarms']) && is_array($_data['alarms'])) {
            $_data['alarms'] = new Tinebase_Record_RecordSet('Tinebase_Model_Alarm', $_data['alarms'], TRUE, $this->convertDates);
        }
        
        parent::setFromArray($_data);
    }
    
    /**
     * checks if event matches period filter
     * 
     * @param Calendar_Model_PeriodFilter $_period
     * @return boolean
     */
    public function isInPeriod(Calendar_Model_PeriodFilter $_period)
    {
        $result = TRUE;
        
        if ($this->dtend->compare($_period->getFrom()) == -1 || $this->dtstart->compare($_period->getUntil()) == 1) {
            $result = FALSE;
        }
        
        return $result;
    }
    
    /**
     * returns TRUE if comparison detects a resechedule / significant change
     * 
     * @param  Calendar_Model_Event $_event
     * @return bool
     */
    public function isRescheduled($_event)
    {
        $diff = $this->diff($_event)->diff;
        
        return (isset($diff['dtstart']) || array_key_exists('dtstart', $diff))
            || (! $this->is_all_day_event && (isset($diff['dtend']) || array_key_exists('dtend', $diff)))
            || (isset($diff['rrule']) || array_key_exists('rrule', $diff));
    }
    
    /**
     * sets and returns the addressbook entry of the organizer
     * 
     * @return Addressbook_Model_Contact
     */
    public function resolveOrganizer()
    {
        if (! empty($this->organizer) && ! $this->organizer instanceof Addressbook_Model_Contact) {
            $contacts = Addressbook_Controller_Contact::getInstance()->getMultiple($this->organizer, TRUE);
            if (count($contacts)) {
                $this->organizer = $contacts->getFirstRecord();
            }
        }
        
        return $this->organizer;
    }
    
    /**
     * checks if given attendee is organizer of this event
     * 
     * @param Calendar_Model_Attender $_attendee
     */
    public function isOrganizer($_attendee=NULL)
    {
        $organizerContactId = NULL;
        if ($_attendee && in_array($_attendee->user_type, array(Calendar_Model_Attender::USERTYPE_USER, Calendar_Model_Attender::USERTYPE_GROUPMEMBER))) {
            $organizerContactId = $_attendee->user_id instanceof Tinebase_Record_Abstract ? $_attendee->user_id->getId() : $_attendee->user_id;
        } else {
            $organizerContactId = Tinebase_Core::getUser()->contact_id;
        }
        
        return $organizerContactId == ($this->organizer instanceof Tinebase_Record_Abstract ? $this->organizer->getId() : $this->organizer);
    }
    
    /**
     * returns true if organizer is external
     * 
     * @return boolean
     */
    public function hasExternalOrganizer()
    {
        $organizer = $this->resolveOrganizer();
        
        return $organizer instanceof Addressbook_Model_Contact && ! $organizer->account_id;
    }
    
    public function toShortString()
    {
        return $this->summary . '(' . $this->dtstart . ' - ' . $this->dtend . ')';
    }
}
