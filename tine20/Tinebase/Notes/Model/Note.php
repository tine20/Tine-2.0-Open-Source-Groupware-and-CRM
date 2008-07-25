<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Notes
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @version     $Id$
 */

/**
 * defines the datatype for one note
 * 
 * @package     Tinebase
 * @subpackage  Notes
 */
class Tinebase_Notes_Model_Note extends Tinebase_Record_Abstract
{
	/**
     * key in $_validators/$_properties array for the filed which 
     * represents the identifier
     * 
     * @var string
     */    
    protected $_identifier = 'id';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Tinebase';
    
    /**
     * list of zend inputfilter
     * 
     * this filter get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_filters = array(
        'note'              => 'StringTrim'
    );
    
    /**
     * list of zend validator
     * 
     * this validators get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_validators = array(
        'id'                     => array('Alnum', 'allowEmpty' => true),
        'note_type_id'           => array('Alnum', 'allowEmpty' => false),
    
        'note'                   => array('presence' => 'required', 'allowEmpty' => false),
        
        'record_id'              => array('allowEmpty' => true),
        'record_model'           => array('allowEmpty' => true),
        'record_backend'         => array('allowEmpty' => true),
    
        'created_by'             => array('allowEmpty' => true),
        'creation_time'          => array('allowEmpty' => true),
        'last_modified_by'       => array('allowEmpty' => true),
        'last_modified_time'     => array('allowEmpty' => true),
        'is_deleted'             => array('allowEmpty' => true),
        'deleted_time'           => array('allowEmpty' => true),
        'deleted_by'             => array('allowEmpty' => true),
    );
    
    protected $_datetimeFields = array(
        'creation_time',
        'last_modified_time',
        'deleted_time',
    );    
    
    /**
     * returns array with record related properties
     * resolves the creator display name and calls Tinebase_Record_Abstract::toArray() 
     *
     * @param boolean $_recursive
     * @param boolean $_resolveCreator
     * @return array
     */    
    public function toArray($_recursive = TRUE, $_resolveCreator = TRUE)
    {
        $result = parent::toArray($_recursive);
        
        // get creator
        if ($this->created_by && $_resolveCreator) {
            $creator = Tinebase_User::getInstance()->getUserById($this->created_by); 
            $result['created_by'] = $creator->accountDisplayName; 
        }
        
        return $result;
    }
}