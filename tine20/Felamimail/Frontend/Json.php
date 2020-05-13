<?php
/**
 * json frontend for Felamimail
 *
 * This class handles all Json requests for the Felamimail application
 *
 * @package     Felamimail
 * @subpackage  Frontend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2016 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */
class Felamimail_Frontend_Json extends Tinebase_Frontend_Json_Abstract
{
    /**
     * application name
     *
     * @var string
     */
    protected $_applicationName = 'Felamimail';

    protected $_configuredModels = [
        'Account',
        'Signature',
    ];

    /***************************** folder funcs *******************************/
    
    /**
     * search folders and update/initialize cache of subfolders 
     *
     * @param  array $filter
     * @return array
     */
    public function searchFolders($filter)
    {
        // close session to allow other requests
        Tinebase_Session::writeClose(true);
        
        $result = $this->_search($filter, '', Felamimail_Controller_Folder::getInstance(), 'Felamimail_Model_FolderFilter');
        
        return $result;
    }

    /**
     * add new folder
     *
     * @param string $name
     * @param string $parent
     * @param string $accountId
     * @return array
     */
    public function addFolder($name, $parent, $accountId)
    {
        $result = Felamimail_Controller_Folder::getInstance()->create($accountId, $name, $parent);
        
        return $result->toArray();
    }

    /**
     * rename folder
     *
     * @param string $newName
     * @param string $oldGlobalName
     * @param string $accountId
     * @return array
     */
    public function renameFolder($newName, $oldGlobalName, $accountId)
    {
        $result = Felamimail_Controller_Folder::getInstance()->rename($accountId, $newName, $oldGlobalName);
        
        return $result->toArray();
    }
    
    /**
     * delete folder
     *
     * @param string $folder the folder global name to delete
     * @param string $accountId
     * @return array
     */
    public function deleteFolder($folder, $accountId)
    {
        $result = Felamimail_Controller_Folder::getInstance()->delete($accountId, $folder);

        return array(
            'status'    => ($result) ? 'success' : 'failure'
        );
    }
    
    /**
     * refresh folder
     *
     * @param string $folderId the folder id to delete
     * @return array
     */
    public function refreshFolder($folderId)
    {
        $result = Felamimail_Controller_Cache_Message::getInstance()->clear($folderId);

        return array(
            'status'    => ($result) ? 'success' : 'failure'
        );
    }

    /**
     * remove all messages from folder and delete subfolders
     *
     * @param  string $folderId the folder id to delete
     * @return array with folder status
     */
    public function emptyFolder($folderId)
    {
        // close session to allow other requests
        Tinebase_Session::writeClose(true);
        
        $result = Felamimail_Controller_Folder::getInstance()->emptyFolder($folderId, TRUE);
        return $this->_recordToJson($result);
    }
    
    /**
     * update folder cache
     *
     * @param string $accountId
     * @param string  $folderName of parent folder
     * @return array of (sub)folders in cache
     */
    public function updateFolderCache($accountId, $folderName)
    {
        // this may take longer
        $this->_longRunningRequest(300);

        $result = Felamimail_Controller_Cache_Folder::getInstance()->update($accountId, $folderName, TRUE);
        return $this->_multipleRecordsToJson($result);
    }
    
    /**
     * get folder status
     *
     * @param array  $filterData
     * @return array of folder status
     * @throws Tinebase_Exception_SystemGeneric
     */
    public function getFolderStatus($filterData)
    {
        // close session to allow other requests
        Tinebase_Session::writeClose(true);
        
        $filter = new Felamimail_Model_FolderFilter($filterData);
        try {
            $result = Felamimail_Controller_Cache_Message::getInstance()->getFolderStatus($filter);
        } catch (Exception $e) {
            // we have to convert this exception because the frontend does not handle the imap errors well...
            throw new Tinebase_Exception_SystemGeneric('Failed to get folder status: ' . $e->getMessage());
        }
        return $this->_multipleRecordsToJson($result);
    }
    
    /***************************** messages funcs *******************************/
    
    /**
     * search messages in message cache
     *
     * @param  array $filter
     * @param  array $paging
     * @return array
     */
    public function searchMessages($filter, $paging)
    {
        $result = $this->_search($filter, $paging, Felamimail_Controller_Message::getInstance(), 'Felamimail_Model_MessageFilter');
        
        return $result;
    }
    
    /**
     * update message cache
     * - use session/writeClose to update incomplete cache and allow following requests
     *
     * @param  string  $folderId id of active folder
     * @param  integer $time     update time in seconds
     * @return array
     */
    public function updateMessageCache($folderId, $time)
    {
        // close session to allow other requests
        Tinebase_Session::writeClose(true);
        
        $folder = Felamimail_Controller_Cache_Message::getInstance()->updateCache($folderId, $time);
        
        return $this->_recordToJson($folder);
    }
    
    /**
     * get message data
     *
     * @param  string $id
     * @param  string $mimeType
     * @return array
     */
    public function getMessage($id, $mimeType='configured')
    {
        // close session to allow other requests
        Tinebase_Session::writeClose(true);
        
        if (strpos($id, '_') !== false) {
            list($messageId, $partId) = explode('_', $id);
        } else {
            $messageId = $id;
            $partId    = null;
        }
        
        $message = Felamimail_Controller_Message::getInstance()->getCompleteMessage($messageId, $partId, $mimeType, false);
        $message->id = $id;
        
        return $this->_recordToJson($message);
    }
    
    /**
     * move messsages to folder
     *
     * @param  array $filterData filter data
     * @param  string $targetFolderId
     * @return array source folder status
     */
    public function moveMessages($filterData, $targetFolderId)
    {
        // close session to allow other requests
        Tinebase_Session::writeClose(true);
        
        $filter = new Felamimail_Model_MessageFilter(array());
        $filter->setFromArrayInUsersTimezone($filterData);
        $updatedFolders = Felamimail_Controller_Message_Move::getInstance()->moveMessages($filter, $targetFolderId);
        
        $result = ($updatedFolders !== NULL) ? $this->_multipleRecordsToJson($updatedFolders) : array();
        
        return $result;
    }
    
    /**
     * save + send message
     * 
     * - this function has to be named 'saveMessage' because of the generic edit dialog function names
     *
     * @param  array $recordData
     * @return array
     */
    public function saveMessage($recordData)
    {
        $message = $this->_jsonToRecord($recordData, Felamimail_Model_Message::class);
        $result = Felamimail_Controller_Message_Send::getInstance()->sendMessage($message);
        $result = $this->_recordToJson($result);
        
        return $result;
    }

    /**
     * save message in folder
     * 
     * @param  string $folderName
     * @param  array $recordData
     * @return array
     */
    public function saveMessageInFolder($folderName, $recordData)
    {
        $message = $this->_jsonToRecord($recordData, Felamimail_Model_Message::class);
        $result = Felamimail_Controller_Message_Send::getInstance()->saveMessageInFolder($folderName, $message);
        return $this->_recordToJson($result);
    }

    /**
     * @param $recordData
     * @return array
     * @throws Tinebase_Exception_Record_DefinitionFailure
     * @throws Tinebase_Exception_Record_Validation
     */
    public function saveDraft($recordData)
    {
        $message = $this->_jsonToRecord($recordData, Felamimail_Model_Message::class);
        $result = Felamimail_Controller_Message::getInstance()->saveDraft($message);
        return $this->_recordToJson($result);
    }

    /**
     * @param integer $uid
     * @param string $accountid
     * @return array
     */
    public function deleteDraft($uid, $accountid)
    {
        return [
            'success' => Felamimail_Controller_Message::getInstance()->deleteDraft($uid, $accountid)
        ];
    }

    /**
     * file messages into Filemanager
     *
     * @param array $filterData
     * @param array $locations
     * @return array
     */
    public function fileMessages($filterData, $locations)
    {
        $this->_longRunningRequest();

        $filter = $this->_decodeFilter($filterData, 'Felamimail_Model_MessageFilter');

        $result = Felamimail_Controller_Message_File::getInstance()->fileMessages($filter,
            new Tinebase_Record_RecordSet(
                Felamimail_Model_MessageFileLocation::class,
                $locations,
                true
            )
        );

        return array(
            'totalcount' => ($result === false) ? 0 : $result,
            'success'    => ($result > 0),
        );
    }

    /**
     * @param string $id
     * @param array $locations
     * @param array $attachments
     * @param string $model
     * @return array
     * @throws Tinebase_Exception_InvalidArgument
     */
    public function fileAttachments($id, $locations, $attachments = [], $model = 'Felamimail_Model_Message')
    {
        return [
            'success' => Felamimail_Controller_Message_File::getInstance()->fileAttachments(
                $id,
                new Tinebase_Record_RecordSet(
                    Felamimail_Model_MessageFileLocation::class,
                    $locations,
                    true
                ),
                $attachments,
                $model
            )
        ];
    }

    /**
     * add given flags to given messages
     *
     * @param  array        $filterData
     * @param  string|array $flags
     * @return array
     * 
     * @todo remove legacy code
     */
    public function addFlags($filterData, $flags)
    {
        // close session to allow other requests
        Tinebase_Session::writeClose(true);
        
        // as long as we get array of ids or filter data from the client, we need to do this legacy handling (1 dimensional -> ids / 2 dimensional -> filter data)
        if (! empty($filterData) && is_array($filterData[0])) {
            $filter = new Felamimail_Model_MessageFilter(array());
            $filter->setFromArrayInUsersTimezone($filterData);
        } else {
            $filter = $filterData;
        }
        
        $affectedFolders = Felamimail_Controller_Message_Flags::getInstance()->addFlags($filter, (array) $flags);
        
        return array(
            'status'    => 'success',
            'result'    => $affectedFolders,
        );
    }
    
    /**
     * clear given flags from given messages
     *
     * @param array         $filterData
     * @param string|array  $flags
     * @return array
     * 
     * @todo remove legacy code
     * @todo return $affectedFolders to client
     */
    public function clearFlags($filterData, $flags)
    {
        // as long as we get array of ids or filter data from the client, we need to do this legacy handling (1 dimensional -> ids / 2 dimensional -> filter data)
        if (! empty($filterData) && is_array($filterData[0])) {
            $filter = new Felamimail_Model_MessageFilter(array());
            $filter->setFromArrayInUsersTimezone($filterData);
        } else {
            $filter = $filterData;
        }
        Felamimail_Controller_Message_Flags::getInstance()->clearFlags($filter, (array) $flags);
        
        return array(
            'status' => 'success'
        );
    }
    
    /**
     * returns message prepared for json transport
     * - overwriten to convert recipients to array
     *
     * @param Tinebase_Record_Interface $_record
     * @return array record data
     */
    protected function _recordToJson($_record)
    {
        if ($_record instanceof Felamimail_Model_Message) {
            foreach (array('to', 'cc', 'bcc') as $type) {
                if (! is_array($_record->{$type})) {
                    if (! empty($_record->{$type})) {
                        $exploded = explode(',', $_record->{$type});
                        $_record->{$type} = $exploded;
                    } else {
                        $_record->{$type} = array();
                    }
                }
            }
            
            if ($_record->preparedParts instanceof Tinebase_Record_RecordSet) {
                foreach ($_record->preparedParts as $preparedPart) {
                    if ($preparedPart->preparedData instanceof Calendar_Model_iMIP) {
                        $iMIPFrontend = new Calendar_Frontend_iMIP();
                        try {
                            $iMIPFrontend->prepareComponent($preparedPart->preparedData, /* $_throwException = */ false);
                        } catch (Exception $e) {
                            // maybe this wasn't an iMIP part ... or it could not be parsed - skip it
                            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(
                                __METHOD__ . '::' . __LINE__ . ' Could not handle preparedPart: ' . $e->getMessage()
                            );
                        }
                    }
                }
            }

        } else if ($_record instanceof Felamimail_Model_Sieve_Vacation) {
            if (! $_record->mime) {
                $_record->reason = Tinebase_Mail::convertFromTextToHTML($_record->reason, 'felamimail-body-blockquote');
            }
        }
        
        return parent::_recordToJson($_record);
    }
    
    /**
     * update flags
     * - use session/writeClose to allow following requests
     *
     * @param  string  $folderId id of active folder
     * @param  integer $time     update time in seconds
     * @return array
     */
    public function updateFlags($folderId, $time)
    {
        // close session to allow other requests
        Tinebase_Session::writeClose(true);
        
        $folder = Felamimail_Controller_Cache_Message::getInstance()->updateFlags($folderId, $time);
        
        return $this->_recordToJson($folder);
    }
    
    /**
     * send reading confirmation
     * 
     * @param string $messageId
     * @return array
     */
    public function sendReadingConfirmation($messageId)
    {
        Felamimail_Controller_Message::getInstance()->sendReadingConfirmation($messageId);
        
        return array(
            'status' => 'success'
        );
    }
    
    /***************************** accounts funcs *******************************/
    
    /**
     * search accounts
     * 
     * @param  array $filter
     * @return array
     */
    public function searchAccounts($filter)
    {
        $accounts = $this->_search($filter, '', Felamimail_Controller_Account::getInstance(), 'Felamimail_Model_AccountFilter');
        // add signatures and remove ADB list type from result set
        // TODO move this to a better place (default filter?)
        foreach ($accounts['results'] as $idx => $account) {
            if (in_array($account['type'], [
                Felamimail_Model_Account::TYPE_SHARED,
                Felamimail_Model_Account::TYPE_USER,
                Felamimail_Model_Account::TYPE_USER_INTERNAL,
                Felamimail_Model_Account::TYPE_SYSTEM,
            ])) {
                $fullAccount = $this->getAccount($account['id']);
                $this->_reloadFolderCacheOnPrimaryAccount($fullAccount);
                $accounts['results'][$idx] = $fullAccount;
            } else {
                unset($accounts['results'][$idx]);
                $accounts['totalcount']--;
            }
        }
        // Reorder the array (client does not like missing indices
        $accounts['results'] = array_values($accounts['results']);

        return $accounts;
    }

    /**
     * reload folder cache on primary account - only do this once an hour (with caching)
     *
     * @param $account
     * @return boolean
     */
    protected function _reloadFolderCacheOnPrimaryAccount($account)
    {
        if (Tinebase_Core::getPreference($this->_applicationName)->{Felamimail_Preference::DEFAULTACCOUNT} !== $account['id']) {
            return false;
        }

        $cache = Tinebase_Core::getCache();
        $cacheId = Tinebase_Helper::convertCacheId('_reloadFolderCacheOnPrimaryAccount' . $account['id']);
        if ($cache->test($cacheId)) {
            return $cache->load($cacheId);
        }

        try {
            $this->updateFolderCache($account['id'], '');
        } catch (Exception $e) {
            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(
                __METHOD__ . '::' . __LINE__ . ' Could not update account folder cache: ' . $e->getMessage()
            );
        }
        $cache->save(true, $cacheId, [], 3600);
        return true;
    }
    
    /**
     * get account data
     *
     * @param string $id
     * @return array
     */
    public function getAccount($id)
    {
        return $this->_get($id, Felamimail_Controller_Account::getInstance());
    }
    
    /**
     * creates/updates a record
     *
     * @param  array $recordData
     * @return array created/updated record
     */
    public function saveAccount($recordData)
    {
        return $this->_save($recordData, Felamimail_Controller_Account::getInstance(), 'Account');
    }
    
    /**
     * deletes existing accounts
     *
     * @param  array $ids
     * @return array
     */
    public function deleteAccounts($ids)
    {
        return array('status' => $this->_delete($ids, Felamimail_Controller_Account::getInstance()));
    }
    
    /**
     * change account pwd / username
     *
     * @param string $id
     * @param string $username
     * @param string $password
     * @return array
     */
    public function changeCredentials($id, $username, $password)
    {
        Tinebase_Core::getLogger()->addReplacement($password);
        $result = Felamimail_Controller_Account::getInstance()->changeCredentials($id, $username, $password);
        return [
            'status' => ($result) ? 'success' : 'failure'
        ];
    }

    /**
     * @param string $accountId
     * @return array
     * @throws Tinebase_Exception_AccessDenied
     */
    public function approveAccountMigration($accountId)
    {
        $account = Felamimail_Controller_Account::getInstance()->approveMigration($accountId);
        return [
            'status' => ($account->migration_approved === 1) ? 'success' : 'failure'
        ];
    }
    
    /***************************** sieve funcs *******************************/
    
    /**
     * get sieve vacation for account 
     *
     * @param  string $id account id
     * @return array
     */
    public function getVacation($id)
    {
        $record = Felamimail_Controller_Sieve::getInstance()->getVacation($id);
        
        return $this->_recordToJson($record);
    }

    /**
     * set sieve vacation for account 
     *
     * @param  array $recordData
     * @return array
     */
    public function saveVacation($recordData)
    {
        $record = new Felamimail_Model_Sieve_Vacation(array(), TRUE);
        $record->setFromJsonInUsersTimezone($recordData);
        
        $record = Felamimail_Controller_Sieve::getInstance()->setVacation($record);
        
        return $this->_recordToJson($record);
    }
    
    /**
     * get sieve rules for account 
     *
     * @param  string $accountId
     * @return array
     */
    public function getRules($accountId)
    {
        $records = Felamimail_Controller_Sieve::getInstance()->getRules($accountId);
        
        return array(
            'results'       => $this->_multipleRecordsToJson($records),
            'totalcount'    => count($records),
        );
    }

    /**
     * set sieve rules for account 
     *
     * @param   array $accountId
     * @param   array $rulesData
     * @return  array
     */
    public function saveRules($accountId, $rulesData)
    {
        $records = new Tinebase_Record_RecordSet('Felamimail_Model_Sieve_Rule', $rulesData);
        $records = Felamimail_Controller_Sieve::getInstance()->setRules($accountId, $records);
        
        return $this->_multipleRecordsToJson($records);
    }

    /**
     * get available vacation message templates
     * 
     * @return array
     */
    public function getVacationMessageTemplates()
    {
        return $this->getTemplates(Felamimail_Config::getInstance()->{Felamimail_Config::VACATION_TEMPLATES_CONTAINER_ID});
    }
    
    /**
     * get vacation message defined by template / do substitutions for dates and representative 
     * 
     * @param array $vacationData
     * @return array
     */
    public function getVacationMessage($vacationData)
    {
        $record = new Felamimail_Model_Sieve_Vacation(array(), TRUE);
        $record->setFromJsonInUsersTimezone($vacationData);
        
        $message = Felamimail_Controller_Sieve::getInstance()->getVacationMessage($record);
        $htmlMessage = Tinebase_Mail::convertFromTextToHTML($message, 'felamimail-body-blockquote');
        
        return array(
            'message' => $htmlMessage
        );
    }
    
    /***************************** other funcs *******************************/
    
    /**
     * Returns registry data of felamimail.
     * @see Tinebase_Application_Json_Abstract
     * 
     * @return mixed array 'variable name' => 'data'
     */
    public function getRegistryData()
    {
        $supportedFlags = Felamimail_Controller_Message_Flags::getInstance()->getSupportedFlags();
        
        $result = array(
            'supportedFlags'        => array(
                'results'       => $supportedFlags,
                'totalcount'    => count($supportedFlags),
            ),
        );
        
        $result['vacationTemplates'] = $this->getVacationMessageTemplates();
        
        return $result;
    }

    /**
     * @param array $mails
     * @return array
     * @throws Tinebase_Exception_InvalidArgument
     */
    public function doMailsBelongToAccount($mails)
    {
        $contactFilter = new Addressbook_Model_ContactFilter([
            [
                'field' => 'type',
                'operator' => 'equals',
                'value' => Addressbook_Model_Contact::CONTACTTYPE_USER
            ],
            [
                'condition' => 'OR',
                'filters' => [
                    [
                        'field' => 'email',
                        'operator' => 'in',
                        'value' => $mails
                    ],
                    [
                        'field' => 'email_home',
                        'operator' => 'in',
                        'value' => $mails
                    ]
                ]
            ]
        ]);
        
        $contacts = Addressbook_Controller_Contact::getInstance()->search($contactFilter);
        
        $usermails = array_filter(array_merge($contacts->email, $contacts->email_home));
        
        return array_diff($mails, $usermails);
    }

    /**
     * returns eml node converted to Felamimail message
     *
     * @param $nodeId
     * @return array
     */
    public function getMessageFromNode($nodeId)
    {
        $message = Felamimail_Controller_Message::getInstance()->getMessageFromNode($nodeId);
        return $this->_recordToJson($message);
    }

    /**
     * fetch suggestions for filing places for given message / recipients / ...
     *
     * @param array $message
     * @return array
     */
    public function getFileSuggestions($message)
    {
        $result = [];
        if ($message) {
            $record = $this->_jsonToRecord($message, Felamimail_Model_Message::class);
            $suggestions = Felamimail_Controller_Message_File::getInstance()->getFileSuggestions($record);
            foreach ($suggestions as $suggestion) {
                $result[] = [
                    'type' => $suggestion->type,
                    'model' => $suggestion->model,
                    'record' => $this->_recordToJson($suggestion->record),
                ];
            }
        }
        return $result;
    }
}
