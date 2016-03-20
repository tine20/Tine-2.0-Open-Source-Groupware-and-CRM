<?php
/**
 * class to hold InventoryItem data
 * 
 * @package     Inventory
 * @subpackage  Model
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Stefanie Stamer <s.stamer@metaways.de>
 * @copyright   Copyright (c) 2007-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 */

/**
 * class to hold InventoryItem data
 * 
 * @package     Inventory
 * @subpackage  Model
 */
class Inventory_Model_InventoryItem extends Tinebase_Record_Abstract
{
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
        'recordName'        => 'Inventory item',
        'recordsName'       => 'Inventory items', // ngettext('Inventory item', 'Inventory items', n)
        'containerProperty' => 'container_id',
        'titleProperty'     => 'name',
        'containerName'     => 'Inventory item list',
        'containersName'    => 'Inventory item lists', // ngettext('Inventory item list', 'Inventory item lists', n)
        'hasRelations'      => TRUE,
        'hasCustomFields'   => TRUE,
        'hasNotes'          => TRUE,
        'hasTags'           => TRUE,
        'modlogActive'      => TRUE,
        'hasAttachments'    => TRUE,

        'createModule'    => TRUE,

        'appName'         => 'Inventory',
        'modelName'       => 'InventoryItem',
        
        'fields'          => array(
            'name' => array(
                'validators'  => array(Zend_Filter_Input::ALLOW_EMPTY => false, 'presence' => 'required'),
                'label'       => 'Name', // _('Name')
                'queryFilter' => TRUE
            ),
            'status' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label' => 'Status', // _('Status')
                'type' => 'keyfield',
                'name' => 'inventoryStatus',
            ),
            'inventory_id' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => 'Inventory ID' // _('Inventory ID')
            ),
            'description' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      =>'Description' // _('Description')
            ),
            'location' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => 'Location', // _('Location')
            ),
            'invoice_date' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => 'Invoice date', // _('Invoice date')
                'inputFilters' => array('Zend_Filter_Empty' => NULL),
                'hidden'     => TRUE,
                'default'    => NULL,
                'type'       => 'datetime',
                'inputFilters' => array('Zend_Filter_Empty' => NULL),
            ),
            'total_number' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => NULL,
                'inputFilters' => array('Zend_Filter_Empty' => NULL),
                'default'    => 1,
            ),
            'invoice' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => 'Invoice', // _('Invoice')
                'hidden'     => TRUE
            ),
            'price' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => 'Price', // _('Price')
                'hidden'     => TRUE,
                'inputFilters' => array('Zend_Filter_Empty' => NULL),
            ),
            'costcentre' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => 'Cost centre', // _('Cost Center')
                'hidden'     => TRUE,
                'type'       => 'record',
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE, Zend_Filter_Input::DEFAULT_VALUE => NULL),
                'config' => array(
                    'appName'     => 'Sales',
                    'modelName'   => 'CostCenter',
                    'idProperty'  => 'id',
                ),
            ),
            'warranty' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => 'Warranty', // _('Warranty')
                'hidden'     => TRUE,
                'inputFilters' => array('Zend_Filter_Empty' => NULL),
                'type'       => 'datetime'
            ),
            'added_date' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => 'Item added', // _('Item added')
                'hidden'     => TRUE,
                'inputFilters' => array('Zend_Filter_Empty' => NULL),
                'type'       => 'datetime'
            ),
            'removed_date' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => 'Item removed', // _('Item removed')
                'hidden'     => TRUE,
                'inputFilters' => array('Zend_Filter_Empty' => NULL),
                'type'       => 'datetime'
            ),
            'active_number' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => 'Available number', // _(Available number)
                'inputFilters' => array('Zend_Filter_Empty' => NULL),
                'default'    => 1,
            ),
            'depreciate_status' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE, Zend_Filter_Input::DEFAULT_VALUE => 0),
                //'label' => 'Depreciate', // _('Depreciate')
                'label'      => NULL,
                'inputFilters' => array('Zend_Filter_Empty' => NULL),
            ),
            'image' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label' => 'Image', // _('Image')
                'inputFilters' => array('Zend_Filter_Empty' => NULL),
                // is saved in vfs, only image files allowed
                'type' => 'image'
            ),
        )
    );
}
