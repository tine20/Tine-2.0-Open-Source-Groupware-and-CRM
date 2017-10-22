<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @copyright   Copyright (c) 2016-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */
class Tinebase_Setup_Update_Release11 extends Setup_Update_Abstract
{
    /**
     * update to 11.1
     *
     * change configuration column to xprops in accounts
     */
    public function update_0()
    {
        $release10 = new Tinebase_Setup_Update_Release10($this->_backend);
        $release10->update_42();
        $this->setApplicationVersion('Tinebase', '11.1');
    }

    /**
     * update to 11.2
     *
     * add deleted_time to unique index for groups, roles, tree_nodes
     */
    public function update_1()
    {
        $release10 = new Tinebase_Setup_Update_Release10($this->_backend);
        $release10->update_43();
        $this->setApplicationVersion('Tinebase', '11.2');
    }

    /**
     * update to 11.3
     *
     * add do_acl to record_observer
     */
    public function update_2()
    {
        $release10 = new Tinebase_Setup_Update_Release10($this->_backend);
        $release10->update_44();
        $this->setApplicationVersion('Tinebase', '11.3');
    }

    /**
     * update to 11.4
     *
     * fix pgsql index creation issue
     */
    public function update_3()
    {
        $release9 = new Tinebase_Setup_Update_Release9($this->_backend);
        $release9->update_13();
        $this->setApplicationVersion('Tinebase', '11.4');
    }

    /**
     * update to 11.5
     *
     * add acl table cleanup task
     */
    public function update_4()
    {
        $release9 = new Tinebase_Setup_Update_Release10($this->_backend);
        $release9->update_46();
        $this->setApplicationVersion('Tinebase', '11.5');
    }

    /**
     * update to 11.6
     *
     * fix pgsql index creation issue (again! as it did not work in the previous CE release)
     */
    public function update_5()
    {
        $release9 = new Tinebase_Setup_Update_Release9($this->_backend);
        $release9->update_13();
        $this->setApplicationVersion('Tinebase', '11.6');
    }

    /**
     * update to 11.7
     *
     * add full text index to customfield
     */
    public function update_6()
    {
        $release10 = new Tinebase_Setup_Update_Release10($this->_backend);
        $release10->update_47();
        $this->setApplicationVersion('Tinebase', '11.7');
    }

    /**
     * update to 11.8
     *
     * add index(255) on customfield.value
     */
    public function update_7()
    {
        $release10 = new Tinebase_Setup_Update_Release10($this->_backend);
        $release10->update_48();
        $this->setApplicationVersion('Tinebase', '11.8');
    }

    /**
     * update to 11.9
     *
     * addFileSystemSanitizePreviewsTask
     */
    public function update_8()
    {
        $release10 = new Tinebase_Setup_Update_Release10($this->_backend);
        $release10->update_49();
        $this->setApplicationVersion('Tinebase', '11.9');
    }
}
