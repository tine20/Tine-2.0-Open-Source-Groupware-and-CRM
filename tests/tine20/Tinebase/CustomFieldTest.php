<?php
/**
 * Tine 2.0 - http://www.tine20.org
 *
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2009-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for Tinebase_CustomField
 */
class Tinebase_CustomFieldTest extends TestCase
{
    /**
     * unit under test (UIT)
     * @var Tinebase_CustomField
     */
    protected $_instance;

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        parent::setUp();
        $this->_instance = Tinebase_CustomField::getInstance();
        
        Sales_Controller_Contract::getInstance()->setNumberPrefix();
        Sales_Controller_Contract::getInstance()->setNumberZerofill();
    }

    /**
     * test add customfield to the same record
     * #7330: https://forge.tine20.org/mantisbt/view.php?id=7330
     */
    public function testAddSelfCustomField()
    {
        $cf = self::getCustomField(array(
            'application_id' => Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId(),
            'model' => 'Addressbook_Model_Contact',
            'definition' => array('type' => 'record', "recordConfig" => array("value" => array("records" => "Tine.Addressbook.Model.Contact")))
            ));

        $cf = $this->_instance->addCustomField($cf);
        
        $record = Addressbook_Controller_Contact::getInstance()->create(new Addressbook_Model_Contact(array('n_family' => 'Cleverer', 'n_given' => 'Ben')));
        $cfName = $cf->name;
        $record->customfields = array($cfName => $record->toArray());
        
        $this->setExpectedException('Tinebase_Exception_Record_Validation');
        Addressbook_Controller_Contact::getInstance()->update($record);
    }
    
    /**
     * test add customfield to the same record by multiple update
     *
     * @see #7330: https://forge.tine20.org/mantisbt/view.php?id=7330
     * @see 0007350: multipleUpdate - record not found
     */
    public function testAddSelfCustomFieldByMultipleUpdate()
    {
        // test needs transaction because Controller does rollback when exception is thrown
        Tinebase_TransactionManager::getInstance()->commitTransaction($this->_transactionId);
        $this->_transactionId = NULL;
        
        $cf = self::getCustomField(array(
            'application_id' => Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId(),
            'model' => 'Addressbook_Model_Contact',
            'definition' => array('type' => 'record', "recordConfig" => array("value" => array("records" => "Tine.Addressbook.Model.Contact")))
        ));

        $cf = $this->_instance->addCustomField($cf);
        $c = Addressbook_Controller_Contact::getInstance();

        $record1 = $c->create(new Addressbook_Model_Contact(array('n_family' => 'Friendly', 'n_given' => 'Rupert')), false);
        $record2 = $c->create(new Addressbook_Model_Contact(array('n_family' => 'Friendly', 'n_given' => 'Matt')), false);
        $contactIds = array($record1->getId(), $record2->getId());
        
        $filter = new Addressbook_Model_ContactFilter(array(
            array('field' => 'n_family', 'operator' => 'equals', 'value' => 'Friendly')
            ), 'AND');

        $result = $c->updateMultiple($filter, array('#' . $cf->name => $contactIds[0]));
        
        $this->assertEquals(1, $result['totalcount']);
        $this->assertEquals(1, $result['failcount']);
        
        // cleanup required because we do not have the tearDown() rollback here
        $this->_instance->deleteCustomField($cf);
        Addressbook_Controller_Contact::getInstance()->delete($contactIds);
    }
    
    /**
     * test custom fields
     *
     * - add custom field
     * - get custom fields for app
     * - delete custom field
     */
    public function testCustomFields()
    {
        // create
        $customField = self::getCustomField();
        $createdCustomField = $this->_instance->addCustomField($customField);
        $this->assertEquals($customField->name, $createdCustomField->name);
        $this->assertNotNull($createdCustomField->getId());
        
        // fetch
        $application = Tinebase_Application::getInstance()->getApplicationByName('Tinebase');
        $appCustomFields = $this->_instance->getCustomFieldsForApplication(
            $application->getId()
        );
        $this->assertGreaterThan(0, count($appCustomFields));
        $this->assertEquals($application->getId(), $appCustomFields[0]->application_id);

        // check with model name
        $appCustomFieldsWithModelName = $this->_instance->getCustomFieldsForApplication(
            $application->getId(),
            $customField->model
        );
        $this->assertGreaterThan(0, count($appCustomFieldsWithModelName));
        $this->assertEquals($customField->model, $appCustomFieldsWithModelName[0]->model, 'didn\'t get correct model name');
        
        // check if grants are returned
        $this->_instance->resolveConfigGrants($appCustomFields);
        $accountGrants = $appCustomFields->getFirstRecord()->account_grants;
        sort($accountGrants);
        $this->assertEquals(Tinebase_Model_CustomField_Grant::getAllGrants(), $accountGrants);
        
        // delete
        $this->_instance->deleteCustomField($createdCustomField);
        $this->setExpectedException('Tinebase_Exception_NotFound');
        $this->_instance->getCustomField($createdCustomField->getId());
    }
    
    /**
     * test custom field acl
     *
     * - add custom field
     * - remove grants
     * - cf should no longer be returned
     */
    public function testCustomFieldAcl()
    {
        $createdCustomField = $this->_instance->addCustomField(self::getCustomField());
        $this->_instance->setGrants($createdCustomField);
        
        $application = Tinebase_Application::getInstance()->getApplicationByName('Tinebase');
        $appCustomFields = $this->_instance->getCustomFieldsForApplication(
            $application->getId()
        );
        
        $this->assertEquals(0, count($appCustomFields));
    }
    
    /**
     * testAddressbookCustomFieldAcl
     *
     * @see 0007630: Customfield read access to all users
     */
    public function testAddressbookCustomFieldAcl()
    {
        $createdCustomField = $this->_instance->addCustomField(self::getCustomField(array(
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId(),
            'model'             => 'Addressbook_Model_Contact',
        )));
        $anotherCustomField = $this->_instance->addCustomField(self::getCustomField(array(
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId(),
            'model'             => 'Addressbook_Model_Contact',
        )));
        $contact = Addressbook_Controller_Contact::getInstance()->create(new Addressbook_Model_Contact(array(
            'n_family'     => 'testcontact',
            'container_id' => Tinebase_Container::getInstance()->getSharedContainer(
                Tinebase_Core::getUser(), 'Addressbook', Tinebase_Model_Grants::GRANT_READ)->getFirstRecord()->getId()
        )));
        $cfValue = array(
            $createdCustomField->name => 'test value',
            $anotherCustomField->name => 'test value 2'
        );
        $contact->customfields = $cfValue;
        $contact = Addressbook_Controller_Contact::getInstance()->update($contact);
        $this->assertEquals($cfValue, $contact->customfields, 'cf not saved: ' . print_r($contact->toArray(), TRUE));
        
        // create group and only give acl to this group
        $group = Tinebase_Group::getInstance()->getDefaultAdminGroup();
        $this->_instance->setGrants($createdCustomField, array(
            Tinebase_Model_CustomField_Grant::GRANT_READ,
            Tinebase_Model_CustomField_Grant::GRANT_WRITE,
        ), Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP, $group->getId());
        $contact = Addressbook_Controller_Contact::getInstance()->get($contact->getId());
        $this->assertEquals(2, count($contact->customfields));
        
        // change user and check cfs
        $sclever = Tinebase_User::getInstance()->getFullUserByLoginName('sclever');
        Tinebase_Core::set(Tinebase_Core::USER, $sclever);
        $contact = Addressbook_Controller_Contact::getInstance()->get($contact->getId());
        $this->assertEquals(array($anotherCustomField->name => 'test value 2'), $contact->customfields, 'cf should be hidden: ' . print_r($contact->customfields, TRUE));
    }

    /**
     * testMultiRecordCustomField
     */
    public function testMultiRecordCustomField()
    {
        $createdCustomField = $this->_instance->addCustomField(self::getCustomField(array(
            'name'              => 'test',
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId(),
            'model'             => 'Addressbook_Model_Contact',
            'definition' => array('type' => 'recordList', "recordListConfig" => array("value" => array("records" => "Tine.Addressbook.Model.Contact")))
        )));

        //Customfield record 1
        $contact1 = Addressbook_Controller_Contact::getInstance()->create(new Addressbook_Model_Contact(array(
            'org_name'     => 'contact 1'
        )));
        //Customfield record 2
        $contact2 = Addressbook_Controller_Contact::getInstance()->create(new Addressbook_Model_Contact(array(
            'org_name'     => 'contact 2'
        )));

        $contact = Addressbook_Controller_Contact::getInstance()->create(new Addressbook_Model_Contact(array(
            'n_family'     => 'contact'
        )));

        $cfValue = array($createdCustomField->name => array($contact1, $contact2));
        $contact->customfields = $cfValue;
        $contact = Addressbook_Controller_Contact::getInstance()->update($contact);

        self::assertTrue(is_array($contact->customfields['test']),
            'cf not saved: ' . print_r($contact->toArray(), TRUE));
        self::assertEquals(2, count($contact->customfields['test']));
        self::assertTrue(in_array($contact->customfields['test'][0]['org_name'], array('contact 1', 'contact 2')));
    }
    /**
     * @see 0012222: customfields with space in name are not shown
     */
    public function testAddCustomFieldWithSpace()
    {
        $createdCustomField = $this->_instance->addCustomField(self::getCustomField(array(
            'name'              => 'my customfield',
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId(),
            'model'             => 'Addressbook_Model_Contact',
        )));

        self::assertEquals('mycustomfield', $createdCustomField->name);
    }

    /**
     * get custom field record
     *
     * @param array $config
     * @return Tinebase_Model_CustomField_Config
     */
    public static function getCustomField($config = array())
    {
        return new Tinebase_Model_CustomField_Config(array_replace_recursive(array(
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Tinebase')->getId(),
            'name'              => Tinebase_Record_Abstract::generateUID(),
            'model'             => Tinebase_Record_Abstract::generateUID(),
            'definition'        => array(
                'label' => Tinebase_Record_Abstract::generateUID(),
                'type'  => 'string',
                'uiconfig' => array(
                    'xtype'  => Tinebase_Record_Abstract::generateUID(),
                    'length' => 10,
                    'group'  => 'unittest',
                    'order'  => 100,
                )
            )
        ), $config));
    }
    
    /**
     * test searching records by date as a customfield type
     * https://forge.tine20.org/mantisbt/view.php?id=6730
     */
    public function testSearchByDate()
    {
        $date = new Tinebase_DateTime();
        $cf = self::getCustomField(array('application_id' => Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId(), 'model' => 'Addressbook_Model_Contact', 'definition' => array('type' => 'date')));
        $this->_instance->addCustomField($cf);
        
        $contact = new Addressbook_Model_Contact(array('n_given' => 'Rita', 'n_family' => 'Blütenrein'));
        $contact->customfields = array($cf->name => $date);
        $contact = Addressbook_Controller_Contact::getInstance()->create($contact, false);
        
        $json = new Addressbook_Frontend_Json();
        $filter = array("condition" => "OR",
            "filters" => array(array("condition" => "AND",
                "filters" => array(
                    array("field" => "customfield", "operator" => "within", "value" => array("cfId" => $cf->getId(), "value" => "weekThis")),
                )
            ))
        );
        $result = $json->searchContacts(array($filter), array());
        
        $this->assertEquals(1, $result['totalcount'], 'searched contact not found. filter: ' . print_r($filter, true));
        $this->assertEquals('Rita', $result['results'][0]['n_given']);
        
        $json->deleteContacts(array($contact->getId()));
        
        $this->_instance->deleteCustomField($cf);
    }
    
    /**
     * test searching records by bool as a customfield type
     * https://forge.tine20.org/mantisbt/view.php?id=6730
     */
    public function testSearchByBool()
    {
        $cf = self::getCustomField(array(
            'application_id' => Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId(),
            'model' => 'Addressbook_Model_Contact',
            'definition' => array('type' => 'bool')
        ));
        $this->_instance->addCustomField($cf);
        
        // contact1 with customfield bool = true
        $contact1 = new Addressbook_Model_Contact(array('n_given' => 'Rita', 'n_family' => 'Blütenrein'));
        $contact1->customfields = array($cf->name => true);
        $contact1 = Addressbook_Controller_Contact::getInstance()->create($contact1, false);
        
        // contact2 with customfield bool is not set -> should act like set to false
        $contact2 = new Addressbook_Model_Contact(array('n_given' => 'Rainer', 'n_family' => 'Blütenrein'));
        $contact2 = Addressbook_Controller_Contact::getInstance()->create($contact2, false);
        
        // test bool = true
        $json = new Addressbook_Frontend_Json();
        $result = $json->searchContacts(array(
            array("condition" => "OR",
                "filters" => array(array("condition" => "AND",
                    "filters" => array(
                        array("field" => "customfield", "operator" => "equals", "value" => array("cfId" => $cf->getId(), "value" => true)),
                        array('field' => 'n_family', 'operator' => 'equals', 'value' => 'Blütenrein')
                    )
                ))
            )
        ), array());
        
        // test bool = false
        $this->assertEquals(1, $result['totalcount'], 'One Record should have been found where cf-bool = true (Rita Blütenrein)');
        $this->assertEquals('Rita', $result['results'][0]['n_given'], 'The Record should be Rita Blütenrein');
        
        $result = $json->searchContacts(array(
            array("condition" => "OR",
                "filters" => array(array("condition" => "AND",
                    "filters" => array(
                        array("field" => "customfield", "operator" => "equals", "value" => array("cfId" => $cf->getId(), "value" => false)),
                        array('field' => 'n_family', 'operator' => 'equals', 'value' => 'Blütenrein')
                    )
                ))
            )
        ), array());
        
        $this->assertEquals(1, $result['totalcount'], 'One Record should have been found where cf-bool is not set (Rainer Blütenrein)');
        $this->assertEquals('Rainer', $result['results'][0]['n_given'], 'The Record should be Rainer Blütenrein');
    }
}
