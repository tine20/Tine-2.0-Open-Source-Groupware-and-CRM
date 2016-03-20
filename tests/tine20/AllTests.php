<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tinebase
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2007-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Matthias Greiling <m.greiling@metaways.de>
 */

// needed for bootstrap / autoloader
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * @package     Tinebase
 */
class AllTests
{
    public static function main()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }
    
    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite('Tine 2.0 All Tests');
        
        $suite->addTest(Tinebase_AllTests::suite());
        $suite->addTest(Addressbook_AllTests::suite());
        $suite->addTest(Admin_AllTests::suite());
        $suite->addTest(Felamimail_AllTests::suite());
        $suite->addTest(Calendar_AllTests::suite());
        $suite->addTest(Crm_AllTests::suite());
        $suite->addTest(Tasks_AllTests::suite());
        $suite->addTest(Voipmanager_AllTests::suite());
        $suite->addTest(Phone_AllTests::suite());
        $suite->addTest(Sales_AllTests::suite());
        $suite->addTest(Timetracker_AllTests::suite());
        $suite->addTest(Courses_AllTests::suite());
        $suite->addTest(ActiveSync_AllTests::suite());
        $suite->addTest(Filemanager_AllTests::suite());
        $suite->addTest(Projects_AllTests::suite());
        $suite->addTest(HumanResources_AllTests::suite());
        $suite->addTest(Inventory_AllTests::suite());
        $suite->addTest(ExampleApplication_AllTests::suite());
        $suite->addTest(Sipgate_AllTests::suite());
        $suite->addTest(SimpleFAQ_AllTests::suite());
        $suite->addTest(CoreData_AllTests::suite());
        $suite->addTest(Zend_AllTests::suite());
        
        return $suite;
    }
}
