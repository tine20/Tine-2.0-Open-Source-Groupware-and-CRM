<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * Test class for Tinebase_Frontend_Json_PersistentFilter
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2009-2016 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * 
 */
class Tinebase_Frontend_Json_PersistentFilterTest extends TestCase
{
    /**
     * @var Tinebase_Frontend_Json_PersistentFilter
     */
    protected $_uit;
    
    /**
     * the test user
     * 
     * @var Tinebase_Model_FullUser
     */
    protected $_originalTestUser;
    
    /**
     * setUp
     */
    public function setUp()
    {
        parent::setUp();
        
        $this->_uit = new Tinebase_Frontend_Json_PersistentFilter();
        $this->_originalTestUser = Tinebase_Core::getUser();
    }
    
    /**
     * tearDown
     */
    public function tearDown()
    {
        Tinebase_Core::set(Tinebase_Core::USER, $this->_originalTestUser);
        
        parent::tearDown();
    }
    
    /**
     * test to save a persistent filter
     *
     * @param array|null $filterData
     * @return array
     */
    public function testSaveFilter($filterData = NULL)
    {
        $exampleFilterData = $filterData ? $filterData : self::getPersistentFilterData();
        $savedFilterData = $this->_uit->savePersistentFilter(self::getPersistentFilterData());
        
        $this->_assertSavedFilterData($exampleFilterData, $savedFilterData);
        $this->assertTrue(! empty($savedFilterData['grants']));
        $this->assertTrue(! empty($savedFilterData['account_grants']));
        foreach (array('readGrant', 'editGrant', 'deleteGrant') as $grant) {
            $this->assertTrue($savedFilterData['account_grants'][$grant]);
        }
        
        return $savedFilterData;
    }
    
    /**
     * test to save a shared persistent filter
     */
    public function testSaveSharedFilter()
    {
        $exampleFilterData = self::getPersistentFilterData();
        $exampleFilterData['account_id'] = NULL;
        $savedFilterData = $this->_uit->savePersistentFilter($exampleFilterData);
        
        $this->_assertSavedFilterData($exampleFilterData, $savedFilterData);
        
        return $savedFilterData;
    }
    
    /**
     * testGetSimpleFilter
     */
    public function testGetSimpleFilter()
    {
        $exampleFilterData = self::getPersistentFilterData();
        $savedFilterData = $this->testSaveFilter($exampleFilterData);
        $loadedFilterData = $this->_uit->getPersistentFilter($savedFilterData['id']);
        $this->_assertSavedFilterData($exampleFilterData, $loadedFilterData);
    }
    
    /**
     * testTimezoneConversion
     */
    public function testTimezoneConversion()
    {
        $exampleFilterData = self::getPersistentFilterData();
        $savedFilterData = $this->testSaveFilter($exampleFilterData);
        
        $testUserTimezone = Tinebase_Core::getUserTimezone();
        Tinebase_Core::set(Tinebase_Core::USERTIMEZONE, $testUserTimezone !== 'US/Pacific' ? 'US/Pacific' : 'UTC');
        
        $originalDueDateFilter = $this->_getFilter('due', $exampleFilterData);
        $convertedDueDataFilter = $this->_getFilter('due', $this->_uit->getPersistentFilter($savedFilterData['id']));

        Tinebase_Core::set(Tinebase_Core::USERTIMEZONE, $testUserTimezone);
        
        $this->assertNotEquals($originalDueDateFilter['value'], $convertedDueDataFilter['value']);
    }
    
    /**
     * testSearchFilter
     */
    public function testSearchFilter()
    {
        $exampleFilterData = self::getPersistentFilterData();
        $savedFilterData = $this->testSaveFilter($exampleFilterData);
        
        $filterData = array(
            array('field' => 'model',   'operator' => 'equals', 'value' => 'Tasks_Model_TaskFilter'),
            array('field' => 'id',      'operator' => 'equals', 'value' => $savedFilterData['id'])
        );
        
        $searchResult = $this->_uit->searchPersistentFilter($filterData, NULL);

        $this->assertEquals(1, $searchResult['totalcount']);
        $this->assertTrue(isset($searchResult['results'][0]), 'filter not found in results: ' . print_r($searchResult['results'], true));
        $this->_assertSavedFilterData($exampleFilterData, $searchResult['results'][0]);
    }

    /**
     * testSearchIncludesSharedFavorites
     */
    public function testSearchIncludesSharedFavorites()
    {
        $this->markTestSkipped('@see 0010192: fix persistent filter tests');
        
        $sharedFavorite = self::getPersitentFilter();
        $sharedFavorite->name = 'PHPUnit shared filter';
        $sharedFavorite->account_id = NULL;
        
        $persistentSharedFavorite = Tinebase_PersistentFilter::getInstance()->create($sharedFavorite);
        
        $exampleFilterData = self::getPersistentFilterData();
        $savedFilterData = $this->testSaveFilter($exampleFilterData);
        
        $filterData = array(
            array('field' => 'model',   'operator' => 'equals',     'value' => 'Tasks_Model_TaskFilter'),
            array('field' => 'name',    'operator' => 'startswith', 'value' => 'PHPUnit'),
        );
        
        $searchResult = $this->_uit->searchPersistentFilter($filterData, NULL);
        
        $this->assertGreaterThanOrEqual(2, $searchResult['totalcount']);
        
        $ids = array();
        foreach ($searchResult['results'] as $filterData) {
            $ids[] = $filterData['id'];
        }
        $this->assertEquals(
            2,
            count(array_intersect($ids, array($persistentSharedFavorite->getId(), $savedFilterData['id']))),
            'did not find both favorites in search results: ' . print_r($searchResult['results'], true)
        );
    }
    
    /**
     * testInitialRegistry
     *
     * @group needsbuild
     */
    public function testInitialRegistry()
    {
        $exampleFilterData = self::getPersistentFilterData();
        $savedFilterData = $this->testSaveFilter($exampleFilterData);
        
        $tfj = new Tinebase_Frontend_Json();
        $allRegData = $tfj->getRegistryData();
        
        $this->assertTrue((isset($allRegData['persistentFilters']) || array_key_exists('persistentFilters', $allRegData)), 'persistentFilters is missing in $allRegData');
        
        $ids = array();
        foreach($allRegData['persistentFilters']['results'] as $filterData) {
            $ids[] = $filterData['id'];
        }
        
        $this->assertEquals(1, count(array_intersect($ids, array($savedFilterData['id']))));
    }
    
    /**
     * testCreateFilterWithDeletedName
     */
    public function testCreateFilterWithDeletedName()
    {
        $savedFilter = $this->testSaveFilter();
        $this->_uit->deletePersistentFilters($savedFilter['id']);
        
        // this failed with old db constraints, cause name was used with is_deleted
        $this->testSaveFilter();
    }

    /**
     * testCheckSameFilterNameInDifferentApplications
     */
    public function testCheckSameFilterNameInDifferentApplications()
    {
        $savedFilter1 = $this->testSaveFilter();
        
        $filterData2 = self::getPersistentFilterData();
        $filterData2['application_id'] = Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId();
        $filterData2['model'] = 'Addressbook_Model_ContactFilter';
        $filterData2['filters'] = array(
            array('field' => 'query',        'operator' => 'contains',  'value' => 'test')
        );
        $savedFilter2 = $this->_uit->savePersistentFilter($filterData2);

        foreach (array('model', 'filters', 'application_id') as $fieldToTest) {
            $this->assertNotEquals($savedFilter1[$fieldToTest], $savedFilter2[$fieldToTest]);
        }
        $this->assertEquals($savedFilter1['name'], $savedFilter2['name']);
        
        $filter1 = Tinebase_PersistentFilter::getFilterById($savedFilter1['id']);
        $filter2 = Tinebase_PersistentFilter::getFilterById($savedFilter2['id']);
        
        $this->assertEquals($savedFilter1['model'], get_class($filter1));
        $this->assertEquals($savedFilter2['model'], get_class($filter2));
    }
    
    /**
     * test overwriting existing filter
     */
    public function testOverwriteExistingFilter()
    {
        $filter1 = $this->testSaveFilter();
        $filter2 = $this->testSaveFilter();
        
        $this->assertEquals($filter1['id'], $filter2['id']);
    }
    
    /**
     * test delete (and if prefs are removed
     */
    public function testDeleteFilter()
    {
        $filter = $this->testSaveFilter();
        Tinebase_Core::getPreference('Tasks')->{Tinebase_Preference_Abstract::DEFAULTPERSISTENTFILTER} = $filter['id'];
        
        $this->_uit->deletePersistentFilters(array($filter['id']));
        $this->assertNotEquals(Tinebase_Core::getPreference('Tasks')->{Tinebase_Preference_Abstract::DEFAULTPERSISTENTFILTER}, $filter['id']);
        
        $this->setExpectedException('Tinebase_Exception_NotFound');
        Tinebase_PersistentFilter::getInstance()->get($filter['id']);
    }
    
    /**
     * test save shipped filter as not shipped
     * 
     * #6990: user with right "manage_shared_*_favorites" should be able to delete/edit default shared favorites
     * https://forge.tine20.org/mantisbt/view.php?id=6990
     */
    public function testSaveShippedFilter()
    {
        $accountId = Tinebase_Core::getUser()->getId();
        
        $filterData = array(
            array('field' => 'model',      'operator' => 'equals', 'value' => 'Tasks_Model_TaskFilter'),
            array('field' => 'account_id', 'operator' => 'notin', 'value' => array($accountId))
        );
        // search for shipped filters
        $searchResult = $this->_uit->searchPersistentFilter($filterData, NULL);
        if(count($searchResult['results']) == 0) {
            $this->markTestSkipped('There haven\'t been found any persistenfilters.');
        }
        // take first found
        $filter = $searchResult['results'][0];
        $filter['account_id'] = $accountId;
        $filter['name'] = 'UNITTEST';
        
        $savedFilter = $this->_uit->savePersistentFilter($filter);
        $this->assertEquals($accountId, $savedFilter['created_by']);
        $this->assertEquals($accountId, $savedFilter['account_id']);
        $this->assertEquals('UNITTEST', $savedFilter['name']);
    }
    
    /**
     * assert saved filer matches expections for $this->_testFilterData
     *
     * @param array $expectedFilterData
     * @param array $savedFilterData
     * @return void
     */
    protected function _assertSavedFilterData($expectedFilterData, $savedFilterData)
    {
        $this->assertTrue(is_array($savedFilterData), 'saved filter should be an array');
        $this->assertEquals($expectedFilterData['name'],  $savedFilterData['name'], 'name does not match');
        
        $this->assertTrue((isset($savedFilterData['filters']) || array_key_exists('filters', $savedFilterData)), 'saved filter data is not included');
        $this->assertTrue((isset($savedFilterData['name']) || array_key_exists('name', $savedFilterData)), 'saved filter name is not included');
        
        foreach ($expectedFilterData['filters'] as $requestFilter) {
            $responseFilter = $this->_getFilter($requestFilter['field'], $savedFilterData);
            $this->assertTrue(is_array($responseFilter), 'filter is missing in response');
            $this->assertEquals($requestFilter['operator'], $responseFilter['operator'], 'operator missmatch');
            
            switch ($requestFilter['field']) {
                case 'container_id':
                    $this->assertTrue(is_array($responseFilter['value']), 'container is not resolved');
                    $this->assertEquals($requestFilter['value'], $responseFilter['value']['id'], 'wrong containerId');
                    break;
                case 'organizer':
                    $this->assertTrue(is_array($responseFilter['value']), 'user is not resolved');
                    if ($requestFilter['value'] !== 'currentAccount') {
                        $this->assertEquals($requestFilter['value'], $responseFilter['value']['accountId'], 'wrong accountId');
                    } else {
                        $this->assertEquals(Tinebase_Core::getUser()->getId(), $responseFilter['value']['accountId'], 'wrong accountId');
                    }
                    break;
                case 'due':
                    $this->assertEquals($requestFilter['value'], $responseFilter['value'], 'wrong due date');
                    break;
                default:
                    // do nothting
                    break;
            }
        }
    }
    
    /**
     * returns data for an example persistent filter
     *
     * @param Tinebase_Model_FullUser $user
     * @return array
     */
    public static function getPersistentFilterData($user = null)
    {
        if (null === $user) {
            $user = Tinebase_Core::getUser();
        }
        return array(
            'name'              => 'PHPUnit testFilter',
            'description'       => 'a test filter created by PHPUnit',
            'account_id'        => $user->getId(),
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Tasks')->getId(),
            'model'             => 'Tasks_Model_TaskFilter',
            'filters'           => array(
                array('field' => 'query',        'operator' => 'contains',  'value' => 'test'),
                array('field' => 'container_id', 'operator' => 'equals',    'value' => Tasks_Controller::getInstance()->getDefaultContainer()->getId()),
                array('field' => 'organizer',    'operator' => 'equals',    'value' => Tinebase_Model_User::CURRENTACCOUNT),
                array('field' => 'due',          'operator' => 'after',     'value' => '2010-03-20 18:00:00'),
            )
        );
    }
    
    /**
     * returns an example persistent filter
     * 
     * @return Tinebase_Model_PersistentFilter
     */
    public static function getPersitentFilter()
    {
        return new Tinebase_Model_PersistentFilter(self::getPersistentFilterData());
    }
    
    /**
     * returns first filter of given field in filtersetData
     * 
     * @param string $_field
     * @param array $_filterData
     * @return array|null
     */
    protected function &_getFilter($_field, $_filterData)
    {
        foreach ($_filterData['filters'] as &$filter) {
            if ($filter['field'] == $_field) {
                return $filter;
            }
        }
        return null;
    }
    
    /**
     * testOverwriteFilterAsUnprivilegedUser
     * 
     * @see 0008272: can not overwrite existing favorite without manage-right
     */
    public function testOverwriteFilterAsUnprivilegedUser()
    {
        $personas = Zend_Registry::get('personas');
        Tinebase_Core::set(Tinebase_Core::USER, $personas['jsmith']);
        
        $this->testOverwriteExistingFilter();
    }

    /**
     * testFilterResolving
     * 
     * -> relations should not be resolved here!
     */
    public function testFilterResolving()
    {
        $contact1 = Addressbook_Controller_Contact::getInstance()->create(new Addressbook_Model_Contact(array('n_family' => 'test heini')));
        $contactWithRelations = array(
            'n_family' => 'test typ',
            'relations' => array(array(
                'own_model'              => 'Addressbook_Model_Contact',
                'own_backend'            => 'Sql',
                'own_id'                 => 0,
                'related_degree'         => Tinebase_Model_Relation::DEGREE_SIBLING,
                'type'                   => '',
                'related_backend'        => 'Sql',
                'related_id'             => $contact1->getId(),
                'related_model'          => 'Addressbook_Model_Contact',
                'remark'                 => NULL,
            ),
            )
        );
        $contact2 = Addressbook_Controller_Contact::getInstance()->create(new Addressbook_Model_Contact($contactWithRelations));
        $exampleFilterData = self::getPersistentFilterData();
        $exampleFilterData['filters'] = array(
            array('field' => 'foreignRecord', 'operator' => 'AND', 'value' => array(
                'appName' => 'Addressbook',
                'modelName' => 'Contact',
                'linkType' => 'relation',
                'filters' => array
                (
                    array
                    (
                        'field' => ':id',
                        'operator' => 'equals',
                        'value' => $contact2->getId()
                    )
                )
            ))
        );
        $savedFilterData = $this->_uit->savePersistentFilter($exampleFilterData);
        
        $filterData = array(
            array('field' => 'model',   'operator' => 'equals', 'value' => 'Tasks_Model_TaskFilter'),
            array('field' => 'id',      'operator' => 'equals', 'value' => $savedFilterData['id'])
        );
        
        $searchResult = $this->_uit->searchPersistentFilter($filterData, NULL);
        
        $this->assertEquals(1, $searchResult['totalcount']);
        
        $this->assertTrue(isset($searchResult['results'][0]['filters'][0]['value']['filters'][0]['value']));
        $filterContact = $searchResult['results'][0]['filters'][0]['value']['filters'][0]['value'];
        $this->assertTrue(! isset($filterContact['relations']), 'relations should not be resolved:' . print_r($filterContact, true));
    }
}
