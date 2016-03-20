<?php
/**
 * Tine 2.0
 * 
 * @package     Addressbook
 * @subpackage  Acl
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2009-2015 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * 
 */

/**
 * this class handles the rights for the Addressbook application
 * 
 * a right is always specific to an application and not to a record
 * examples for rights are: admin, run
 * 
 * to add a new right you have to do these 3 steps:
 * - add a constant for the right
 * - add the constant to the $addRights in getAllApplicationRights() function
 * . add getText identifier in getTranslatedRightDescriptions() function
 * 
 * @package     Addressbook
 * @subpackage  Acl
 */
class Addressbook_Acl_Rights extends Tinebase_Acl_Rights_Abstract
{
    /**
     * the right to manage shared contact favorites
     * 
     * @staticvar string
     */
    const MANAGE_SHARED_CONTACT_FAVORITES = 'manage_shared_contact_favorites';

    /**
     * the right to manage lists in core data
     *
     * @staticvar string
     */
    const MANAGE_CORE_DATA_LISTS = 'manage_core_data_lists';

    /**
     * the right to manage list roles in core data
     *
     * @staticvar string
     */
    const MANAGE_CORE_DATA_LIST_ROLES = 'manage_core_data_list_roles';

    /**
     * holds the instance of the singleton
     *
     * @var Addressbook_Acl_Rights
     */
    private static $_instance = NULL;
    
    /**
     * the clone function
     *
     * disabled. use the singleton
     */
    private function __clone() 
    {
    }
    
    /**
     * the constructor
     *
     */
    private function __construct()
    {
        
    }    
    
    /**
     * the singleton pattern
     *
     * @return Addressbook_Acl_Rights
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Addressbook_Acl_Rights;
        }
        
        return self::$_instance;
    }
    
    /**
     * get all possible application rights
     *
     * @return  array   all application rights
     */
    public function getAllApplicationRights()
    {
        
        $allRights = parent::getAllApplicationRights();
        
        $addRights = array(
            Tinebase_Acl_Rights::MANAGE_SHARED_FOLDERS,
            Tinebase_Acl_Rights::USE_PERSONAL_TAGS,
            self::MANAGE_SHARED_CONTACT_FAVORITES,
            self::MANAGE_CORE_DATA_LISTS,
            self::MANAGE_CORE_DATA_LIST_ROLES,
        );
        $allRights = array_merge($allRights, $addRights);
        
        return $allRights;
    }

    /**
     * get translated right descriptions
     * 
     * @return  array with translated descriptions for this applications rights
     */
    public static function getTranslatedRightDescriptions()
    {
        $translate = Tinebase_Translation::getTranslation('Addressbook');
        
        $rightDescriptions = array(
            Tinebase_Acl_Rights::MANAGE_SHARED_FOLDERS => array(
                'text'          => $translate->_('manage shared addressbooks'),
                'description'   => $translate->_('Create new shared addressbook folders'),
            ),
            self::MANAGE_SHARED_CONTACT_FAVORITES => array(
                'text'          => $translate->_('manage shared addressbook favorites'),
                'description'   => $translate->_('Create or update shared addressbook favorites'),
            ),
            self::MANAGE_CORE_DATA_LISTS => array(
                'text'          => $translate->_('Manage lists in CoreData'),
                'description'   => $translate->_('View, create, delete or update lists in CoreData application'),
            ),
            self::MANAGE_CORE_DATA_LIST_ROLES => array(
                'text'          => $translate->_('Manage list roles in CoreData'),
                'description'   => $translate->_('View, create, delete or update list roles in CoreData application'),
            ),
        );
        
        $rightDescriptions = array_merge($rightDescriptions, parent::getTranslatedRightDescriptions());
        return $rightDescriptions;
    }
}
