<?php
/**
 * class to hold ExampleRecord data
 * 
 * @package     ExampleApplication
 * @subpackage  Model
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 */

/**
 * class to hold ExampleRecord data
 * 
 * @package     ExampleApplication
 * @subpackage  Model
 */
class ExampleApplication_Model_ExampleRecord extends Tinebase_Record_Abstract
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
        'version'           => 1,
        'recordName'        => 'example record', // _('example record') ngettext('example record', 'example records', n)
        'recordsName'       => 'example records', // _('example records')
        'containerProperty' => 'container_id',
        'titleProperty'     => 'name',
        'containerName'     => 'example record list', // _('example record list')
        'containersName'    => 'example record lists', // _('example record lists')
        'hasRelations'      => TRUE,
        'hasCustomFields'   => TRUE,
        'hasNotes'          => TRUE,
        'hasTags'           => TRUE,
        'modlogActive'      => TRUE,
        'hasAttachments'    => TRUE,

        'createModule'      => TRUE,

        'exposeHttpApi'     => true,
        'exposeJsonApi'     => true,

        'appName'           => 'ExampleApplication',
        'modelName'         => 'ExampleRecord',

        'table'             => array(
            'name'    => 'example_application_record',
            'options' => array('collate' => 'utf8_general_ci'),
            'indexes' => array(
                'testcontainer_id' => array(
                    'columns' => array('container_id')
                ),
                'description' => array(
                    'columns' => array('description'),
                    'flags' => array('fulltext')
                )
            ),
        ),

        'export'            => array(
            'supportedFormats' => array('csv'),
        ),

        'fields'          => array(
            'name' => array(
                'type'       => 'string',
                'length'     => 255,
                'nullable'   => false,
                'validators'  => array(Zend_Filter_Input::ALLOW_EMPTY => false, 'presence' => 'required'),
                'label'       => 'Name', // _('Name')
                'queryFilter' => TRUE
            ),
            'description' => array(
                'type'       => 'fulltext',
                'nullable'   => true,
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'      => 'Description',
            ),
            'status' => array(
                'validators' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label' => 'Status', // _('Status')
                'type' => 'keyfield',
                'nullable'   => false,
                'name' => 'exampleStatus',
                'default' => 'IN-PROCESS'
            ),
            'reason' => array(
                'reason' => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label' => 'Reason', // _('Reason')
                'type' => 'keyfield',
                'name' => 'exampleReason',
                'nullable'   => true
            ),
            'number_str' => array(
                'validators'  => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'       => 'Number', // _('Number')
                // TODO reactivate when fixed in query filter
                //'queryFilter' => TRUE,
                'type' => 'numberableStr',
                'config' => array(
                    Tinebase_Numberable::CONF_STEPSIZE          => 1,
                    Tinebase_Numberable::CONF_BUCKETKEY         => 'ExampleApplication_Model_ExampleRecord#number_str',
                    Tinebase_Numberable_String::CONF_PREFIX     => 'ER-',
                    Tinebase_Numberable_String::CONF_ZEROFILL   => 0,
                    // TODO implement that
//                    'filters' => '', // group/filters - use to link with container for example
//                    'allowClientSet' => '', // force?
//                    'allowDuplicate' => '',
//                    'duplicateResolve' => array(
//                        'inc/2 (recursive)' => '',
//                        'next free' => '',
//                        'exception' => '',
//                    ),
                )
            ),
            'number_int' => array(
                'validators'  => array(Zend_Filter_Input::ALLOW_EMPTY => TRUE),
                'label'       => 'Number', // _('Number')
                // TODO reactivate when fixed in query filter
                //'queryFilter' => TRUE,
                'type' => 'numberableInt',
                'config' => array(
                    Tinebase_Numberable::CONF_STEPSIZE          => 1,
                    Tinebase_Numberable::CONF_BUCKETKEY          => 'ExampleApplication_Model_ExampleRecord#number_int',
                )
            ),
        )
    );
}
