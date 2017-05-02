<?php
/**
 * Tine 2.0
 *
 * @package     Projects
 * @subpackage  Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @copyright   Copyright (c) 2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Michael Spahn <m.spahn@metaways.de>
 */
class Projects_Setup_Update_Release10 extends Setup_Update_Abstract
{
    /**
     * update to 10.1
     *
     * Add fulltext index to description field of projects_project
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

        $this->_backend->addIndex('projects_project', $declaration);

        $this->setTableVersion('projects_project', '3');
        $this->setApplicationVersion('Projects', '10.1');
    }
}
