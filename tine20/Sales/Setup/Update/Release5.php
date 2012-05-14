<?php
/**
 * Tine 2.0
 *
 * @package     Sales
 * @subpackage  Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @copyright   Copyright (c) 2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */

class Sales_Setup_Update_Release5 extends Setup_Update_Abstract
{
    /**
     * update from 5.0 -> 5.1
     * - save shared contracts container id in config
     * 
     * @return void
     */
    public function update_0()
    {
        $appId = Tinebase_Application::getInstance()->getApplicationByName('Sales')->getId();
        try {
            $sharedContractsId = Tinebase_Config::getInstance()->getConfig(Sales_Model_Config::SHAREDCONTRACTSID, $appId)->value;
            $sharedContracts = Tinebase_Container::getInstance()->get($sharedContractsId ? $sharedContractsId : 1);
        } catch (Tinebase_Exception_NotFound $tenf) {
            // try to fetch default shared container
            $filter = new Tinebase_Model_ContainerFilter(array(
                array('field' => 'application_id', 'operator' => 'equals',
                    'value' => $appId),
                array('field' => 'name', 'operator' => 'equals', 'value' => 'Shared Contracts'),
                array('field' => 'type', 'operator' => 'equals', 'value' => Tinebase_Model_Container::TYPE_SHARED),
            ));
            
            $sharedContracts = Tinebase_Container::getInstance()->search($filter)->getFirstRecord();
            if ($sharedContracts) {
                Tinebase_Config::getInstance()->setConfigForApplication(Sales_Model_Config::SHAREDCONTRACTSID, $sharedContracts->getId(), 'Sales');
            }
        }
        
        $this->setApplicationVersion('Sales', '5.1');
    }    
    
    /**
     * update from 5.1 -> 5.2
     * - default contracts & products
     * 
     * @return void
     */
    public function update_1() {
        
        // Products
        $commonValues = array(
                    'account_id'        => NULL,
                    'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Sales')->getId(),
                    'model'             => 'Sales_Model_ProductFilter',
        );
        
        $pfe = new Tinebase_PersistentFilter_Backend_Sql();
        
        $pfe->create(new Tinebase_Model_PersistentFilter(
            array_merge($commonValues, array(
                'name'              => "My Products", // _('My Products')
                'description'       => "Products created by me", // _('Products created by myself')
                'filters'           => array(
                    array(
                        'field'     => 'created_by',
                        'operator'  => 'equals',
                        'value'     => Tinebase_Model_User::CURRENTACCOUNT
                    )
                ),
            ))
        ));
        
        // Contracts
        $commonValues = array(
            'account_id'        => NULL,
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Sales')->getId(),
            'model'             => 'Sales_Model_ContractFilter',
        );
        
        $pfe->create(new Tinebase_Model_PersistentFilter(
            array_merge($commonValues, array(
                'name'              => "My Contracts", // _('My Contracts')
                'description'       => "Contracts created by me", // _('Contracts created by myself')
                'filters'           => array(
                    array(
                        'field'     => 'created_by',
                        'operator'  => 'equals',
                        'value'     => Tinebase_Model_User::CURRENTACCOUNT
                    )
                ),
            ))
        ));
        
        $this->setApplicationVersion('Sales', '5.2');
    }
    
    /**
     * update from 5.2 -> 5.3
     * - default contracts & products
     * 
     * @return void
     */
    public function update_2() {
        $declaration = new Setup_Backend_Schema_Field_Xml('
            <field>
                <name>cleared_in</name>
                <type>text</type>
                <length>256</length>
                <notnull>false</notnull>
            </field>'
        );
        $this->_backend->addCol('sales_contracts', $declaration);
        
        $declaration = new Setup_Backend_Schema_Field_Xml('
            <field>
                <name>cleared</name>
                <type>boolean</type>
                <default>false</default>
            </field>'
        );
        $this->_backend->addCol('sales_contracts', $declaration);
        
        $this->setTableVersion('sales_contracts', 2);
        $this->setApplicationVersion('Sales', '5.3');
    }
    
    /**
     * update from 5.3 -> 5.4
     * - change number type to text
     * 
     * @return void
     */    
    public function update_3()
    {
        $declaration = new Setup_Backend_Schema_Field_Xml('
            <field>
                <name>number</name>
                <type>text</type>
                <length>64</length>
                <notnull>true</notnull>
            </field>'
        );
        
        $this->_backend->alterCol('sales_contracts', $declaration);
        $this->setTableVersion('sales_contracts', 3);
        $this->setApplicationVersion('Sales', '5.4');
    }
}
