<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Backend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@serpro.gov.br>
 * @copyright   Copyright (c) 2011-2014 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * encapsulates SQL commands that are different for each dialect
 *
 * @package     Tinebase
 * @subpackage  Backend
 */
interface Tinebase_Backend_Sql_Command_Interface
{
    /**
     * @param string $field
     * @return string
     */
    public function getAggregate($field);
    
    /**
     * @param string $field
     * @param mixed $returnIfTrue
     * @param mixed $returnIfFalse
     * @return string
     */
    public function getIfIsNull($field, $returnIfTrue, $returnIfFalse);
    
    /**
     * @param string $condition
     * @param string $returnIfTrue
     * @param string $returnIfFalse
     * @return string
     */
    public function getIfElse($condition, $returnIfTrue, $returnIfFalse);
    
    /**
     * get switch case expression with multiple cases
     *
     * @param string $field
     * @param array $cases
     *
     * @return Zend_Db_Expr
     */
    public function getSwitch($field, $cases);
    
    /**
     * @param date $date
     */
    public function setDate($date);
    
    /**
     * @param date $date
     */
    public function setDateValue($date);

    /**
     * returns the false value according to backend
     * @return mixed
     */
    public function getFalseValue();
    
    /**
     * returns the true value according to backend
     * @return mixed
     */
    public function getTrueValue();
    
    /**
     * @param array $field
     */
    public function setDatabaseJokerCharacters();
    
    /**
     * get like keyword
     *
     * @return string
     */
    public function getLike();
    
    /**
     * prepare value for case insensitive search
     *
     * @param string $value
     * @return string
     */
    public function prepareForILike($value);
    
    /**
     * returns field without accents (diacritic signs) - for Pgsql;
     *
     * @param string $field
     * @return string
     */
    public function getUnaccent($field);
    
    /**
     * escape special char
     *
     * @ return string
     */
    public function escapeSpecialChar($value);
    
    /**
     * Initializes database procedures
     * @param Setup_Backend_Interface $backend
     */
    public function initProcedures(Setup_Backend_Interface $backend);

    /**
     * returns something similar to "interval $staticPart * $dynamicPart $timeUnit"
     *
     * @param string $timeUnit
     * @param string $staticPart
     * @param string $dynamicPart
     * @return string
     */
    public function getDynamicInterval($timeUnit, $staticPart, $dynamicPart);

    /**
     * returns something similar to "interval $staticPart $timeUnit"
     *
     * @param string $timeUnit
     * @param string $staticPart
     * @return string
     */
    public function getInterval($timeUnit, $staticPart);
}
