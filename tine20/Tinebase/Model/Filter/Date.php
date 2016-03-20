<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Filter
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * 
 * @todo        add year to 'inweek' filter?
 */

/**
 * Tinebase_Model_Filter_Date
 * 
 * filters date in one property
 * 
 * @package     Tinebase
 * @subpackage  Filter
 */
class Tinebase_Model_Filter_Date extends Tinebase_Model_Filter_Abstract
{
    /**
     * @var array list of allowed operators
     */
    protected $_operators = array(
        0 => 'equals',
        1 => 'within',
        2 => 'before',
        3 => 'after',
        4 => 'isnull',
        5 => 'notnull',
        6 => 'inweek',
        7 => 'before_or_equals',
        8 => 'after_or_equals',
    );
    
    /**
     * @var array maps abstract operators to sql operators
     */
    protected $_opSqlMap = array(
        'equals'            => array('sqlop' => ' = ?'),
        'within'            => array('sqlop' => array(' >= ? ', ' <= ?')),
        'before'            => array('sqlop' => ' < ?'),
        'after'             => array('sqlop' => ' > ?'),
        'isnull'            => array('sqlop' => ' IS NULL'),
        'notnull'           => array('sqlop' => ' IS NOT NULL'),
        'inweek'            => array('sqlop' => array(' >= ? ', ' <= ?')),
        'before_or_equals'  => array('sqlop' => ' <= ?'),
        'after_or_equals'   => array('sqlop' => ' >= ?'),
    );
    
    /**
     * date format string
     *
     * @var string
     */
    protected $_dateFormat = 'Y-m-d';
    
    /**
     * appends sql to given select statement
     *
     * @param Zend_Db_Select                $_select
     * @param Tinebase_Backend_Sql_Abstract $_backend
     */
    public function appendFilterSql($_select, $_backend)
    {
        // prepare value
        if ($this->_operator === 'equals' && empty($this->_value)) {
            // @see 0009362: allow to filter for empty datetimes
            $operator = 'isnull';
            $value = array($this->_value);
        } else {
            $operator = $this->_operator;
            $value = $this->_getDateValues($operator, $this->_value);
            if (! is_array($value)) {
                // NOTE: (array) null is an empty array
                $value = array($value);
            }
        }
        
        // quote field identifier
        $field = $this->_getQuotedFieldName($_backend);

        $db = Tinebase_Core::getDb();
        $dbCommand = Tinebase_Backend_Sql_Command::factory($db);
         
        // append query to select object
        foreach ((array)$this->_opSqlMap[$operator]['sqlop'] as $num => $operator) {
            if ((isset($value[$num]) || array_key_exists($num, $value))) {
                if (get_parent_class($this) === 'Tinebase_Model_Filter_Date' || in_array($operator, array('isnull', 'notnull'))) {
                    $_select->where($field . $operator, $value[$num]);
                } else {
                    $_select->where($dbCommand->setDate($field). $operator, new Zend_Db_Expr($dbCommand->setDateValue($value[$num])));
                }
            } else {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' No filter value found, skipping operator: ' . $operator);
            }
        }
    }
    
    /**
     * calculates the date filter values
     *
     * @param string $_operator
     * @param string $_value
     * @return array|string date value
     */
    protected function _getDateValues($_operator, $_value)
    {
        if ($_operator === 'within') {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Setting "within" filter: ' . $_value);
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Timezone: ' . date_default_timezone_get());
            
            $date = $this->_getDate(NULL, TRUE);
            
            // special values like this week, ...
            switch($_value) {

                /******** anytime ******/
                case 'anytime':
                    $last = $date->toString('Y-m-t');
                    $first = '1970-01-01';
                    
                    $value = array(
                        $first, 
                        $last,
                    );
                    break;
                
                /******* week *********/
                case 'weekNext':
                    $date->add(21, Tinebase_DateTime::MODIFIER_DAY);
                case 'weekBeforeLast':    
                    $date->sub(7, Tinebase_DateTime::MODIFIER_DAY);
                case 'weekLast':    
                    $date->sub(7, Tinebase_DateTime::MODIFIER_DAY);
                case 'weekThis':
                    $value = $this->_getFirstAndLastDayOfWeek($date);
                    break;
                /******* month *********/
                case 'monthNext':
                    $date->add(2, Tinebase_DateTime::MODIFIER_MONTH);
                case 'monthLast':
                    $month = $date->get('m');
                    if ($month > 1) {
                        $date = $date->setDate($date->get('Y'), $month - 1, 1);
                    } else {
                        $date->subMonth(1);
                    }
                case 'monthThis':
                    $dayOfMonth = $date->get('j');
                    $monthDays = $date->get('t');
                    
                    $first = $date->toString('Y-m') . '-01';
                    $date->add($monthDays-$dayOfMonth, Tinebase_DateTime::MODIFIER_DAY);
                    $last = $date->toString($this->_dateFormat);
    
                    $value = array(
                        $first, 
                        $last,
                    );
                    break;
                    
                case 'monthThreeLast':
                    $last = $date->toString('Y-m-d');
                    $date->subMonth(3);
                    $first = $date->toString($this->_dateFormat);
                    
                    $value = array(
                        $first, 
                        $last,
                    );
                    break;
                    
                case 'monthSixLast':
                    $last = $date->toString('Y-m-d');
                    $date->subMonth(6);
                    $first = $date->toString($this->_dateFormat);
                    
                    $value = array(
                        $first, 
                        $last,
                    );
                    break;
                                        
                /******* year *********/
                case 'yearNext':
                    $date->add(2, Tinebase_DateTime::MODIFIER_YEAR);
                case 'yearLast':
                    $date->sub(1, Tinebase_DateTime::MODIFIER_YEAR);
                case 'yearThis':
                    $value = array(
                        $date->toString('Y') . '-01-01', 
                        $date->toString('Y') . '-12-31',
                    );
                    break;
                /******* quarter *********/
                case 'quarterNext':
                    $date->add(6, Tinebase_DateTime::MODIFIER_MONTH);
                case 'quarterLast':
                    $date->sub(3, Tinebase_DateTime::MODIFIER_MONTH);
                case 'quarterThis':
                    $month = $date->get('m');
                    if ($month < 4) {
                        $first = $date->toString('Y' . '-01-01');
                        $last = $date->toString('Y' . '-03-31');
                    } elseif ($month < 7) {
                        $first = $date->toString('Y' . '-04-01');
                        $last = $date->toString('Y' . '-06-30');
                    } elseif ($month < 10) {
                        $first = $date->toString('Y' . '-07-01');
                        $last = $date->toString('Y' . '-09-30');
                    } else {
                        $first = $date->toString('Y' . '-10-01');
                        $last = $date->toString('Y' . '-12-31');
                    }
                    $value = array(
                        $first, 
                        $last
                    );
                    break;
                /******* day *********/
                case 'dayNext':
                    $date->add(2, Tinebase_DateTime::MODIFIER_DAY);
                case 'dayLast':
                    $date->sub(1, Tinebase_DateTime::MODIFIER_DAY);
                case 'dayThis':
                    $value = array(
                        $date->toString($this->_dateFormat), 
                        $date->toString($this->_dateFormat), 
                    );
                    
                    break;
                /******* try to create datetime from value string *********/
                default:
                    try {
                        $date = $this->_getDate($_value, TRUE);
                        
                        $value = array(
                            $date->toString($this->_dateFormat),
                            $date->toString($this->_dateFormat),
                        );
                    } catch (Exception $e) {
                        Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' Bad value: ' . $_value);
                        Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $e);
                        $value = '';
                    }
            }
        } elseif ($_operator === 'inweek') {
            $date = $this->_getDate(NULL, TRUE);
            
            if ($_value < 1) {
                $_value = $date->get('W');
            }
            $value = $this->_getFirstAndLastDayOfWeek($date, $_value);
            
        } else  {
            $value = substr($_value, 0, 10);
        }
        
        return $value;
    }
    
    /**
     * get string representation of first and last days of the week defined by date/week number
     * 
     * @param Tinebase_DateTime $_date
     * @param integer $_weekNumber optional
     * @return array
     */
    protected function _getFirstAndLastDayOfWeek(Tinebase_DateTime $_date, $_weekNumber = NULL)
    {
        $firstDayOfWeek = $this->_getFirstDayOfWeek();
        
        if ($_weekNumber !== NULL) {
            $_date->setWeek($_weekNumber);
        } 
        
        $dayOfWeek = $_date->get('w');
        // in some locales sunday is last day of the week -> we need to init dayOfWeek with 7
        $dayOfWeek = ($firstDayOfWeek == 1 && $dayOfWeek == 0) ? 7 : $dayOfWeek;
        $_date->sub($dayOfWeek - $firstDayOfWeek, Tinebase_DateTime::MODIFIER_DAY);
        
        $firstDay = $_date->toString($this->_dateFormat);
        $_date->add(6, Tinebase_DateTime::MODIFIER_DAY);
        $lastDay = $_date->toString($this->_dateFormat);
            
        $result = array(
            $firstDay,
            $lastDay, 
        );
        
        return $result;
    }
    
    /**
     * returns number of the first day of the week (0 = sunday or 1 = monday) depending on locale
     * 
     * @return integer
     */
    protected function _getFirstDayOfWeek()
    {
        $locale = Tinebase_Core::getLocale();
        $weekInfo = Zend_Locale_Data::getList($locale, 'week');

        if (!isset($weekInfo['firstDay'])) {
            $result = 1;
        } else {
            $result = ($weekInfo['firstDay'] === 'sun') ? 0 : 1;
        }
        
        return $result;
    }
    
    /**
     * returns the current date if no $date string is given (needed for mocking only)
     * 
     * @param string $date
     * @param boolean $usertimezone
     */
    protected function _getDate($date = NULL, $usertimezone = FALSE)
    {
        if (! $date) {
            $date = Tinebase_DateTime::now();
        } else {
            $date = new Tinebase_DateTime($date);
        }
        
        if ($usertimezone) {
            $date->setTimezone(Tinebase_Core::getUserTimezone());
        }
        
        return $date;
    }
}
