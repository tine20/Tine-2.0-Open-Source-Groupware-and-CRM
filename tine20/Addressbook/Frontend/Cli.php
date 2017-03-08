<?php
/**
 * Tine 2.0
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2008-2016 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 */

/**
 * cli server for addressbook
 *
 * This class handles cli requests for the addressbook
 *
 * @package     Addressbook
 */
class Addressbook_Frontend_Cli extends Tinebase_Frontend_Cli_Abstract
{
    /**
     * the internal name of the application
     *
     * @var string
     */
    protected $_applicationName = 'Addressbook';
    
    /**
     * import config filename
     *
     * @var string
     */
    protected $_configFilename = 'importconfig.inc.php';

    /**
     * help array with function names and param descriptions
     */
    protected $_help = array(
        'import' => array(
            'description'   => 'Import new contacts into the addressbook.',
            'params'        => array(
                'filenames'   => 'Filename(s) of import file(s) [required]',
                'definition'  => 'Name of the import definition or filename [required] -> for example admin_user_import_csv(.xml)',
            )
        ),
        'export' => array(
            'description'   => 'Exports contacts as csv data to stdout',
            'params'        => array(
                'addressbookId' => 'only export contcts of the given addressbook',
                'tagId'         => 'only export contacts having the given tag'
            )
        ),
        'syncbackends' => array(
            'description'   => 'Syncs all contacts to the sync backends',
            'params'        => array(),
        )
    );

    public function syncbackends($_opts)
    {
        $sqlBackend = new Addressbook_Backend_Sql();
        $controller = Addressbook_Controller_Contact::getInstance();
        $syncBackends = $controller->getSyncBackends();

        foreach ($sqlBackend->getAll() as $contact) {
            $oldRecordBackendIds = $contact->syncBackendIds;
            if (is_string($oldRecordBackendIds)) {
                $oldRecordBackendIds = explode(',', $contact->syncBackendIds);
            } else {
                $oldRecordBackendIds = array();
            }

            $updateSyncBackendIds = false;
            
            foreach($syncBackends as $backendId => $backendArray)
            {
                if (isset($backendArray['filter'])) {
                    $oldACL = $controller->doContainerACLChecks(false);

                    $filter = new Addressbook_Model_ContactFilter($backendArray['filter']);
                    $filter->addFilter(new Addressbook_Model_ContactIdFilter(
                        array('field' => $contact->getIdProperty(), 'operator' => 'equals', 'value' => $contact->getId())
                    ));

                    // record does not match the filter, attention searchCount returns a STRING! "1"...
                    if ($controller->searchCount($filter) != 1) {

                        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                            . ' record did not match filter of syncBackend "' . $backendId . '"');

                        // record is stored in that backend, so we remove it from there
                        if (in_array($backendId, $oldRecordBackendIds)) {

                            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                                . ' deleting record from syncBackend "' . $backendId . '"');

                            try {
                                $backendArray['instance']->delete($contact);

                                $contact->syncBackendIds = trim(preg_replace('/(^|,)' . $backendId . '($|,)/', ',', $contact->syncBackendIds), ',');

                                $updateSyncBackendIds = true;
                            } catch (Exception $e) {
                                Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' could not delete record from sync backend "' .
                                    $backendId . '": ' . $e->getMessage());
                                Tinebase_Exception::log($e, false);
                            }
                        }

                        $controller->doContainerACLChecks($oldACL);

                        continue;
                    }
                    $controller->doContainerACLChecks($oldACL);
                }

                // if record is in this syncbackend, update it
                if (in_array($backendId, $oldRecordBackendIds)) {

                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' update record in syncBackend "' . $backendId . '"');

                    try {
                        $backendArray['instance']->update($contact);
                    } catch (Exception $e) {
                        Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' could not update record in sync backend "' .
                            $backendId . '": ' . $e->getMessage());
                        Tinebase_Exception::log($e, false);
                    }

                    // else create it
                } else {

                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' create record in syncBackend "' . $backendId . '"');

                    try {
                        $backendArray['instance']->create($contact);

                        $contact->syncBackendIds = (empty($contact->syncBackendIds)?'':$contact->syncBackendIds . ',') . $backendId;

                        $updateSyncBackendIds = true;
                    } catch (Exception $e) {
                        Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' could not create record in sync backend "' .
                            $backendId . '": ' . $e->getMessage());
                        Tinebase_Exception::log($e, false);
                    }
                }
            }

            if (true === $updateSyncBackendIds) {
                $sqlBackend->updateSyncBackendIds($contact->getId(), $contact->syncBackendIds);
            }
        }
    }

    /**
     * import contacts
     *
     * @param Zend_Console_Getopt $_opts
     */
    public function import($_opts)
    {
        parent::_import($_opts);
    }
    
    /**
     * export contacts csv to STDOUT
     * 
     * NOTE: exports contacts in container id 1 by default. id needs to be changed in the code.
     *
     * //@ param Zend_Console_Getopt $_opts
     * 
     * @todo allow to pass container id (and maybe more filter options) as param
     */
    public function export(/*$_opts*/)
    {
        $containerId = 1;
        
        $filter = new Addressbook_Model_ContactFilter(array(
            array('field' => 'container_id',     'operator' => 'equals',   'value' => $containerId     )
        ));

        $csvExporter = new Addressbook_Export_Csv($filter, null, array('toStdout' => true));
        
        $csvExporter->generate();
    }

    /**
     * remove autogenerated contacts
     *
     * @param Zend_Console_Getopt $opts
     *
     * @throws Addressbook_Exception
     * @throws Tinebase_Exception_InvalidArgument
     * @todo use OR filter for different locales
     */
    public function removeAutogeneratedContacts($opts)
    {
        if (! Tinebase_Application::getInstance()->isInstalled('Calendar')) {
            throw new Addressbook_Exception('Calendar application not installed');
        }
        
        $params = $this->_parseArgs($opts);
        
        $languages = isset($params['languages']) ? $params['languages'] : array('en', 'de');
        
        $contactBackend = new Addressbook_Backend_Sql();
        
        foreach ($languages as $language) {
            $locale = new Zend_Locale($language);
            
            $translation = Tinebase_Translation::getTranslation('Calendar', $locale);
            // search all contacts with note "This contact has been automatically added by the system as an event attender"
            $noteFilter = new Addressbook_Model_ContactFilter(array(
                array('field' => 'note', 'operator' => 'equals', 'value' => 
                    $translation->_('This contact has been automatically added by the system as an event attender')),
            ));
            $contactIdsToDelete = $contactBackend->search($noteFilter, null, Tinebase_Backend_Sql_Abstract::IDCOL);
            
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . " About to delete " . count($contactIdsToDelete) . ' contacts ...');
            
            $number = $contactBackend->delete($contactIdsToDelete);
            
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . " Deleted " . $number . ' autogenerated contacts for language ' . $language);
        }
    }

    /**
     * update geodata - only updates addresses without geodata for adr_one
     *
     * @param Zend_Console_Getopt $opts
     */
    public function updateContactGeodata($opts)
    {
        $params = $this->_parseArgs($opts, array('containerId'));

        // get all contacts in a container
        $filter = new Addressbook_Model_ContactFilter(array(
            array('field' => 'container_id', 'operator' => 'equals', 'value' => $params['containerId']),
            array('field' => 'adr_one_lon', 'operator' => 'isnull', 'value' => null)
        ));
        Addressbook_Controller_Contact::getInstance()->setGeoDataForContacts(true);
        $result = Addressbook_Controller_Contact::getInstance()->updateMultiple($filter, array());

        echo 'Updated ' . $result['totalcount'] . ' Record(s)';
    }
}
