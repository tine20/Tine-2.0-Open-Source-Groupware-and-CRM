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
class Tinebase_Setup_Update_Release10 extends Setup_Update_Abstract
{
    /**
     * update to 10.1
     *
     * @see 0012162: create new MailFiler application
     */
    public function update_0()
    {
        $release9 = new Tinebase_Setup_Update_Release9($this->_backend);
        $release9->update_9();
        $this->setApplicationVersion('Tinebase', '10.1');
    }

    /**
     * update to 10.2
     *
     * @see 0012300: add container owner column
     */
    public function update_1()
    {
        $release9 = new Tinebase_Setup_Update_Release9($this->_backend);
        try {
            $release9->update_4();
        } catch (Zend_Db_Exception $zde) {
            Tinebase_Exception::log($zde);
        }
        $this->setApplicationVersion('Tinebase', '10.2');
    }

    /**
     * update to 10.3
     *
     * change length of groups.description column from varchar(255) to text
     */
    public function update_2()
    {
        $release9 = new Tinebase_Setup_Update_Release9($this->_backend);
        $release9->update_5();
        $this->setApplicationVersion('Tinebase', '10.3');
    }

    /**
     * update to 10.4
     *
     * dd numberables
     */
    public function update_3()
    {
        $release9 = new Tinebase_Setup_Update_Release9($this->_backend);
        $release9->update_10();
        $this->setApplicationVersion('Tinebase', '10.4');
    }

    /**
     * needs to be done again to make sure we have the column!
     *
     * @see 0012300: add container owner column
     */
    public function update_4()
    {
        $release9 = new Tinebase_Setup_Update_Release9($this->_backend);
        try {
            $release9->update_4();
        } catch (Zend_Db_Exception $zde) {
            Tinebase_Exception::log($zde);
        }
        $this->setApplicationVersion('Tinebase', '10.5');
    }

    /**
     * update to 10.6
     *
     * add account sync scheduler job
     */
    public function update_5()
    {
        $scheduler = Tinebase_Core::getScheduler();
        Tinebase_Scheduler_Task::addAccountSyncTask($scheduler);

        $this->setApplicationVersion('Tinebase', '10.6');
    }


    /**
     * update to 10.7
     *
     * update timemachine_modlog table
     */
    public function update_6()
    {
        if (!$this->_backend->columnExists('instance_id', 'timemachine_modlog')) {

            $this->_backend->renameTable('timemachine_modlog', 'timemachine_modlog_bkp');
            $db = Tinebase_Core::getDb();
            if ($db instanceof Zend_Db_Adapter_Pdo_Pgsql) {
                $db->exec('ALTER INDEX "' . SQL_TABLE_PREFIX . 'timemachine_modlog_pkey" RENAME TO ' . SQL_TABLE_PREFIX . 'timemachine_modlog_pkey_bkp');
                $db->exec('ALTER INDEX "' . SQL_TABLE_PREFIX . 'timemachine_modlog_seq" RENAME TO ' . SQL_TABLE_PREFIX . 'timemachine_modlog_seq_bkp');
                $db->exec('ALTER INDEX "' . SQL_TABLE_PREFIX . 'timemachine_modlog_unique-fields_key" RENAME TO "' . SQL_TABLE_PREFIX . 'timemachine_modlog_unique-fields_key_bkp"');
            }

            $this->_backend->createTable(new Setup_Backend_Schema_Table_Xml('<table>
                <name>timemachine_modlog</name>
                <version>5</version>
                <declaration>
                    <field>
                        <name>id</name>
                        <type>text</type>
                        <length>40</length>
                        <notnull>true</notnull>
                    </field>
                    <field>
                        <name>instance_id</name>
                        <type>text</type>
                        <length>40</length>
                        <notnull>false</notnull>
                    </field>
                    <field>
                        <name>instance_seq</name>
                        <type>integer</type>
                        <notnull>true</notnull>
                        <autoincrement>true</autoincrement>
                    </field>
                    <field>
                        <name>change_type</name>
                        <type>text</type>
                        <length>40</length>
                        <notnull>false</notnull>
                    </field>
                    <field>
                        <name>application_id</name>
                        <type>text</type>
                        <length>40</length>
                        <notnull>true</notnull>
                    </field>
                    <field>
                        <name>record_id</name>
                        <type>text</type>
                        <length>40</length>
                        <notnull>false</notnull>
                    </field>
                    <field>
                        <name>record_type</name>
                        <type>text</type>
                        <length>64</length>
                        <notnull>false</notnull>
                    </field>
                    <field>
                        <name>record_backend</name>
                        <type>text</type>
                        <length>64</length>
                        <notnull>false</notnull>
                    </field>
                    <field>
                        <name>modification_time</name>
                        <type>datetime</type>
                        <notnull>true</notnull>
                    </field>
                    <field>
                        <name>modification_account</name>
                        <type>text</type>
                        <length>40</length>
                        <notnull>true</notnull>
                    </field>
                    <field>
                        <name>modified_attribute</name>
                        <type>text</type>
                        <length>64</length>
                        <notnull>false</notnull>
                    </field>
                    <field>
                        <name>old_value</name>
                        <type>clob</type>
                    </field>
                    <field>
                        <name>new_value</name>
                        <type>clob</type>
                    </field>
                    <field>
                        <name>seq</name>
                        <type>integer</type>
                        <length>64</length>
                    </field>
                    <field>
                        <name>client</name>
                        <type>text</type>
                        <length>255</length>
                        <notnull>true</notnull>
                    </field>
                    <index>
                        <name>id</name>
                        <primary>true</primary>
                        <field>
                            <name>id</name>
                        </field>
                    </index>
                    <index>
                        <name>instance_id</name>
                        <field>
                            <name>instance_id</name>
                        </field>
                    </index>
                    <index>
                        <name>instance_seq</name>
                        <unique>true</unique>
                        <field>
                            <name>instance_seq</name>
                        </field>
                    </index>
                    <index>
                        <name>seq</name>
                        <field>
                            <name>seq</name>
                        </field>
                    </index>
                    <index>
                        <name>unique-fields</name>
                        <unique>true</unique>
                        <field>
                            <name>application_id</name>
                        </field>
                        <field>
                            <name>record_id</name>
                        </field>
                        <field>
                            <name>record_type</name>
                        </field>
                        <field>
                            <name>modification_time</name>
                        </field>
                        <field>
                            <name>modification_account</name>
                        </field>
                        <field>
                            <name>modified_attribute</name>
                        </field>
                        <field>
                            <name>seq</name>
                        </field>
                    </index>
                </declaration>
            </table>'));



            $appIds[] = Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId();
            if (Tinebase_Application::getInstance()->isInstalled('Calendar')) {
                $appIds[] = Tinebase_Application::getInstance()->getApplicationByName('Calendar')->getId();
            }

            $select = $db->select()->from(SQL_TABLE_PREFIX . 'timemachine_modlog_bkp')->order('modification_time ASC')
                ->where($db->quoteInto($db->quoteIdentifier('application_id') . ' IN (?)', $appIds))
                ->where($db->quoteInto($db->quoteIdentifier('record_type') . ' IN (?)', array('Addressbook_Model_Contact', 'Calendar_Model_Resource')))
                ->where($db->quoteInto($db->quoteIdentifier('modified_attribute') . ' IN (?)', array('email', 'email_home')));

            $stmt = $db->query($select);
            $resultArray = $stmt->fetchAll(Zend_Db::FETCH_ASSOC);

            if (count($resultArray) > 0) {
                foreach($resultArray as $row) {
                    $row['client'] = 'update script';
                    $db->insert(SQL_TABLE_PREFIX . 'timemachine_modlog', $row);
                }
            }

            $this->setTableVersion('timemachine_modlog', '5');
        }

        $this->setApplicationVersion('Tinebase', '10.7');
    }

    /**
     * update to 10.8
     *
     * update roles and application table
     */
    public function update_7()
    {
        if (!$this->_backend->columnExists('is_deleted', 'roles')) {
            $query = $this->_backend->addAddCol(null, 'roles',
                new Setup_Backend_Schema_Field_Xml('<field>
                    <name>is_deleted</name>
                    <type>boolean</type>
                    <default>false</default>
                </field>'), 'last_modified_time'
            );
            $query = $this->_backend->addAddCol($query, 'roles',
                new Setup_Backend_Schema_Field_Xml('<field>
                    <name>deleted_by</name>
                    <type>text</type>
                    <length>40</length>
                </field>'), 'is_deleted'
            );
            $query = $this->_backend->addAddCol($query, 'roles',
                new Setup_Backend_Schema_Field_Xml('<field>
                    <name>deleted_time</name>
                    <type>datetime</type>
                </field>'), 'deleted_by'
            );
            $query = $this->_backend->addAddCol($query, 'roles',
                new Setup_Backend_Schema_Field_Xml('<field>
                    <name>seq</name>
                    <type>integer</type>
                    <notnull>true</notnull>
                    <default>0</default>
                </field>'), 'deleted_time'
            );
            $this->_backend->execQueryVoid($query);
            $this->setTableVersion('roles', '2');
        }

        if (!$this->_backend->columnExists('state', 'applications')) {

            $this->_backend->addCol('applications',
                new Setup_Backend_Schema_Field_Xml('<field>
                    <name>state</name>
                    <type>text</type>
                    <length>65535</length>
                    <notnull>false</notnull>
                </field>')
            );

            $this->setTableVersion('applications', '4');
        }

        $this->setApplicationVersion('Tinebase', '10.8');
    }

    /**
     * update to 10.9
     *
     * add client row to timemachine_modlog
     *
     * @see 0012830: add client user agent to modlog
     */
    public function update_8()
    {
        if (! $this->_backend->columnExists('client', 'timemachine_modlog')) {
            $declaration = new Setup_Backend_Schema_Field_Xml('<field>
                    <name>client</name>
                    <type>text</type>
                    <length>255</length>
                    <notnull>true</notnull>
                </field>');
            $this->_backend->addCol('timemachine_modlog', $declaration);

            $this->setTableVersion('timemachine_modlog', '5');
        }

        $this->setApplicationVersion('Tinebase', '10.9');
    }

    /**
     * update to 10.10
     *
     * adding path filter feature switch & structure update
     */
    public function update_9()
    {
        $this->dropTable('path');

        $declaration = new Setup_Backend_Schema_Table_Xml('<table>
            <name>path</name>
            <version>2</version>
            <requirements>
                <required>mysql >= 5.6.4</required>
            </requirements>
            <declaration>
                <field>
                    <name>id</name>
                    <type>text</type>
                    <length>40</length>
                    <notnull>true</notnull>
                </field>
                <field>
                    <name>path</name>
                    <type>text</type>
                    <length>65535</length>
                    <notnull>true</notnull>
                </field>
                <field>
                    <name>shadow_path</name>
                    <type>text</type>
                    <length>65535</length>
                    <notnull>true</notnull>
                </field>
                <field>
                    <name>creation_time</name>
                    <type>datetime</type>
                </field>
                <index>
                    <name>id</name>
                    <primary>true</primary>
                    <field>
                        <name>id</name>
                    </field>
                </index>
                <index>
                <name>path</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>path</name>
                    </field>
                </index>
                <index>
                    <name>shadow_path</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>shadow_path</name>
                    </field>
                </index>
            </declaration>
        </table>');

        $this->createTable('path', $declaration, 'Tinebase', 2);

        Tinebase_Controller::getInstance()->rebuildPaths();

        $this->setApplicationVersion('Tinebase', '10.10');
    }

    /**
     * update to 10.11
     *
     * create external_fulltext table
     */
    public function update_10()
    {
        $this->_backend->createTable(new Setup_Backend_Schema_Table_Xml('<table>
            <name>external_fulltext</name>
            <version>1</version>
            <declaration>
                <field>
                    <name>id</name>
                    <type>text</type>
                    <length>40</length>
                    <notnull>true</notnull>
                </field>
                <field>
                    <name>text_data</name>
                    <type>text</type>
                    <length>2147483647</length>
                    <notnull>true</notnull>
                </field>
                <index>
                    <name>id</name>
                    <primary>true</primary>
                    <field>
                        <name>id</name>
                    </field>
                </index>
                <index>
                    <name>text_data</name>
                    <fulltext>true</fulltext>
                    <field>
                        <name>text_data</name>
                    </field>
                </index>
            </declaration>
        </table>'), 'Tinebase', 'external_fulltext');

        $this->setApplicationVersion('Tinebase', '10.11');
    }

    /**
     * update to 10.12
     *
     * add revision_size to tree_fileobjects
     */
    public function update_11()
    {

        if (!$this->_backend->columnExists('total_size', 'tree_fileobjects')) {
            $declaration = new Setup_Backend_Schema_Field_Xml('<field>
                    <name>revision_size</name>
                    <type>integer</type>
                    <notnull>true</notnull>
                    <default>0</default>
                </field>');

            $query = $this->_backend->addAddCol('', 'tree_fileobjects', $declaration);

            $declaration = new Setup_Backend_Schema_Field_Xml('<field>
                    <name>indexed_hash</name>
                    <type>text</type>
                    <length>40</length>
                </field>');

            $query = $this->_backend->addAddCol($query, 'tree_fileobjects', $declaration);

            $this->_backend->execQueryVoid($query);

            $this->setTableVersion('tree_fileobjects', '4');
        }

        $this->setApplicationVersion('Tinebase', '10.12');
    }
}
