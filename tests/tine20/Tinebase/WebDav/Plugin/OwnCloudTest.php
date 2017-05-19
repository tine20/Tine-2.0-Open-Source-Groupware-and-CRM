<?php
/**
 * Tine 2.0 - http://www.tine20.org
 *
 * @package     Tinebase
 * @subpackage  Frontend
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2013-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */


/**
 * Test class for Tinebase_WebDav_Plugin_OwnCloud
 */
class Tinebase_WebDav_Plugin_OwnCloudTest extends Tinebase_WebDav_Plugin_AbstractBaseTest
{
    const REQUEST_BODY = '<?xml version="1.0" encoding="utf-8"?>
        <propfind xmlns="DAV:">
            <prop>
                <getlastmodified xmlns="DAV:"/>
                <getcontentlength xmlns="DAV:"/>
                <resourcetype xmlns="DAV:"/>
                <getetag xmlns="DAV:"/>
                <id xmlns="http://owncloud.org/ns"/>
            </prop>
        </propfind>';

    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite = new PHPUnit_Framework_TestSuite('Tine 2.0 Plugin OwnCloud Tests');
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

        $this->plugin = new Tinebase_WebDav_Plugin_OwnCloud();

        // Create request, there is no in tinebase while running unittests, but the owncloud plugin needs an user agent
        $request = \Zend\Http\PhpEnvironment\Request::fromString(
            "POST /index.php HTTP/1.1\r\n" .
            "Host: localhost\r\n" .
            "Content-Type: application/json\r\n" .
            "User-Agent: Mozilla/5.0 (Macintosh) mirall/2.2.4 (build 3709)\r\n"
        );
        Tinebase_Core::set('request', $request);

        $this->server->addPlugin($this->plugin);
    }

    /**
     * tear down tests
     */
    protected function tearDown()
    {
        parent::tearDown();
        Tinebase_Config::getInstance()->set(Tinebase_Config::USE_LOGINNAME_AS_FOLDERNAME, false);
    }

    /**
     * test getPluginName method
     */
    public function testGetPluginName()
    {
        $pluginName = $this->plugin->getPluginName();

        $this->assertEquals('Tinebase_WebDav_Plugin_OwnCloud', $pluginName);
    }

    /**
     * test testGetProperties method
     */
    public function testGetProperties()
    {
        $request = new Sabre\HTTP\Request(array(
            'REQUEST_METHOD' => 'PROPFIND',
            'REQUEST_URI' => '/remote.php/webdav/' . Tinebase_Core::getUser()->accountDisplayName,
            'HTTP_DEPTH' => '0',
        ));
        $request->setBody(static::REQUEST_BODY);

        $this->server->httpRequest = $request;
        $this->server->exec();
        //var_dump($this->response->body);
        $this->assertEquals('HTTP/1.1 207 Multi-Status', $this->response->status);

        $responseDoc = new DOMDocument();
        $responseDoc->loadXML($this->response->body);
        //$responseDoc->formatOutput = true; echo $responseDoc->saveXML();
        $xpath = new DomXPath($responseDoc);
        $xpath->registerNamespace('owncloud', 'http://owncloud.org/ns');

        $nodes = $xpath->query('//d:multistatus/d:response/d:propstat/d:prop/owncloud:id');
        $this->assertEquals(1, $nodes->length, $responseDoc->saveXML());
        $this->assertNotEmpty($nodes->item(0)->nodeValue, $responseDoc->saveXML());
    }

    /**
     * test testGetProperties method with an invalid client
     */
    public function testInvalidOwnCloudVersion()
    {
        static::setExpectedException(InvalidArgumentException::class, sprintf(
            'OwnCloud client min version is "%s"!',
            Tinebase_WebDav_Plugin_OwnCloud::OWNCLOUD_MIN_VERSION
        ));


        // use old owncloud user agent!
        $request = \Zend\Http\PhpEnvironment\Request::fromString(
            "POST /index.php HTTP/1.1\r\n" .
            "Host: localhost\r\n" .
            "Content-Type: application/json\r\n" .
            "User-Agent: Mozilla/5.0 (Macintosh) mirall/1.5.0 (build 3709)\r\n"
        );
        Tinebase_Core::set('request', $request);

        $request = new Sabre\HTTP\Request(array(
            'REQUEST_METHOD' => 'PROPFIND',
            'REQUEST_URI' => '/remote.php/webdav/' . Tinebase_Core::getUser()->accountDisplayName,
            'HTTP_DEPTH' => '0',
        ));
        $request->setBody(static::REQUEST_BODY);

        $this->server->httpRequest = $request;
        $this->server->exec();
    }

    /**
     * test testGetProperties method with alternate loginname config
     */
    public function testGetProperties2()
    {
        Tinebase_Config::getInstance()->set(Tinebase_Config::USE_LOGINNAME_AS_FOLDERNAME, true);

        $request = new Sabre\HTTP\Request(array(
            'REQUEST_METHOD' => 'PROPFIND',
            'REQUEST_URI' => '/remote.php/webdav/' . Tinebase_Core::getUser()->accountLoginName,
            'HTTP_DEPTH' => '0',
        ));
        $request->setBody(static::REQUEST_BODY);

        $this->server->httpRequest = $request;
        $this->server->exec();
        $this->assertEquals('HTTP/1.1 207 Multi-Status', $this->response->status);

        $responseDoc = new DOMDocument();
        $responseDoc->loadXML($this->response->body);
        $xpath = new DomXPath($responseDoc);
        $xpath->registerNamespace('owncloud', 'http://owncloud.org/ns');

        $nodes = $xpath->query('//d:multistatus/d:response/d:propstat/d:prop/owncloud:id');
        $this->assertEquals(1, $nodes->length, $responseDoc->saveXML());
        $this->assertNotEmpty($nodes->item(0)->nodeValue, $responseDoc->saveXML());
    }
}
