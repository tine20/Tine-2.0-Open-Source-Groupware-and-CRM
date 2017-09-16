<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Calendar
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2009-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 */

/**
 * Test class for Calendar_Backend_Sql
 * 
 * @package     Calendar
 */
abstract class Calendar_TestCase extends TestCase
{
    /**
     * @var Calendar_Backend_Sql SQL Backend in test
     */
    protected $_backend;
    
    /**
     * @var Tinebase_Model_Container
     */
    protected $_testCalendar = NULL;
    
    /**
     * @var Tinebase_Record_RecordSet test calendars to be deleted on tearDown
     */
    protected $_testCalendars = NULL;
    
    /**
     * @var Addressbook_Model_Contact
     */
    protected $_testUserContact = NULL;
    
    /**
     * personas
     *
     * @var array
     */
    protected $_personas = NULL;
    
    /**
     * personas contacts
     * @var array
     */
    protected $_personasContacts = array();
    
    /**
     * persona calendars
     *
     * @var array
     */
    protected $_personasDefaultCals = array();
    
    /**
     * set up tests
     */
    public function setUp()
    {
        parent::setUp();
        
        $this->_backend = new Calendar_Backend_Sql();
        
        $this->_personas = Zend_Registry::get('personas');
        foreach ($this->_personas as $loginName => $user) {
            $defaultCalendarId = Tinebase_Core::getPreference('Calendar')->getValueForUser(Calendar_Preference::DEFAULTCALENDAR, $user->getId());
            $this->_personasContacts[$loginName] = Addressbook_Controller_Contact::getInstance()->getContactByUserId($user->getId());
            $this->_personasDefaultCals[$loginName] = Tinebase_Container::getInstance()->getContainerById($defaultCalendarId);
        }
        
        $this->_testUserContact = Addressbook_Controller_Contact::getInstance()->getContactByUserId($this->_originalTestUser->getId());
        $this->_testCalendar = $this->_getTestContainer('Calendar');
        
        $this->_testCalendars = new Tinebase_Record_RecordSet('Tinebase_Model_Container');
        $this->_testCalendars->addRecord($this->_testCalendar);
    }
    
    /**
     * tear down tests
     */
    public function tearDown()
    {
        parent::tearDown();
        
        Calendar_Controller_Event::getInstance()->sendNotifications(false);
        
        Tinebase_Acl_Roles::getInstance()->resetClassCache();
        
        if (! $this->_transactionId) {
            if ($this->_backend != NULL) {
                $events = $this->_backend->search(new Calendar_Model_EventFilter(array(
                    array('field' => 'container_id', 'operator' => 'in', 'value' => $this->_testCalendars->getId()),
                )), new Tinebase_Model_Pagination(array()));
                
                // delete alarms
                Tinebase_Alarm::getInstance()->deleteAlarmsOfRecord('Calendar_Model_Event', $events->getArrayOfIds());
                
                foreach ($events as $event) {
                    $this->_backend->delete($event->getId());
                }
            }
            foreach ($this->_testCalendars as $cal) {
                Tinebase_Container::getInstance()->deleteContainer($cal, true);
            }
        }
        
        $this->_testUserContact = NULL;
        $this->_testCalendar = NULL;
        $this->_testCalendars = NULL;
        $this->_personas = NULL;
        $this->_personasContacts = array();
        $this->_personasDefaultCals = array();
    }
    
    /**
     * returns test persons contacts
     * 
     * @param string $loginName
     * @return Addressbook_Model_Contact
     */
    protected function _getPersonasContacts($loginName)
    {
        if (!isset($this->_personasContacts[$loginName])) {
            $user = $this->_getPersona($loginName);
            $this->_personasContacts[$loginName] = Addressbook_Controller_Contact::getInstance()->getContactByUserId($user->getId());
        }
        return $this->_personasContacts[$loginName];
    }
    
    /**
     * returns test persons default calendar
     * 
     * @param string $loginName
     * @return Tinebase_Model_Container
     */
    protected function _getPersonasDefaultCals($loginName)
    {
        if (!isset($this->_personasDefaultCals[$loginName])) {
            $user = $this->_getPersona($loginName);
            $defaultCalendarId = Tinebase_Core::getPreference('Calendar')->getValueForUser(Calendar_Preference::DEFAULTCALENDAR, $user->getId());
            $this->_personasDefaultCals[$loginName] = Tinebase_Container::getInstance()->getContainerById($defaultCalendarId);
        }
        return $this->_personasDefaultCals[$loginName];
    }
    
    /**
     * returns all test persons default calendar
     * 
     * @return array
     */
    protected function _getAllPersonasDefaultCals()
    {
        foreach ($this->_getPersonas() as $loginName => $user)
        {
            $this->_getPersonasDefaultCals($loginName);
        }
        return $this->_personasDefaultCals;
    }
    
    /** return a test person
     * @return 
     */
    protected function _getPersona($loginName)
    {
        if ($this->_personas === NULL) {
            $this->_getPersonas();
        }
        return $this->_personas[$loginName];
    }
    
    /**
     * returns an array of test persons
     * 
     * @return array
     */
    protected function _getPersonas()
    {
        if ($this->_personas === NULL) {
            $this->_personas = Zend_Registry::get('personas');
        }
        return $this->_personas;
    }
    
    /**
     * returns a test user
     * 
     * @return Tinebase_Model_FullUser
     */
    protected function _getTestUser()
    {
        return $this->_originalTestUser;
    }
    
    /**
     * returns the test users contact
     * 
     * @return
     */
    protected function _getTestUserContact()
    {
        if ($this->_testUserContact === NULL) {
            $this->_testUserContact = Addressbook_Controller_Contact::getInstance()->getContactByUserId($this->_getTestUser()->getId());
        }
        return $this->_testUserContact;
    }
    /**
     * returns a test calendar set
     * 
     * @return Tinebase_Record_RecordSet
     */
    protected function _getTestCalendars()
    {
        if ($this->_testCalendars === NULL) {
            $this->_getTestCalendar();
        }
        return $this->_testCalendars;
    }
    /**
     * returns a test calendar
     * 
     * @return Tinebase_Model_Container
     */
    public function _getTestCalendar()
    {
        if ($this->_testCalendar === NULL) {
            $this->_testCalendar = $this->_getTestContainer('Calendar');
            
            $this->_testCalendars = new Tinebase_Record_RecordSet('Tinebase_Model_Container');
            $this->_testCalendars->addRecord($this->_testCalendar);
        }
        return $this->_testCalendar;
    }
    
    /**
     * returns a simple event
     * 
     * @param bool $now
     * @param bool $mute
     * @return Calendar_Model_Event
     */
    protected function _getEvent($now = FALSE, $mute = NULL)
    {
        $event = new Calendar_Model_Event(array(
            'summary'     => 'Wakeup',
            'dtstart'     => '2009-03-25 06:00:00',
            'dtend'       => '2009-03-25 06:15:00',
            'description' => 'Early to bed and early to rise, makes a men healthy, wealthy and wise',
            'attendee'    => $this->_getAttendee(),
        
            'container_id' => $this->_getTestCalendar()->getId(),
            'organizer'    => $this->_getTestUserContact()->getId(),
            'uid'          => Calendar_Model_Event::generateUID(),

            'mute'         => $mute,
        
            Tinebase_Model_Grants::GRANT_READ    => true,
            Tinebase_Model_Grants::GRANT_EDIT    => true,
            Tinebase_Model_Grants::GRANT_DELETE  => true,
        ));
        
        if ($now) {
            $event->dtstart = Tinebase_DateTime::now();
            $event->dtend = Tinebase_DateTime::now()->addMinute(15);
        }
        
        return $event;
    }

    /**
     * returns a simple event
     *
     * @param bool $now use date of now
     * @return Calendar_Model_Event
     */
    protected function _getEventWithAlarm($now = FALSE)
    {
        $event = $this->_getEvent($now);
        $event->alarms = new Tinebase_Record_RecordSet('Tinebase_Model_Alarm', array(
            array(
                'minutes_before'    => 0
            ),
        ), TRUE);
        
        return $event;
    }
    
    /**
     * get test attendee
     *
     * @return Tinebase_Record_RecordSet
     */
    protected function _getAttendee()
    {
        return new Tinebase_Record_RecordSet('Calendar_Model_Attender', array(
            $this->_createAttender($this->_getTestUserContact()->getId())->toArray(),
            $this->_createAttender($this->_GetPersonasContacts('sclever')->getId())->toArray(),
        ));
    }
    

    /**
     * create new attender
     *
     * @param string $userId
     * @param string $type
     * @return Calendar_Model_Attender
     */
    protected function _createAttender($userId, $type = Calendar_Model_Attender::USERTYPE_USER)
    {
        return new Calendar_Model_Attender(array(
            'user_id'        => $userId,
            'user_type'      => $type,
            'role'           => Calendar_Model_Attender::ROLE_REQUIRED,
            'status_authkey' => Tinebase_Record_Abstract::generateUID(),
        ));
    }
    
    /**
     * get resource
     * 
     * @return Calendar_Model_Resource
     */
    protected function _getResource()
    {
        return new Calendar_Model_Resource(array(
            'name'                 => 'Meeting Room',
            'description'          => 'Our main meeting room',
            'email'                => 'room@example.com',
            'is_location'          => TRUE,
        ));
    }

    /**
     * get all calendar grants
     * 
     * @param Tinebase_Model_User $user
     * @return array
     */
    protected function _getAllCalendarGrants($user = null)
    {
        return array(
            'account_id'    => $user ? $user->getId() : Tinebase_Core::getUser()->getId(),
            'account_type'  => 'user',
            Tinebase_Model_Grants::GRANT_READ     => true,
            Tinebase_Model_Grants::GRANT_ADD      => true,
            Tinebase_Model_Grants::GRANT_EDIT     => true,
            Tinebase_Model_Grants::GRANT_DELETE   => true,
            Tinebase_Model_Grants::GRANT_PRIVATE  => true,
            Tinebase_Model_Grants::GRANT_ADMIN    => true,
            Tinebase_Model_Grants::GRANT_FREEBUSY => true,
            // TODO add sync grant?
        );
    }
    
    /**
     * helper function for getting attender (current user or persona) from attendee set
     * 
     * @param Tinebase_Record_RecordSet $attendee
     * @param string $persona
     * @return Calendar_Model_Attender
     */
    protected function _getAttenderFromAttendeeSet($attendee, $persona = null)
    {
        $contactId = $persona ? $this->_getPersonasContacts($persona)->getId() : Tinebase_Core::getUser()->contact_id;
        $attender = new Calendar_Model_Attender(array(
            'user_id'        => $contactId,
            'user_type'      => Calendar_Model_Attender::USERTYPE_USER,
        ));
        
        return Calendar_Model_Attender::getAttendee($attendee, $attender);
    }
}
