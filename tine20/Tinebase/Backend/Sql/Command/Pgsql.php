<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Backend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@serpro.gov.br>
 * @copyright   Copyright (c) 2011-2015 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * encapsulates SQL commands of PostgreSQL database
 *
 * @package     Tinebase
 * @subpackage  Backend
 */
class Tinebase_Backend_Sql_Command_Pgsql implements Tinebase_Backend_Sql_Command_Interface
{
    /**
     * @var Tinebase_Backend_Sql_Adapter_Pdo_Pgsql
     */
    protected $_adapter;
    
    /**
     * @param Tinebase_Backend_Sql_Adapter_Pdo_Pgsql $adapter
     */
    public function __construct(Tinebase_Backend_Sql_Adapter_Pdo_Pgsql $adapter)
    {
        $this->_adapter = $adapter;
    }
    
    /**
     * @param string $field
     * @return string
     */
    public function getAggregate($field)
    {
        $quotedField = $this->_adapter->quoteIdentifier($field);
        
        // since 9.0
        #return new Zend_Db_Expr("string_agg(DISTINCT $quotedField, ',')");
        
        // before 9.0
        return new Zend_Db_Expr("array_to_string(ARRAY(SELECT DISTINCT unnest(array_agg($quotedField))),',')");
    }
    
    /**
     * returns concatenation expression
     *
     * @param array $values
     */
    public function getConcat($values)
    {
        $str = '';
        $i   = 1;
        $vc  = count($values);
        
        foreach($values as $value) {
            $str .= $value;
            if ($i < $vc) {
                $str .= ' || ';
            }
            $i++;
        }
        
        return new Zend_Db_Expr($str);
    }
    
    /**
     * @param string $field
     * @param mixed $returnIfTrue
     * @param mixed $returnIfFalse
     * @return string
     */
    public function getIfIsNull($field, $returnIfTrue, $returnIfFalse)
    {
        $quotedField = $this->_adapter->quoteIdentifier($field);
        
        return new Zend_Db_Expr("(CASE WHEN $quotedField IS NULL THEN $returnIfTrue ELSE $returnIfFalse END)");
    }
    
    /**
     * @param Zend_Db_Adapter_Abstract $adapter
     * @param string $condition
     * @param string $returnIfTrue
     * @param string $returnIfFalse
     * @return string
     */
    public function getIfElse($condition, $returnIfTrue, $returnIfFalse)
    {
        return new Zend_Db_Expr("(CASE WHEN $condition THEN $returnIfTrue ELSE $returnIfFalse END)");
    }
    
    /**
     * get get switch case expression with multiple cases
     * 
     * @param string $field
     * @param array $cases
     * 
     * @return Zend_Db_Expr
     */
    public function getSwitch($field, $cases)
    {
        $case = 'CASE ' . $this->_adapter->quoteIdentifier($field) . ' ';
        foreach ($cases as $when => $then) {
            $case .=  $this->_adapter->quoteInto(' WHEN ' . $when . ' THEN ?', $then);
        }
        $case .= ' END';
        return new Zend_Db_Expr($case);
    }
    
    /**
     * @param string $date
     * @return string
     */
    public function setDate($date)
    {
        return "DATE({$date})";
    }
    
    /**
     * @param string $value
     * @return string
     */
    public function setDateValue($value)
    {
        return $this->_adapter->quote($value);
    }
    
    /**
     * @return mixed
     */
    public function getFalseValue()
    {
        return 'FALSE';
    }
    
    /**
     * @return mixed
     */
    public function getTrueValue()
    {
        return 'TRUE';
    }
    
    /**
     * @return string
     */
    public function setDatabaseJokerCharacters()
    {
        return array('%', '\_');
    }
    
    /**
     * get like keyword
     *
     * @return string
     */
    public function getLike()
    {
        return 'iLIKE';
    }
    
    /**
     * prepare value for case insensitive search
     *
     * @param string $value
     * @return string
     */
    public function prepareForILike($value)
    {
        return $value;
    }
    
    /**
     * Even if the database backend is PostgreSQL, we have to verify
     *  if the extension Unaccent is installed and loaded.
     *  This is done in Tinebase_Core::checkUnaccentExtension.
     *
     * @return boolean
     */
    protected function _hasUnaccentExtension()
    {
        try {
            $session = Tinebase_Session::getSessionNamespace();
            
            if (isset($session->dbcapabilities) && (isset($session->dbcapabilities['unaccent']) || array_key_exists('unaccent', $session->dbcapabilities))) {
                $result = $session->dbcapabilities['unaccent'];
            } else {
                $result = $this->_adapter->hasUnaccentExtension();
                
                $capabilities['unaccent'] = $result;
                
                $session->dbcapabilities = $capabilities;
            }
            
        } catch (Zend_Session_Exception $zse) {
            $result = $this->_adapter->hasUnaccentExtension();
        }
        
        return $result;
    }
    
    /**
     * returns field without accents (diacritic signs) - for Pgsql;
     *
     * @param string $field
     * @return string
     */
    public function getUnaccent($field)
    {
        if ($this->_hasUnaccentExtension()){
            return ' unaccent('.$field.') ';
        } else{
            return $field;
        }
    }
    
    /**
     * escape special char
     *
     * @return string
     */
     public function escapeSpecialChar($value)
     {
         return str_replace('\\', '\\\\', $value);
     }
     
     /**
      * Initializes database procedures
      * @param Setup_Backend_Interface $backend
      */
     public function initProcedures(Setup_Backend_Interface $backend)
     {

     }

    /**
     * returns something similar to "(interval '$staticPart $timeUnit' * $dynamicPart)"
     *
     * @see http://www.postgresql.org/docs/9.1/static/functions-datetime.html
     *
     * @param string $timeUnit
     * @param string $staticPart
     * @param string $dynamicPart
     * @return string
     */
    public function getDynamicInterval($timeUnit, $staticPart, $dynamicPart)
    {
        return '(' . $this->getInterval($timeUnit, $staticPart) .  ' * ' . $dynamicPart . ')';
    }

    /**
     * returns something similar to "interval '$staticPart $timeUnit'"
     *
     * @param string $timeUnit
     * @param string $staticPart
     * @return string
     */
    public function getInterval($timeUnit, $staticPart)
    {
        return "INTERVAL '" . $staticPart . ' ' . $timeUnit . "'";
    }
}
