<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2008-2015 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */

/**
 * Test class for Addressbook_Import_Csv
 */
class Addressbook_Import_CsvTest extends ImportTestCase
{
    protected $_deletePersonalContacts = false;

    protected $_importerClassName = 'Addressbook_Import_Csv';
    protected $_modelName = 'Addressbook_Model_Contact';

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        // always resolve customfields
        Addressbook_Controller_Contact::getInstance()->resolveCustomfields(TRUE);
    }

    /**
     * Tears down the fixture
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
        if ($this->_deletePersonalContacts) {
            Addressbook_Controller_Contact::getInstance()->deleteByFilter(new Addressbook_Model_ContactFilter(array(array(
                'field' => 'container_id', 'operator' => 'equals', 'value' => Addressbook_Controller_Contact::getInstance()->getDefaultAddressbook()->getId()
            ))));
        }
        
        Addressbook_Controller_Contact::getInstance()->duplicateCheckFields(Addressbook_Config::getInstance()->get(Addressbook_Config::CONTACT_DUP_FIELDS));
    }
    
    /**
     * test import duplicate data
     *
     * @return array
     */
    public function testImportDuplicates()
    {
        $internalContainer = Tinebase_Container::getInstance()->getContainerByName('Addressbook', 'Internal Contacts', Tinebase_Model_Container::TYPE_SHARED);
        $options = array(
            'container_id'  => $internalContainer->getId(),
        );
        $result = $this->_doImport($options, 'adb_tine_import_csv', new Addressbook_Model_ContactFilter(array(
            array('field' => 'container_id', 'operator' => 'equals', 'value' => $internalContainer->getId()),
        )));

        $this->assertGreaterThan(0, $result['duplicatecount'], 'no duplicates.');
        $this->assertTrue($result['exceptions'] instanceof Tinebase_Record_RecordSet);

        return $result;
    }
    
    /**
     * test import data
     */
    public function testImportSalutation()
    {
        $myContact = Addressbook_Controller_Contact::getInstance()->getContactByUserId(Tinebase_Core::getUser()->getId());
        $salutation = Addressbook_Config::getInstance()->get(Addressbook_Config::CONTACT_SALUTATION)->records->getFirstRecord()->value;
        $myContact->salutation = $salutation;
        Addressbook_Controller_Contact::getInstance()->update($myContact);
        
        $result = $this->testImportDuplicates();
        
        $found = FALSE;
        foreach ($result['exceptions'] as $exception) {
            if ($exception['exception']['clientRecord']['email'] === Tinebase_Core::getUser()->accountEmailAddress) {
                $found = TRUE;
                $this->assertTrue(isset($exception['exception']['clientRecord']['salutation']), 'no salutation found: ' . print_r($exception['exception']['clientRecord'], TRUE));
                $this->assertEquals($salutation, $exception['exception']['clientRecord']['salutation']);
                break;
            }
        }
        
        $this->assertTrue($found,
            'did not find user ' . Tinebase_Core::getUser()->accountFullName . ' in import exceptions: '
            . print_r($result['exceptions']->toArray(), true));
    }

    /**
     * test import umlaut
     * 
     * @see 0006936: detect import file encoding
     */
    public function testImportUmlaut()
    {
        $myContact = Addressbook_Controller_Contact::getInstance()->getContactByUserId(Tinebase_Core::getUser()->getId());
        $myContact->org_name = 'Übel leckerer Äppler';
        Addressbook_Controller_Contact::getInstance()->update($myContact);
        
        $result = $this->testImportDuplicates();
        
        $found = FALSE;
        foreach ($result['exceptions'] as $exception) {
            $record = $exception['exception']['clientRecord'];
            if ($record['email'] === Tinebase_Core::getUser()->accountEmailAddress) {
                $found = TRUE;
                $this->assertEquals($myContact->org_name, $record['org_name']);
            }
        }
        
        $this->assertTrue($found);
    }
    
    /**
     * import google contacts
     */
    public function testImportGoogleContacts()
    {
        $this->_filename = dirname(__FILE__) . '/files/google_contacts.csv';
        $this->_deleteImportFile = FALSE;
        
        $result = $this->_doImport(array('dryrun' => TRUE), 'adb_google_import_csv');
        
        $this->assertEquals(5, $result['totalcount']);
        $this->assertEquals('Niedersachsen Ring 22', $result['results'][4]->adr_one_street);
        $this->assertEquals('abc@here.de', $result['results'][3]->email);
        $this->assertEquals('+49227913452', $result['results'][0]->tel_work);
    }
    
    /**
     * test import of a customfield
     */
    public function testImportCustomField()
    {
        $this->_createCustomField();
        
        // create/get new import/export definition with customfield
        $filename = dirname(__FILE__) . '/files/adb_google_import_csv_test.xml';
        $applicationId = Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId();
        $definition = Tinebase_ImportExportDefinition::getInstance()->getFromFile($filename, $applicationId);
        
        $this->_filename = dirname(__FILE__) . '/files/google_contacts.csv';
        $this->_deleteImportFile = FALSE;
        
        $result = $this->_doImport(array(), $definition);
        $this->_deletePersonalContacts = TRUE;
        $this->assertEquals(5, $result['totalcount']);
        
        $contacts = Addressbook_Controller_Contact::getInstance()->search(new Addressbook_Model_ContactFilter(array(
            array('field' => 'container_id', 'operator' => 'equals', 'value' => Addressbook_Controller_Contact::getInstance()->getDefaultAddressbook()->getId()),
            array('field' => 'n_given', 'operator' => 'equals', 'value' => 'Ando'),
        )));
        $this->assertEquals(1, count($contacts));
        $ando = $contacts->getFirstRecord();
        $this->assertEquals(array('YomiName' => 'yomi'), $ando->customfields);
    }
    
    /**
     * testExportAndImportWithCustomField
     * 
     * @see 0006230: add customfields to csv export
     */
    public function testExportAndImportWithCustomField()
    {
        $customField = $this->_createCustomField();
        $this->assertTrue($customField instanceof Tinebase_Model_CustomField_Config);
        $ownContact = Addressbook_Controller_Contact::getInstance()->getContactByUserId(Tinebase_Core::getUser()->getId());
        $cfValue = array($customField->name => 'testing');
        $ownContact->customfields = $cfValue;
        Addressbook_Controller_Contact::getInstance()->update($ownContact);
        
        $definition = Tinebase_ImportExportDefinition::getInstance()->getByName('adb_tine_import_csv');
        $definition->plugin_options = preg_replace('/<\/mapping>/',
            '<field>
                <source>' . $customField->name . '</source>
                <destination>'. $customField->name . '</destination>
            </field></mapping>', $definition->plugin_options);
        $result = $this->_doImport(array(), $definition, new Addressbook_Model_ContactFilter(array(
            array('field' => 'id', 'operator' => 'equals', 'value' => $ownContact->getId()),
        )));
        $this->assertGreaterThan(0, $result['duplicatecount'], 'no duplicates.');
        $this->assertTrue($result['exceptions'] instanceof Tinebase_Record_RecordSet);

        $exceptionArray = $result['exceptions']->toArray();
        $this->assertTrue(isset($exceptionArray[0]['exception']['clientRecord']['customfields']),
            'could not find customfields in client record: ' . print_r($exceptionArray[0]['exception']['clientRecord'], TRUE));
        $this->assertEquals('testing', $exceptionArray[0]['exception']['clientRecord']['customfields'][$customField->name],
            'could not find cf value in client record: ' . print_r($exceptionArray[0]['exception']['clientRecord'], TRUE));
    }
    
    /**
     * testImportWithUmlautsWin1252
     * 
     * @see 0006534: import of contacts with umlaut as first char fails
     */
    public function testImportWithUmlautsWin1252()
    {
        $definition = $this->_getDefinitionFromFile('adb_import_csv_win1252.xml');
        
        $this->_filename = dirname(__FILE__) . '/files/importtest_win1252.csv';
        $this->_deleteImportFile = FALSE;
        
        $result = $this->_doImport(array(), $definition);
        $this->_deletePersonalContacts = TRUE;
        
        $this->assertEquals(4, $result['totalcount']);
        $this->assertEquals('Üglü, ÖzdemirÖ', $result['results'][2]->n_fileas, 'Umlauts were not imported correctly: ' . print_r($result['results'][2]->toArray(), TRUE));
    }
    
    /**
     * returns import definition from file
     * 
     * @param string $filename
     * @return Tinebase_Model_ImportExportDefinition
     */
    protected function _getDefinitionFromFile($filename, $path = null)
    {
        $filename = ($path ? $path : dirname(__FILE__) . '/files/') . $filename;
        $applicationId = Tinebase_Application::getInstance()->getApplicationByName('Addressbook')->getId();
        $definition = Tinebase_ImportExportDefinition::getInstance()->getFromFile($filename, $applicationId);
        
        return $definition;
    }

    /**
     * testImportDuplicateResolve
     * 
     * @see 0009316: add duplicate resolving to cli import
     *
     * @param bool $withCustomfields
     * @return array
     */
    public function testImportDuplicateResolve($withCustomfields = false)
    {
        $definition = $this->_getDefinitionFromFile('adb_import_csv_duplicate.xml');
        
        $this->_filename = dirname(__FILE__) . ($withCustomfields ? '/files/import_duplicate_1_cf.csv' : '/files/import_duplicate_1.csv');
        $this->_deleteImportFile = FALSE;
        
        $this->_doImport(array(), $definition);
        $this->_deletePersonalContacts = TRUE;

        $this->_filename = dirname(__FILE__) .($withCustomfields ? '/files/import_duplicate_2_cf.csv' : '/files/import_duplicate_2.csv');
        
        $result = $this->_doImport(array(), $definition);
        
        $this->assertEquals(1, $result['updatecount'], 'should have updated 1 contact');
        $this->assertEquals(0, $result['totalcount'], 'should have imported 0 records: ' . print_r($result['results']->toArray(), true));
        $this->assertEquals(0, $result['failcount']);
        $this->assertEquals('joerg@home.com', $result['results'][0]->email_home, 'duplicates resolving did not work: ' . print_r($result['results']->toArray(), true));
        $this->assertEquals('Jörg', $result['results'][0]->n_given, 'wrong encoding: ' . print_r($result['results']->toArray(), true));

        return $result;
    }

    /**
     * testImportDuplicateResolveCustomfields
     */
    public function testImportDuplicateResolveCustomfields()
    {
        $this->_createCustomField('customfield1');
        $this->_createCustomField('customfield2');
        // empty values: should not trigger record updates
        $this->_createCustomField('customfield3');

        $result = $this->testImportDuplicateResolve(/* $withCustomfields */ true);

        // check customfields in result
        $joerg = $result['results'][0]->toArray();
        $this->assertTrue(isset($joerg['customfields']), 'cfs missing: ' .  print_r($joerg, true));
        $this->assertFalse(isset($joerg['customfields']['customfield1']), print_r($joerg, true));
        $this->assertEquals('cf2-2', $joerg['customfields']['customfield2']);
    }

    /**
     * testImportLxOffice
     */
    public function testImportLxOffice()
    {
        $options = array(
            'container_id'  => Addressbook_Controller_Contact::getInstance()->getDefaultAddressbook()->getId(),
        );
        
        // add duplicate field "customernumber"
        Addressbook_Controller_Contact::getInstance()->duplicateCheckFields(array(
            array('email'),
            array('customernumber')
        ));
        
        $this->_createCustomField('customernumber');
        
        $definition = $this->_getDefinitionFromFile('adb_lxoffice_import_csv.xml',
            dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/tine20/Addressbook/Import/definitions/');
        
        $this->_filename = dirname(__FILE__) . '/files/importtest_lxoffice1.csv';
        $this->_deleteImportFile = FALSE;
        
        $result = $this->_doImport($options, $definition);
        $this->_deletePersonalContacts = TRUE;
        
        $this->assertEquals(3, $result['totalcount'], print_r($result['results']->toArray(), true));
        
        $contacts = $result['results'];
        $berger = $contacts->getFirstRecord();
        $this->assertEquals(array('customernumber' => '73029'), $berger->customfields, print_r($berger->toArray(), true));
        
        $this->_filename = dirname(__FILE__) . '/files/importtest_lxoffice2.csv';
        
        $result = $this->_doImport($options, $definition);
        
        $this->assertEquals(5, count($result['results']));
        // NOTE: this assertion is strange because the results vary between 1 and 2
        $this->assertGreaterThanOrEqual(1, $result['updatecount'], 'should have updated 1 or more contacts / results: '
            . print_r($result['results']->toArray(), true));
        // NOTE: this assertion is strange because the results vary between 3 and 4
        $this->assertTrue((3 === $result['totalcount'] || 4 === $result['totalcount']), 'should have added 3 or 4 contacts');
        $this->assertEquals('Straßbough', $result['results'][1]['adr_one_locality'],
                'should have changed the locality of contact #2: ' . print_r($result['results'][1]->toArray(), true));
        $this->assertEquals('Gartencenter Röhr & Vater', $result['results'][3]['n_family']);
    }

    public function testSplitField()
    {
        $definition = $this->_getDefinitionFromFile('adb_import_csv_split.xml');

        $this->_filename = dirname(__FILE__) . '/files/import_split.csv';
        $this->_deleteImportFile = FALSE;

        $result = $this->_doImport(array('dryrun' => true), $definition);

        $this->assertEquals(1, $result['totalcount'], print_r($result, true));
        $importedRecord = $result['results']->getFirstRecord();

        $this->assertEquals('21222', $importedRecord->adr_one_postalcode, print_r($importedRecord->toArray(), true));
        $this->assertEquals('Käln', $importedRecord->adr_one_locality, print_r($importedRecord->toArray(), true));
    }

    /**
     * @see 0011354: keep both records if duplicates are within current import file
     */
    public function testImportDuplicateInImport()
    {
        $definition = $this->_getDefinitionFromFile('adb_import_csv_split.xml');

        $this->_filename = dirname(__FILE__) . '/files/import_split_duplicate.csv';
        $this->_deletePersonalContacts = TRUE;
        $this->_deleteImportFile = FALSE;

        $result = $this->_doImport(array('dryrun' => false), $definition);

        $this->assertEquals(2, $result['totalcount'], print_r($result, true));
        $this->assertEquals(2, count(array_unique($result['results']->getArrayOfIds())));
    }
}
