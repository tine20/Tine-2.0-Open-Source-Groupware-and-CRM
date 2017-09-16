<?php
/**
 * Tine 2.0
 * 
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2010-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * class to hold addressbook list data
 *
 * @package     Addressbook
 *
 * @property    string      $id
 * @property    string      $container_id
 * @property    string      $name
 * @property    string      $description
 * @property    array       $members
 * @property    array       $memberroles
 * @property    string      $email
 * @property    string      $type                 type of list
 */
class Addressbook_Model_List extends Tinebase_Record_Abstract
{
    /**
     * list type: list (user defined lists)
     * 
     * @var string
     */
    const LISTTYPE_LIST = 'list';
    
    /**
     * list type: group (lists matching a system group)
     * 
     * @var string
     */
    const LISTTYPE_GROUP = 'group';

    /**
     * name of fields which require manage accounts to be updated
     *
     * @var array list of fields which require manage accounts to be updated
     */
    protected static $_manageAccountsFields = array(
        'name',
        'description',
        'email',
    );

    /**
     * holds the configuration object (must be declared in the concrete class)
     *
     * @var Tinebase_ModelConfiguration
     */
    protected static $_configurationObject = NULL;

    /**
     * Holds the model configuration (must be assigned in the concrete class)
     *
     * @var array
     */
    protected static $_modelConfiguration = array(
        'recordName'        => 'List',
        'recordsName'       => 'Lists', // ngettext('List', 'Lists', n)
        'hasRelations'      => true,
        'hasCustomFields'   => true,
        'hasNotes'          => true,
        'hasTags'           => true,
        'modlogActive'      => true,
        'hasAttachments'    => false,
        'createModule'      => true,

        'containerProperty' => 'container_id',

        'containerName'     => 'Lists',
        'containersName'    => 'Lists',
        'containerUsesFilter' => true, // TODO true?

        'titleProperty'     => 'name',//array('%s - %s', array('number', 'title')),
        'appName'           => 'Addressbook',
        'modelName'         => 'List',

        'filterModel'       => array(
            'path'              => array(
                'filter'            => 'Tinebase_Model_Filter_Path',
                'label'             => null,
                'options'           => array()
            ),
            'showHidden'        => array(
                'filter'            => 'Addressbook_Model_ListHiddenFilter',
                'label'             => null,
                'options'           => array()
            ),
            'contact'           => array(
                'filter'            => 'Addressbook_Model_ListMemberFilter',
                'label'             => null,
                'options'           => array()
            ),
        ),

        'fields'            => array(
            'name'              => array(
                'label'             => 'Name', //_('Percent')
                'type'              => 'string',
                'queryFilter'       => true,
                'validators'        => array('presence' => 'required'),
            ),
            'description'       => array(
                'label'             => 'Description', //_('Description')
                'type'              => 'text',
                'validators'        => array(Zend_Filter_Input::ALLOW_EMPTY => true),
                'queryFilter'       => true,
            ),
            'members'           => array(
                'label'             => 'Members', //_('Members')
                'type'              => 'FOO',
                'default'           => array(),
                'validators'        => array(Zend_Filter_Input::ALLOW_EMPTY => true),
            ),
            'email'             => array(
                'label'             => 'Email', //_('Email')
                'type'              => 'string',
                'queryFilter'       => true,
                'validators'        => array(Zend_Filter_Input::ALLOW_EMPTY => true),
            ),
            'type'              => array(
                'label'             => 'Type', //_('Type')
                'type'              => 'string',
                'default'           => self::LISTTYPE_LIST,
                'validators'        => array(
                    Zend_Filter_Input::ALLOW_EMPTY => true,
                    array('InArray', array(self::LISTTYPE_LIST, self::LISTTYPE_GROUP)),
                ),
            ),
            'list_type'         => array(
                'label'             => null, // TODO fill this?
                'type'              => 'string',
                'validators'        => array(Zend_Filter_Input::ALLOW_EMPTY => true),
            ),
            'group_id'          => array(
                'label'             => null, // TODO fill this?
                'type'              => 'string',
                'validators'        => array(Zend_Filter_Input::ALLOW_EMPTY => true),
            ),
            'emails'            => array(
                'label'             => null, // TODO fill this?
                'type'              => 'string',
                'validators'        => array(Zend_Filter_Input::ALLOW_EMPTY => true),
            ),
            'memberroles'       => array(
                'label'             => null, // TODO fill this?
                'type'              => 'string',
                'validators'        => array(Zend_Filter_Input::ALLOW_EMPTY => true),
            ),
            'paths'             => array(
                'label'             => null, // TODO fill this?
                'type'              => 'FOO',
                'validators'        => array(Zend_Filter_Input::ALLOW_EMPTY => true),
            ),
        ),
    );

    /**
     * if foreign Id fields should be resolved on search and get from json
     * should have this format:
     *     array('Calendar_Model_Contact' => 'contact_id', ...)
     * or for more fields:
     *     array('Calendar_Model_Contact' => array('contact_id', 'customer_id), ...)
     * (e.g. resolves contact_id with the corresponding Model)
     *
     * @var array
     */
    protected static $_resolveForeignIdFields = array(
        Tinebase_Model_User::class => array(
            'created_by',
            'last_modified_by'
        )
    );

    /**
     * @return array
     */
    static public function getManageAccountFields()
    {
        return self::$_manageAccountsFields;
    }

    /**
     * converts a string or Addressbook_Model_List to a list id
     *
     * @param   string|Addressbook_Model_List  $_listId  the contact id to convert
     * 
     * @return  string
     * @throws  UnexpectedValueException  if no list id set 
     */
    static public function convertListIdToInt($_listId)
    {
        if ($_listId instanceof self) {
            if ($_listId->getId() == null) {
                throw new UnexpectedValueException('No identifier set.');
            }
            $id = (string) $_listId->getId();
        } else {
            $id = (string) $_listId;
        }
        
        if (empty($id)) {
            throw new UnexpectedValueException('Identifier can not be empty.');
        }
        
        return $id;
    }

    /**
     * returns an array containing the parent neighbours relation objects or record(s) (ids) in the key 'parents'
     * and containing the children neighbours in the key 'children'
     *
     * @return array
     */
    public function getPathNeighbours()
    {
        $result = parent::getPathNeighbours();

        if (!empty($this->members)) {
            foreach(Addressbook_Controller_Contact::getInstance()->getMultiple($this->members, true) as $member) {
                $members[$member->getId()] = $member;
            }
        } else {
            $members = array();
        }

        if (!empty($this->memberroles)) {

            $listRoles = array();
            /** @var Addressbook_Model_ListMemberRole $role */
            foreach($this->memberroles as $role)
            {
                $listRoles[$role->list_role_id] = $role->list_role_id;
                if (isset($members[$role->contact_id])) {
                    unset($members[$role->contact_id]);
                }
            }

            $pathController = Tinebase_Record_Path::getInstance();
            $pathController->addAfterRebuildQueueHook(array(array('Addressbook_Model_ListRole', 'setParent')));
            Addressbook_Model_ListRole::setParent($this);

            $memberRoles = Addressbook_Controller_ListRole::getInstance()->getMultiple($listRoles, true)->asArray();
            foreach($memberRoles as $memberRole) {
                $pathController->addToRebuildQueue(array($memberRole));
                $members[] = $memberRole;
            }

        }

        $result['children'] = array_merge($result['children'], $members);

        return $result;
    }
}
