<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Crm
 * @subpackage  Export
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2009-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * 
 */

/**
 * Test helper
 */
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for Crm_Export_Xls
 */
class Crm_Export_XlsTest extends Crm_Export_AbstractTest
{
    /**
     * csv export class
     *
     * @var Crm_Export_Xls
     */
    protected $_instance;
    
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite('Tine 2.0 Crm_Export_XlsTest');
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
        $this->_instance = Tinebase_Export::factory(new Crm_Model_LeadFilter($this->_getLeadFilter()), 'xls');
        parent::setUp();
    }

    /**
     * test xls export
     * 
     * @return void
     * 
     * @todo save and test xls file (with xls reader)
     * @todo check metadata
     * @todo add note/partner checks again?
     */
    public function testExportXls()
    {
        $translate = Tinebase_Translation::getTranslation('Crm');
        $excelObj = $this->_instance->generate();
        
        // output as csv
        $xlswriter = new PHPExcel_Writer_CSV($excelObj);
        $xlswriter->setSheetIndex(1);
        //$xlswriter->save('php://output');
        
        $csvFilename = tempnam(sys_get_temp_dir(), 'csvtest');
        $xlswriter->save($csvFilename);
        
        $this->assertTrue(file_exists($csvFilename));
        $export = file_get_contents($csvFilename);
        
        $this->assertEquals(1, preg_match("/PHPUnit/",                          $export), 'no name');
        $this->assertEquals(1, preg_match("/Description/",                      $export), 'no description');
        $this->assertEquals(1, preg_match('/' . preg_quote(Tinebase_Core::getUser()->accountDisplayName) . '/',          $export), 'no creator');
        $this->assertEquals(1, preg_match('/' . $translate->_('open') . '/',    $export), 'no leadstate');
        
        unlink($csvFilename);
    }
}
