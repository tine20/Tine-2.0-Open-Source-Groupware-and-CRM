<?php
/**
 * Tine 2.0
 *
 * @package     Tasks
 * @subpackage  Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @copyright   Copyright (c) 2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Michael Spahn <m.spahn@metaways.de>
 */
class Tasks_Setup_Update_Release10 extends Setup_Update_Abstract
{
    /**
     * update to 10.1
     *
     * Add fulltext index for description field
     */
    public function update_0()
    {
        $declaration = new Setup_Backend_Schema_Index_Xml('
            <index>
                <name>description</name>
                <fulltext>true</fulltext>
                <field>
                    <name>description</name>
                </field>
            </index>
        ');

        $this->_backend->addIndex('tasks', $declaration);

        $this->setTableVersion('tasks', 9);
        $this->setApplicationVersion('Tasks', '10.1');
    }

    public function update_1()
    {
        $this->setApplicationVersion('Tasks', '10.2');
    }

    public function update_2()
    {
        $this->setApplicationVersion('Tasks', '11.0');
    }
}
