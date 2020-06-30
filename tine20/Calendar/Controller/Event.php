<?php
/**
 * Tine 2.0
 * 
 * @package     Calendar
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2010-2019 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * Calendar Event Controller
 * 
 * In the calendar application, the container grants concept is slightly extended:
 *  1. GRANTS for events are not only based on the events "calendar" (technically 
 *     a container) but additionally a USER gets implicit grants for an event if 
 *     he is ATTENDER (+READ GRANT) or ORGANIZER (+READ,EDIT GRANT).
 *  2. ATTENDER which are invited to a certain "event" can assign the "event" to
 *     one of their personal calenders as "display calendar" (technically personal 
 *     containers they are admin of). The "display calendar" of an ATTENDER is
 *     stored in the attendee table.  Each USER has a default calendar, as 
 *     PREFERENCE,  all invitations are assigned to.
 *  3. The "effective GRANT" a USER has on an event (read/update/delete/...) is the 
 *     maximum GRANT of the following sources: 
 *      - container: GRANT the USER has to the calender of the event
 *      - implicit:  Additional READ GRANT for an attender and READ,EDIT
 *                   GRANT for the organizer.
*       - inherited: FREEBUSY, READ, PRIVATE, SYNC, EXPORT can be inherited
*                    from the GRANTS USER has to the a display calendar
 * 
 * When Applying/Asuring grants, we have to deal with two differnt situations:
 *  A: Check: Check individual grants on a event (record) basis.
 *            This is required for create/update/delete actions and done by 
 *            this controllers _checkGrant method.
 *  B: Seach: From the grants perspective this is a multi step process
 *            1. fetch all records with appropriate grants from backend
 *            2. cleanup records user has only free/busy grant for
 * 
 *  NOTE: To empower the client for enabling/disabling of actions based on the 
 *        grants a user has to an event, we need to compute the "effective GRANT"
 *        for read/search operations.
 *                  
 * Case A is not critical, as the amount of data is low. 
 * Case B however is the hard one, as lots of events and calendars may be
 * involved.
 * 
 * NOTE: the backend always fetches full records for grant calculations.
 *       searching ids only does not hlep with performance
 * 
 * @package Calendar
 */
class Calendar_Controller_Event extends Tinebase_Controller_Record_Abstract implements Tinebase_Controller_Alarm_Interface
{
    /**
     * @var boolean
     * 
     * just set is_delete=1 if record is going to be deleted
     */
    protected $_purgeRecords = FALSE;
    
    /**
     * send notifications?
     *
     * @var boolean
     */
    protected $_sendNotifications = TRUE;

    /**
     * skip scheduling adoptions for exdates/rrule
     *
     * @var bool $_skipRecurAdoptions
     */
    protected $_skipRecurAdoptions = false;

    /**
     * @see Tinebase_Controller_Record_Abstract
     * 
     * @var boolean
     */
    protected $_resolveCustomFields = TRUE;

    /**
     * @var Calendar_Model_Attender
     */
    protected $_calendarUser = NULL;

    /**
     * @var bool
     */
    protected $_keepAttenderStatus = false;

    /**
     * @var Calendar_Controller_Event
     */
    private static $_instance = NULL;
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct()
    {
        $this->_applicationName = 'Calendar';
        $this->_modelName       = 'Calendar_Model_Event';
        $this->_backend         = new Calendar_Backend_Sql();

        try {
            // set default CU
            $this->setCalendarUser(new Calendar_Model_Attender(array(
                'user_type' => Calendar_Model_Attender::USERTYPE_USER,
                'user_id' => Calendar_Controller_MSEventFacade::getCurrentUserContactId()
            )));
        } catch (Tinebase_Exception_NotFound $tenf) {
            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                . " User might not have a contact (visibility: hidden) - exception: " . $tenf->getMessage());
        }
    }

    /**
     * don't clone. Use the singleton.
     */
    private function __clone() 
    {
        
    }
    
    /**
     * singleton
     *
     * @return Calendar_Controller_Event
     */
    public static function getInstance() 
    {
        if (self::$_instance === null) {
            self::$_instance = new Calendar_Controller_Event();
        }
        return self::$_instance;
    }

    public static function unsetInstance()
    {
        self::$_instance = null;
    }

    /**
     * sets current calendar user
     *
     * @param Calendar_Model_Attender $_calUser
     * @return Calendar_Model_Attender oldUser
     */
    public function setCalendarUser(Calendar_Model_Attender $_calUser)
    {
        if (! in_array($_calUser->user_type, array(Calendar_Model_Attender::USERTYPE_USER, Calendar_Model_Attender::USERTYPE_GROUPMEMBER))) {
            throw new Tinebase_Exception_UnexpectedValue('Calendar user must be a contact');
        }
        $oldUser = $this->_calendarUser;
        $this->_calendarUser = $_calUser;

        return $oldUser;
    }

    /**
     * get current calendar user
     *
     * @return Calendar_Model_Attender
     */
    public function getCalendarUser()
    {
        return $this->_calendarUser;
    }

    /**
     * checks if all attendee of given event are not busy for given event
     * 
     * @param Calendar_Model_Event $_event
     * @return void
     * @throws Calendar_Exception_AttendeeBusy
     */
    public function checkBusyConflicts($_event)
    {
        $ignoreUIDs = !empty($_event->uid) ? array($_event->uid) : array();
        
        if ($_event->transp == Calendar_Model_Event::TRANSP_TRANSP || empty($_event->attendee)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . " Skipping free/busy check because event is transparent or has no attendee");
            return;
        }

        $periods = $this->getBlockingPeriods($_event, array(
            'from'  => $_event->dtstart,
            'until' => $_event->dtstart->getClone()->addMonth(2)
        ));

        $fbInfo = $this->getFreeBusyInfo($periods, $_event->attendee, $ignoreUIDs);
        
        if (count($fbInfo) > 0) {
            $busyException = new Calendar_Exception_AttendeeBusy();
            $busyException->setFreeBusyInfo($fbInfo);
            
            Calendar_Model_Attender::resolveAttendee($_event->attendee, /* resolve_display_containers = */ false);
            $busyException->setEvent($_event);
            
            throw $busyException;
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . " Free/busy check: no conflict found");
    }
    
    /**
     * add one record
     *
     * @param   Tinebase_Record_Interface $_record
     * @param   bool                      $_checkBusyConflicts
     * @return  Calendar_Model_Event
     * @throws  Tinebase_Exception_AccessDenied
     * @throws  Tinebase_Exception_Record_Validation
     */
    public function create(Tinebase_Record_Interface $_record, $_checkBusyConflicts = FALSE, $skipEvent = false)
    {
        if (Tinebase_Core::isFilesystemAvailable()) {
            // fill stat cache to avoid deadlocks. Needs to happen outside a transaction
            $path = Tinebase_FileSystem_RecordAttachments::getInstance()->getRecordAttachmentBasePath($_record);
            Tinebase_FileSystem::getInstance()->fileExists($path);
        }

        try {
            $db = $this->_backend->getAdapter();
            $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($db);
            
            $this->_inspectEvent($_record, $skipEvent);
            
            // we need to resolve groupmembers before free/busy checking
            Calendar_Model_Attender::resolveGroupMembers($_record->attendee);
            
            if ($_checkBusyConflicts) {
                // ensure that all attendee are free
                $this->checkBusyConflicts($_record);
            }
            
            $sendNotifications = $this->_sendNotifications;
            $this->_sendNotifications = FALSE;
            
            $createdEvent = parent::create($_record);
            
            $this->_sendNotifications = $sendNotifications;
            
            Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
        } catch (Exception $e) {
            Tinebase_TransactionManager::getInstance()->rollBack();
            throw $e;
        }
        
        // send notifications
        $createdEvent->mute = $_record->mute;
        if ($this->_sendNotifications) {
            $this->doSendNotifications($createdEvent, Tinebase_Core::getUser(), 'created');
        }        

        return $createdEvent;
    }
    
    /**
     * inspect creation of one record (after create)
     *
     * @param   Tinebase_Record_Interface $_createdRecord
     * @param   Tinebase_Record_Interface $_record
     * @return  void
     */
    protected function _inspectAfterCreate($_createdRecord, Tinebase_Record_Interface $_record)
    {
        $this->_saveAttendee($_record, $_createdRecord);
    }
    
    /**
     * deletes a recur series
     *
     * @param  Calendar_Model_Event $_recurInstance
     * @return void
     */
    public function deleteRecurSeries($_recurInstance)
    {
        $baseEvent = $this->getRecurBaseEvent($_recurInstance);
        $this->delete($baseEvent->getId());
    }
    
    /**
     * Gets all entries
     *
     * @param string $_orderBy Order result by
     * @param string $_orderDirection Order direction - allowed are ASC and DESC
     * @throws Tinebase_Exception_InvalidArgument
     * @return Tinebase_Record_RecordSet
     */
    public function getAll($_orderBy = 'id', $_orderDirection = 'ASC') 
    {
        throw new Tinebase_Exception_NotImplemented('not implemented');
    }

    /**
     * returns period filter expressing blocking times caused by given event
     *
     * @param  Calendar_Model_Event         $event
     * @param  array                        $checkPeriod array(
            "from"  => DateTime  (defaults to dtstart)
            "until" => DateTime  (defaults to dtstart + 2 years)
            "max"   => Integer   (defaults to 25)
        )
     * @param boolean                       $returnRawData
     * @return Calendar_Model_EventFilter|array
     */
    public function getBlockingPeriods($event, $checkPeriod = array(), $returnRawData = false)
    {
        $eventSet = new Tinebase_Record_RecordSet('Calendar_Model_Event', array($event));

        if (! empty($event->rrule) && ! $event->recurid) {
            try {
                $eventSet->merge($this->getRecurExceptions($event, true));
            } catch (Tinebase_Exception_AccessDenied $e) {
                // it's ok, if we dont see the (base)event, we dont see the exceptions as well
            } catch (Tinebase_Exception_NotFound $e) {
                // it's ok, event is not exising yet so we don't have exceptions as well
            }

            $from = isset($checkPeriod['from']) ? $checkPeriod['from'] : clone $event->dtstart;
            $until = isset($checkPeriod['until']) ? $checkPeriod['until'] : $from->getClone()->addMonth(24);
            Calendar_Model_Rrule::mergeRecurrenceSet($eventSet, $from, $until);
        }

        $periodFilters = [];
        foreach ($eventSet as $candidate) {
            if ($candidate->transp != Calendar_Model_Event::TRANSP_TRANSP && !$candidate->is_deleted) {
                $periodFilters[] = $returnRawData ? [
                    'from' => $candidate->dtstart,
                    'until' => $candidate->dtend,
                ] : [
                    'field' => 'period',
                    'operator' => 'within',
                    'value' => [
                        'from' => $candidate->dtstart,
                        'until' => $candidate->dtend,
                    ],
                ];
            }
        }

        return $returnRawData ? $periodFilters :
            new Calendar_Model_EventFilter($periodFilters, Tinebase_Model_Filter_FilterGroup::CONDITION_OR);
    }

    /**
     * returns conflicting periods
     *
     * @param Calendar_Model_EventFilter $periodCandidates
     * @param Calendar_Model_EventFilter $conflictCriteria
     * @param bool                       $getAll
     * @return array
     */
    public function getConflictingPeriods($periodCandidates, $conflictCriteria, $getAll=false)
    {
        $conflictFilter = clone $conflictCriteria;
        $conflictFilter->addFilterGroup($periodCandidates);

        // NOTE: we need attendee in conflictingPeriods
        $doContainerAclChecks = $this->doContainerACLChecks(false);
        $conflictCandidates = $this->search($conflictFilter);
        $this->doContainerACLChecks($doContainerAclChecks);

        $from = $until = false;
        foreach ($periodCandidates as $periodFilter) {
            $period = $periodFilter->getValue();
            $from = $from ? min($from, $period['from']) : $period['from'];
            $until = $until ?  max($until, $period['until']) : $period['until'];
        }
        if ($from instanceof DateTime) {
            // NOTE: $periodCandidates might be empty e.g. transparent event
            Calendar_Model_Rrule::mergeRecurrenceSet($conflictCandidates, $from, $until);
        }

        $conflicts = array();
        foreach ($periodCandidates as $periodFilter) {
            $period = $periodFilter->getValue();

            foreach($conflictCandidates as $event) {
                if ($event->dtstart->isEarlier($period['until']) && $event->dtend->isLater($period['from'])) {
                    $conflicts[] = array(
                        'from' => $period['from'],
                        'until' => $period['until'],
                        'event' => $event
                    );

                    if (! $getAll) {
                        break;
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param Tinebase_Record_RecordSet $_records
     * @return array
     */
    public function mergeFreeBusyInfo(Tinebase_Record_RecordSet $_records)
    {
        $_records->sort('dtstart');
        $result = array();
        /** @var Calendar_Model_Event $event */
        foreach($_records as $event) {
            foreach($result as $key => &$period) {
                if ($event->dtstart->isEarlierOrEquals($period['dtend'])) {
                    if ($event->dtend->isLaterOrEquals($period['dtstart'])) {
                        if ($event->dtstart->isEarlier($period['dtstart'])) {
                            $period['dtstart'] = clone $event->dtstart;
                        }
                        if ($event->dtend->isLater($period['dtend'])) {
                            $period['dtend'] = clone $event->dtend;
                        }
                        continue 2;
                    } else {
                        throw new Tinebase_Exception_UnexpectedValue('record set sort by dtstart did not work!');
                    }
                }
            }
            $result[] = array(
                'dtstart' => $event->dtstart,
                'dtend' => $event->dtend
            );
        }

        return $result;
    }

    /**
     * returns freebusy information for given period and given attendee
     * 
     * @todo merge overlapping events to one freebusy entry
     * 
     * @param  Calendar_Model_EventFilter                           $_periods
     * @param  Tinebase_Record_RecordSet                            $_attendee
     * @param  array                                                $_ignoreUIDs
     * @return Tinebase_Record_RecordSet of Calendar_Model_FreeBusy
     */
    public function getFreeBusyInfo($_periods, $_attendee, $_ignoreUIDs = array())
    {
        $fbInfoSet = new Tinebase_Record_RecordSet('Calendar_Model_FreeBusy');
        
        // map groupmembers to users
        $attendee = clone $_attendee;
        $groupmembers = $attendee->filter('user_type', Calendar_Model_Attender::USERTYPE_GROUPMEMBER);
        $groupmembers->user_type = Calendar_Model_Attender::USERTYPE_USER;
        /*$groups = $attendee->filter('user_type', Calendar_Model_Attender::USERTYPE_GROUP);
        $attendee->removeRecords($groups);
        /** @var Calendar_Model_Attender $group *
        foreach($groups as $group) {
            $group = Tinebase_Group::getInstance()->getGroupById($group->user_id);

            // fetch list only if list_id is not NULL, otherwise we get back an empty list object
            if (!empty($group->list_id)) {
                $contactList = Addressbook_Controller_List::getInstance()->get($group->list_id);
                foreach ($contactList->members as $member) {
                    $attendee->addRecord(new Calendar_Model_Attender(array(
                        'user_id' => $member,
                        'user_type' => Calendar_Model_Attender::USERTYPE_USER
                    ), true));
                }
            }
        }*/

        $conflictCriteria = new Calendar_Model_EventFilter(array(
            array('field' => 'attender', 'operator' => 'in',     'value' => $attendee),
            array('field' => 'transp',   'operator' => 'equals', 'value' => Calendar_Model_Event::TRANSP_OPAQUE)
        ));

        $conflictingPeriods = $this->getConflictingPeriods($_periods, $conflictCriteria, true);

        // create a typemap
        $typeMap = array();
        foreach ($attendee as $attender) {
            if (! isset($typeMap[$attender['user_type']])) {
                $typeMap[$attender['user_type']] = array();
            }
            if (is_object($attender['user_id'])) {
                $attender['user_id'] = $attender['user_id']->getId();
            }
            $typeMap[$attender['user_type']][$attender['user_id']] = array();
        }
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . ' ' . __LINE__
            . ' value: ' . print_r($typeMap, true));

        // fetch resources to get freebusy type
        // NOTE: we could add busy_type to attendee later
        if (isset($typeMap[Calendar_Model_Attender::USERTYPE_RESOURCE])) {
            $resources = Calendar_Controller_Resource::getInstance()->getMultiple(array_keys($typeMap[Calendar_Model_Attender::USERTYPE_RESOURCE]), true);
        }

        $processedEventIds = array();

        foreach ($conflictingPeriods as $conflictingPeriod) {
            $event = $conflictingPeriod['event'];

            // one event may conflict multiple periods
            if (isset($processedEventIds[$event->getId()])) {
                continue;
            }

            $processedEventIds[$event->getId()] = true;

            // skip events with ignoreUID
            if (in_array($event->uid, $_ignoreUIDs)) {
                continue;
            }

            // map groupmembers to users
            $groupmembers = $event->attendee->filter('user_type', Calendar_Model_Attender::USERTYPE_GROUPMEMBER);
            $groupmembers->user_type = Calendar_Model_Attender::USERTYPE_USER;

            foreach ($event->attendee as $attender) {
                // skip declined/transp events
                if ($attender->status == Calendar_Model_Attender::STATUS_DECLINED ||
                    $attender->transp == Calendar_Model_Event::TRANSP_TRANSP) {
                    continue;
                }

                if ((isset($typeMap[$attender->user_type]) || array_key_exists($attender->user_type, $typeMap)) && (isset($typeMap[$attender->user_type][$attender->user_id]) || array_key_exists($attender->user_id, $typeMap[$attender->user_type]))) {
                    $type = Calendar_Model_FreeBusy::FREEBUSY_BUSY;

                    if ($attender->user_type == Calendar_Model_Attender::USERTYPE_RESOURCE) {
                        $resource = $resources->getById($attender->user_id);
                        if ($resource) {
                            $type = $resource->busy_type;
                        }
                    }

                    $fbInfo = new Calendar_Model_FreeBusy(array(
                        'user_type' => $attender->user_type,
                        'user_id'   => $attender->user_id,
                        'dtstart'   => clone $event->dtstart,
                        'dtend'     => clone $event->dtend,
                        'type'      => $type,
                    ), true);

                    if ($event->{Tinebase_Model_Grants::GRANT_READ}) {
                        $fbInfo->event = clone $event;
                        $fbInfo->event->doFreeBusyCleanup();
                        unset($fbInfo->event->attendee);
                    }

                    //$typeMap[$attender->user_type][$attender->user_id][] = $fbInfo;
                    $fbInfoSet->addRecord($fbInfo);
                }
            }
        }
        
        return $fbInfoSet;
    }

    /**
     * update future constraint exdates of a single event
     *
     * @param $_record
     */
    public function setConstraintsExdates($_record)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' event has rrule constrains, calculating exdates');

        $exdates = is_array($_record->exdate) ? $_record->exdate : array();

        // own event should not trigger constraints conflicts
        if ($_record->rrule_constraints && $_record->rrule_constraints instanceof Calendar_Model_EventFilter) {
            $constraints = clone $_record->rrule_constraints;
            $constraints->addFilter(new Tinebase_Model_Filter_Text('uid', 'not', $_record->uid));

            $constrainExdatePeriods = $this->getConflictingPeriods($this->getBlockingPeriods($_record), $constraints);
            foreach ($constrainExdatePeriods as $constrainExdatePeriod) {
                $exdates[] = $constrainExdatePeriod['from'];
            }
        }

        $_record->exdate = array_unique($exdates);
    }

    /**
     * update all future constraints exdates
     *
     * @return bool
     */
    public function updateConstraintsExdates()
    {
        // find all future recur events with constraints (ignoring ACL's)
        $constraintsEventIds = $this->_backend->search(new Calendar_Model_EventFilter(array(
            array('field' => 'period', 'operator' => 'within', 'value' => array(
                'from' => Tinebase_DateTime::now(),
                'until' => Tinebase_DateTime::now()->addMonth(24)
            )),
            array('field' => 'rrule_contraints', 'operator' => 'notnull', 'value' => NULL)
        )), NULL, 'id');

        // update each
        foreach ($constraintsEventIds as $constraintsEventId) {
            try {
                $event = $this->_backend->get($constraintsEventId);
                $this->setConstraintsExdates($event);
                // NOTE: touch also updates
                $this->_touch($event);
            } catch (Exception $e) {
                Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . " cannot update constraints exdates for event {$constraintsEventId}: " . $e->getMessage());
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . " cannot update constraints exdates for event {$constraintsEventId}: " . $e);
            }

            Tinebase_Lock::keepLocksAlive();
        }

        return true;
    }

    /**
     * get list of records
     *
     * @param Tinebase_Model_Filter_FilterGroup             $_filter
     * @param Tinebase_Model_Pagination                     $_pagination
     * @param bool                                          $_getRelations
     * @param boolean                                       $_onlyIds
     * @param string                                        $_action for right/acl check
     * @return Tinebase_Record_RecordSet|array
     */
    public function search(Tinebase_Model_Filter_FilterGroup $_filter = NULL, Tinebase_Model_Pagination $_pagination = NULL, $_getRelations = FALSE, $_onlyIds = FALSE, $_action = 'get')
    {
        $events = parent::search($_filter, $_pagination, $_getRelations, $_onlyIds, $_action);
        if (! $_onlyIds && $this->_doContainerACLChecks) {
            $this->_freeBusyCleanup($events, $_action);
        }
        
        return $events;
    }

    /**
     * Returns a set of records identified by their id's
     *
     * @param   array $_ids array of record identifiers
     * @param   bool $_ignoreACL don't check acl grants
     * @param null|Tinebase_Record_Expander $_expander
     * @param bool $_getDeleted
     * @return Tinebase_Record_RecordSet of $this->_modelName
     */
    public function getMultiple($_ids, $_ignoreACL = false, Tinebase_Record_Expander $_expander = null, $_getDeleted = false)
    {
        $events = parent::getMultiple($_ids, $_ignoreACL, $_expander, $_getDeleted);
        if ($_ignoreACL !== true) {
            $this->_freeBusyCleanup($events, 'get');
        }
        
        return $events;
    }
    
    /**
     * cleanup search results (freebusy)
     * 
     * @param Tinebase_Record_RecordSet $_events
     * @param string $_action
     */
    protected function _freeBusyCleanup(Tinebase_Record_RecordSet $_events, $_action)
    {
        if (!$this->_doContainerACLChecks) return;

        /** @var Calendar_Model_Event $event */
        foreach ($_events as $event) {
            $doFreeBusyCleanup = $event->doFreeBusyCleanup();
            if ($doFreeBusyCleanup && $_action !== 'get') {
                $_events->removeRecord($event);
            }
        }
    }

    /**
     * @param Calendar_Model_Event $_event with
     *   attendee to find free timeslot for
     *   dtstart, dtend -> to calculate duration
     *   rrule optional
     * @param array $_options
     *  'from'         datetime (optional, defaults event->dtstart) from where to start searching
     *  'until'        datetime (optional, defaults 2 years) until when to giveup searching
     *  'constraints'  array    (optional, defaults to 8-20 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR') array of timespecs to limit the search with
     *     timespec:
     *       dtstart,
     *       dtend,
     *       rrule ... for example "work days" -> 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR'
     * @return Tinebase_Record_RecordSet record set of event sugestions
     * @throws Tinebase_Exception_NotImplemented
     */
    public function searchFreeTime($_event, $_options)
    {
        $functionTime = time();

        // validate $_event, originator_tz will be validated by setTimezone() call
        if (!isset($_event->dtstart) || !$_event->dtstart instanceof Tinebase_DateTime) {
            throw new Tinebase_Exception_UnexpectedValue('dtstart needs to be set');
        }
        if (!isset($_event->dtstart) || !$_event->dtstart instanceof Tinebase_DateTime) {
            throw new Tinebase_Exception_UnexpectedValue('dtend needs to be set');
        }
        if (!isset($_event->attendee) || !$_event->attendee instanceof Tinebase_Record_RecordSet ||
                $_event->attendee->count() < 1) {
            throw new Tinebase_Exception_UnexpectedValue('attendee needs to be set and contain at least one attendee');
        }

        if (empty($_event->originator_tz)) {
            $_event->originator_tz = Tinebase_Core::getUserTimezone();
        }

        // disable rrule for the moment:
        $_event->rrule = null;

        $from = isset($_options['from']) ? ($_options['from'] instanceof Tinebase_DateTime ? $_options['from'] :
            new Tinebase_DateTime($_options['from'])) : clone $_event->dtstart;
        $until = isset($_options['until']) ? ($_options['until'] instanceof Tinebase_DateTime ? $_options['until'] :
            new Tinebase_DateTime($_options['until'])) : $_event->dtend->getClone()->addYear(2);

        $currentFrom = $from->getClone();
        $currentUntil = $from->getClone()->addDay(6)->setTime(23, 59, 59);
        if ($currentUntil->isLater($until)) {
            $currentUntil = clone $until;
        }
        $durationSec = (int)$_event->dtend->getTimestamp() - (int)$_event->dtstart->getTimestamp();
        $constraints = new Tinebase_Record_RecordSet('Calendar_Model_Event', array());
        $exceptions = new Tinebase_Record_RecordSet('Calendar_Model_Event', array());

        if (isset($_options['constraints'])) {
            foreach ($_options['constraints'] as $constraint) {
                if (!isset($constraint['dtstart']) || !isset($constraint['dtend'])) {
                    // LOG
                    continue;
                }
                $constraint['uid'] = Tinebase_Record_Abstract::generateUID();
                $event = new Calendar_Model_Event(array(), true);
                $event->setFromJsonInUsersTimezone($constraint);
                $event->originator_tz = $_event->originator_tz;
                $constraints->addRecord($event);
            }
        }

        if ($constraints->count() === 0) {
            //here the timezone will come from the getClone, not need to set it
            $constraints->addRecord(new Calendar_Model_Event(
                array(
                    'uid'           => Tinebase_Record_Abstract::generateUID(),
                    'dtstart'       => $currentFrom->getClone()->setHour(8)->setMinute(0)->setSecond(0),
                    'dtend'         => $currentFrom->getClone()->setHour(20)->setMinute(0)->setSecond(0),
                    'rrule'         => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR',
                    'originator_tz' => $_event->originator_tz
                ), true)
            );
        }

        do {
            if (time() - $functionTime > 23) {
                $exception = new Calendar_Exception_AttendeeBusy();
                $exception->setEvent(new Calendar_Model_Event(array('dtend' => $currentFrom), true));
                throw $exception;
            }

            $currentConstraints = clone $constraints;
            Calendar_Model_Rrule::mergeRecurrenceSet($currentConstraints, $currentFrom, $currentUntil);
            $currentConstraints->sort('dtstart');
            $remove = array();

            // sort out constraints that do not fit the rrule
            if (!empty($_event->rrule)) {
                /** @var Calendar_Model_Event $event */
                foreach ($currentConstraints as $event) {
                    $recurEvent = clone $_event;
                    $recurEvent->uid = Tinebase_Record_Abstract::generateUID();
                    $recurEvent->dtstart = $event->dtstart->getClone()->subDay(1);
                    if ($_event->is_all_day_event) {
                        $recurEvent->dtend = $event->dtend->getClone()->subDay(1);
                    } else {
                        $recurEvent->dtend = $recurEvent->dtstart->getClone()->addSecond($durationSec);
                    }
                    if (null === ($recurEvent = Calendar_Model_Rrule::computeNextOccurrence($recurEvent, $exceptions, $event->dtstart))
                            || $recurEvent->dtstart->isLater($event->dtend)) {
                        $remove[] = $event;
                    }
                }
                foreach($remove as $event) {
                    $currentConstraints->removeRecord($event);
                }
            }

            if ($currentConstraints->count() > 0) {
                $periods = array();
                /** @var Calendar_Model_Event $event */
                foreach ($currentConstraints as $event) {
                    $periods[] = array(
                        'field' => 'period',
                        'operator' => 'within',
                        'value' => array(
                            'from' => $event->dtstart,
                            'until' => $event->dtend
                        ),
                    );
                }

                $busySlots = $this->getFreeBusyInfo(new Calendar_Model_EventFilter($periods, Tinebase_Model_Filter_FilterGroup::CONDITION_OR), $_event->attendee);
                $busySlots = $this->mergeFreeBusyInfo($busySlots);

                /** @var Calendar_Model_Event $event */
                foreach ($currentConstraints as $event) {
                    if ($event->dtend->isEarlierOrEquals($currentFrom)) {
                        continue;
                    }

                    if ($_event->is_all_day_event) {
                        $durationSec = (int)$event->dtend->getTimestamp() - (int)$event->dtstart->getTimestamp();
                    }

                    $constraintStart = (int)$event->dtstart->getTimestamp();
                    if ($constraintStart < (int)$currentFrom->getTimestamp()) {
                        $constraintStart = (int)$currentFrom->getTimestamp();
                    }
                    $constraintEnd = (int)$event->dtend->getTimestamp();
                    if ($constraintEnd > (int)$currentUntil->getTimestamp()) {
                        $constraintEnd = (int)$currentUntil->getTimestamp();
                    }
                    $lastBusyEnd = $constraintStart;
                    $remove = array();
                    /** @var Calendar_Model_FreeBusy $busy */
                    foreach ($busySlots as $key => $busy) {
                        $busyStart = (int)$busy['dtstart']->getTimestamp();
                        $busyEnd = (int)$busy['dtend']->getTimestamp();

                        if ($busyEnd < $constraintStart) {
                            $remove[] = $key;
                            continue;
                        }

                        if ($busyStart > ($constraintEnd - $durationSec)) {
                            break;
                        }

                        if (($lastBusyEnd + $durationSec) <= $busyStart) {
                            // check between $lastBusyEnd and $busyStart
                            $result = $this->_tryForFreeSlot($_event, $lastBusyEnd, $busyStart, $durationSec, $until);
                            if ($result->count() > 0) {
                                if ($_event->is_all_day_event) {
                                    $result->getFirstRecord()->dtstart = $_event->dtstart;
                                    $result->getFirstRecord()->dtend = $_event->dtend;
                                }
                                return $result;
                            }
                        }
                        $lastBusyEnd = $busyEnd;
                    }
                    foreach ($remove as $key) {
                        unset($busySlots[$key]);
                    }

                    if (($lastBusyEnd + $durationSec) <= $constraintEnd) {
                        // check between $lastBusyEnd and $constraintEnd
                        $result = $this->_tryForFreeSlot($_event, $lastBusyEnd, $constraintEnd, $durationSec, $until);
                        if ($result->count() > 0) {
                            if ($_event->is_all_day_event) {
                                $result->getFirstRecord()->dtstart = $_event->dtstart;
                                $result->getFirstRecord()->dtend = $_event->dtend;
                            }
                            return $result;
                        }
                    }
                }
            }

            $currentFrom->addDay(7)->setTime(0, 0, 0);
            $currentUntil->addDay(7);
            if ($currentUntil->isLater($until)) {
                $currentUntil = clone $until;
            }
        } while ($until->isLater($currentFrom));

        $exception = new Calendar_Exception_AttendeeBusy();
        $exception->setEvent(new Calendar_Model_Event(array('dtend' => $until), true));
        throw $exception;
    }

    protected function _tryForFreeSlot(Calendar_Model_Event $_event, $_startSec, $_endSec, $_durationSec, Tinebase_DateTime $_until)
    {
        $event = new Calendar_Model_Event(array(
            'uid'           => Tinebase_Record_Abstract::generateUID(),
            'dtstart'       => new Tinebase_DateTime($_startSec),
            'dtend'         => new Tinebase_DateTime($_startSec + $_durationSec),
            'originator_tz' => $_event->originator_tz,
        ), true);
        $result = new Tinebase_Record_RecordSet('Calendar_Model_Event', array($event));

        if (!empty($_event->rrule)) {
            $event->rrule = $_event->rrule;
            do {
                $until = $event->dtstart->getClone()->addMonth(2);
                if ($until->isLater($_until)) {
                    $until = $_until;
                }
                $periods = $this->getBlockingPeriods($event, array(
                    'from'  => $event->dtstart,
                    'until' => $until
                ));
                $busySlots = $this->getFreeBusyInfo($periods, $_event->attendee);
                $event->dtstart->addMinute(15);
                $event->dtend->addMinute(15);
            } while($busySlots->count() > 0 && $event->dtend->getTimestamp() <= $_endSec && $event->dtend->isEarlierOrEquals($_until));

            if ($busySlots->count() > 0) {
                $result->removeAll();
            } else {
                $event->dtstart->subMinute(15);
                $event->dtend->subMinute(15);
            }
        }

        return $result;
    }
    
    /**
     * update one record
     *
     * @param   Tinebase_Record_Interface $_record
     * @param   bool                      $_checkBusyConflicts
     * @param   bool                      $skipEvent
     * @return  Calendar_Model_Event
     * @throws  Tinebase_Exception_AccessDenied
     * @throws  Tinebase_Exception_Record_Validation
     */
    public function update(Tinebase_Record_Interface $_record, $_checkBusyConflicts = FALSE, $skipEvent = false)
    {
        if (Tinebase_Core::isFilesystemAvailable()) {
            // fill stat cache to avoid deadlocks. Needs to happen outside a transaction
            $path = Tinebase_FileSystem_RecordAttachments::getInstance()->getRecordAttachmentPath($_record);
            Tinebase_FileSystem::getInstance()->fileExists($path);
        }

        /** @var Calendar_Model_Event $_record */
        try {
            $db = $this->_backend->getAdapter();
            $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($db);
            
            $sendNotifications = $this->sendNotifications(FALSE);

            $event = $this->get($_record->getId());
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    .' Going to update the following event. rawdata: ' . print_r($event->toArray(), true));

            //NOTE we check via get(full rights) here whereas _updateACLCheck later checks limited rights from search
            if ($this->_doContainerACLChecks === FALSE || $event->hasGrant(Tinebase_Model_Grants::GRANT_EDIT)) {
                Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " updating event: {$_record->id}");
                
                // we need to resolve groupmembers before free/busy checking
                Calendar_Model_Attender::resolveGroupMembers($_record->attendee);
                $this->_inspectEvent($_record, $skipEvent);
               
                if ($_checkBusyConflicts) {
                    if ($event->isRescheduled($_record) ||
                        count(array_diff($_record->attendee->user_id, $event->attendee->user_id)) > 0
                    ) {
                        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) {
                            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                                . " Ensure that all attendee are free with free/busy check ... ");
                        }
                        $this->checkBusyConflicts($_record);
                    } else {
                        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) {
                            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                                . " Skipping free/busy check because event has not been rescheduled and no new attender has been added");
                        }
                    }
                }
                
                parent::update($_record);

            } else if ($_record->attendee instanceof Tinebase_Record_RecordSet) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . " user has no editGrant for event: {$_record->id}, updating attendee status with valid authKey only");
                foreach ($_record->attendee as $attender) {
                    if ($attender->status_authkey) {
                        $this->attenderStatusUpdate($_record, $attender, $attender->status_authkey);
                    }
                }

                $updatedEvent = $this->get($_record->getId());
                $currentMods = $this->_writeModLog($updatedEvent, $event);
                $this->_setSystemNotes($updatedEvent, Tinebase_Model_Note::SYSTEM_NOTE_NAME_CHANGED, $currentMods);
            }

            Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
        } catch (Exception $e) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Rolling back because: ' . $e);
            Tinebase_TransactionManager::getInstance()->rollBack();
            $this->sendNotifications($sendNotifications);
            throw $e;
        }
        
        $updatedEvent = $this->get($event->getId(), null, true, true);

        if ($skipEvent === false) {
            Tinebase_Record_PersistentObserver::getInstance()->fireEvent(new Calendar_Event_InspectEventAfterUpdate([
                'observable' => $updatedEvent
            ]));
        }

        // send notifications
        $this->sendNotifications($sendNotifications);
        $updatedEvent->mute = $_record->mute;
        if ($this->_sendNotifications) {
            $this->doSendNotifications($updatedEvent, Tinebase_Core::getUser(), 'changed', $event);
        }
        return $updatedEvent;
    }
    
    /**
     * inspect update of one record (after update)
     *
     * @param   Tinebase_Record_Interface $updatedRecord   the just updated record
     * @param   Tinebase_Record_Interface $record          the update record
     * @param   Tinebase_Record_Interface $currentRecord   the current record (before update)
     * @return  void
     */
    protected function _inspectAfterUpdate($updatedRecord, $record, $currentRecord)
    {
        $this->_saveAttendee($record, $currentRecord, $record->isRescheduled($currentRecord));
        // need to save new attendee set in $updatedRecord for modlog
        $updatedRecord->attendee = clone($record->attendee);
    }
    
    /**
     * update multiple records
     * 
     * @param   Tinebase_Model_Filter_FilterGroup $_filter
     * @param   array $_data
     * @return  integer number of updated records
     */
    public function updateMultiple($_filter, $_data)
    {
        $this->_checkRight('update');
        $this->checkFilterACL($_filter, 'update');
        
        // get only ids
        $ids = $this->_backend->search($_filter, NULL, TRUE);
        
        foreach ($ids as $eventId) {
            $event = $this->get($eventId);
            foreach ($_data as $field => $value) {
                $event->$field = $value;
            }
            
            $this->update($event);
        }
        
        return count($ids);
    }
    
    /**
     * Deletes a set of records.
     * 
     * If one of the records could not be deleted, no record is deleted
     * 
     * @param   string|array $_ids array of record identifiers
     * @param   string $range
     * @return  NULL
     * @throws Tinebase_Exception_NotFound|Tinebase_Exception
     */
    public function delete($_ids, $range = Calendar_Model_Event::RANGE_THIS)
    {
        if ($_ids instanceof $this->_modelName) {
            $_ids = (array)$_ids->getId();
        }
        
        $records = $this->_backend->getMultiple((array) $_ids);
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . " Deleting " . count($records) . ' with range ' . $range . ' ...');
        
        foreach ($records as $record) {
            if ($record->isRecurException() && in_array($range, array(Calendar_Model_Event::RANGE_ALL, Calendar_Model_Event::RANGE_THISANDFUTURE))) {
                $this->_deleteExdateRange($record, $range);
            }
            
            try {
                $db = $this->_backend->getAdapter();
                $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($db);
                
                // delete if delete grant is present
                if ($this->_doContainerACLChecks === FALSE || $record->hasGrant(Tinebase_Model_Grants::GRANT_DELETE)) {
                    // NOTE delete needs to update sequence otherwise iTIP based protocolls ignore the delete
                    $record->status = Calendar_Model_Event::STATUS_CANCELED;
                    $this->_touch($record);
                    if ($record->isRecurException()) {
                        try {
                            $baseEvent = $this->getRecurBaseEvent($record);
                            $this->_touch($baseEvent);
                        } catch (Tinebase_Exception_NotFound $tnfe) {
                            // base Event might be gone already
                            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                                . " BaseEvent of exdate {$record->uid} to delete not found ");

                        }
                    }
                    parent::delete($record);
                }
                
                // otherwise update status for user to DECLINED
                else if ($record->attendee instanceof Tinebase_Record_RecordSet) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " user has no deleteGrant for event: " . $record->id . ", updating own status to DECLINED only");
                    $ownContact = Tinebase_Core::getUser()->contact_id;
                    foreach ($record->attendee as $attender) {
                        if ($attender->user_id == $ownContact && in_array($attender->user_type, array(Calendar_Model_Attender::USERTYPE_USER, Calendar_Model_Attender::USERTYPE_GROUPMEMBER))) {
                            $attender->status = Calendar_Model_Attender::STATUS_DECLINED;
                            $this->attenderStatusUpdate($record, $attender, $attender->status_authkey);
                        }
                    }
                }
                
                // increase display container content sequence for all attendee of deleted event
                if ($record->attendee instanceof Tinebase_Record_RecordSet) {
                    foreach ($record->attendee as $attender) {
                        $this->_increaseDisplayContainerContentSequence($attender, $record, Tinebase_Model_ContainerContent::ACTION_DELETE);
                    }
                }
                
                Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
            } catch (Exception $e) {
                Tinebase_TransactionManager::getInstance()->rollBack();
                throw $e;
            }
        }
    }
    
    /**
     * delete range of events starting with given recur exception
     *
     * NOTE: if exdate is persistent, it will not be deleted by this function
     *       but by the original call of delete
     *
     * @param Calendar_Model_Event $exdate
     * @param string $range
     */
    protected function _deleteExdateRange($exdate, $range)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Deleting events (range: ' . $range . ') belonging to recur exception event ' . $exdate->getId());
        
        $baseEvent = $this->getRecurBaseEvent($exdate);
        
        if ($range === Calendar_Model_Event::RANGE_ALL) {
            $this->deleteRecurSeries($exdate);
        } else if ($range === Calendar_Model_Event::RANGE_THISANDFUTURE) {
            $nextRegularRecurEvent = Calendar_Model_Rrule::computeNextOccurrence($baseEvent, new Tinebase_Record_RecordSet('Calendar_Model_Event'), $exdate->dtstart);
            
            if ($nextRegularRecurEvent == $baseEvent) {
                // NOTE if a fist instance exception takes place before the
                //      series would start normally, $nextOccurence is the
                //      baseEvent of the series. As createRecurException can't
                //      deal with this situation we delete whole series here
                $this->_deleteExdateRange($exdate, Calendar_Model_Event::RANGE_ALL);
            } else if ($nextRegularRecurEvent) {
                $this->createRecurException($nextRegularRecurEvent, TRUE, TRUE);
            }
        }
    }
    
    /**
     * updates a recur series
     *
     * @param  Calendar_Model_Event $_recurInstance
     * @param  bool                 $_checkBusyConflicts
     * @return Calendar_Model_Event
     * @throws Tinebase_Exception_InvalidArgument
     */
    public function updateRecurSeries($_recurInstance, $_checkBusyConflicts = FALSE)
    {
        $baseEvent = $this->getRecurBaseEvent($_recurInstance);

        // replace baseEvent with adopted instance
        $newBaseEvent = clone $_recurInstance;
        $newBaseEvent->setId($baseEvent->getId());
        unset($newBaseEvent->recurid);
        $newBaseEvent->exdate = $baseEvent->exdate;

        $rrule = $baseEvent->rrule;
        $newRrule = $newBaseEvent->rrule;

        if (! $rrule) {
            throw new Tinebase_Exception_InvalidArgument('No rrule in baseevent found!');
        }

        if ((string)$rrule === (string)$newRrule) {
            $originalDtStart = $_recurInstance->getOriginalDtStart();
            if ($rrule->freq === Calendar_Model_Rrule::FREQ_WEEKLY && !empty($rrule->byday) &&
                strpos($rrule->byday, ',') !== false && $_recurInstance->dtstart->format('D') !== $originalDtStart
                    ->format('D') && $originalDtStart->format('D') !== $baseEvent->dtstart->format('D')) {
                // special case for multi byday
                $this->_applyDateTimeDiff($newBaseEvent, $_recurInstance, $baseEvent, true);
                $this->_updateRruleBasedOnDtstartChange($rrule, $_recurInstance->dtstart, $originalDtStart);
                $newBaseEvent->rrule = $rrule;
            } else {
                // we apply date and time change to base event
                $this->_applyDateTimeDiff($newBaseEvent, $_recurInstance, $baseEvent);
            }

        } else {
            // rrule was changed by the user, so we only apply the time diff to the base event
            $this->_applyDateTimeDiff($newBaseEvent, $_recurInstance, $baseEvent, true);
        }
        
        return $this->update($newBaseEvent, $_checkBusyConflicts);
    }
    
    /**
     * apply time diff
     * 
     * @param Calendar_Model_Event $newEvent
     * @param Calendar_Model_Event $fromEvent
     * @param Calendar_Model_Event $baseEvent
     * @param boolean              $onlyTime
     */
    protected function _applyDateTimeDiff($newEvent, $fromEvent, $baseEvent = NULL, $onlyTime = false)
    {
        if (! $baseEvent) {
            $baseEvent = $newEvent;
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' New event: ' . print_r($newEvent->toArray(), TRUE));
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' From event: ' . print_r($fromEvent->toArray(), TRUE));

        // compute time diff (NOTE: if the $fromEvent is the baseEvent, it has no recurid)
        $originalDtStart = $fromEvent->recurid ? new Tinebase_DateTime(substr($fromEvent->recurid, -19), 'UTC') :
            clone $baseEvent->dtstart;

        if ($onlyTime) {
            $tmp = clone $fromEvent->dtstart;
            $tmp->setDate($originalDtStart->format('Y'), $originalDtStart->format('m'), $originalDtStart->format('d'));
            $dtstartDiff = $originalDtStart->diff($tmp);
        } else {
            $dtstartDiff = $originalDtStart->diff($fromEvent->dtstart);
        }
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . " Dtstart diff: " . $dtstartDiff->format('%Y-%M-%D %H:%I:%S'));
        $eventDuration = $fromEvent->dtstart->diff($fromEvent->dtend);
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . " Duration diff: " . $dtstartDiff->format('%Y-%M-%D %H:%I:%S'));
        
        $newEvent->dtstart = clone $baseEvent->dtstart;
        $newEvent->dtstart->add($dtstartDiff);
        
        $newEvent->dtend = clone $newEvent->dtstart;
        $newEvent->dtend->add($eventDuration);
    }
    
    /**
     * creates an exception instance of a recurring event
     *
     * NOTE: deleting persistent exceptions is done via a normal delete action
     *       and handled in the deleteInspection
     * 
     * @param  Calendar_Model_Event  $_event
     * @param  bool                  $_deleteInstance
     * @param  bool                  $_allFollowing
     * @param  bool                  $_checkBusyConflicts
     * @return Calendar_Model_Event  exception Event | updated baseEvent
     * @throws Tinebase_Exception_NotFound
     * @throws Tinebase_Exception_ConcurrencyConflict
     *
     * @todo replace $_allFollowing param with $range
     * @deprecated replace with create/update/delete
     */
    public function createRecurException($_event, $_deleteInstance = FALSE, $_allFollowing = FALSE, $_checkBusyConflicts = FALSE)
    {
        $baseEvent = $this->getRecurBaseEvent($_event);

        if (! $baseEvent) {
            throw new Tinebase_Exception_NotFound('base event of a recurring series not found');
        }
        
        if ($baseEvent->last_modified_time != $_event->last_modified_time) {
            if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                . " It is not allowed to create recur instance if it is clone of base event");
            throw new Tinebase_Exception_ConcurrencyConflict('concurrency conflict!');
        }

//        // Maybe Later
//        // exdates needs to stay in baseEvents container
//        if ($_event->container_id != $baseEvent->container_id) {
//            throw new Calendar_Exception_ExdateContainer();
//        }

        // check if this is an exception to the first occurence
        if ($baseEvent->getId() == $_event->getId()) {
            if ($_allFollowing) {
                throw new Exception('please edit or delete complete series!');
            }
            // NOTE: if the baseEvent gets a time change, we can't compute the recurdid w.o. knowing the original dtstart
            $recurid = $baseEvent->setRecurId($baseEvent->getId());
            unset($baseEvent->recurid);
            $_event->recurid = $recurid;
        }
        
        // just do attender status update if user has no edit grant
        if ($this->_doContainerACLChecks && !$baseEvent->{Tinebase_Model_Grants::GRANT_EDIT}) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . " user has no editGrant for event: '{$baseEvent->getId()}'. Only creating exception for attendee status");
            if ($_event->attendee instanceof Tinebase_Record_RecordSet) {
                foreach ($_event->attendee as $attender) {
                    if ($attender->status_authkey) {
                        $exceptionAttender = $this->attenderStatusCreateRecurException($_event, $attender, $attender->status_authkey, $_allFollowing);
                    }
                }
            }
            
            if (! isset($exceptionAttender)) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG) && $_event->attendee instanceof Tinebase_Record_RecordSet) {
                    Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Failed to update attendee: " . print_r($_event->attendee->toArray(), true));
                }
                throw new Tinebase_Exception_AccessDenied('Failed to update attendee, status authkey might be missing');
            }
            
            return $this->get($exceptionAttender->cal_event_id);
        }
        
        // NOTE: recurid is computed by rrule recur computations and therefore is already part of the event.
        if (empty($_event->recurid)) {
            throw new Exception('recurid must be present to create exceptions!');
        }
        
        // we do notifications ourself
        $sendNotifications = $this->sendNotifications(FALSE);
        
        // EDIT for baseEvent is checked above, CREATE, DELETE for recur exceptions is implied with it
        $doContainerACLChecks = $this->doContainerACLChecks(FALSE);
        
        $db = $this->_backend->getAdapter();
        $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($db);
        
        $exdate = new Tinebase_DateTime(substr($_event->recurid, -19));
        $exdates = is_array($baseEvent->exdate) ? $baseEvent->exdate : array();
        $originalDtstart = $_event->getOriginalDtStart();
        $originalEvent = Calendar_Model_Rrule::computeNextOccurrence($baseEvent, new Tinebase_Record_RecordSet('Calendar_Model_Event'), $originalDtstart);

        if ($_allFollowing != TRUE) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                . " Adding exdate for: '{$_event->recurid}'");
            
            array_push($exdates, $exdate);
            $baseEvent->exdate = $exdates;
            $updatedBaseEvent = $this->update($baseEvent, FALSE);
            
            if ($_deleteInstance == FALSE) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                    . " Creating persistent exception for: '{$_event->recurid}'");
                if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
                    . " Recur exception: " . print_r($_event->toArray(), TRUE));

                $_event->base_event_id = $baseEvent->getId();
                $_event->setId(NULL);
                unset($_event->rrule);
                unset($_event->exdate);
            
                foreach (array('attendee', 'notes', 'alarms') as $prop) {
                    if ($_event->{$prop} instanceof Tinebase_Record_RecordSet) {
                        $_event->{$prop}->setId(NULL);
                    }
                }
                
                $originalDtstart = $_event->getOriginalDtStart();
                $dtStartHasDiff = $originalDtstart->compare($_event->dtstart) != 0; // php52 compat
                
                if (! $dtStartHasDiff) {
                    $attendees = $_event->attendee;
                    unset($_event->attendee);
                }
                unset($_event->notes);
                $persistentExceptionEvent = $this->create($_event, $_checkBusyConflicts);
                
                if (! $dtStartHasDiff) {
                    // we save attendee seperatly to preserve their attributes
                    if ($attendees instanceof Tinebase_Record_RecordSet) {
                        $attendees->cal_event_id = $persistentExceptionEvent->getId();
                        $calendar = Tinebase_Container::getInstance()->getContainerById($_event->container_id);
                        foreach ($attendees as $attendee) {
                            $this->_createAttender($attendee, $_event, TRUE, $calendar);
                            $this->_increaseDisplayContainerContentSequence($attendee, $persistentExceptionEvent, Tinebase_Model_ContainerContent::ACTION_CREATE);
                        }
                    }
                }
                
                // @todo save notes and add a update note -> what was updated? -> modlog is also missing
                $persistentExceptionEvent = $this->get($persistentExceptionEvent->getId());
            }
            
        } else {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " shorten recur series for/to: '{$_event->recurid}'");

            $originalDtstart = $_event->getOriginalDtStart();
            $rrule = Calendar_Model_Rrule::getRruleFromString($baseEvent->rrule);
            if (isset($rrule->count)) {
                // get all occurences and find the split

                $exdate = $baseEvent->exdate;
                $baseEvent->exdate = NULL;
                //$baseCountOccurrence = Calendar_Model_Rrule::computeNextOccurrence($baseEvent, new Tinebase_Record_RecordSet('Calendar_Model_Event'), $baseEvent->rrule_until, $baseCount);
                $recurSet = Calendar_Model_Rrule::computeRecurrenceSet($baseEvent, new Tinebase_Record_RecordSet('Calendar_Model_Event'), $baseEvent->dtstart, $baseEvent->rrule_until);
                $baseEvent->exdate = $exdate;

                foreach($recurSet as $idx => $rInstance) {
                    if ($rInstance->dtstart >= $originalDtstart) break;
                }

                $rrule->count = $idx+1;
            } else {
                $rrule->until = clone $originalDtstart;
                $rrule->until->subSecond(1);
            }

            // split past/future exceptions
            $pastExdates = array();
            $futureExdates = array();
            foreach($exdates as $exdate) {
                $exdate->isLater($originalDtstart) ? $futureExdates[] = $exdate :
                    ($exdate->isEarlier($originalDtstart) ? $pastExdates[] = $exdate : null);
            }
            
            $persistentExceptionEvents = $this->getRecurExceptions($_event);
            $pastPersistentExceptionEvents = new Tinebase_Record_RecordSet('Calendar_Model_Event');
            $futurePersistentExceptionEvents = new Tinebase_Record_RecordSet('Calendar_Model_Event');
            foreach ($persistentExceptionEvents as $persistentExceptionEvent) {
                $pEEOrgStart = $persistentExceptionEvent->getOriginalDtStart();
                $pEEOrgStart->isLater($originalDtstart) ?
                    $futurePersistentExceptionEvents->addRecord($persistentExceptionEvent) :
                    ($pEEOrgStart->isEarlier($originalDtstart) ?
                        $pastPersistentExceptionEvents->addRecord($persistentExceptionEvent) :
                        $this->delete($persistentExceptionEvent));
            }

            $origBaseEventRrule = (string)$baseEvent->rrule;
            
            if (!$_deleteInstance) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " create new recur series for/at: '{$_event->recurid}'");

                $_event->originator_tz = Tinebase_Core::getUserTimezone();
                $dtStartOrgTz = $_event->dtstart->getClone()->setTimezone($_event->originator_tz);
                $origDtStartOrgTz = $originalDtstart->getClone()->setTimezone($_event->originator_tz);
                $dtStartHasDiff = $dtStartOrgTz->compare($origDtStartOrgTz) !== 0;

                $origRrule = (string)$_event->rrule;
                $ruleChanged = false;
                // adopt count
                if (isset($rrule->count)) {
                    $newRrule = Calendar_Model_Rrule::getRruleFromString($_event->rrule);
                    $newRrule->count = $newRrule->count - $rrule->count;
                    $_event->rrule = (string)$newRrule;
                }
                
                $_event->setId(Tinebase_Record_Abstract::generateUID());
                $_event->uid = $futurePersistentExceptionEvents->uid = Tinebase_Record_Abstract::generateUID();
                $_event->setId(Tinebase_Record_Abstract::generateUID());
                if ($dtStartHasDiff) {

                    if ($origBaseEventRrule === $origRrule && $dtStartOrgTz->compare($origDtStartOrgTz, 'Y-m-d')) {
                        if (!$_event->rrule instanceof Calendar_Model_Rrule) {
                            $_event->rrule = new Calendar_Model_Rrule($_event->rrule);
                        }
                        $ruleChanged = $this->_updateRruleBasedOnDtstartChange($_event->rrule, $dtStartOrgTz, $origDtStartOrgTz);
                    }

                    $diff = $origDtStartOrgTz->diff($dtStartOrgTz);
                    /** @var Calendar_Model_Event $exception */
                    foreach ($futurePersistentExceptionEvents as $exception) {
                        $exception->recurid = $exception->getOriginalDtStart($diff);
                    }
                    foreach ($futureExdates as $date) {
                        $date->modifyTime($diff);
                    }
                }
                $_event->exdate = $futureExdates;
                $futurePersistentExceptionEvents->setRecurId($_event->getId());
                unset($_event->recurid);
                unset($_event->base_event_id);
                foreach(array('attendee', 'notes', 'alarms') as $prop) {
                    if ($_event->{$prop} instanceof Tinebase_Record_RecordSet) {
                        $_event->{$prop}->setId(NULL);
                    }
                }

                $attendees = $_event->attendee; unset($_event->attendee);
                $note = $_event->notes;
                unset($_event->notes);
                $persistentExceptionEvent = $this->create($_event, $_checkBusyConflicts && $dtStartHasDiff);
                
                // we save attendee separately to preserve their attributes
                if ($attendees instanceof Tinebase_Record_RecordSet) {
                    foreach($attendees as $attendee) {
                        $this->_createAttender($attendee, $persistentExceptionEvent, true);
                    }
                }
                
                // @todo save notes and add a update note -> what was updated? -> modlog is also missing
                
                foreach($futurePersistentExceptionEvents as $futurePersistentExceptionEvent) {
                    $this->update($futurePersistentExceptionEvent, FALSE);
                }

                if ($ruleChanged || $origRrule !== $origBaseEventRrule) {
                    if ($this->_validateAndFixExdatesVsRrule($_event)) {
                        // NOTE: we don't want implicit attendee updates
                        $this->_inspectEvent($_event);
                        parent::update($_event);
                    }
                }

                $persistentExceptionEvent = $this->get($persistentExceptionEvent->getId());
            }

            // update baseEvent
            $baseEvent->rrule = (string) $rrule;
            $baseEvent->exdate = $pastExdates;

            // NOTE: we don't want implicit attendee updates
            //$updatedBaseEvent = $this->update($baseEvent, FALSE);
            $this->_inspectEvent($baseEvent);
            $updatedBaseEvent = parent::update($baseEvent);
        }
        
        Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
        
        // restore original notification handling
        $this->sendNotifications($sendNotifications);
        $notificationAction = $_deleteInstance ? 'deleted' : 'changed';
        $notificationEvent = $_deleteInstance ? $_event : $persistentExceptionEvent;
        
        // restore acl
        $this->doContainerACLChecks($doContainerACLChecks);
        
        // send notifications
        if ($this->_sendNotifications) {
            // NOTE: recur exception is a fake event from client. 
            //       this might lead to problems, so we wrap the calls
            try {
                if (count($_event->attendee) > 0) {
                    $_event->attendee->bypassFilters = TRUE;
                }
                $_event->created_by = $baseEvent->created_by;
                
                $this->doSendNotifications($notificationEvent, Tinebase_Core::getUser(), $notificationAction, $originalEvent);
            } catch (Exception $e) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " " . $e->getTraceAsString());
                Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . " could not send notification {$e->getMessage()}");
            }
        }
        
        return $_deleteInstance ? $updatedBaseEvent : $persistentExceptionEvent;
    }
    
    /**
     * (non-PHPdoc)
     * @see Tinebase_Controller_Record_Abstract::get()
     * @return Calendar_Model_Event
     */
    public function get($_id, $_containerId = NULL, $_getRelatedData = TRUE, $_getDeleted = FALSE)
    {
        if (preg_match('/^fakeid(.*)\/(.*)/', $_id, $matches)) {
            $baseEvent = $this->get($matches[1]);
            $exceptions = $this->getRecurExceptions($baseEvent);
            $originalDtStart = new Tinebase_DateTime($matches[2]);

            $exdates = $exceptions->getOriginalDtStart();
            $exdate = array_search($originalDtStart, $exdates);

            return $exdate !== false ? $exceptions[$exdate] :
                Calendar_Model_Rrule::computeNextOccurrence($baseEvent, $exceptions, $originalDtStart);
        } else {
            return parent::get($_id, $_containerId, $_getRelatedData, $_getDeleted);
        }
    }
    
    /**
     * returns base event of a recurring series
     *
     * @param  Calendar_Model_Event $_event
     * @return Calendar_Model_Event
     */
    public function getRecurBaseEvent($_event)
    {
        $baseEventId = $_event->base_event_id ?: $_event->id;

        if (! $baseEventId) {
            throw new Tinebase_Exception_NotFound('base event of a recurring series not found');
        }

        // make sure we have a 'fully featured' event
        return $this->get($baseEventId);
    }

    /**
     * returns all persistent recur exceptions of recur series
     * 
     * NOTE: deleted instances are saved in the base events exception property
     * NOTE: returns all exceptions regardless of current filters and access restrictions
     * 
     * @param  Calendar_Model_Event        $_event
     * @param  boolean                     $_fakeDeletedInstances
     * @param  Calendar_Model_EventFilter  $_eventFilter
     * @return Tinebase_Record_RecordSet of Calendar_Model_Event
     */
    public function getRecurExceptions($_event, $_fakeDeletedInstances = FALSE, $_eventFilter = NULL)
    {
        $baseEventId = $_event->base_event_id ?: $_event->id;

        $exceptionFilter = new Calendar_Model_EventFilter(array(
            array('field' => 'base_event_id', 'operator' => 'equals',  'value' => $baseEventId),
            array('field' => 'recurid',       'operator' => 'notnull', 'value' => NULL)
        ));
        
        if ($_eventFilter instanceof Calendar_Model_EventFilter) {
            $exceptionFilter->addFilterGroup($_eventFilter);
        }
        
        $exceptions = $this->_backend->search($exceptionFilter);
        
        if ($_fakeDeletedInstances) {
            $baseEvent = $this->getRecurBaseEvent($_event);
            $this->fakeDeletedExceptions($baseEvent, $exceptions);
        }
        
        $exceptions->exdate = NULL;
        $exceptions->rrule = NULL;
        $exceptions->rrule_until = NULL;
        
        return $exceptions;
    }

    /**
     * add exceptions events for deleted instances
     *
     * @param Calendar_Model_Event $baseEvent
     * @param Tinebase_Record_RecordSet $exceptions
     */
    public function fakeDeletedExceptions($baseEvent, $exceptions)
    {
        if (! $baseEvent->dtstart instanceof Tinebase_DateTime) {
            if (Tinebase_Core::isLogLevel(Zend_Log::ERR)) Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ .
                ' No valid dtstart in baseevent: ' . print_r($baseEvent->toArray(), true));
            return;
        }
        $eventLength = $baseEvent->dtstart->diff($baseEvent->dtend);

        // compute remaining exdates
        $deletedInstanceDtStarts = array_diff(array_unique((array) $baseEvent->exdate), $exceptions->getOriginalDtStart());

        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
            ' Faking ' . count($deletedInstanceDtStarts) . ' deleted exceptions');

        foreach((array) $deletedInstanceDtStarts as $deletedInstanceDtStart) {
            $fakeEvent = clone $baseEvent;
            $fakeEvent->setId(NULL);

            $fakeEvent->dtstart = clone $deletedInstanceDtStart;
            $fakeEvent->dtend = clone $deletedInstanceDtStart;
            $fakeEvent->dtend->add($eventLength);
            $fakeEvent->is_deleted = TRUE;
            $fakeEvent->setRecurId($baseEvent->getId());
            $fakeEvent->rrule = null;

            $exceptions->addRecord($fakeEvent);
        }
    }

   /**
    * adopt alarm time to next occurrence for recurring events
    *
    * @param Tinebase_Record_Interface $_record
    * @param Tinebase_Model_Alarm $_alarm
    * @param bool $_nextBy {instance|time} set recurr alarm to next from given instance or next by current time
    * @return void
    */
    public function adoptAlarmTime(Tinebase_Record_Interface $_record, Tinebase_Model_Alarm $_alarm, $_nextBy = 'time')
    {
        if ($_record->rrule) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
                 ' Adopting alarm time for next recur occurrence (by ' . $_nextBy . ')');
            if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ .
                 ' ' . print_r($_record->toArray(), TRUE));
            
            if ($_nextBy == 'time') {
                // NOTE: this also finds instances running right now
                $from = Tinebase_DateTime::now();
            
            } else {
                $recurid = $_alarm->getOption('recurid');
                $instanceStart = $recurid ? new Tinebase_DateTime(substr($recurid, -19)) : clone $_record->dtstart;
                $eventLength = $_record->dtstart->diff($_record->dtend);
                
                $instanceStart->setTimezone($_record->originator_tz);
                $from = $instanceStart->add($eventLength);
                $from->setTimezone('UTC');
            }
            
            // compute next
            $exceptions = $this->getRecurExceptions($_record);
            $nextOccurrence = Calendar_Model_Rrule::computeNextOccurrence($_record, $exceptions, $from);
            
            if ($nextOccurrence === NULL) {
                if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ .
                    ' Recur series is over, no more alarms pending');
            } else {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
                    ' Found next occurrence, adopting alarm to dtstart ' . $nextOccurrence->dtstart->toString());
            }
            
            // save recurid so we know for which recurrance the alarm is for
            $_alarm->setOption('recurid', isset($nextOccurrence) ? $nextOccurrence->recurid : NULL);
            
            $_alarm->sent_status = $nextOccurrence ? Tinebase_Model_Alarm::STATUS_PENDING : Tinebase_Model_Alarm::STATUS_SUCCESS;
            $_alarm->sent_message = $nextOccurrence ?  '' : 'Nothing to send, series is over';
            
            $eventStart = $nextOccurrence ? clone $nextOccurrence->dtstart : clone $_record->dtstart;
        } else {
            $eventStart = clone $_record->dtstart;
        }
        
        // save minutes before / compute it for custom alarms
        $minutesBefore = $_alarm->minutes_before == Tinebase_Model_Alarm::OPTION_CUSTOM 
            ? ($_record->dtstart->getTimestamp() - $_alarm->alarm_time->getTimestamp()) / 60 
            : $_alarm->minutes_before;
        $minutesBefore = round($minutesBefore);
        
        $_alarm->setOption('minutes_before', $minutesBefore);
        $_alarm->alarm_time = $eventStart->subMinute($minutesBefore);

        if ($_alarm->sent_status === Tinebase_Model_Alarm::STATUS_SUCCESS && null !== $_alarm->sent_time &&
                $_alarm->alarm_time > $_alarm->sent_time) {
            $_alarm->sent_time = null;
            $_alarm->sent_status = Tinebase_Model_Alarm::STATUS_PENDING;
        }

        if ($_record->rrule && $_alarm->sent_status == Tinebase_Model_Alarm::STATUS_PENDING && $_alarm->alarm_time < $_alarm->sent_time) {
            $this->adoptAlarmTime($_record, $_alarm, 'instance');
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ .
            ' alarm: ' . print_r($_alarm->toArray(), true));
    }
    
    /****************************** overwritten functions ************************/
    
    /**
     * restore original alarm time of recurring events
     * 
     * @param Tinebase_Record_Interface $_record
     * @return void
     */
    protected function _inspectAlarmGet(Tinebase_Record_Interface $_record)
    {
        foreach ($_record->alarms as $alarm) {
            if ($recurid = $alarm->getOption('recurid')) {
                $alarm->alarm_time = clone $_record->dtstart;
                $alarm->alarm_time->subMinute((int) $alarm->getOption('minutes_before'));
            }
        }
        
        parent::_inspectAlarmGet($_record);
    }
    
    /**
     * adopt alarm time to next occurance for recurring events
     * 
     * @param Tinebase_Record_Interface $_record
     * @param Tinebase_Model_Alarm $_alarm
     * @param bool $_nextBy {instance|time} set recurr alarm to next from given instance or next by current time
     * @return void
     * @throws Tinebase_Exception_InvalidArgument
     */
    protected function _inspectAlarmSet(Tinebase_Record_Interface $_record, Tinebase_Model_Alarm $_alarm, $_nextBy = 'time')
    {
        parent::_inspectAlarmSet($_record, $_alarm);
        $this->adoptAlarmTime($_record, $_alarm, 'time');
    }

    /**
     * inspect creation of one record (before create)
     *
     * @param   Tinebase_Record_Interface $_record
     * @return  void
     */
    protected function _inspectBeforeCreate(Tinebase_Record_Interface $_record)
    {
        Calendar_Controller_Poll::getInstance()->inspectBeforeCreateEvent($_record);
        parent::_inspectBeforeCreate($_record);
    }

    /**
     * inspect update of one record
     * 
     * @param   Calendar_Model_Event $_record      the update record
     * @param   Calendar_Model_Event $_oldRecord   the current persistent record
     * @return  void
     */
    protected function _inspectBeforeUpdate($_record, $_oldRecord)
    {
        if ($this->_skipRecurAdoptions) {
            return;
        }

        if (empty($_record->recurid) && !empty($_record->rrule) && (string)$_record->rrule === (string)$_oldRecord->rrule) {
            $baseEventUnchangedRrule = true;
        } else {
            $baseEventUnchangedRrule = false;
        }

        $oldDtStartOrigTz = $_oldRecord->dtstart->getClone()->setTimezone($_oldRecord->originator_tz);
        $dtStartOrigTz = $_record->dtstart->getClone()->setTimezone($_record->originator_tz);

        // if dtstart of an event changes, we update the originator_tz, alarm times
        if (! $_oldRecord->dtstart->equals($_record->dtstart)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' dtstart changed -> adopting organizer_tz');
            $_record->originator_tz = Tinebase_Core::getUserTimezone();
            $dtStartOrigTz->setTimezone($_record->originator_tz);

            if ($baseEventUnchangedRrule) {
                $diff = $oldDtStartOrigTz->diff($dtStartOrigTz);

                if ($_record->rrule->until instanceof Tinebase_DateTime) {
                    $_record->rrule->until->modifyTime($diff);
                }

                if ($dtStartOrigTz->compare($oldDtStartOrigTz, 'H:i:s') !== 0) {
                    // we are in a base event & dtstart changed the time! & the rrule was not changed
                    $this->_updateRecurIdOfExdates($_record, $diff);
                }
            }
        }
        
        // delete recur exceptions if update is no longer a recur series
        if (! empty($_oldRecord->rrule) && empty($_record->rrule)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' deleting recur exceptions as event is no longer a recur series');
            $this->_backend->delete($this->getRecurExceptions($_record));
        }
        
        // touch base event of a recur series if an persistent exception changes
        if ($_record->recurid) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' touch base event of a persistent exception');
            $baseEvent = $this->getRecurBaseEvent($_record);
            $this->_touch($baseEvent, TRUE);
        }

        if ($baseEventUnchangedRrule && $dtStartOrigTz->compare($oldDtStartOrigTz, 'Y-m-d') !== 0) {
            // we are in a base event & dtstart changed the date & the rrule was not changed
            // if easy rrule, try to adapt
            if (! $_record->rrule instanceof Calendar_Model_Rrule) {
                $_record->rrule = new Calendar_Model_Rrule($_record->rrule);
            }
            if ($this->_updateRruleBasedOnDtstartChange($_record->rrule, $dtStartOrigTz, $oldDtStartOrigTz)) {
                $this->_validateAndFixExdatesVsRrule($_record);
            }
        }

        // baseevent with changed rrule -> validate exceptions
        if (empty($_record->recurid) && !empty($_record->rrule) && (string)$_record->rrule !== (string)$_oldRecord->rrule) {
            $this->_validateAndFixExdatesVsRrule($_record);
        }

        Calendar_Controller_Poll::getInstance()->inspectBeforeUpdateEvent($_record, $_oldRecord);
    }

    /**
     * @param Calendar_Model_Event $_event
     * @return bool true if something changed, otherwise false
     * @throws Tinebase_Exception
     * @throws Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    protected function _validateAndFixExdatesVsRrule(Calendar_Model_Event $_event)
    {
        $_event->sortExdates();
        $persistentExceptions = $this->getRecurExceptions($_event);

        if (empty($_event->exdate) && $persistentExceptions->count() === 0) {
            return false;
        }
        $changed = false;

        $exdates = $_event->exdate ?: [];
        $_event->exdate = [];
        if (!empty($exdates)) {
            $endDate = clone end($exdates);
        } else {
            $endDate = $_event->dtstart;
        }
        /** @var Calendar_Model_Event $exception */
        foreach ($persistentExceptions as $exception) {
            if ($endDate->isEarlier($exception->getOriginalDtStart())) {
                $endDate = $exception->getOriginalDtStart();
            }
        }
        $endDate->addDay(1);

        $exceptions = new Tinebase_Record_RecordSet(Calendar_Model_Event::class);
        $recurDates = [$_event->dtstart->toString() => true];
        /** @var Calendar_Model_Event $recur */
        foreach (Calendar_Model_Rrule::computeRecurrenceSet($_event, $exceptions, $_event->dtstart, $endDate) as $recur) {
            $recurDates[$recur->dtstart->toString()] = true;
        }
        foreach ($exdates as $key => $value) {
            if (!isset($recurDates[$value->toString()])) {
                $changed = true;
                unset($exdates[$key]);
            }
        }
        $toDelete = [];
        foreach ($persistentExceptions as $exception) {
            if (!isset($recurDates[$exception->getOriginalDtStart()->toString()])) {
                $toDelete[] = $exception->getId();
            }
        }
        if (!empty($toDelete)) {
            $changed = true;
            $this->delete($toDelete);
        }
        $_event->exdate = $exdates;

        return $changed;
    }

    /**
     * @param Calendar_Model_Rrule $rrule
     * @param Tinebase_DateTime $newDtstart
     * @param Tinebase_DateTime $oldDtstart
     * @return bool whether rrule was changed (true) or not (false)
     * @throws Tinebase_Exception_SystemGeneric
     */
    protected function _updateRruleBasedOnDtstartChange(Calendar_Model_Rrule $rrule, Tinebase_DateTime $newDtstart,
        Tinebase_DateTime $oldDtstart)
    {
        $success = false;
        $changed = false;

        switch($rrule->freq)
        {
            case Calendar_Model_Rrule::FREQ_DAILY:
                // nothing to do, it's a success case
                $success = true;
                break;

            case Calendar_Model_Rrule::FREQ_WEEKLY:
                // we need bydays
                if (empty($rrule->byday)) {
                    break;
                }
                $byDay = explode(',', $rrule->byday);
                $oldByDay = Calendar_Model_Rrule::$WEEKDAY_MAP_REVERSE[strtolower($oldDtstart->format('D'))];
                $newByDay = Calendar_Model_Rrule::$WEEKDAY_MAP_REVERSE[strtolower($newDtstart->format('D'))];

                // check old dtstart matches a byday, if not, we abort
                if (!in_array($oldByDay, $byDay)) {
                    break;
                }
                // in case the day of week didn't change, nothing to do (+ it's a success case too)
                if ($oldByDay === $newByDay) {
                    $success = true;
                    break;
                }

                $byDay = array_filter($byDay, function ($val) use ($oldByDay) {
                    return $val !== $oldByDay;
                });
                $byDay[] = Calendar_Model_Rrule::$WEEKDAY_MAP_REVERSE[strtolower($newDtstart->format('D'))];
                usort($byDay, function ($a, $b) {
                    return $a === $b ? 0 :
                        (Calendar_Model_Rrule::$WEEKDAY_DIGIT_MAP[$a] > Calendar_Model_Rrule::$WEEKDAY_DIGIT_MAP[$b] ?
                            1 : -1);
                });

                $rrule->byday = join(',', $byDay);
                $success = true;
                $changed = true;
                break;

            case Calendar_Model_Rrule::FREQ_MONTHLY:
                // we need a day specification
                if (empty($rrule->byday) && empty($rrule->bymonthday)) {
                    break;
                }
                // only do simple rules, byday combined with bymonthday is not supported
                // also we only support simple byday rules
                if (!empty($rrule->byday) && (!empty($rrule->bymonthday) || strpos($rrule->byday, ',') !== false)) {
                    break;
                }

                if (!empty($rrule->byday)) {

                    $bydayPrefix = intval($rrule->byday);
                    $byday = substr($rrule->byday, -2);

                    // if we dont have a quantifier we abort
                    if ($bydayPrefix === 0) {
                        break;
                    }

                    // check old dtstart matches byday, if not we abort
                    if (strtolower($oldDtstart->format('D')) !== Calendar_Model_Rrule::$WEEKDAY_MAP[$byday]) {
                        break;
                    }

                    $dtstartJ = $oldDtstart->format('j');
                    // check old dtstart matches bydayPrefix, if not we abort
                    if ($bydayPrefix === -1) {
                        if ($oldDtstart->format('t') - $dtstartJ > 6) {
                            break;
                        }
                    } else {
                        if ($dtstartJ - (($bydayPrefix-1)*7) > 6 || $dtstartJ - (($bydayPrefix-1)*7) < 1) {
                            break;
                        }
                    }

                    if ($newDtstart->format('j') > 28 || ($bydayPrefix === -1 && $newDtstart->format('t') - $newDtstart->format('j') < 7)) {
                        // keep -1 => last X
                        $prefix = '-1';
                    } else {
                        $prefix = floor(($newDtstart->format('j') - 1) / 7) + 1;
                    }

                    $rrule->byday = $prefix . Calendar_Model_Rrule::$WEEKDAY_MAP_REVERSE[strtolower($newDtstart->format('D'))];
                    $success = true;
                    $changed = true;

                } else {

                    $byMonthDay = explode(',', $rrule->bymonthday);
                    $oldMonthDay = $oldDtstart->format('j');
                    $newMonthDay = $newDtstart->format('j');

                    // check old dtstart is in bymonthday, if not we abort
                    if (!in_array($oldMonthDay, $byMonthDay)) {
                        break;
                    }
                    // if monthday did not change, nothing to do, it's a success case
                    if ($oldMonthDay === $newMonthDay) {
                        $success = true;
                        break;
                    }

                    $byMonthDay = array_filter($byMonthDay, function ($val) use ($oldMonthDay) {
                        return $val !== $oldMonthDay;
                    });
                    $byMonthDay[] = $newMonthDay;
                    asort($byMonthDay);

                    $rrule->bymonthday = join(',', $byMonthDay);
                    $success = true;
                    $changed = true;
                }

                break;

            case Calendar_Model_Rrule::FREQ_YEARLY:
                // we need a day specification and a month
                if (empty($rrule->bymonth) || (empty($rrule->byday) && empty($rrule->bymonthday))) {
                    break;
                }
                // only do simple rules, byday combined with bymonthday is not supported
                if (!empty($rrule->byday) && !empty($rrule->bymonthday)) {
                    break;
                }

                if (!empty($rrule->byday)) {
                    // only do simple rules
                    if (strpos($rrule->byday, ',') !== false) {
                        break;
                    }

                    $bydayPrefix = intval($rrule->byday);
                    $byday = substr($rrule->byday, -2);

                    // if we dont have a quantifier we abort
                    if ($bydayPrefix === 0) {
                        break;
                    }

                    // check old dtstart matches byday, if not we abort
                    if (strtolower($oldDtstart->format('D')) !== Calendar_Model_Rrule::$WEEKDAY_MAP[$byday]
                            || $oldDtstart->format('n') != $rrule->bymonth) {
                        break;
                    }

                    $dtstartJ = $oldDtstart->format('j');
                    // check old dtstart matches bydayPrefix, if not we abort
                    if ($bydayPrefix === -1) {
                        if ($oldDtstart->format('t') - $dtstartJ > 6) {
                            break;
                        }
                    } else {
                        if ($dtstartJ - (($bydayPrefix-1)*7) > 6 || $dtstartJ - (($bydayPrefix-1)*7) < 1) {
                            break;
                        }
                    }

                    if ($newDtstart->format('j') > 28 || ($bydayPrefix === -1 && $newDtstart->format('t') - $newDtstart->format('j') < 7)) {
                        // keep -1 => last X
                        $prefix = '-1';
                    } else {
                        $prefix = floor(($newDtstart->format('j') - 1) / 7) + 1;
                    }

                    $rrule->byday = $prefix . Calendar_Model_Rrule::$WEEKDAY_MAP_REVERSE[strtolower($newDtstart->format('D'))];
                    $rrule->bymonth = $newDtstart->format('n');
                    $success = true;
                    $changed = true;

                } else {
                    // only do simple rules
                    if (strpos($rrule->bymonthday, ',') !== false ||
                        // check old dtstart matches the date
                        $oldDtstart->format('j') != $rrule->bymonthday || $oldDtstart->format('n') != $rrule->bymonth
                    ) {
                        break;
                    }

                    $rrule->bymonthday = $newDtstart->format('j');
                    $rrule->bymonth = $newDtstart->format('n');
                    $success = true;
                    $changed = true;
                }

                break;
        }

        if (!$success) {
            // _('The new recurrence rule is unpredictable. Please choose a valid recurrence rule')
            throw new Tinebase_Exception_SystemGeneric(
                'The new recurrence rule is unpredictable. Please choose a valid recurrence rule');
        }

        return $changed;
    }
    
    /**
     * update exdates and recurids if dtstart of an recurevent changes
     * 
     * @param Calendar_Model_Event $_record
     * @param DateInterval $diff
     */
    protected function _updateRecurIdOfExdates($_record, $diff)
    {
        // update exceptions
        $exceptions = $this->getRecurExceptions($_record);
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' dtstart of a series changed -> adopting '. count($exceptions) . ' recurid(s)');

        foreach ($exceptions as $exception) {
            $exception->recurid = new Tinebase_DateTime(substr($exception->recurid, -19));
            $exception->recurid->modifyTime($diff);

            $exception->setRecurId($_record->getId());
            $this->_backend->update($exception);
        }

        if (is_array($_record->exdate)) {
            foreach ($_record->exdate as $exdate) {
                $exdate->modifyTime($diff);
            }
        }

        $missingExdates = array_diff($exceptions->getOriginalDtStart(), (array)$_record->exdate);
        $_record->exdate = array_merge((array)$_record->exdate, $missingExdates);
    }
    
    /**
     * inspect before create/update
     * 
     * @TODO move stuff from other places here
     * @param   Calendar_Model_Event $_record      the record to inspect
     */
    protected function _inspectEvent($_record, $skipEvent = false)
    {
        if ($this->_doContainerACLChecks && Tinebase_Core::isReplicationSlave() && $_record->isReplicable()) {
            throw new Tinebase_Exception_AccessDenied('replicatable events are read-only on the slaves!');
        }

        $_record->uid = $_record->uid ? $_record->uid : Tinebase_Record_Abstract::generateUID();
        $_record->organizer = $_record->organizer ? $_record->organizer : Tinebase_Core::getUser()->contact_id;
        $_record->transp = $_record->transp ? $_record->transp : Calendar_Model_Event::TRANSP_OPAQUE;

        $this->_inspectOriginatorTZ($_record);

        if ($_record->hasExternalOrganizer()) {
            // assert calendarUser as attendee. This is important to keep the event in the loop via its displaycontianer(s)
            try {
                $container = Tinebase_Container::getInstance()->getContainerById($_record->container_id);
                $owner = $container->getOwner();
                $calendarUserId = Addressbook_Controller_Contact::getInstance()->getContactByUserId($owner, true)->getId();
            } catch (Exception $e) {
                $container = NULL;
                $calendarUserId = Tinebase_Core::getUser()->contact_id;
            }
            
            $attendee = $_record->assertAttendee(new Calendar_Model_Attender(array(
                'user_type'    => Calendar_Model_Attender::USERTYPE_USER,
                'user_id'      => $calendarUserId
            )), false, false, true);
            
            if ($attendee && $container instanceof Tinebase_Model_Container) {
                $attendee->displaycontainer_id = $container->getId();
            }
            
            if (! $container instanceof Tinebase_Model_Container || $container->type == Tinebase_Model_Container::TYPE_PERSONAL) {
                // move into special (external users) container
                $container = Calendar_Controller::getInstance()->getInvitationContainer($_record->resolveOrganizer());
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Setting container_id to ' . $container->getId() . ' for external organizer ' . $_record->organizer->email);
                $_record->container_id = $container->getId();
            }
            
        }
        
        if ($_record->is_all_day_event) {
            // harmonize datetimes of all day events
            $_record->setTimezone($_record->originator_tz);
            if (! $_record->dtend) {
                $_record->dtend = clone $_record->dtstart;
                $_record->dtend->setTime(23,59,59);
            }
            $_record->dtstart->setTime(0,0,0);
            $_record->dtend->setTime(23,59,59);
            $_record->setTimezone('UTC');
        }
        $_record->setRruleUntil();
        
        if ($_record->rrule instanceof Calendar_Model_Rrule) {
            $_record->rrule->normalize($_record);
        }

        if ($_record->isRecurException()) {
            $_record->rrule = NULL;
            $_record->rrule_constraints = NULL;

//            // Maybe Later
//            $baseEvent = $this->getRecurBaseEvent($_record);
//            // exdates needs to stay in baseEvents container
//            if($_record->container_id != $baseEvent->container_id) {
//                throw new Calendar_Exception_ExdateContainer();
//            }
        }

        // inspect rrule_constraints
        if ($_record->rrule_constraints) {
            $this->setConstraintsExdates($_record);
        }

        if (!$skipEvent) {
            Tinebase_Record_PersistentObserver::getInstance()->fireEvent(new Calendar_Event_InspectEvent(array(
                'observable' => $_record
            )));
        }
    }

    /**
     * checks/sets originator timezone
     *
     * @param $record
     * @throws Tinebase_Exception_Record_Validation
     */
    protected function _inspectOriginatorTZ($record)
    {
        $record->originator_tz = $record->originator_tz ? $record->originator_tz : Tinebase_Core::getUserTimezone();

        try {
            new DateTimeZone($record->originator_tz);
        } catch (Exception $e) {
            throw new Tinebase_Exception_Record_Validation('Bad Timezone: ' . $record->originator_tz);
        }
    }
    
    /**
     * inspects delete action
     *
     * @param array $_ids
     * @return array of ids to actually delete
     */
    protected function _inspectDelete(array $_ids) {
        $events = $this->_backend->getMultiple($_ids);
        
        foreach ($events as $event) {

            if ($this->_doContainerACLChecks && Tinebase_Core::isReplicationSlave() && $event->isReplicable()) {
                throw new Tinebase_Exception_AccessDenied('replicatable events are read-only on the slaves!');
            }

            // implicitly delete persistent recur instances of series
            if (! empty($event->rrule)) {
                $exceptions = $this->getRecurExceptions($event);
                // if we have grants to delete base event -> force grants on all recur exceptions too
                $exceptions->class = Calendar_Model_Event::CLASS_PUBLIC;
                $exceptions->{Tinebase_Model_Grants::GRANT_DELETE} = true;
                $events->merge($exceptions);

                $exceptionIds = $exceptions->getId();
                $_ids = array_merge($_ids, $exceptionIds);
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Implicitly deleting ' . $exceptions->count() . ' persistent exception(s) for recurring series with uid' . $event->uid);
            }

            // TODO make this undoable!
            Tinebase_Record_PersistentObserver::getInstance()->fireEvent(new Calendar_Event_InspectDeleteEvent([
                'observable' => $event,
                'deletedIds' => $_ids
            ]));
        }
        Calendar_Controller_Poll::getInstance()->inspectDeleteEvents($events);
        
        return $events;
    }
    
    /**
     * redefine required grants for get actions
     * 
     * @param Tinebase_Model_Filter_FilterGroup $_filter
     * @param string $_action get|update
     */
    public function checkFilterACL(Tinebase_Model_Filter_FilterGroup $_filter, $_action = 'get')
    {
        $hasGrantsFilter = FALSE;
        foreach($_filter->getAclFilters() as $aclFilter) {
            if ($aclFilter instanceof Calendar_Model_GrantFilter) {
                $hasGrantsFilter = TRUE;
                break;
            }
        }
        
        if (! $hasGrantsFilter && $this->_doContainerACLChecks) {
            // force a grant filter
            // NOTE: actual grants are set via setRequiredGrants later
            $grantsFilter = $_filter->createFilter('grants', 'in', '@setRequiredGrants');
            $_filter->addFilter($grantsFilter);
        }
        
        parent::checkFilterACL($_filter, $_action);
        
        if ($_action == 'get') {
            $_filter->setRequiredGrants(array(
                Calendar_Model_EventPersonalGrants::GRANT_FREEBUSY,
                Tinebase_Model_Grants::GRANT_READ,
                Tinebase_Model_Grants::GRANT_ADMIN,
            ));
        }
    }
    
    /**
     * check grant for action (CRUD)
     *
     * @param Tinebase_Record_Interface $_record
     * @param string $_action
     * @param boolean $_throw
     * @param string $_errorMessage
     * @param Tinebase_Record_Interface $_oldRecord
     * @return boolean
     * @throws Tinebase_Exception_AccessDenied
     * 
     * @todo use this function in other create + update functions
     * @todo invent concept for simple adding of grants (plugins?) 
     */
    protected function _checkGrant($_record, $_action, $_throw = TRUE, $_errorMessage = 'No Permission.', $_oldRecord = NULL)
    {
        if (    ! $this->_doContainerACLChecks 
            // admin grant includes all others (only if class is PUBLIC)
            ||  (($_record->class === Calendar_Model_Event::CLASS_PUBLIC || $_action === self::ACTION_DELETE)
                && $_record->container_id && Tinebase_Core::getUser()->hasGrant($_record->container_id, Tinebase_Model_Grants::GRANT_ADMIN))
            // external invitations are in a spechial invitaion calendar. only attendee can see it via displaycal
            ||  $_record->hasExternalOrganizer()
        ) {
            return true;
        }
        
        switch ($_action) {
            case 'get':
                // NOTE: free/busy is not a read grant!
                $hasGrant = $_record->hasGrant(Tinebase_Model_Grants::GRANT_READ);
                break;
            case 'create':
                $hasGrant = Tinebase_Core::getUser()->hasGrant($_record->container_id, Tinebase_Model_Grants::GRANT_ADD);
                break;
            case 'update':
                $hasGrant = (bool) $_oldRecord->hasGrant(Tinebase_Model_Grants::GRANT_EDIT);
                
                if ($_oldRecord->container_id != $_record->container_id) {
                    $hasGrant &= Tinebase_Core::getUser()->hasGrant($_record->container_id, Tinebase_Model_Grants::GRANT_ADD)
                                 && $_oldRecord->hasGrant(Tinebase_Model_Grants::GRANT_DELETE);
                }
                break;
            case 'delete':
                $hasGrant = (bool) $_record->hasGrant(Tinebase_Model_Grants::GRANT_DELETE);
                break;
            case 'sync':
                $hasGrant = (bool) $_record->hasGrant(Tinebase_Model_Grants::GRANT_SYNC);
                break;
            case 'export':
                $hasGrant = (bool) $_record->hasGrant(Tinebase_Model_Grants::GRANT_EXPORT);
                break;
        }

        if (! $hasGrant && 'get' === $_action) {
            $_record->doFreeBusyCleanup();
        }

        if (! $hasGrant) {
            if ($_throw) {
                throw new Tinebase_Exception_AccessDenied($_errorMessage);
            } else {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' No permissions to ' . $_action . ' in container ' . $_record->container_id);
            }
        }
        
        return $hasGrant;
    }
    
    /**
     * touches (sets seq, last_modified_time and container content sequence) given event
     * 
     * @param  $_event
     * @return void
     */
    protected function _touch($_event, $_setModifier = FALSE)
    {
        $_event->last_modified_time = Tinebase_DateTime::now();
        $_event->seq = (int)$_event->seq + 1;
        if ($_setModifier) {
            $_event->last_modified_by = Tinebase_Core::getUser()->getId();
        }
        
        $this->_backend->update($_event);
        
        $this->_increaseContainerContentSequence($_event, Tinebase_Model_ContainerContent::ACTION_UPDATE);
    }
    
    /**
     * increase container content sequence
     *
     * @param Tinebase_Record_Interface $_record
     * @param string $action
     */
    protected function _increaseContainerContentSequence(Tinebase_Record_Interface $record, $action = NULL)
    {
        parent::_increaseContainerContentSequence($record, $action);
        
        if ($record->attendee instanceof Tinebase_Record_RecordSet) {
            $updatedContainerIds = array($record->container_id);
            foreach ($record->attendee as $attender) {
                if (isset($attender->displaycontainer_id) && ! in_array($attender->displaycontainer_id, $updatedContainerIds)) {
                    Tinebase_Container::getInstance()->increaseContentSequence($attender->displaycontainer_id, $action, $record->getId());
                    $updatedContainerIds[] = $attender->displaycontainer_id;
                }
            }
        }
    }
    
    
    /****************************** attendee functions ************************/
    
    /**
     * creates an attender status exception of a recurring event series
     * 
     * NOTE: Recur exceptions are implicitly created
     *
     * @param  Calendar_Model_Event    $_recurInstance
     * @param  Calendar_Model_Attender $_attender
     * @param  string                  $_authKey
     * @param  bool                    $_allFollowing
     * @return Calendar_Model_Attender updated attender
     */
    public function attenderStatusCreateRecurException($_recurInstance, $_attender, $_authKey, $_allFollowing = FALSE)
    {
        try {
            $db = $this->_backend->getAdapter();
            $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction($db);
            
            $baseEvent = $this->getRecurBaseEvent($_recurInstance);
            $baseEventAttendee = Calendar_Model_Attender::getAttendee($baseEvent->attendee, $_attender);
            
            if ($baseEvent->getId() == $_recurInstance->getId()) {
                // exception to the first occurence
                $_recurInstance->setRecurId($baseEvent->getId());
            }
            
            // NOTE: recurid is computed by rrule recur computations and therefore is already part of the event.
            if (empty($_recurInstance->recurid)) {
                throw new Exception('recurid must be present to create exceptions!');
            }
            
            try {
                // check if we already have a persistent exception for this event
                $eventInstance = $this->_backend->getByProperty($_recurInstance->recurid, $_property = 'recurid');
                
                // NOTE: the user must exist (added by someone with appropriate rights by createRecurException)
                $exceptionAttender = Calendar_Model_Attender::getAttendee($eventInstance->attendee, $_attender);
                if (! $exceptionAttender) {
                    throw new Tinebase_Exception_AccessDenied('not an attendee');
                }
                
                
                if ($exceptionAttender->status_authkey != $_authKey) {
                    // NOTE: it might happen, that the user set her status from the base event without knowing about 
                    //       an existing exception. In this case the base event authkey is also valid
                    if (! $baseEventAttendee || $baseEventAttendee->status_authkey != $_authKey) {
                        throw new Tinebase_Exception_AccessDenied('Attender authkey mismatch');
                    }
                }
                
            } catch (Tinebase_Exception_NotFound $e) {
                // otherwise create it implicilty
                
                // check if this intance takes place
                if (in_array($_recurInstance->dtstart, (array)$baseEvent->exdate)) {
                    throw new Tinebase_Exception_AccessDenied('Event instance is deleted and may not be recreated via status setting!');
                }
                
                if (! $baseEventAttendee) {
                    throw new Tinebase_Exception_AccessDenied('not an attendee');
                }
                
                if ($baseEventAttendee->status_authkey != $_authKey) {
                    throw new Tinebase_Exception_AccessDenied('Attender authkey mismatch');
                }
                
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " creating recur exception for a exceptional attendee status");
                
                $doContainerAclChecks = $this->doContainerACLChecks(FALSE);
                $sendNotifications = $this->sendNotifications(FALSE);
                
                // NOTE: the user might have no edit grants, so let's be carefull
                $diff = $baseEvent->dtstart->diff($baseEvent->dtend);
                
                $baseEvent->dtstart = new Tinebase_DateTime(substr($_recurInstance->recurid, -19), 'UTC');
                $baseEvent->dtend   = clone $baseEvent->dtstart;
                $baseEvent->dtend->add($diff);

                $baseEvent->base_event_id = $baseEvent->id;
                $baseEvent->id = $_recurInstance->id;
                $baseEvent->recurid = $_recurInstance->recurid;

                $attendee = $baseEvent->attendee;
                unset($baseEvent->attendee);
                
                $eventInstance = $this->createRecurException($baseEvent, FALSE, $_allFollowing);
                $eventInstance->attendee = new Tinebase_Record_RecordSet('Calendar_Model_Attender');
                $this->doContainerACLChecks($doContainerAclChecks);
                $this->sendNotifications($sendNotifications);
                
                foreach ($attendee as $attender) {
                    $attender->setId(NULL);
                    $attender->cal_event_id = $eventInstance->getId();
                    
                    $attender = $this->_backend->createAttendee($attender);
                    $eventInstance->attendee->addRecord($attender);
                    $this->_increaseDisplayContainerContentSequence($attender, $eventInstance, Tinebase_Model_ContainerContent::ACTION_CREATE);
                }
                
                $exceptionAttender = Calendar_Model_Attender::getAttendee($eventInstance->attendee, $_attender);
            }
            
            $exceptionAttender->status = $_attender->status;
            $exceptionAttender->transp = $_attender->transp;
            $eventInstance->alarms     = clone $_recurInstance->alarms;
            $eventInstance->alarms->setId(NULL);
            
            $updatedAttender = $this->attenderStatusUpdate($eventInstance, $exceptionAttender, $exceptionAttender->status_authkey);
            
            Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
        } catch (Exception $e) {
            Tinebase_TransactionManager::getInstance()->rollBack();
            throw $e;
        }
        
        return $updatedAttender;
    }
    
    /**
     * updates an attender status of a event
     * 
     * @param  Calendar_Model_Event    $_event
     * @param  Calendar_Model_Attender $_attender
     * @param  string                  $_authKey
     * @return Calendar_Model_Attender updated attender
     */
    public function attenderStatusUpdate(Calendar_Model_Event $_event, Calendar_Model_Attender $_attender, $_authKey)
    {
        try {
            $event = $this->get($_event->getId());
            $oldEvent = clone $event;

            if (! $event->attendee) {
                throw new Tinebase_Exception_NotFound('Could not find any attendee of event.');
            }
            
            if (($currentAttender = Calendar_Model_Attender::getAttendee($event->attendee, $_attender)) == null) {
                throw new Tinebase_Exception_NotFound('Could not find attender in event.');
            }
            
            $updatedAttender = clone $currentAttender;
            
            if ($currentAttender->status_authkey !== $_authKey) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) 
                    Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " no permissions to update status for {$currentAttender->user_type} {$currentAttender->user_id}");
                return $updatedAttender;
            }
            
            Calendar_Controller_Alarm::enforceACL($_event, $event);
            
            $currentAttenderDisplayContainerId = $currentAttender->displaycontainer_id instanceof Tinebase_Model_Container ? 
                $currentAttender->displaycontainer_id->getId() : 
                $currentAttender->displaycontainer_id;
            
            $attenderDisplayContainerId = $_attender->displaycontainer_id instanceof Tinebase_Model_Container ? 
                $_attender->displaycontainer_id->getId() : 
                $_attender->displaycontainer_id;
            
            // check if something what can be set as user has changed
            if ($currentAttender->status == $_attender->status &&
                $currentAttenderDisplayContainerId  == $attenderDisplayContainerId   &&
                $currentAttender->transp            == $_attender->transp            &&
                ! Calendar_Controller_Alarm::hasUpdates($_event, $event)
            ) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) 
                    Tinebase_Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__ . "no status change -> do nothing");
                return $updatedAttender;
            }
            
            $updatedAttender->status              = $_attender->status;
            $updatedAttender->displaycontainer_id = isset($_attender->displaycontainer_id) ? $_attender->displaycontainer_id : $updatedAttender->displaycontainer_id;
            $updatedAttender->transp              = isset($_attender->transp) ? $_attender->transp : Calendar_Model_Event::TRANSP_OPAQUE;
            $updatedAttender->xprops              = $_attender->xprops;
            
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) {
                Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . " update attender status to {$_attender->status} for {$currentAttender->user_type}-{$currentAttender->user_id}");
                Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' set alarm_ack_time / alarm_snooze_time: ' . $updatedAttender->alarm_ack_time . ' / ' . $updatedAttender->alarm_snooze_time);
            }
            
            $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());
            
            $updatedAttender = $this->_backend->updateAttendee($updatedAttender);
            if ($_event->alarms instanceof Tinebase_Record_RecordSet) {
                foreach($_event->alarms as $alarm) {
                    $this->_inspectAlarmSet($event, $alarm);
                }
                
                Tinebase_Alarm::getInstance()->setAlarmsOfRecord($_event);
            }

            $event->attendee->removeRecord($currentAttender);
            $event->attendee->addRecord($updatedAttender);

            $this->_increaseDisplayContainerContentSequence($updatedAttender, $event);

            Tinebase_Record_PersistentObserver::getInstance()->fireEvent(new Calendar_Event_InspectEvent(array(
                'observable' => $event
            )));

            // touch event to persist data changed by observers
            $this->_touch($event, true);

            Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
        } catch (Exception $e) {
            Tinebase_TransactionManager::getInstance()->rollBack();
            throw $e;
        }
        
        // send notifications
        if ($currentAttender->status != $updatedAttender->status && $this->_sendNotifications) {
            $updatedEvent = $this->get($event->getId());
            $updatedEvent->mute = $_event->mute;
            $this->doSendNotifications($updatedEvent, Tinebase_Core::getUser(), 'changed', $oldEvent);
        }
        
        return $updatedAttender;
    }
    
    /**
     * saves all attendee of given event
     * 
     * NOTE: This function is executed in a create/update context. As such the user
     *       has edit/update the event and can do anything besides status settings of attendee
     * 
     * @todo add support for resources
     * 
     * @param Calendar_Model_Event $_event
     * @param Calendar_Model_Event $_currentEvent
     * @param bool                 $_isRescheduled event got rescheduled reset all attendee status
     */
    protected function _saveAttendee($_event, $_currentEvent = NULL, $_isRescheduled = FALSE)
    {
        if (! $_event->attendee instanceof Tinebase_Record_RecordSet) {
            $_event->attendee = new Tinebase_Record_RecordSet('Calendar_Model_Attender');
        }
        
        Calendar_Model_Attender::resolveEmailOnlyAttendee($_event);
        
        $_event->attendee->cal_event_id = $_event->getId();
        
        Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " About to save attendee for event {$_event->id} ");
        
        $currentAttendee = $_currentEvent->attendee;

        $diff = Calendar_Model_Attender::getMigration($currentAttendee, $_event->attendee);

        $calendar = Tinebase_Container::getInstance()->getContainerById($_event->container_id);
        
        // delete attendee
        $toDeleteIds = $diff['toDelete']->getArrayOfIds();
        $this->_backend->deleteAttendee($toDeleteIds);
        foreach ($toDeleteIds as $deleteAttenderId) {
            $idx = $currentAttendee->getIndexById($deleteAttenderId);
            if ($idx !== FALSE) {
                $currentAttenderToDelete = $currentAttendee[$idx];
                $this->_increaseDisplayContainerContentSequence($currentAttenderToDelete, $_event, Tinebase_Model_ContainerContent::ACTION_DELETE);
            }
        }

        foreach ($diff['toCreate'] as $attender) {
            $this->_createAttender($attender, $_event, FALSE, $calendar);
        }

        foreach ($diff['toUpdate'] as $attender) {
            ($currentAttender = $currentAttendee->getById($attender->getId())) ?:
                ($currentAttender = Calendar_Model_Attender::getAttendee($currentAttendee, $attender));
            $this->_updateAttender($attender, $currentAttender, $_event, $_isRescheduled, $calendar);
        }
    }

    /**
     * creates a new attender
     * 
     * @param Calendar_Model_Attender  $attender
     * @param Tinebase_Model_Container $_calendar
     * @param boolean $preserveStatus
     * @param Tinebase_Model_Container $calendar
     */
    protected function _createAttender(Calendar_Model_Attender $attender, Calendar_Model_Event $event, $preserveStatus = FALSE, Tinebase_Model_Container $calendar = NULL)
    {
        // apply defaults
        $attender->id                = null;
        $attender->user_type         = isset($attender->user_type) ? $attender->user_type : Calendar_Model_Attender::USERTYPE_USER;
        $attender->cal_event_id      =  $event->getId();
        $calendar = ($calendar) ? $calendar : Tinebase_Container::getInstance()->getContainerById($event->container_id);
        
        $userAccountId = $attender->getUserAccountId();
        
        // generate auth key
        if (! $attender->status_authkey) {
            $attender->status_authkey = Tinebase_Record_Abstract::generateUID();
        }
        
        // attach to display calendar if attender has/is a useraccount
        if ($userAccountId) {
            if ($calendar->type == Tinebase_Model_Container::TYPE_PERSONAL && Tinebase_Container::getInstance()->hasGrant($userAccountId, $calendar, Tinebase_Model_Grants::GRANT_ADMIN)) {
                // if attender has admin grant to (is owner of) personal physical container, this phys. cal also gets displ. cal
                $attender->displaycontainer_id = $calendar->getId();
            } else if ($attender->displaycontainer_id && $userAccountId == Tinebase_Core::getUser()->getId() && Tinebase_Container::getInstance()->hasGrant($userAccountId, $attender->displaycontainer_id, Tinebase_Model_Grants::GRANT_ADMIN)) {
                // allow user to set his own displ. cal
            } else {
                $displayCalId = self::getDefaultDisplayContainerId($userAccountId);
                $attender->displaycontainer_id = $displayCalId;
            }

        } else if ($attender->user_type === Calendar_Model_Attender::USERTYPE_RESOURCE) {
            // we may have only invite grant, but no read grant...
            $resourceController = Calendar_Controller_Resource::getInstance();
            $oldResourceAclCheck = $resourceController->doContainerACLChecks(false);
            try {
                $resource = $resourceController->get($attender->user_id);
                if (! Tinebase_Container::getInstance()->hasGrant(Tinebase_Core::getUser(), $resource->container_id,
                        Calendar_Model_ResourceGrants::RESOURCE_INVITE)) {
                    throw new Tinebase_Exception_AccessDenied('you do not have permission to invite this resource');
                }
            } finally {
                $resourceController->doContainerACLChecks($oldResourceAclCheck);
            }
            $attender->displaycontainer_id = $resource->container_id;
        }
        
        if ($attender->displaycontainer_id && !$this->_keepAttenderStatus) {
            // check if user is allowed to set status
            if ($attender->user_type === Calendar_Model_Attender::USERTYPE_RESOURCE) {
                if (! $preserveStatus && !Tinebase_Core::getUser()->hasGrant($attender->displaycontainer_id,
                            Calendar_Model_ResourceGrants::EVENTS_EDIT)) {
                    //If resource has an default status use this
                    $attender->status = isset($resource->status) ? $resource->status : Calendar_Model_Attender::STATUS_NEEDSACTION;
                }
            } else {
                if (! $preserveStatus && ! Tinebase_Core::getUser()->hasGrant($attender->displaycontainer_id, Tinebase_Model_Grants::GRANT_EDIT)) {
                    $attender->status = Calendar_Model_Attender::STATUS_NEEDSACTION;
                }
            }
        }

        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . " New attender: " . print_r($attender->toArray(), TRUE));

        Tinebase_Timemachine_ModificationLog::getInstance()->setRecordMetaData($attender, 'create');
        $this->_backend->createAttendee($attender);
        $this->_increaseDisplayContainerContentSequence($attender, $event, Tinebase_Model_ContainerContent::ACTION_CREATE);
    }
    
        
    /**
     * returns the default calendar
     * 
     * @return Tinebase_Model_Container
     */
    public function getDefaultCalendar()
    {
        return Tinebase_Container::getInstance()->getDefaultContainer($this->_modelName, NULL, Calendar_Preference::DEFAULTCALENDAR);
    }
    
    /**
     * returns default displayContainer id of given attendee
     *
     * @param string $userAccountId
     */
    public static function getDefaultDisplayContainerId($userAccountId)
    {
        $userAccountId = Tinebase_Model_User::convertUserIdToInt($userAccountId);
        $displayCalId = Tinebase_Core::getPreference('Calendar')->getValueForUser(Calendar_Preference::DEFAULTCALENDAR, $userAccountId);
        
        try {
            // assert that displaycal is of type personal
            $container = Tinebase_Container::getInstance()->getContainerById($displayCalId);
            if ($container->type != Tinebase_Model_Container::TYPE_PERSONAL) {
                $displayCalId = NULL;
            }
        } catch (Exception $e) {
            $displayCalId = NULL;
        }
        
        if (! isset($displayCalId)) {
            $containers = Tinebase_Container::getInstance()->getPersonalContainer($userAccountId, 'Calendar_Model_Event', $userAccountId, 0, true);
            if ($containers->count() > 0) {
                $displayCalId = $containers->getFirstRecord()->getId();
            }
        }
        
        return $displayCalId;
    }
    
    /**
     * increases content sequence of attender display container
     * 
     * @param Calendar_Model_Attender $attender
     * @param Calendar_Model_Event $event
     * @param string $action
     */
    protected function _increaseDisplayContainerContentSequence($attender, $event, $action = Tinebase_Model_ContainerContent::ACTION_UPDATE)
    {
        if ($event->container_id === $attender->displaycontainer_id || empty($attender->displaycontainer_id)) {
            // no need to increase sequence
            return;
        }
        
        Tinebase_Container::getInstance()->increaseContentSequence($attender->displaycontainer_id, $action, $event->getId());
    }
    
    /**
     * updates an attender
     * 
     * @param Calendar_Model_Attender  $attender
     * @param Calendar_Model_Attender  $currentAttender
     * @param Calendar_Model_Event     $event
     * @param bool                     $isRescheduled event got rescheduled reset all attendee status
     * @param Tinebase_Model_Container $calendar
     */
    protected function _updateAttender($attender, $currentAttender, $event, $isRescheduled, $calendar = NULL)
    {
        $userAccountId = $currentAttender->getUserAccountId();

        // update display calendar if attender has/is a useraccount
        if ($userAccountId) {
            if ($calendar->type == Tinebase_Model_Container::TYPE_PERSONAL && Tinebase_Container::getInstance()->hasGrant($userAccountId, $calendar, Tinebase_Model_Grants::GRANT_ADMIN)) {
                // if attender has admin grant to personal physical container, this phys. cal also gets displ. cal
                $attender->displaycontainer_id = $calendar->getId();
            } else if ($userAccountId == Tinebase_Core::getUser()->getId() && Tinebase_Container::getInstance()->hasGrant($userAccountId, $attender->displaycontainer_id, Tinebase_Model_Grants::GRANT_ADMIN)) {
                // allow user to set his own displ. cal
            } else {
                $attender->displaycontainer_id = $currentAttender->displaycontainer_id;
            }
        }

        // reset status if user has no right and authkey is wrong
        if ($attender->displaycontainer_id && $attender->status_authkey !== $currentAttender->status_authkey &&
                !$this->_keepAttenderStatus) {
            if ($attender->user_type === Calendar_Model_Attender::USERTYPE_RESOURCE) {
                if (!Tinebase_Core::getUser()->hasGrant($attender->displaycontainer_id,
                        Calendar_Model_ResourceGrants::EVENTS_EDIT)) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) {
                        Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                            . ' Wrong authkey, resetting status (' . $attender->status . ' -> ' . $currentAttender->status . ')');
                    }
                    $attender->status = $currentAttender->status;
                }
            } else {
                if (!Tinebase_Core::getUser()->hasGrant($attender->displaycontainer_id,
                        Tinebase_Model_Grants::GRANT_EDIT)) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) {
                        Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                            . ' Wrong authkey, resetting status (' . $attender->status . ' -> ' . $currentAttender->status . ')');
                    }
                    $attender->status = $currentAttender->status;
                }
            }
        }

        // reset all status but calUser on reschedule except resources (Resources might have a configured default value)
        if ($isRescheduled && !$attender->isSame($this->getCalendarUser()) && !$this->_keepAttenderStatus) {
            if ($attender->user_type === Calendar_Model_Attender::USERTYPE_RESOURCE) {
                //If resource has a default status reset to this
                // we may have only invite grant, but no read grant...
                $resourceController = Calendar_Controller_Resource::getInstance();
                $oldResourceAclCheck = $resourceController->doContainerACLChecks(false);
                try {
                    $resource = $resourceController->get($attender->user_id);
                } finally {
                    $resourceController->doContainerACLChecks($oldResourceAclCheck);
                }
                $attender->status = isset($resource->status) ? $resource->status : Calendar_Model_Attender::STATUS_NEEDSACTION;
            } else {
                $attender->status = Calendar_Model_Attender::STATUS_NEEDSACTION;
            }
            $attender->transp = null;
        }

        // preserve old authkey
        $attender->status_authkey = $currentAttender->status_authkey;

        if ($attender->diff($currentAttender)->isEmpty()) {
            return;
        }

        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . " Updating attender: " . print_r($attender->toArray(), TRUE));


        Tinebase_Timemachine_ModificationLog::getInstance()->setRecordMetaData($attender, 'update', $currentAttender);
        Tinebase_Timemachine_ModificationLog::getInstance()->writeModLog($attender, $currentAttender, get_class($attender), $this->_getBackendType(), $attender->getId());
        $this->_backend->updateAttendee($attender);
        
        if ($attender->displaycontainer_id !== $currentAttender->displaycontainer_id) {
            $this->_increaseDisplayContainerContentSequence($currentAttender, $event, Tinebase_Model_ContainerContent::ACTION_DELETE);
            $this->_increaseDisplayContainerContentSequence($attender, $event, Tinebase_Model_ContainerContent::ACTION_CREATE);
        } else {
            $this->_increaseDisplayContainerContentSequence($attender, $event);
        }
    }
    
    /**
     * event handler for group updates
     * 
     * @param Tinebase_Model_Group $_group
     * @return void
     */
    public function onUpdateGroup($_groupId)
    {
        $doContainerACLChecks = $this->doContainerACLChecks(FALSE);
        
        $filter = new Calendar_Model_EventFilter(array(
            array('field' => 'attender', 'operator' => 'equals', 'value' => array(
                'user_type' => Calendar_Model_Attender::USERTYPE_GROUP,
                'user_id'   => $_groupId
            )),
            array('field' => 'period', 'operator' => 'within', 'value' => array(
                'from'  => Tinebase_DateTime::now()->get(Tinebase_Record_Abstract::ISO8601LONG),
                'until' => Tinebase_DateTime::now()->addYear(100)->get(Tinebase_Record_Abstract::ISO8601LONG))
            )
        ));
        $events = $this->search($filter, new Tinebase_Model_Pagination(), FALSE, FALSE);
        
        foreach($events as $event) {
            try {
                if (! $event->rrule) {
                    // update non recurring futrue events
                    Calendar_Model_Attender::resolveGroupMembers($event->attendee);
                    $this->update($event);
                } else {
                    // update thisandfuture for recurring events
                    $nextOccurrence = Calendar_Model_Rrule::computeNextOccurrence($event, $this->getRecurExceptions($event), Tinebase_DateTime::now());
                    if ($nextOccurrence) {
                        Calendar_Model_Attender::resolveGroupMembers($nextOccurrence->attendee);

                        if ($nextOccurrence->dtstart != $event->dtstart) {
                            $this->createRecurException($nextOccurrence, FALSE, TRUE);
                        } else {
                            $this->update($nextOccurrence);
                        }
                    }
                }
            } catch (Exception $e) {
                Tinebase_Core::getLogger()->NOTICE(__METHOD__ . '::' . __LINE__ . " could not update attendee");
            }
        }
        
        $this->doContainerACLChecks($doContainerACLChecks);
    }
    
    /****************************** alarm functions ************************/
    
    /**
     * send an alarm
     *
     * @param  Tinebase_Model_Alarm $_alarm
     * @return void
     * @throws Exception
     * 
     * NOTE: the given alarm is raw and has not passed _inspectAlarmGet
     */
    public function sendAlarm(Tinebase_Model_Alarm $_alarm) 
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " About to send alarm " . print_r($_alarm->toArray(), TRUE));
        
        $doContainerACLChecks = $this->doContainerACLChecks(FALSE);

        try {
            $event = $this->get($_alarm->record_id);
            $event->alarms = new Tinebase_Record_RecordSet('Tinebase_Model_Alarm', array($_alarm));
            $this->_inspectAlarmGet($event);
        } catch (Exception $e) {
            $this->doContainerACLChecks($doContainerACLChecks);
            throw($e);
        }

        $this->doContainerACLChecks($doContainerACLChecks);

        if ($event->rrule) {
            $recurid = $_alarm->getOption('recurid');
            
            // adopts the (referenced) alarm and sets alarm time to next occurance
            parent::_inspectAlarmSet($event, $_alarm);
            $this->adoptAlarmTime($event, $_alarm, 'instance');
            
            // sent_status might have changed in adoptAlarmTime()
            if ($_alarm->sent_status !== Tinebase_Model_Alarm::STATUS_PENDING) {
                if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Not sending alarm for event at ' . $event->dtstart->toString() . ' with status ' . $_alarm->sent_status);
                return;
            }
            
            if ($recurid) {
                // NOTE: In case of recuring events $event is always the baseEvent,
                //       so we might need to adopt event time to recur instance.
                $diff = $event->dtstart->diff($event->dtend);
                
                $event->dtstart = new Tinebase_DateTime(substr($recurid, -19));
                
                $event->dtend = clone $event->dtstart;
                $event->dtend->add($diff);
            }
            
            if ($event->exdate && in_array($event->dtstart, $event->exdate)) {
                if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . " Not sending alarm because instance at " . $event->dtstart->toString() . ' is an exception.');
                return;
            }
        }
        
        Calendar_Controller_EventNotifications::getInstance()->doSendNotifications($event, Tinebase_Core::getUser(), 'alarm', NULL, array('alarm' => $_alarm));
    }
    
    /**
     * send notifications 
     * 
     * @param Tinebase_Record_Interface  $_event
     * @param Tinebase_Model_FullUser    $_updater
     * @param String                     $_action
     * @param Tinebase_Record_Interface  $_oldEvent
     * @param Array                      $_additionalData
     */
    public function doSendNotifications(Tinebase_Record_Interface $_event, Tinebase_Model_FullUser $_updater, $_action, Tinebase_Record_Interface $_oldEvent = NULL, array $_additionalData = array())
    {
        if ($_event->mute) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' skip sending notifications as event is muted');

            return;
        }
        Tinebase_ActionQueue::getInstance()->queueAction('Calendar.sendEventNotifications', 
            $_event, 
            $_updater,
            $_action, 
            $_oldEvent ? $_oldEvent : NULL
        );
    }

    public function compareCalendars($cal1, $cal2, $from, $until)
    {
        $matchingEvents = new Tinebase_Record_RecordSet('Calendar_Model_Event');
        $changedEvents = new Tinebase_Record_RecordSet('Calendar_Model_Event');
        $missingEventsInCal1 = new Tinebase_Record_RecordSet('Calendar_Model_Event');
        $missingEventsInCal2 = new Tinebase_Record_RecordSet('Calendar_Model_Event');
        $cal2EventIdsAlreadyProcessed = array();
        
        while ($from->isEarlier($until)) {
    
            $endWeek = $from->getClone()->addWeek(1);
            
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Comparing period ' . $from . ' - ' . $endWeek);
    
            // get all events from cal1+cal2 for the week
            $cal1Events = $this->_getEventsForPeriodAndCalendar($cal1, $from, $endWeek);
            $cal1EventsClone = clone $cal1Events;
            $cal2Events = $this->_getEventsForPeriodAndCalendar($cal2, $from, $endWeek);
            $cal2EventsClone = clone $cal2Events;
            
            $from->addWeek(1);
            if (count($cal1Events) == 0 && count($cal2Events) == 0) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' No events found');
                continue;
            }
    
            foreach ($cal1Events as $event) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Checking event "' . $event->summary . '" ' . $event->dtstart . ' - ' . $event->dtend);
                
                if ($event->container_id != $cal1) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                            . ' Event is in another calendar - skip');
                    $cal1Events->removeRecord($event);
                    continue;
                }
                
                $summaryMatch = $cal2Events->filter('summary', $event->summary);
                if (count($summaryMatch) > 0) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . " Found " . count($summaryMatch) . ' events with matching summaries');
                    
                    $dtStartMatch = $summaryMatch->filter('dtstart', $event->dtstart);
                    if (count($dtStartMatch) > 0) {
                        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                            . " Found " . count($summaryMatch) . ' events with matching dtstarts and summaries');
                        
                        $matchingEvents->merge($dtStartMatch);
                        // remove from cal1+cal2
                        $cal1Events->removeRecord($event);
                        $cal2Events->removeRecords($dtStartMatch);
                        $cal2EventIdsAlreadyProcessed = array_merge($cal2EventIdsAlreadyProcessed, $dtStartMatch->getArrayOfIds());
                    } else {
                        $changedEvents->merge($summaryMatch);
                        $cal1Events->removeRecord($event);
                        $cal2Events->removeRecords($summaryMatch);
                        $cal2EventIdsAlreadyProcessed = array_merge($cal2EventIdsAlreadyProcessed, $summaryMatch->getArrayOfIds());
                    }
                }
            }
            
            // add missing events
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . " Found " . count($cal1Events) . ' events missing in cal2');
            $missingEventsInCal2->merge($cal1Events);
            
            // compare cal2 -> cal1 and add events as missing from cal1 that we did not detect before
            foreach ($cal2EventsClone as $event) {
                if (in_array($event->getId(), $cal2EventIdsAlreadyProcessed)) {
                    continue;
                }
                if ($event->container_id != $cal2) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                            . ' Event is in another calendar - skip');
                    continue;
                }
                
                $missingEventsInCal1->addRecord($event);
            }
        }
        
        $result = array(
            'matching'      => $matchingEvents,
            'changed'       => $changedEvents,
            'missingInCal1' => $missingEventsInCal1,
            'missingInCal2' => $missingEventsInCal2,
        );
        return $result;
    }
    
    protected function _getEventsForPeriodAndCalendar($calendarId, $from, $until)
    {
        $filter = new Calendar_Model_EventFilter(array(
            array('field' => 'period', 'operator' => 'within', 'value' =>
                array("from" => $from, "until" => $until)
            ),
            array('field' => 'container_id', 'operator' => 'equals', 'value' => $calendarId),
        ));
    
        $events = Calendar_Controller_Event::getInstance()->search($filter);
        Calendar_Model_Rrule::mergeAndRemoveNonMatchingRecurrences($events, $filter);
        return $events;
    }
    
    /**
     * add calendar owner as attendee if not already set
     * 
     * @param string $calendarId
     * @param Tinebase_DateTime $from
     * @param Tinebase_DateTime $until
     * @param boolean $dry run
     * 
     * @return number of updated events
     * @throws Tinebase_Exception_InvalidArgument
     */
    public function repairAttendee($calendarId, $from, $until, $dry = false)
    {
        $container = Tinebase_Container::getInstance()->getContainerById($calendarId);
        if ($container->type !== Tinebase_Model_Container::TYPE_PERSONAL) {
            throw new Tinebase_Exception_InvalidArgument('Only allowed for personal containers!');
        }
        if ($container->owner_id !== Tinebase_Core::getUser()->getId()) {
            throw new Tinebase_Exception_InvalidArgument('Only allowed for own containers!');
        }
        
        $updateCount = 0;
        while ($from->isEarlier($until)) {
            $endWeek = $from->getClone()->addWeek(1);
            
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Repairing period ' . $from . ' - ' . $endWeek);
            
            
            // TODO we need to detect events with DECLINED/DELETED attendee
            $events = $this->_getEventsForPeriodAndCalendar($calendarId, $from, $endWeek);
            
            $from->addWeek(1);
            
            if (count($events) == 0) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' No events found');
                continue;
            }
            
            foreach ($events as $event) {
                // add attendee if not already set
                if ($event->isRecurInstance()) {
                    // TODO get base event
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                            . ' Skip recur instance ' . $event->toShortString());
                    continue;
                }
                
                $ownAttender = Calendar_Model_Attender::getOwnAttender($event->attendee);
                if (! $ownAttender) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                            . ' Add missing attender to event ' . $event->toShortString());
                    
                    $attender = new Calendar_Model_Attender(array(
                        'user_type' => Calendar_Model_Attender::USERTYPE_USER,
                        'user_id'   => Tinebase_Core::getUser()->contact_id,
                        'status'    => Calendar_Model_Attender::STATUS_ACCEPTED
                    ));
                    $event->attendee->addRecord($attender);
                    if (! $dry) {
                        $this->update($event);
                    }
                    $updateCount++;
                }
            }
        }
        
        return $updateCount;
    }

    /**
     * @return bool
     */
    public function sendTentativeNotifications()
    {
        $eventNotificationController = Calendar_Controller_EventNotifications::getInstance();
        $calConfig = Calendar_Config::getInstance();
        if (true !== $calConfig->{Calendar_Config::TENTATIVE_NOTIFICATIONS}
                ->{Calendar_Config::TENTATIVE_NOTIFICATIONS_ENABLED}) {
            return true;
        }

        $days = $calConfig->{Calendar_Config::TENTATIVE_NOTIFICATIONS}->{Calendar_Config::TENTATIVE_NOTIFICATIONS_DAYS};
        $additionalFilters = $calConfig->{Calendar_Config::TENTATIVE_NOTIFICATIONS}
            ->{Calendar_Config::TENTATIVE_NOTIFICATIONS_FILTER};

        $filter = array(
            array('field' => 'period', 'operator' => 'within', 'value' => array(
                'from'  => Tinebase_DateTime::now(),
                'until' => Tinebase_DateTime::now()->addDay($days)
            )),
            array('field' => 'status', 'operator' => 'equals', 'value' => Calendar_Model_Event::STATUS_TENTATIVE)
        );

        if (null !== $additionalFilters) {
            $filter = array_merge($filter, $additionalFilters);
        }

        $filter = new Calendar_Model_EventFilter($filter);
        foreach ($this->search($filter) as $event) {
            $eventNotificationController->doSendNotifications($event, null, 'tentative');

            Tinebase_Lock::keepLocksAlive();
        }

        return true;
    }

    /**
     * returns active fixed calendars for users (combines config and preference)
     *
     * @return array
     * @throws Tinebase_Exception_NotFound
     */
    public function getFixedCalendarIds()
    {
        $fixedCalendars = (array) Calendar_Config::getInstance()->get(Calendar_Config::FIXED_CALENDARS);

        // add fixed calendars from user preference
        $fixedCalendarsPref = Tinebase_Core::getPreference('Calendar')->getValue(Calendar_Preference::FIXED_CALENDARS);
        if (is_array($fixedCalendarsPref)) {
            foreach ($fixedCalendarsPref as $container) {
                if (isset($container['id'])) {
                    $fixedCalendars[] = $container['id'];
                }
            }
        }

        return $fixedCalendars;
    }

    /**
     * set/get the skipRecurAdoptions state
     *
     * @param  boolean optional
     * @return boolean
     */
    public function skipRecurAdoptions()
    {
        $value = (func_num_args() === 1) ? (bool) func_get_arg(0) : NULL;
        return $this->_setBooleanMemberVar('_skipRecurAdoptions', $value);
    }

    /**
     * @param Tinebase_Model_ModificationLog $_modification
     * @param bool $_dryRun
     */
    public function undoReplicationModificationLog(Tinebase_Model_ModificationLog $_modification, $_dryRun)
    {
        $oldKeepAttenderStatus = $this->_keepAttenderStatus;
        $this->_keepAttenderStatus = true;
        $oldDoContainerAcl = $this->_doContainerACLChecks;
        $this->_doContainerACLChecks = false;
        $oldSendNotifications = $this->_sendNotifications;
        $this->_sendNotifications = false;
        try {
            if (Tinebase_Timemachine_ModificationLog::CREATED === $_modification->change_type) {
                if (!$_dryRun) {
                    $this->delete(array($_modification->record_id));
                }
            } elseif (Tinebase_Timemachine_ModificationLog::DELETED === $_modification->change_type) {
                $deletedRecord = $this->get($_modification->record_id, null, true, true);
                $diff = new Tinebase_Record_Diff(json_decode($_modification->new_value, true));
                $model = $_modification->record_type;
                /** @var Calendar_Model_Event $record */
                $record = new $model($diff->oldData, true);
                foreach (Tinebase_Model_Grants::getAllGrants() as $grant) {
                    if ($record->has($grant)) {
                        $record->{$grant} = $deletedRecord->{$grant};
                    }
                }
                if (!$_dryRun) {
                    $this->unDelete($record);
                }
            } else {
                $record = $this->get($_modification->record_id, null, true, true);
                $diff = new Tinebase_Record_Diff(json_decode($_modification->new_value, true));
                $record->undo($diff);

                if (!$_dryRun) {
                    $this->update($record);
                }
            }
        } finally {
            $this->_keepAttenderStatus = $oldKeepAttenderStatus;
            $this->_doContainerACLChecks = $oldDoContainerAcl;
            $this->_sendNotifications = $oldSendNotifications;
        }
    }

    /**
     * @param Tinebase_Model_ModificationLog $_modification
     * @throws Tinebase_Exception
     * @throws Tinebase_Exception_Record_DefinitionFailure
     */
    public function applyReplicationModificationLog(Tinebase_Model_ModificationLog $_modification)
    {
        $oldDoContainerAcl = $this->_doContainerACLChecks;
        $this->_doContainerACLChecks = false;
        $oldSendNotifications = $this->_sendNotifications;
        $this->_sendNotifications = false;

        try {
            switch ($_modification->change_type) {
                case Tinebase_Timemachine_ModificationLog::CREATED:
                    $diff = new Tinebase_Record_Diff(json_decode($_modification->new_value, true));
                    $model = $_modification->record_type;
                    /** @var Calendar_Model_Event $record */
                    $record = new $model($diff->diff);
                    $record->attendee = null;
                    $this->create($record);
                    break;

                case Tinebase_Timemachine_ModificationLog::UPDATED:
                    $diff = new Tinebase_Record_Diff(json_decode($_modification->new_value, true));
                    if (isset($diff->diff['attendee'])) {
                        $d = $diff->diff;
                        unset($d['attendee']);
                        $diff->diff = $d;
                    }
                    $record = $this->get($_modification->record_id, null, true, true);
                    $record->applyDiff($diff);
                    $this->update($record);
                    break;

                case Tinebase_Timemachine_ModificationLog::DELETED:
                    $this->delete($_modification->record_id);
                    break;

                default:
                    throw new Tinebase_Exception('unknown Tinebase_Model_ModificationLog->change_type: ' .
                        $_modification->change_type);
            }
        } finally {
            $this->_doContainerACLChecks = $oldDoContainerAcl;
            $this->_sendNotifications = $oldSendNotifications;
        }
    }

    /**
     * @param Tinebase_Model_Container $_container
     * @param bool $_ignoreAcl
     * @param null $_filter
     */
    public function deleteContainerContents(Tinebase_Model_Container $_container, $_ignoreAcl = false, $_filter = null)
    {
        if (null === $_filter) {
            // do not use container_id here! it would use a Calendar_Model_CalendarFilter, we do NOT want that
            $_filter = new Calendar_Model_EventFilter([
                ['field' => 'base_event_id', 'operator' => 'isnull', 'value' => true]
            ]);
            $_filter->addFilter(new Tinebase_Model_Filter_Id('container_id', 'equals', $_container->id));

            // we first delete all base events, then in a second go, we delete again everything that has not yet been deleted
            parent::deleteContainerContents($_container, $_ignoreAcl, $_filter);

            // do not use container_id here! it would use a Calendar_Model_CalendarFilter, we do NOT want that
            $_filter = new Calendar_Model_EventFilter();
            $_filter->addFilter(new Tinebase_Model_Filter_Id('container_id', 'equals', $_container->id));
            parent::deleteContainerContents($_container, $_ignoreAcl, $_filter);
        } else {
            parent::deleteContainerContents($_container, $_ignoreAcl, $_filter);
        }
    }
}
