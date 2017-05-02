<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * this tests some filters
 * 
 * @package     Timetracker
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2012-2015 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Alexander Stintzing <a.stintzing@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for Tinebase_Group
 */
class Timetracker_FilterTest extends Timetracker_AbstractTest
{
    /**
     * @var Timetracker_Controller_Timeaccount
     */
    protected $_timeaccountController = array();
    
    /**
     * objects
     *
     * @var array
     */
    protected $_objects = array();
    
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Tine 2.0 Timetracker Filter Tests');
        PHPUnit_TextUI_TestRunner::run($suite);
    }

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        parent::setUp();
        $this->_timeaccountController = Timetracker_Controller_Timeaccount::getInstance();
    }
    
    /************ test functions follow **************/

    /**
     * test timeaccount - sales contract filter
     * also tests Tinebase_Model_Filter_ExplicitRelatedRecord
     */
    public function testTimeaccountContractFilter()
    {
        $this->_getTimeaccount(array('title' => 'TA1', 'number' => 12345, 'description' => 'UnitTest'), true);
        $ta1 = $this->_timeaccountController->get($this->_lastCreatedRecord['id']);
        
        $this->_getTimeaccount(array('title' => 'TA2', 'number' => 12346, 'description' => 'UnitTest'), true);
        $ta2 = $this->_timeaccountController->get($this->_lastCreatedRecord['id']);
        
        $cId = Tinebase_Container::getInstance()->getDefaultContainer('Sales_Model_Contract')->getId();
        $contract = Sales_Controller_Contract::getInstance()->create(new Sales_Model_Contract(
            array('title' => 'testRelateTimeaccount', 'number' => Tinebase_Record_Abstract::generateUID(), 'container_id' => $cId)
        ));
        $ta1->relations = array($this->_getRelation($contract, $ta1));
        $this->_timeaccountController->update($ta1);

        $this->_deleteTimeAccounts[] = $ta1->getId();
        $this->_deleteTimeAccounts[] = $ta2->getId();
        Tinebase_TransactionManager::getInstance()->commitTransaction($this->_transactionId);
        $this->_transactionId = Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());
        
        // search by contract
        $f = new Timetracker_Model_TimeaccountFilter(array(array('field' => 'contract', 'operator' => 'AND', 'value' =>
            array(array('field' => ':id', 'operator' => 'equals', 'value' => $contract->getId()))
        )));

        $filterArray = $f->toArray();
        $this->assertEquals($contract->getId(), $filterArray[0]['value'][0]['value']['id']);
        
        $result = $this->_timeaccountController->search($f);
        $this->assertEquals(1, $result->count());
        $this->assertEquals('TA1', $result->getFirstRecord()->title);
        
        // test empty filter (without contract)
        $f = new Timetracker_Model_TimeaccountFilter(array(
            array('field' => 'contract', 'operator' => 'AND', 'value' =>
            array(array('field' => ':id', 'operator' => 'equals', 'value' => null))
        ),
            array('field' => 'description', 'operator' => 'equals', 'value' => 'UnitTest')
        ));

        $result = $this->_timeaccountController->search($f);

        $this->assertEquals(1, $result->count(), 'Only one record should have been found!');
        $this->assertEquals('TA2', $result->getFirstRecord()->title);
        
        // test generic relation filter
        $f = new Timetracker_Model_TimeaccountFilter(array(array('field' => 'foreignRecord', 'operator' => 'AND', 'value' =>
            array('appName' => 'Sales', 'linkType' => 'relation', 'modelName' => 'Contract',
                'filters' => array('field' => 'query', 'operator' => 'contains', 'value' => 'TA1'))
        )));
        $result = $this->_timeaccountController->search($f);
        $this->assertEquals(1, $result->count());
        $this->assertEquals('TA1', $result->getFirstRecord()->title);
        
        // test "not" operator
        $f = new Timetracker_Model_TimeaccountFilter(array(
            array('field' => 'contract', 'operator' => 'AND', 'value' =>
                array(array('field' => ':id', 'operator' => 'not', 'value' => $contract->getId()))
            ),
            array('field' => 'description', 'operator' => 'equals', 'value' => 'UnitTest')
        ));
        
        $result = $this->_timeaccountController->search($f);
        
        // TODO is this correct? do we expect the timaccount without contract to be missing from results?
        $this->assertEquals(0, $result->count(), 'No record should be found');
    }

    /**
     * returns timeaccount-contract relation
     * @param Sales_Model_Contract $contract
     * @param Timetracker_Model_Timeaccount $timeaccount
     */
    protected function _getRelation($contract, $timeaccount)
    {
        $r = new Tinebase_Model_Relation();
        $ra = array(
            'own_model' => 'Timetracker_Model_Timeaccount',
            'own_backend' => 'Sql',
            'own_id' => $timeaccount->getId(),
            'related_degree' => 'sibling',
            'remark' => 'phpunit test',
            'related_model' => 'Sales_Model_Contract',
            'related_backend' => 'Sql',
            'related_id' => $contract->getId(),
            'type' => 'CONTRACT');
        $r->setFromArray($ra);
        return $r;
    }
    
    /**
     * tests the corret handling of the usertimezone in the date filter
     */
    public function testDateIntervalFilter()
    {
        $taController = Timetracker_Controller_Timeaccount::getInstance();
        $tsController = Timetracker_Controller_Timesheet::getInstance();
        $dateString = '2014-07-14 00:15:00';
        
        $date = new Tinebase_DateTime($dateString, Tinebase_Core::getUserTimezone());
        
        $ta = $taController->create(new Timetracker_Model_Timeaccount(array('number' => '123', 'title' => 'test')));
        $r = new Timetracker_Model_Timesheet(array(
            'timeaccount_id' => $ta->getId(), 
            'account_id' => Tinebase_Core::getUser()->getId(),
            'description' => 'lazy boring',
            'start_date' => $date,
            'duration' => 30,
        ));
        
        $r->setTimezone('UTC');
        
        $ts = $tsController->create($r);
        
        $filter = new Timetracker_Model_TimesheetFilter(array());
        $dateFilter = new Tinebase_Model_Filter_DateMock(
            array('field' => 'start_date', 'operator' => "within", "value" => "weekThis")
        );
        
        $dateFilter::$testDate = $date;
        
        $filter->addFilter($dateFilter);
        
        $results = $tsController->search($filter);
        
        $this->assertEquals(1, $results->count());
    }
    
    /**
     * tests if the Timeaccount Filter is there
     */
    public function testTimeaccountfilterWithoutAdmin()
    {
        $this->_removeRoleRight('Timetracker', Tinebase_Acl_Rights::ADMIN);
        $this->assertFalse(Tinebase_Core::getUser()->hasRight('Timetracker', Tinebase_Acl_Rights::ADMIN));
        $config = Tinebase_ModelConfiguration::getFrontendConfigForModels(array('Timetracker_Model_Timesheet'));
        $this->assertTrue(isset($config['Timesheet']['filterModel']['timeaccount_id']));
    }
}
