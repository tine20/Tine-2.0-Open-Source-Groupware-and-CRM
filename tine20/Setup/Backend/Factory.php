<?php
/**
 * backend factory class for the Setup
 *
 * @package     Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2007-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * backend factory class for the Setup
 *
 * @package     Setup
 */
class Setup_Backend_Factory
{

    static protected $_instanceCache = array();

    /**
     * factory function to return a selected setup backend class
     *
     * @param string|null $_type
     * @return Setup_Backend_Interface
     * @throws Setup_Exception
     * @throws Tinebase_Exception_InvalidArgument
     */
    static public function factory($_type = null)
    {
        if (isset(static::$_instanceCache[$_type])) {
            return static::$_instanceCache[$_type];
        }

        if (empty($_type)) {
            $db = Tinebase_Core::getDb();
            $adapterName = get_class($db);

            // get last part of class name
            if (empty($adapterName) || strpos($adapterName, '_') === FALSE) {
                throw new Setup_Exception('Could not get DB adapter name.');
            }
            $adapterNameParts = explode('_',$adapterName);
            $type = array_pop($adapterNameParts);
            
            // special handling for Oracle
            $type = str_replace('Oci', Tinebase_Core::ORACLE, $type);
            
            $className = 'Setup_Backend_' . ucfirst($type);
        } else {
            $className = 'Setup_Backend_' . ucfirst($_type);
        }
        
        if (!class_exists($className)) {
            throw new Tinebase_Exception_InvalidArgument('Invalid database backend type defined.');
        }
        
        $instance = new $className();

        static::$_instanceCache[$_type] = $instance;

        return $instance;
    }
}
