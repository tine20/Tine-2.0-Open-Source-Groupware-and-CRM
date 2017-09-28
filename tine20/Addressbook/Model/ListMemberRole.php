<?php
/**
 * Tine 2.0
 * 
 * @package     Addressbook
 * @subpackage  Model
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2016-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 * @property string $list_id
 * @property string $list_role_id
 * @property string $contact_id
 */
class Addressbook_Model_ListMemberRole extends Tinebase_Record_Abstract
{
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Addressbook';

    /**
     * key in $_validators/$_properties array for the field which
     * represents the identifier
     *
     * @var string
     */
    protected $_identifier = 'id';

    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Record/Abstract::$_validators
     */
    protected $_validators = array(
        // tine record fields
        'id'                   => array('allowEmpty' => true,         ),

        // record specific
        'list_id'              => array('allowEmpty' => false         ),
        'list_role_id'         => array('allowEmpty' => false         ),
        'contact_id'           => array('allowEmpty' => false         ),
    );

    /**
     * returns the title of the record
     *
     * @return string
     */
    public function getTitle()
    {
        $listRole = Addressbook_Controller_ListRole::getInstance()->get($this->list_role_id);
        $contact = Addressbook_Controller_Contact::getInstance()->get($this->contact_id);
        return $listRole->getTitle() . ': ' . $contact->getTitle();
    }
}
