<?php
/**
 * Tine 2.0 role controller
 *
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Paul Mehrer <p.mehrer@metaways.de>
 *
 */

/**
 * this class handles the roles
 *
 * @package     Tinebase
 */
class Tinebase_Role extends Tinebase_Acl_Roles
{
    /**
     * holds the _instance of the singleton
     *
     * @var Tinebase_Role
     */
    private static $_instance = NULL;

    /**
     * the singleton pattern
     *
     * @return Tinebase_Acl_Roles
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Tinebase_Role;
        }

        return self::$_instance;
    }

    /**
     * update one record
     *
     * @param   Tinebase_Record_Interface $_record
     * @param   boolean $_duplicateCheck
     * @return  Tinebase_Record_Interface
     * @throws  Tinebase_Exception_AccessDenied
     *
     * @todo    fix duplicate check on update / merge needs to remove the changed record / ux discussion
     */
    public function update(Tinebase_Record_Interface $_record, $_duplicateCheck = TRUE)
    {
        /** @var Tinebase_Record_RecordSet $members */
        $members = $_record->members;
        $_record->members = null;
        /** @var Tinebase_Record_RecordSet $rights */
        $rights = $_record->rights;
        $_record->rights = null;

        $return = parent::update($_record, $_duplicateCheck);

        if (null !== $members) {
            $tmpMembers = $members->toArray();
            foreach($tmpMembers as &$m) {
                $m['dataId'] = $m['id'];
                $m['type'] = $m['account_type'];
                $m['id'] = $m['account_id'];
            }
            $this->setRoleMembers($_record->getId(), $tmpMembers, true);
        }
        if (null !== $rights) {
            $this->setRoleRights($_record->getId(), $rights->toArray());
        }
        $this->resetClassCache();

        $return->members = $members;
        $return->rights = $rights;

        return $return;
    }
}