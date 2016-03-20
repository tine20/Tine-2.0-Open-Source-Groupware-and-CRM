<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Server
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * 
 */

/**
 * Cli Server class with handle() function
 * 
 * @package     Tinebase
 * @subpackage  Server
 */
class Setup_Server_Cli implements Tinebase_Server_Interface
{
    /**
     * (non-PHPdoc)
     * @see Tinebase_Server_Interface::handle()
     */
    public function handle(\Zend\Http\Request $request = null, $body = null)
    {
        try {
            $opts = new Zend_Console_Getopt(
            array(
                'help|h'                    => 'Display this help Message',
                'verbose|v'                 => 'Output messages',
                'config|c=s'                => 'Path to config.inc.php file',
                'setconfig'                 => 'Update config. To specify the key and value, append \' -- configkey="your_key" configValue="your config value"\'
                         Examples:
                           setup.php --setconfig -- configkey=sample1 configvalue=value11
                           setup.php --setconfig -- configkey=sample2 configvalue=arrayKey1:Value1,arrayKey2:value2
                          ',
                'getconfig'                 => 'Get Config value for a specify the key \' -- configkey="your_key"\'',
                'check_requirements'        => 'Check if all requirements are met to install and run tine20',
                'create_admin'              => 'Create new admin user (or reactivate if already exists)',
                'install-s'                 => 'Install applications [All] or comma separated list;'
                    . ' To specify the login name and login password of the admin user that is created during installation, append \' -- adminLoginName="admin" adminPassword="password"\''
                    . ' To add imap or smtp settings, append (for example) \' -- imap="host:mail.example.org,port:143,dbmail_host:localhost" smtp="ssl:tls"\'',
                'update-s'                  => 'Update applications [All] or comma separated list',
                'uninstall-s'               => 'Uninstall application [All] or comma separated list',
                'list-s'                    => 'List installed applications',
                'sync_accounts_from_ldap'   => 'Import user and groups from ldap',
                    'dbmailldap'            => 'Only usable with sync_accounts_from_ldap. Fetches dbmail email user data from LDAP.',
                    'onlyusers'             => 'Only usable with sync_accounts_from_ldap. Fetches only users and no groups from LDAP.',
                    'syncdeletedusers'      => 'Only usable with sync_accounts_from_ldap. Removes users from Tine 2.0 DB that no longer exist in LDAP',
                    'syncaccountstatus'     => 'Only usable with sync_accounts_from_ldap. Synchronizes current account status from LDAP',
                'sync_passwords_from_ldap'  => 'Synchronize user passwords from ldap',
                'egw14import'               => 'Import user and groups from egw14
                         Examples: 
                          setup.php --egw14import /path/to/config.ini',
                'reset_demodata'            => 'reinstall applications and install Demodata (Needs Admin user)',
                'updateAllImportExportDefinitions' => 'update ImportExport definitions for all applications',
                'backup'                    => 'backup config and data
                         Examples:
                           setup.php --backup -- config=1 db=1 files=1 backupDir=/backup/tine20 noTimestamp=1',
                'restore'                   => 'restore config and data
                         Examples:
                           setup.php --restore -- config=1 db=1 files=1 backupDir=/backup/tine20',
            ));
            $opts->parse();
        } catch (Zend_Console_Getopt_Exception $e) {
            echo "Invalid usage: {$e->getMessage()}\n\n";
            echo $e->getUsageMessage();
            exit;
        }

        if (count($opts->toArray()) === 0 || $opts->h || 
            (empty($opts->install) && 
            empty($opts->update) && 
            empty($opts->uninstall) && 
            empty($opts->list) && 
            empty($opts->sync_accounts_from_ldap) && 
            empty($opts->sync_passwords_from_ldap) && 
            empty($opts->egw14import) && 
            empty($opts->check_requirements) && 
            empty($opts->reset_demodata) &&
            empty($opts->updateAllImportExportDefinitions) &&
            empty($opts->create_admin) && 
            empty($opts->setconfig) && 
            empty($opts->backup) &&
            empty($opts->restore) &&
            empty($opts->getconfig)))
        {
            echo $opts->getUsageMessage();
            exit;
        }

        if ($opts->config) {
            // add path to config.inc.php to include path
            $path = strstr($opts->config, 'config.inc.php') !== false ? dirname($opts->config) : $opts->config;
            set_include_path($path . PATH_SEPARATOR . get_include_path());
        }
        
        Setup_Core::initFramework();

        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .' Is cli request. method: ' . $this->getRequestMethod());
        
        $setupServer = new Setup_Frontend_Cli();
        #$setupServer->authenticate($opts->username, $opts->password);
        return $setupServer->handle($opts);
    }
    
    /**
    * returns request method
    *
    * @return string|NULL
    */
    public function getRequestMethod()
    {
        $opts = Tinebase_Core::get('opts');
        return (isset($opts->mode)) ? $opts->mode : NULL;
    }
}
