<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2010-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * class to hold data representing one object which can be inserted into the tree
 * 
 * @property  string   name
 * @property  string   revision
 * @property  string   available_revisions
 * @property  string   description
 * @property  string   contenttype
 * @property  integer  size
 * @property  integer  revision_size
 * @property  string   indexed_hash
 * @property  string   hash
 * @property  string   type
 * @property  integer  preview_count
 */
class Tinebase_Model_Tree_FileObject extends Tinebase_Record_Abstract
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
     * object type: folder
     * 
     * @var string
     */
    const TYPE_FOLDER = 'folder';
    
    /**
     * object type: file
     * 
     * @var string
     */
    const TYPE_FILE   = 'file';


    /**
     * object type: preview
     *
     * @var string
     */
    const TYPE_PREVIEW = 'preview';
    
    /**
     * this filter get used when validating user generated content with Zend_Input_Filter
     *
     * @var array list of zend inputfilter
     */
    protected $_filters = array(
        'contenttype' => 'StringToLower'
    );
    
    /**
     * list of zend validator
     * 
     * this validators get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_validators = array (
        // tine 2.0 generic fields
        'id'                    => array(Zend_Filter_Input::ALLOW_EMPTY => true, Zend_Filter_Input::DEFAULT_VALUE => NULL),
        'created_by'            => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'creation_time'         => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'last_modified_by'      => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'last_modified_time'    => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'is_deleted'            => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'deleted_time'          => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'deleted_by'            => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'seq'                   => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        
        // model specific fields
        'revision'              => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'available_revisions'   => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'description'           => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'contenttype'           => array(Zend_Filter_Input::ALLOW_EMPTY => true, Zend_Filter_Input::DEFAULT_VALUE => 'application/octet-stream'),
        'size'                  => array(Zend_Filter_Input::ALLOW_EMPTY => true, 'Digits'),
        'revision_size'         => array(Zend_Filter_Input::ALLOW_EMPTY => true, 'Digits'),
        'preview_count'         => array(Zend_Filter_Input::ALLOW_EMPTY => true, 'Digits', Zend_Filter_Input::DEFAULT_VALUE => 0),
        'hash'                  => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'indexed_hash'          => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'type'                  => array(
            'presence' => 'required',
            array('InArray', array(self::TYPE_FOLDER, self::TYPE_FILE, self::TYPE_PREVIEW))
        )
    );
    
    /**
     * name of fields containing datetime or or an array of datetime information
     *
     * @var array list of datetime fields
     */
    protected $_datetimeFields = array(
        'creation_time',
        'last_modified_time',
        'deleted_time'
    );

    /**
     * name of fields that should be omitted from modlog
     *
     * @var array list of modlog omit fields
     */
    protected $_modlogOmitFields = array('indexed_hash', 'preview_count', 'revision_size', 'available_revisions');

    protected static $_isReplicable = true;
    
    /**
     * converts a string or Addressbook_Model_List to a list id
     *
     * @param   string|Addressbook_Model_List  $_listId  the contact id to convert
     * 
     * @return  string
     * @throws  UnexpectedValueException  if no list id set 
     */
    static public function convertListIdToInt($_listId)
    {
        if ($_listId instanceof self) {
            if ($_listId->getId() == null) {
                throw new UnexpectedValueException('No identifier set.');
            }
            $id = (string) $_listId->getId();
        } else {
            $id = (string) $_listId;
        }
        
        if (empty($id)) {
            throw new UnexpectedValueException('Identifier can not be empty.');
        }
        
        return $id;
    }

    /**
     * returns real filesystem path
     * 
     * @param string $baseDir
     * @throws Tinebase_Exception_NotFound
     * @return string
     */
    public function getFilesystemPath($baseDir = NULL)
    {
        if (empty($this->hash)) {
            throw new Tinebase_Exception_NotFound('file object hash is missing');
        }
        
        if ($baseDir === NULL) {
            $baseDir = Tinebase_Core::getConfig()->filesdir;
        }
        
        return $baseDir . DIRECTORY_SEPARATOR . substr($this->hash, 0, 3) . DIRECTORY_SEPARATOR . substr($this->hash, 3);
    }

    public function runConvertToRecord()
    {
        if(isset($this->_properties['available_revisions'])) {
            $this->_properties['available_revisions'] = explode(',', ltrim(rtrim($this->_properties['available_revisions'], '}'), '{'));
        }

        parent::runConvertToRecord();
    }

    /**
     * @param bool $value
     * @return bool
     */
    public static function setReplicable($value)
    {
        $return = static::$_isReplicable;
        static::$_isReplicable = $value;
        return $return;
    }

    /**
     * returns true if this record should be replicated
     *
     * @return bool
     */
    public function isReplicable()
    {
        return static::$_isReplicable && (self::TYPE_FILE === $this->type || self::TYPE_FOLDER === $this->type);
    }
}
