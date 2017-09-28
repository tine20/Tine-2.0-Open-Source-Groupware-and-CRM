<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2008-2014 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * All Addressbook tests
 * 
 * @package     Addressbook
 */
class Addressbook_AllTests
{
    public static function main ()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }
    
    public static function suite ()
    {
        $suite = new PHPUnit_Framework_TestSuite('All Addressbook tests');
        
        $suite->addTest(Addressbook_Backend_AllTests::suite());
        $suite->addTest(Addressbook_Convert_Contact_VCard_AllTests::suite());
        $suite->addTest(Addressbook_Frontend_AllTests::suite());
        $suite->addTest(Addressbook_Import_AllTests::suite());
        
        $suite->addTestSuite('Addressbook_ControllerTest');
        $suite->addTestSuite('Addressbook_Controller_ListTest');
        $suite->addTestSuite('Addressbook_PdfTest');
        $suite->addTestSuite('Addressbook_JsonTest');
        $suite->addTestSuite('Addressbook_CliTest');
        $suite->addTestSuite('Addressbook_Model_ContactIdFilterTest');
        $suite->addTestSuite('Addressbook_Export_DocTest');
        $suite->addTestSuite('Addressbook_Export_XlsTest');
        $suite->addTestSuite(Addressbook_ListControllerTest::class);

        if (Tinebase_User::getConfiguredBackend() === Tinebase_User::LDAP) {
            $suite->addTestSuite('Addressbook_LdapSyncTest');
        }

        // TODO: enable this again, when its fast
//         $suite->addTestSuite('Addressbook_Setup_DemoDataTests');
        return $suite;
    }
}
