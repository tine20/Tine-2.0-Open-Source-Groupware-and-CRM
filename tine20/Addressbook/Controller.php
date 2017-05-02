<?php
/**
 * Tine 2.0
 * 
 * MAIN controller for addressbook, does event and container handling
 *
 * @package     Addressbook
 * @subpackage  Controller
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 */

/**
 * main controller for Addressbook
 *
 * @package     Addressbook
 * @subpackage  Controller
 */
class Addressbook_Controller extends Tinebase_Controller_Event implements Tinebase_Application_Container_Interface
{
    /**
     * holds the instance of the singleton
     *
     * @var Addressbook_Controller
     */
    private static $_instance = NULL;

    /**
     * holds the default Model of this application
     * @var string
     */
    protected static $_defaultModel = 'Addressbook_Model_Contact';

    /**
     * Models of this application that make use of Tinebase_Record_Path
     *
     * @var array|null
     */
    protected $_modelsUsingPath = array(
        'Addressbook_Model_Contact'
    );
    
    /**
     * constructor (get current user)
     */
    private function __construct() {
        $this->_applicationName = 'Addressbook';
    }
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() 
    {
    }
    
    /**
     * the singleton pattern
     *
     * @return Addressbook_Controller
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Addressbook_Controller;
        }
        
        return self::$_instance;
    }

    /**
     * event handler function
     * 
     * all events get routed through this function
     *
     * @param Tinebase_Event_Abstract $_eventObject the eventObject
     * 
     * @todo    write test
     */
    protected function _handleEvent(Tinebase_Event_Abstract $_eventObject)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . ' (' . __LINE__ . ') handle event of type ' . get_class($_eventObject));
        
        switch(get_class($_eventObject)) {
            case 'Admin_Event_AddAccount':
                $this->createPersonalFolder($_eventObject->account);
                break;
            case 'Tinebase_Event_User_DeleteAccount':
                /**
                 * @var Tinebase_Event_User_DeleteAccount $_eventObject
                 */
                if ($_eventObject->deletePersonalContainers()) {
                    $this->deletePersonalFolder($_eventObject->account);
                }

                //make to be deleted accounts (user) contact a normal contact
                if ($_eventObject->keepAsContact()) {
                    $contact = Addressbook_Controller_Contact::getInstance()->get($_eventObject->account->contact_id);
                    $contact->type = Addressbook_Model_Contact::CONTACTTYPE_CONTACT;
                    Addressbook_Controller_Contact::getInstance()->update($contact);

                } else {
                    //or just delete it
                    $contactsBackend = Addressbook_Backend_Factory::factory(Addressbook_Backend_Factory::SQL);
                    $contactsBackend->delete($_eventObject->account->contact_id);
                }
                break;
        }
    }
        
    /**
     * creates the initial folder for new accounts
     *
     * @param mixed[int|Tinebase_Model_User] $_account   the accountd object
     * @return Tinebase_Record_RecordSet of subtype Tinebase_Model_Container
     * 
     * @todo replace this with Tinebase_Container::getInstance()->getDefaultContainer
     */
    public function createPersonalFolder($_account)
    {
        $translation = Tinebase_Translation::getTranslation($this->_applicationName);
        
        $account = Tinebase_User::getInstance()->getUserById($_account);
        
        $newContainer = new Tinebase_Model_Container(array(
            'name'              => sprintf($translation->_("%s's personal addressbook"), $account->accountFullName),
            'type'              => Tinebase_Model_Container::TYPE_PERSONAL,
            'owner_id'          => $account->getId(),
            'backend'           => 'Sql',
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName($this->_applicationName)->getId(),
            'model'             => 'Addressbook_Model_Contact'
        ));
        
        $personalContainer = Tinebase_Container::getInstance()->addContainer($newContainer);
        $container = new Tinebase_Record_RecordSet('Tinebase_Model_Container', array($personalContainer));
        
        return $container;
    }

    /**
     * returns contact image
     * 
     * @param   string $_identifier record identifier
     * @param   string $_location not used, required by interface
     * @return  Tinebase_Model_Image
     * @throws  Addressbook_Exception_NotFound if no image found
     */
    public function getImage($_identifier, $_location = '')
    {
        // get contact to ensure user has read rights
        $image = Addressbook_Controller_Contact::getInstance()->getImage($_identifier);
        
        if (empty($image)) {
            throw new Addressbook_Exception_NotFound('Contact has no image.');
        }
        $imageInfo = Tinebase_ImageHelper::getImageInfoFromBlob($image);

        return new Tinebase_Model_Image($imageInfo + array(
            'id'           => sha1($image),
            'application'  => $this->_applicationName,
            'data'         => $image
        ));
    }

    /**
     * get core data for this application
     *
     * @return Tinebase_Record_RecordSet
     */
    public function getCoreDataForApplication()
    {
        $result = parent::getCoreDataForApplication();

        $application = Tinebase_Application::getInstance()->getApplicationByName($this->_applicationName);

        if (Tinebase_Core::getUser()->hasRight($application, Addressbook_Acl_Rights::MANAGE_CORE_DATA_LISTS)) {
            $result->addRecord(new CoreData_Model_CoreData(array(
                'id' => 'adb_lists',
                'application_id' => $application,
                'model' => 'Addressbook_Model_List',
                'label' => 'Lists' // _('Lists')
            )));
        }

        if (Tinebase_Core::getUser()->hasRight($application, Addressbook_Acl_Rights::MANAGE_CORE_DATA_LIST_ROLES)) {
            $result->addRecord(new CoreData_Model_CoreData(array(
                'id' => 'adb_list_roles',
                'application_id' => $application,
                'model' => 'Addressbook_Model_ListRole',
                'label' => 'List Roles' // _('List Roles')
            )));
        }

        if (Addressbook_Config::getInstance()->featureEnabled(Addressbook_Config::FEATURE_INDUSTRY)) {
            $result->addRecord(new CoreData_Model_CoreData(array(
                    'id' => 'adb_industries',
                    'application_id' => $application,
                    'model' => 'Addressbook_Model_Industry',
                    'label' => 'Industries' // _('Industries')
            )));
        }
        return $result;
    }
}
