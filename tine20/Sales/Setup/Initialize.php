<?php
/**
 * Tine 2.0
 * 
 * @package     Sales
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Jonas Fischer <j.fischer@metaways.de>
 * @copyright   Copyright (c) 2011-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * class for Tinebase initialization
 * 
 * @package     Sales
 */
class Sales_Setup_Initialize extends Setup_Initialize
{
    /**
    * init favorites
    */
    protected function _initializeFavorites() {
        // Products
        $commonValues = array(
            'account_id'        => NULL,
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Sales')->getId(),
            'model'             => 'Sales_Model_ProductFilter',
        );
        
        $pfe = Tinebase_PersistentFilter::getInstance();
        
        $pfe->createDuringSetup(new Tinebase_Model_PersistentFilter(
            array_merge($commonValues, array(
                'name'              => "My Products", // _('My Products')
                'description'       => "Products created by me", // _('Products created by me')
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
        $commonValues['model'] = 'Sales_Model_ContractFilter';
        
        $pfe->createDuringSetup(new Tinebase_Model_PersistentFilter(
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
        
        // Customers
        $commonValues['model'] = 'Sales_Model_CustomerFilter';
        
        $pfe->createDuringSetup(new Tinebase_Model_PersistentFilter(
            array_merge($commonValues, array(
                'name'        => "All Customers", // _('All Customers')
                'description' => "All customer records", // _('All customer records')
                'filters'     => array(
                ),
            ))
        ));
        
        // Offers
        $commonValues['model'] = 'Sales_Model_OfferFilter';
        
        $pfe->createDuringSetup(new Tinebase_Model_PersistentFilter(
            array_merge($commonValues, array(
                'name'        => "All Offers", // _('All Offers')
                'description' => "All offer records", // _('All offer records')
                'filters'     => array(
                ),
            ))
        ));
        
        Sales_Setup_Update_Release8::createDefaultFavoritesForSub20();
        
        Sales_Setup_Update_Release8::createDefaultFavoritesForSub22();
        
        Sales_Setup_Update_Release8::createDefaultFavoritesForSub24();
    }
    
    /**
     * init key fields
     */
    protected function _initializeKeyFields()
    {
        $cb = new Tinebase_Backend_Sql(array(
            'modelName' => 'Tinebase_Model_Config',
            'tableName' => 'config',
        ));
        $appId = Tinebase_Application::getInstance()->getApplicationByName('Sales')->getId();
        
        // create product categories
        $tc = array(
            'name'    => Sales_Config::PRODUCT_CATEGORY,
            'records' => array(
                array('id' => 'DEFAULT', 'value' => 'Default', 'system' => true) // _('Default')
            ),
        );
        
        $cb->create(new Tinebase_Model_Config(array(
            'application_id' => $appId,
            'name'           => Sales_Config::PRODUCT_CATEGORY,
            'value'          => json_encode($tc),
        )));
        
        // create type config
        $tc = array(
            'name'    => Sales_Config::INVOICE_TYPE,
            'records' => array(
                array('id' => 'INVOICE',  'value' => 'Invoice',  'system' => true), // _('Invoice')
                array('id' => 'REVERSAL', 'value' => 'Reversal', 'system' => true), // _('Reversal')
                array('id' => 'CREDIT',   'value' => 'Credit',   'system' => true)  // _('Credit')
            ),
        );
        
        $cb->create(new Tinebase_Model_Config(array(
            'application_id' => $appId,
            'name'           => Sales_Config::INVOICE_TYPE,
            'value'          => json_encode($tc),
        )));
        
        // create cleared state keyfields
        $tc = array(
            'name'    => Sales_Config::INVOICE_CLEARED,
            'records' => array(
                array('id' => 'TO_CLEAR', 'value' => 'to clear', 'system' => true), // _('to clear')
                array('id' => 'CLEARED',  'value' => 'cleared',  'system' => true), // _('cleared')
            ),
        );
        
        $cb->create(new Tinebase_Model_Config(array(
            'application_id' => $appId,
            'name'           => Sales_Config::INVOICE_CLEARED,
            'value'          => json_encode($tc),
        )));
        
        // create payment types config
        $tc = array(
            'name'    => Sales_Config::PAYMENT_METHODS,
            'records' => array(
                array('id' => 'BANK TRANSFER', 'value' => 'Bank transfer', 'system' => true), // _('Bank transfer')
                array('id' => 'DIRECT DEBIT',  'value' => 'Direct debit',  'system' => true),  // _('Direct debit')
                array('id' => 'CANCELLATION',  'value' => 'Cancellation',  'system' => true),  // _('Cancellation')
                array('id' => 'CREDIT',  'value' => 'Credit',  'system' => true),  // _('Credit')
                array('id' => 'CREDIT CARD',  'value' => 'Credit card',  'system' => true),  // _('Credit card')
                array('id' => 'PAYPAL',  'value' => 'Paypal',  'system' => true),  // _('Paypal')
            ),
        );
        
        $cb->create(new Tinebase_Model_Config(array(
            'application_id' => $appId,
            'name'           => Sales_Config::PAYMENT_METHODS,
            'value'          => json_encode($tc),
        )));
        
    }
    
    /**
     * init scheduler tasks
     */
    protected function _initializeSchedulerTasks()
    {
        $scheduler = Tinebase_Core::getScheduler();
        Sales_Scheduler_Task::addUpdateProductLifespanTask($scheduler);
    }
}
