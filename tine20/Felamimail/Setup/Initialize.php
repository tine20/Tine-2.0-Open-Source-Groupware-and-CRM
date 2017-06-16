<?php
/**
 * Tine 2.0
 * 
 * @package     Felamimail
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Jonas Fischer <j.fischer@metaways.de>
 * @copyright   Copyright (c) 2008-2010 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * class for Felamimail initialization
 * 
 * @package     Setup
 */
class Felamimail_Setup_Initialize extends Setup_Initialize
{
    /**
    * array with user role rights
    *
    * @var array
    */
    static protected $_userRoleRights = array(
        Tinebase_Acl_Rights::RUN,
        Felamimail_Acl_Rights::MANAGE_ACCOUNTS,
    );
    
    /**
     * init favorites
     */
    protected function _initializeFavorites()
    {
        $pfe = Tinebase_PersistentFilter::getInstance();
        
        $commonValues = array(
            'account_id'        => NULL,
            'application_id'    => Tinebase_Application::getInstance()->getApplicationByName('Felamimail')->getId(),
            'model'             => 'Felamimail_Model_MessageFilter',
        );
        
        $myInboxPFilter = $pfe->createDuringSetup(new Tinebase_Model_PersistentFilter(array_merge($commonValues, array(
            'name'              => Felamimail_Preference::DEFAULTPERSISTENTFILTER_NAME,
            'description'       => 'All inboxes of my email accounts', // _("All inboxes of my email accounts")
            'filters'           => array(
                array('field' => 'path'    , 'operator' => 'in', 'value' => Felamimail_Model_MessageFilter::PATH_ALLINBOXES),
            )
        ))));
        
        $myUnseenPFilter = $pfe->createDuringSetup(new Tinebase_Model_PersistentFilter(array_merge($commonValues, array(
            'name'              => 'All unread mail', // _("All unread mail")
            'description'       => 'All unread mail of my email accounts', // _("All unread mail of my email accounts")
            'filters'           => array(
                array('field' => 'flags'    , 'operator' => 'notin', 'value' => Zend_Mail_Storage::FLAG_SEEN),
            )
        ))));

        $myHighlightedPFilter = $pfe->createDuringSetup(new Tinebase_Model_PersistentFilter(array_merge($commonValues, array(
            'name'              => 'All highlighted mail', // _("All highlighted mail")
            'description'       => 'All highlighted mail of my email accounts', // _("All highlighted mail of my email accounts")
            'filters'           => array(
                array('field' => 'flags'    , 'operator' => 'in', 'value' => Zend_Mail_Storage::FLAG_FLAGGED),
            )
        ))));

        $myDraftsPFilter = $pfe->createDuringSetup(new Tinebase_Model_PersistentFilter(array_merge($commonValues, array(
            'name'              => 'All drafts', // _("All drafts")
            'description'       => 'All mails with the draft flag', // _("All mails with the draft flag")
            'filters'           => array(
                array('field' => 'flags'    , 'operator' => 'in', 'value' => Zend_Mail_Storage::FLAG_DRAFT),
            )
        ))));
    }
    
    /**
     * init application folders
     */
    protected function _initializeFolders()
    {
        self::createVacationTemplatesFolder();
        self::createEmailNotificationTemplatesFolder();
    }
    
    /**
     * create vacation templates folder
     */
    public static function createVacationTemplatesFolder()
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Creating vacation template in vfs ...');
        
        try {
            $basepath = Tinebase_FileSystem::getInstance()->getApplicationBasePath(
                'Felamimail',
                Tinebase_FileSystem::FOLDER_TYPE_SHARED
            );
            $node = Tinebase_FileSystem::getInstance()->createAclNode($basepath . '/Vacation Templates');
            Felamimail_Config::getInstance()->set(Felamimail_Config::VACATION_TEMPLATES_CONTAINER_ID, $node->getId());
        } catch (Tinebase_Exception_Backend $teb) {
            if (Tinebase_Core::isLogLevel(Zend_Log::ERR)) Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__
                . ' Could not create vacation template folder: ' . $teb);
        }
    }

    /**
     * create email notification templates folder
     */
    public static function createEmailNotificationTemplatesFolder()
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Creating email notification template in vfs ...');

        try {
            $basepath = Tinebase_FileSystem::getInstance()->getApplicationBasePath(
                'Felamimail',
                Tinebase_FileSystem::FOLDER_TYPE_SHARED
            );
            $node = Tinebase_FileSystem::getInstance()->createAclNode($basepath . '/Email Notification Templates');
            Felamimail_Config::getInstance()->set(Felamimail_Config::EMAIL_NOTIFICATION_TEMPLATES_CONTAINER_ID, $node->getId());

            if (false === ($fh = Tinebase_FileSystem::getInstance()->fopen($basepath . '/Email Notification Templates/defaultForwarding.sieve', 'w'))) {
                if (Tinebase_Core::isLogLevel(Zend_Log::ERR)) Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__
                    . ' Could not create defaultForwarding.sieve file');
                return;
            }

            fwrite($fh, <<<'sieveFile'
require ["enotify", "variables", "copy"];

if header :contains "X-Tine20-Type" "Notification" {
    redirect :copy "USER_EXTERNAL_EMAIL"; 
} else {
    notify :message "you have a new mail"
              "mailto:USER_EXTERNAL_EMAIL";
}
sieveFile
            );

            if (true !== Tinebase_FileSystem::getInstance()->fclose($fh)) {
                if (Tinebase_Core::isLogLevel(Zend_Log::ERR)) Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__
                    . ' Could not create defaultForwarding.sieve file');
                return;
            }

        } catch (Tinebase_Exception_Backend $teb) {
            if (Tinebase_Core::isLogLevel(Zend_Log::ERR)) Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__
                . ' Could not create email notification template folder: ' . $teb);
        }
    }
}
