<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Filemanager
 * @license     http://www.gnu.org/licenses/agpl.html
 * @copyright   Copyright (c) 2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * 
 */

/**
 * Test class for Filemanager_ControllerTests
 * 
 * @package     Filemanager
 */
class Filemanager_ControllerTests extends TestCase
{

    protected function tearDown()
    {
        parent::tearDown();

        Tinebase_Config::getInstance()->set(Tinebase_Config::ACCOUNT_DELETION_EVENTCONFIGURATION, new Tinebase_Config_Struct(array(
        )));
    }

    /**
     * @throws Admin_Exception
     * @throws Exception
     */
    public function testCreatePersonalContainer()
    {
        // create user
        $pw = Tinebase_Record_Abstract::generateUID();
        $user = Admin_Controller_User::getInstance()->create($this->getTestUser(), $pw, $pw);
        $this->_usernamesToDelete[] = $user->accountLoginName;

        // check if personal folder exists
        $personalFolderPath = $this->_getPersonalPath($user);
        $translation = Tinebase_Translation::getTranslation('Tinebase');
        $personalFolderName = sprintf($translation->_("%s's personal files"), $user->accountFullName);

        $node = Tinebase_FileSystem::getInstance()->stat($personalFolderPath . '/' . $personalFolderName);
        $this->assertEquals($personalFolderName, $node->name);

        return $user;
    }

    /**
     * @throws Admin_Exception
     * @throws Exception
     */
    public function testDeletePersonalContainer()
    {
        Tinebase_Config::getInstance()->set(Tinebase_Config::ACCOUNT_DELETION_EVENTCONFIGURATION, new Tinebase_Config_Struct(array(
            '_deletePersonalContainers' => true,
        )));

        $user = $this->testCreatePersonalContainer();
        Admin_Controller_User::getInstance()->delete(array($user->getId()));

        // check if personal folder exists
        $personalFolderPath = $this->_getPersonalPath($user);
        self::setExpectedException('Tinebase_Exception_NotFound', 'child:');
        Tinebase_FileSystem::getInstance()->stat($personalFolderPath);
    }

    public function testNotificationUpdateForReadOnly()
    {
        Zend_Registry::set('locale', new Zend_Locale('en'));
        
        $oldUser = Tinebase_Core::getUser();
        /** @var Tinebase_Model_FullUser $sclever */
        $sclever = $this->_personas['sclever'];
        try {
            $personalFolderPath = $this->_getPersonalPath($oldUser);
            $translation = Tinebase_Translation::getTranslation('Tinebase');
            $fileSystem = Tinebase_FileSystem::getInstance();
            $fileManager = Filemanager_Controller_Node::getInstance();
            $personalFolderName = sprintf($translation->_("%s's personal files"), $oldUser->accountFullName);
            $node = $fileSystem->stat($personalFolderPath . '/' . $personalFolderName);

            // try a failing update
            Tinebase_Core::set(Tinebase_Core::USER, $sclever);
            $scleverNotificationProps = array(
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACCOUNT_ID => $sclever->getId(),
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACCOUNT_TYPE => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER,
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACTIVE => true,
            );
            $node->xprops(Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION)[] = $scleverNotificationProps;
            $failed = false;
            try {
                $fileManager->update($node);
            } catch (Tinebase_Exception_AccessDenied $tead) {
                $failed = true;
            }
            static::assertTrue($failed);

            // now set grants so update can work
            Tinebase_Core::set(Tinebase_Core::USER, $oldUser);
            $node = $fileManager->get($node->getId());
            $node->grants = $fileSystem->getGrantsOfContainer($node);
            $node->grants->addRecord(new Tinebase_Model_Grants(array(
                'account_type'      => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER,
                'account_id'        => $sclever->getId(),
                Tinebase_Model_Grants::GRANT_READ => true,
            )));
            $node = $fileManager->update($node);

            // do update again, it should work now
            // test that updates to other things than own notification are silently dropped
            Tinebase_Core::set(Tinebase_Core::USER, $sclever);
            $notificationProps = array(array(
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACCOUNT_ID => $sclever->getId(),
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACCOUNT_TYPE => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER,
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACTIVE => true,
            ),array(
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACCOUNT_ID => '1233',
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACCOUNT_TYPE => Tinebase_Acl_Rights::ACCOUNT_TYPE_USER,
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACTIVE => true,
            ),array(
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACCOUNT_ID => $sclever->getId(),
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACCOUNT_TYPE => Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP,
                Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION_ACTIVE => true,
            ));
            $node->{Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION} = $notificationProps;
            $oldDescription = $node->description;
            static::assertNotEquals('test', $oldDescription, 'test data bad, the description must not be "test"');
            $node->description = 'test';

            $node = $fileManager->update($node);
            static::assertEquals($oldDescription, $node->description, 'description should not have been updated!');
            static::assertEquals(1, count($node->xprops(Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION)));
            static::assertTrue(
                isset($node->xprops(Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION)[0]) &&
                $scleverNotificationProps == $node->xprops(Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION)[0]
            );

            $node->{Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION} = array();
            $node = $fileManager->update($node);
            static::assertEquals(0, count($node->xprops(Tinebase_Model_Tree_Node::XPROPS_NOTIFICATION)));
        } finally {
            Tinebase_Core::set(Tinebase_Core::USER, $oldUser);
        }
    }

    public function testRenameFolderCaseSensitive()
    {
        // check if personal folder exists
        $personalFolderPath = $this->_getPersonalPath(Tinebase_Core::getUser());
        $translation = Tinebase_Translation::getTranslation('Tinebase');
        $personalFolderPath .= sprintf($translation->_("/%s's personal files"), Tinebase_Core::getUser()->accountFullName);
        $fileManager = Filemanager_Controller_Node::getInstance();

        $fileManager->createNodes($personalFolderPath . '/test', Tinebase_Model_Tree_FileObject::TYPE_FOLDER);
        $fileManager->moveNodes(array($personalFolderPath . '/test'), array($personalFolderPath . '/Test'));
    }
}
