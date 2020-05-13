<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Server
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * 
 */

use Jumbojett\OpenIDConnectClient;

/**
 * Json interface to Tinebase
 * 
 * @package     Tinebase
 * @subpackage  Server
 */
class Tinebase_Frontend_Json extends Tinebase_Frontend_Json_Abstract
{
    const REQUEST_TYPE = 'JSON-RPC';

    /**
     * the application name
     *
     * @var string
     */
    protected $_applicationName = 'Tinebase';
    
    /**
     *
     * @var boolean
     */
    protected $_hasCaptcha = null;

    /**
     * All full configured models
     *
     * @var array
     */
    protected $_configuredModels = [
        'BLConfig',
        'ImportExportDefinition'
    ];

    /**
     * wait for changes
     * 
     * @todo do we still need this?
     */
    public function ping()
    {
        Tinebase_Session::writeClose(true);
        sleep(10);
        return array('changes' => 'contacts');
    }
    
    /**
     * get list of translated country names
     * 
     * Wrapper for {@see Tinebase_Core::getCountrylist}
     * 
     * @return array list of countrys
     */
    public function getCountryList()
    {
        return Tinebase_Translation::getCountryList();
    }
    
    /**
     * returns list of all available translations
     *
     * @return array list of all available translations
     */
    public function getAvailableTranslations()
    {
        $whitelistedLocales = Tinebase_Config::getInstance()->get(Tinebase_Config::AVAILABLE_LANGUAGES);
        $availableTranslations = Tinebase_Translation::getAvailableTranslations();
        foreach ($availableTranslations as $key => &$info) {
            unset($info['path']);
            if (! empty($whitelistedLocales) && ! in_array($info['locale'], $whitelistedLocales)) {
                unset($availableTranslations[$key]);
            }
        }

        return array(
            'results'    => array_values($availableTranslations),
            'totalcount' => count($availableTranslations)
        );
    }
    
    /**
     * sets locale
     *
     * @param  string $localeString
     * @param  bool   $saveaspreference
     * @param  bool   $setcookie
     * @return array
     */
    public function setLocale($localeString, $saveaspreference, $setcookie)
    {
        Tinebase_Core::setupUserLocale($localeString);
        
        if ($saveaspreference && is_object(Tinebase_Core::getUser())) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . " Saving locale: " . $localeString);
            Tinebase_Core::getPreference()->{Tinebase_Preference::LOCALE} = $localeString;
        }
        
        // save in cookie (expires in 365 days)
        if ($setcookie) {
            setcookie('TINE20LOCALE', $localeString, time()+60*60*24*365);
        }
        
        return array(
            'success'      => TRUE
        );
    }
    
    /**
     * sets timezone
     *
     * @param  string $timezoneString
     * @param  bool   $saveaspreference
     * @return string
     */
    public function setTimezone($timezoneString, $saveaspreference)
    {
        $timezone = Tinebase_Core::setupUserTimezone($timezoneString, $saveaspreference);
        
        return $timezone;
    }
    
    /**
     * get users
     *
     * @param string $filter
     * @param string $sort
     * @param string $dir
     * @param int $start
     * @param int $limit
     * @return array with results array & totalcount (int)
     */
    public function getUsers($filter, $sort, $dir, $start, $limit)
    {
        $result = array(
            'results'     => array(),
            'totalcount'  => 0
        );
        
        if($rows = Tinebase_User::getInstance()->getUsers($filter, $sort, $dir, $start, $limit)) {
            $result['results']    = $rows->toArray();
            if($start == 0 && count($result['results']) < $limit) {
                $result['totalcount'] = count($result['results']);
            } else {
                //$result['totalcount'] = $backend->getCountByAddressbookId($addressbookId, $filter);
            }
        }
        
        return $result;
    }
    
    /**
     * Search for roles
     *
     * @param  array $_filter
     * @param  array $_paging
     * @return array
     */
    public function searchRoles($filter, $paging)
    {
        $result = array(
            'results'     => array(),
            'totalcount'  => 0
        );
        
        $filter = new Tinebase_Model_RoleFilter($filter);
        
        $paging['sort'] = isset($paging['sort']) ? $paging['sort'] : 'name';
        $paging['dir'] = isset($paging['dir']) ? $paging['dir'] : 'ASC';
        
        $result['results'] = Tinebase_Acl_Roles::getInstance()->searchRoles($filter, new Tinebase_Model_Pagination($paging))->toArray();
        $result['totalcount'] = Tinebase_Acl_Roles::getInstance()->searchCount($filter);
        
        return $result;
    }
    
    /**
     * change password of user
     *
     * @param  string $oldPassword the old password
     * @param  string $newPassword the new password
     * @return array
     */
    public function changePassword($oldPassword, $newPassword)
    {
        return $this->_changePwOrPin($oldPassword, $newPassword);
    }

    /**
     * @param        $oldPassword
     * @param        $newPassword
     * @param string $pwType
     * @return array
     */
    protected function _changePwOrPin($oldPassword, $newPassword, $pwType = 'password')
    {
        $response = array(
            'success'      => TRUE
        );

        try {
            Tinebase_Controller::getInstance()->changePassword($oldPassword, $newPassword, $pwType);
        } catch (Tinebase_Exception $e) {
            $response = array(
                'success'      => FALSE,
                'errorMessage' => $e->getMessage()
            );
        }

        return $response;
    }

    /**
     * change pin of user
     *
     * @param  string $oldPassword the old password
     * @param  string $newPassword the new password
     * @return array
     */
    public function changePin($oldPassword, $newPassword)
    {
        return $this->_changePwOrPin($oldPassword, $newPassword, 'pin');
    }

    /**
     * clears state
     *
     * @param  string $name
     * @return array
     */
    public function clearState($name)
    {
        Tinebase_State::getInstance()->clearState($name);
        
        return ['success' => true];
    }
    
    /**
     * retuns all states
     *
     * @return array of name => value
     */
    public function loadState()
    {
        return Tinebase_State::getInstance()->loadStateInfo();
    }
    
    /**
     * set state
     *
     * @param  string $name
     * @param  string $value
     * @return array
     */
    public function setState($name, $value)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Setting state: {$name} -> {$value}");
        Tinebase_State::getInstance()->setState($name, $value);
        
        return ['success' => true];
    }
    
    /**
     * adds a new personal tag
     *
     * @param  array $tag
     * @return array
     */
    public function saveTag($tag)
    {
        $inTag = new Tinebase_Model_Tag($tag);
        
        if (strlen($inTag->getId()) < 40) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' creating tag: ' . print_r($inTag->toArray(), true));
            $outTag = Tinebase_Tags::getInstance()->createTag($inTag);
        } else {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' updating tag: ' .print_r($inTag->toArray(), true));
            $outTag = Tinebase_Tags::getInstance()->updateTag($inTag);
        }
        
        return $outTag->toArray();
    }

    /**
     * @param $commencement
     * @param $termOfContractInMonths
     * @param $automaticContractExtensionInMonths
     * @param $cancellationPeriodInMonths
     * @return array
     */
    public function getTerminationDeadline(
        $commencement,
        $termOfContractInMonths,
        $automaticContractExtensionInMonths,
        $cancellationPeriodInMonths,
        $today = null
    ) {
        date_default_timezone_set(Tinebase_Core::getUserTimezone());
        $commencement = new Tinebase_DateTime($commencement);
        $commencement->setTimezone('UTC');
        date_default_timezone_set('UTC');

        $deadline = Tinebase_Helper_Algorithm_TerminationDeadline::getInstance()->getTerminationDeadline(
            $commencement,
            $termOfContractInMonths,
            $automaticContractExtensionInMonths,
            $cancellationPeriodInMonths,
            $today
        );
        $deadline->setTimezone(Tinebase_Core::getUserTimezone());
        $deadline->setTime(0, 0);

        return [
            'terminationDeadline' => $deadline->format(Tinebase_Record_Abstract::ISO8601LONG)
        ];
    }

    /**
     * Used for updating multiple records
     * 
     * @param string $appName
     * @param string $modelName
     * @param array $changes
     * @param array $filter
     */
    public function updateMultipleRecords($appName, $modelName, $changes, $filter)
    {
        // increase execution time to 30 minutes
        Tinebase_Core::setExecutionLifeTime(1800);
        
        $filterModel = $appName . '_Model_' . $modelName . 'Filter';
        $data = array();
        foreach ($changes as $f) {
            $data[preg_replace('/^customfield_/','#', $f['name'])] = $f['value'];
        }
        
        return $this->_updateMultiple($filter, $data, Tinebase_Core::getApplicationInstance($appName, $modelName), $filterModel);
    }

    /**
     * search tags
     *
     * @param  array $filter filter array
     * @param  array $paging pagination info
     * @return array
     */
    public function searchTags($filter, $paging)
    {
        $filter = new Tinebase_Model_TagFilter($filter);
        $paging = new Tinebase_Model_Pagination($paging);
        
        return array(
            'results'    => Tinebase_Tags::getInstance()->searchTags($filter, $paging)->toArray(),
            // TODO we normally use 'totalcount' (all lower case) - this should be streamlined
            'totalCount' => Tinebase_Tags::getInstance()->getSearchTagsCount($filter)
        );
    }
    
    /**
    * search tags by foreign filter
    *
    * @param  array $filterData
    * @param  string $filterName
    * @return array
    */
    public function searchTagsByForeignFilter($filterData, $filterName)
    {
        $filter = $this->_getFilterGroup($filterData, $filterName);
        
        $result = Tinebase_Tags::getInstance()->searchTagsByForeignFilter($filter)->toArray();
        return array(
            'results'    => $result,
            'totalCount' => count($result)
        );
    }
    
    /**
     * get filter group defined by filterName and filterData
     *
     * @param array $_filterData
     * @param string $_filterName
     * @return Tinebase_Model_Filter_FilterGroup
     * @throws Tinebase_Exception_AccessDenied
     */
    protected function _getFilterGroup($_filterData, $_filterName)
    {
        // NOTE: this function makes a new instance of a class whose name is given by user input.
        //       we need to do some sanitising first!
        /** @noinspection PhpUnusedLocalVariableInspection */
        list($appName, $modelString, $filterGroupName) = explode('_', $_filterName);
        if ($modelString !== 'Model') {
            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' spoofing attempt detected, affected account: ' . print_r(Tinebase_Core::getUser()->toArray(), TRUE));
            die('go away!');
        }

        if (! Tinebase_Core::getUser()->hasRight($appName, Tinebase_Acl_Rights_Abstract::RUN)) {
            throw new Tinebase_Exception_AccessDenied('No right to access application ' . $appName);
        }

        $filterGroup = Tinebase_Model_Filter_FilterGroup::getFilterForModel($_filterName, $_filterData);

        return $filterGroup;
    }
    
    /**
     * attach tag to multiple records identified by a filter
     *
     * @param array  $filterData
     * @param string $filterName
     * @param mixed  $tag       string|array existing and non-existing tag
     * @return array
     */
    public function attachTagToMultipleRecords($filterData, $filterName, $tag)
    {
        $this->_longRunningRequest();
        $filter = $this->_getFilterGroup($filterData, $filterName);
        
        Tinebase_Tags::getInstance()->attachTagToMultipleRecords($filter, $tag);
        return array('success' => true);
    }
    
    /**
     * attach multiple tags to multiple records identified by a filter
     *
     * @param array  $filterData
     * @param string $filterName
     * @param mixed  $tags         array of existing and non-existing tags
     * @return void
     */
    public function attachMultipleTagsToMultipleRecords($filterData, $filterName, $tags)
    {
        $this->_longRunningRequest();
        $filter = $this->_getFilterGroup($filterData, $filterName);

        foreach ($tags as $tag) {
            Tinebase_Tags::getInstance()->attachTagToMultipleRecords(clone $filter, $tag);
        }

        return array('success' => true);
    }

    /**
     * detach tags to multiple records identified by a filter
     *
     * @param array  $filterData
     * @param string $filterName
     * @param mixed  $tag       string|array existing and non-existing tag
     * @return array
     */
    public function detachTagsFromMultipleRecords($filterData, $filterName, $tag)
    {
        $this->_longRunningRequest();
        $filter = $this->_getFilterGroup($filterData, $filterName);
        
        Tinebase_Tags::getInstance()->detachTagsFromMultipleRecords($filter, $tag);
        return array('success' => true);
    }
    
    /**
     * search / get notes
     * - used by activities grid
     *
     * @param  array $filter filter array
     * @param  array $paging pagination info
     * @return array
     */
    public function searchNotes($filter, $paging)
    {
        $filter = new Tinebase_Model_NoteFilter($filter);
        $paging = new Tinebase_Model_Pagination($paging);
        
        $records = Tinebase_Notes::getInstance()->searchNotes($filter, $paging, /* ignoreACL = */ false);
        $result = $this->_multipleRecordsToJson($records);
        
        return array(
            'results'       => $result,
            'totalcount'    => Tinebase_Notes::getInstance()->searchNotesCount($filter, /* ignoreACL = */ false)
        );
    }
    
    /**
     * get note types
     *
     */
    public function getNoteTypes()
    {
        $noteTypes = Tinebase_Notes::getInstance()->getNoteTypes();
        $noteTypes->translate();
        
        return array(
            'results'       => $noteTypes->toArray(),
            'totalcount'    => count($noteTypes)
        );
    }
    
    /**
     * deletes tags identified by an array of identifiers
     *
     * @param  array $ids
     * @return array
     */
    public function deleteTags($ids)
    {
        Tinebase_Tags::getInstance()->deleteTags($ids);
        return array('success' => true);
    }

    /**
     * authenticate user by username and password
     *
     * @param  string $username the username
     * @param  string $password the password
     * @return array
     */
    public function authenticate($username, $password)
    {
        $authResult = Tinebase_Auth::getInstance()->authenticate($username, $password);
        
        if ($authResult->isValid()) {
            $response = array(
                'status'    => 'success',
                'msg'       => 'authentication succeed',
                //'loginUrl'  => 'someurl',
            );
        } else {
            $response = array(
                'status'    => 'fail',
                'msg'       => 'authentication failed',
            );
        }
        
        return $response;
    }

    /**
     * login user with given username and password
     *
     * @param  string $username the username
     * @param  string $password the password
     * @param  string $securitycode the security code(captcha)
     * @param  string $otp  the second factor password
     * @return array
     */
    public function login($username, $password, $securitycode = null, $otp = null)
    {
        try {
            Tinebase_Core::startCoreSession();
        } catch (Zend_Session_Exception $zse) {
            if (Tinebase_Core::isLogLevel(Zend_Log::ERR)) {
                Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' Could not start session: ' . $zse->getMessage());
            }
            return array(
                'success'      => false,
                'errorMessage' => "Could not start session!",
            );
        }
        
        if (is_array(($response = $this->_getCaptchaResponse($securitycode)))) {
            return $response;
        }

        // TODO move security code here and use params
        Tinebase_Controller::getInstance()->setRequestContext(array(
            'otp' => $otp
        ));

        // try to login user
        $success = Tinebase_Controller::getInstance()->login(
            $username,
            $password,
            Tinebase_Core::get(Tinebase_Core::REQUEST),
            self::REQUEST_TYPE,
            $securitycode
        );
        
        if ($success === true) {
            return $this->_getLoginSuccessResponse($username);
        } else {
            return $this->_getLoginFailedResponse();
        }
    }

    /**
     * @param $oidcResponse
     * @return array
     */
    public function openIDCLogin($oidcResponse)
    {
        Tinebase_Core::startCoreSession();

        // try to login user
        $success = Tinebase_Controller::getInstance()->loginOIDC(
            $oidcResponse,
            Tinebase_Core::get(Tinebase_Core::REQUEST)
        );

        if ($success === true) {
            return $this->_getLoginSuccessResponse(Tinebase_Core::getUser()->accountLoginName);
        } else {
            return $this->_getLoginFailedResponse();
        }
    }

    /**
     * Returns TRUE if there is a captcha
     * @return boolean
     */
    protected function _hasCaptcha()
    {
        if ($this->_hasCaptcha === null){
            $this->_hasCaptcha = isset(Tinebase_Core::getConfig()->captcha->count) && Tinebase_Core::getConfig()->captcha->count != 0;
        }
        
        return $this->_hasCaptcha;
    }
    
    /**
     *
     * @param string $securitycode
     * @return array | NULL
     */
    protected function _getCaptchaResponse($securitycode)
    {
        if ($this->_hasCaptcha()) {
            $config_count = Tinebase_Core::getConfig()->captcha->count;
            
            $count = (isset(Tinebase_Session::getSessionNamespace()->captcha['count'])
                ? Tinebase_Session::getSessionNamespace()->captcha['count']
                : 1
            );
            
            if ($count >= $config_count) {
                $aux = isset(Tinebase_Session::getSessionNamespace()->captcha['code'])
                    ? Tinebase_Session::getSessionNamespace()->captcha['code']
                    : null;
                
                if ($aux != $securitycode) {
                    $rets = Tinebase_Controller::getInstance()->makeCaptcha(); 
                    $response = array(
                        'success'      => false,
                        'errorMessage' => "Wrong username or password!",
                        'c1'           => $rets['1']
                    );
                    
                    return $response;
                }
            }
        }
        
        return null;
    }
    
    /**
     *
     * @param string $username
     * @return array
     */
    protected function _getLoginSuccessResponse($username)
    {
            $response = array(
                'success'        => true,
                'account'        => Tinebase_Core::getUser()->getPublicUser()->toArray(),
                'sessionId'      => Tinebase_Core::getSessionId(),
                'jsonKey'        => Tinebase_Core::get('jsonKey'),
                'welcomeMessage' => "Welcome to Tine 2.0!"
            );
             
            if (Tinebase_Config::getInstance()->get(Tinebase_Config::REUSEUSERNAME_SAVEUSERNAME, 0)) {
                // save in cookie (expires in 2 weeks)
                setcookie('TINE20LASTUSERID', $username, time()+60*60*24*14);
            } else {
                setcookie('TINE20LASTUSERID', '', 0);
            }

            $this->_setCredentialCacheCookie();
            
        return $response;
    }
    
    /**
     *
     * @return array
     */
    protected function _getLoginFailedResponse()
    {
        $response = array(
            'success'      => false,
            'errorMessage' => "Wrong username or password!",
        );
        
        Tinebase_Auth_CredentialCache::getInstance()->getCacheAdapter()->resetCache();
        
        if ($this->_hasCaptcha()) {
            $config_count = Tinebase_Core::getConfig()->captcha->count;
            
            if (!isset(Tinebase_Session::getSessionNamespace()->captcha['count'])) {
                Tinebase_Session::getSessionNamespace()->captcha['count'] = 1;
            } else {
                Tinebase_Session::getSessionNamespace()->captcha['count'] = Tinebase_Session::getSessionNamespace()->captcha['count'] + 1;
            }
            
            if (Tinebase_Session::getSessionNamespace()->captcha['count'] >= $config_count) {
                $rets = Tinebase_Controller::getInstance()->makeCaptcha(); 
                $response = array(
                    'success'      => false,
                    'errorMessage' => "Wrong username or password!",
                    'c1'           => $rets['1']
                );
            }
        } else {
            Tinebase_Session::destroyAndMantainCookie();
        }
        
        return $response;
    }

    /**
     * set credential cache cookie
     *
     * @return boolean
     */
    protected function _setCredentialCacheCookie()
    {
        if (!Tinebase_Core::isRegistered(Tinebase_Core::USERCREDENTIALCACHE)) {
            Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Something went wrong with the CredentialCache / no CC registered.');
            
            return false;
        }
        
        Tinebase_Auth_CredentialCache::getInstance()->getCacheAdapter()->setCache(Tinebase_Core::getUserCredentialCache());
        
        return true;
    }

    /**
     * update user credential cache
     *
     * - fires Tinebase_Event_User_ChangeCredentialCache
     *
     * @param string $password
     * @return array
     */
    public function updateCredentialCache($password)
    {
        $oldCredentialCache = Tinebase_Core::getUserCredentialCache();
        $credentialCache = Tinebase_Auth_CredentialCache::getInstance()->cacheCredentials(Tinebase_Core::getUser()->accountLoginName, $password);
        Tinebase_Core::set(Tinebase_Core::USERCREDENTIALCACHE, $credentialCache);
        
        $success = $this->_setCredentialCacheCookie();
        
        if ($success) {
            // close session to allow other requests
            Tinebase_Session::writeClose(true);
            $event = new Tinebase_Event_User_ChangeCredentialCache($oldCredentialCache);
            Tinebase_Event::fireEvent($event);
        }
        
        return array(
            'success'      => $success
        );
    }

    /**
     * destroy session
     *
     * @return array
     */
    public function logout()
    {
        Tinebase_Controller::getInstance()->logout();
        
        Tinebase_Auth_CredentialCache::getInstance()->getCacheAdapter()->resetCache();
        
        if (Tinebase_Session::isStarted()) {
            Tinebase_Session::destroyAndRemoveCookie();
        }
        
        $result = array(
            'success'=> true,
        );
        
        return $result;
    }
    
    /**
     * Returns registry data of tinebase.
     * @see Tinebase_Application_Json_Abstract
     *
     * @return mixed array 'variable name' => 'data'
     */
    public function getRegistryData()
    {
        $registryData = $this->_getAnonymousRegistryData();
        
        if (Tinebase_Core::isRegistered(Tinebase_Core::USER)) {
            $userRegistryData = $this->_getUserRegistryData();
            $registryData += $userRegistryData;
        }
        
        return $registryData;
    }

    /**
     * get anonymous registry
     *
     * @return array
     * @throws Tinebase_Exception
     * @throws Exception
     */
    protected function _getAnonymousRegistryData()
    {
        $locale = Tinebase_Core::get('locale');
        $tbFrontendHttp = new Tinebase_Frontend_Http();
        
        // default credentials
        if (isset(Tinebase_Core::getConfig()->login)) {
            $loginConfig = Tinebase_Core::getConfig()->login;
            $defaultUsername = (isset($loginConfig->username)) ? $loginConfig->username : '';
            $defaultPassword = (isset($loginConfig->password)) ? $loginConfig->password : '';
        } else {
            $defaultUsername = '';
            $defaultPassword = '';
        }
        
        $symbols = Zend_Locale::getTranslationList('symbols', $locale);
        try {
            $assetHash = Tinebase_Frontend_Http_SinglePageApplication::getAssetHash();
        } catch (Exception $e) {
            // unittests
            $assetHash = Tinebase_Record_Abstract::generateUID(8);
        }

        $registryData =  array(
            'modSsl'           => Tinebase_Auth::getConfiguredBackend() == Tinebase_Auth::MODSSL,

             // secondfactor config
            'secondFactor' => $this->_getSecondFactorConfig(),

            // sso
            'sso'               => Tinebase_Config::getInstance()->{Tinebase_Config::SSO}->{Tinebase_Config::SSO_ACTIVE},

            'serviceMap'       => $tbFrontendHttp->getServiceMap(),
            'locale'           => array(
                'locale'   => $locale->toString(),
                'language' => Zend_Locale::getTranslation($locale->getLanguage(), 'language', $locale),
                'region'   => Zend_Locale::getTranslation($locale->getRegion(), 'country', $locale),
            ),
            'version'          => array(
                'buildType'     => TINE20_BUILDTYPE,
                'codeName'      => TINE20_CODENAME,
                'packageString' => TINE20_PACKAGESTRING,
                'releaseTime'   => TINE20_RELEASETIME,
                'assetHash'     => $assetHash,
            ),
            'defaultUsername'   => $defaultUsername,
            'defaultPassword'   => $defaultPassword,
            'denySurveys'       => Tinebase_Core::getConfig()->denySurveys,
            'titlePostfix'      => Tinebase_Config::getInstance()->get(Tinebase_Config::PAGETITLEPOSTFIX),
            'redirectUrl'       => Tinebase_Config::getInstance()->get(Tinebase_Config::REDIRECTURL),
            'helpUrl'           => Tinebase_Core::getConfig()->helpUrl,
            'maxFileUploadSize' => Tinebase_Helper::convertToBytes(ini_get('upload_max_filesize')),
            'maxPostSize'       => Tinebase_Helper::convertToBytes(ini_get('post_max_size')),
            'thousandSeparator' => $symbols['group'],
            'decimalSeparator'  => $symbols['decimal'],
            'currencySymbol'    => Tinebase_Config::getInstance()->get(Tinebase_Config::CURRENCY_SYMBOL),
            'filesystemAvailable' => Tinebase_Core::isFilesystemAvailable(),
            'brandingWeburl'    => Tinebase_Config::getInstance()->get(Tinebase_Config::BRANDING_WEBURL),
            'brandingLogo'      => Tinebase_ImageHelper::getDataUrl(Tinebase_Config::getInstance()->get(Tinebase_Config::BRANDING_LOGO)),
            'brandingFaviconSvg' => Tinebase_Config::getInstance()->get(Tinebase_Config::BRANDING_FAVICON_SVG),
            'brandingTitle'     => Tinebase_Config::getInstance()->get(Tinebase_Config::BRANDING_TITLE),
            'brandingDescription'=> Tinebase_Config::getInstance()->get(Tinebase_Config::BRANDING_DESCRIPTION),
            'brandingHelpUrl'    => Tinebase_Config::getInstance()->get(Tinebase_Config::BRANDING_HELPURL),
            'brandingShopUrl'    => Tinebase_Config::getInstance()->get(Tinebase_Config::BRANDING_SHOPURL),
            'brandingBugsUrl'    => Tinebase_Config::getInstance()->get(Tinebase_Config::BRANDING_BUGSURL),
            'installLogo'       => Tinebase_ImageHelper::getDataUrl(Tinebase_Core::getInstallLogo()),
            'websiteUrl'        => Tinebase_Config::getInstance()->get(Tinebase_Config::WEBSITE_URL),
            'fulltextAvailable' => Setup_Backend_Factory::factory()->supports('mysql >= 5.6.4 | mariadb >= 10.0.5') &&
                Tinebase_Config::getInstance()->featureEnabled(Tinebase_Config::FEATURE_FULLTEXT_INDEX),
        );

        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' Anonymous registry: ' . print_r($registryData, TRUE));
        
        return $registryData;
    }

    /**
     * @return array|null
     */
    protected function _getSecondFactorConfig()
    {
        $config = Tinebase_AreaLock::getInstance()->getAreaConfig(Tinebase_Model_AreaLockConfig::AREA_LOGIN);
        return $config ? $config->toArray() : null;
    }
    
    /**
     * get user registry
     *
     * @return array
     * @throws Addressbook_Exception_AccessDenied
     * @throws Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    protected function _getUserRegistryData()
    {
        $user = Tinebase_Core::getUser();
        $userContactArray = array();
        if (Tinebase_Application::getInstance()->isInstalled('Addressbook') === true) {
            try {
                $userContactArray = Addressbook_Controller_Contact::getInstance()->getContactByUserId($user->getId(), TRUE)->toArray();
            } catch (Addressbook_Exception_NotFound $aenf) {
                if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) /** @noinspection PhpUndefinedMethodInspection */
                    Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                        . ' User not found in Addressbook: ' . $user->accountDisplayName);
            }
        }

        try {
            $persistentFilters = Tinebase_Frontend_Json_PersistentFilter::getAllPersistentFilters();
        } catch (Tinebase_Exception_NotFound $tenf) {
            if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                . " Failed to fetch persistent filters. Exception: \n" . $tenf);
            $persistentFilters = array();
        } catch (Exception $e) {
            if (Tinebase_Core::isLogLevel(Zend_Log::ERR)) Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__
                . " Failed to fetch persistent filters. Exception: \n" . $e);
            $persistentFilters = array();
        }

        $manageSmtpEmailUser = Tinebase_EmailUser::manages(Tinebase_Config::SMTP);
        $smtpConfig = $manageSmtpEmailUser ? Tinebase_EmailUser::getConfig(Tinebase_Config::SMTP) : $smtpConfig = array();

        $userRegistryData = array(
            'accountBackend' => Tinebase_User::getConfiguredBackend(),
            'areaLocks' => $this->_multipleRecordsToJson(Tinebase_AreaLock::getInstance()->getAllStates()),
            'timeZone' => Tinebase_Core::getUserTimezone(),
            'currentAccount' => $user->toArray(),
            'userContact' => $userContactArray,
            'jsonKey' => Tinebase_Core::get('jsonKey'),
            'userApplications' => $user->getApplications()->toArray(),
            'NoteTypes' => $this->getNoteTypes(),
            'manageImapEmailUser' => Tinebase_EmailUser::manages(Tinebase_Config::IMAP),
            'manageSmtpEmailUser' => $manageSmtpEmailUser,
            'stateInfo' => Tinebase_State::getInstance()->loadStateInfo(),
            'mustchangepw' => $user->mustChangePassword(),
            'confirmLogout' => Tinebase_Core::getPreference()->getValue(Tinebase_Preference::CONFIRM_LOGOUT, 1),
            'advancedSearch' => Tinebase_Core::getPreference()->getValue(Tinebase_Preference::ADVANCED_SEARCH, 0),
            'persistentFilters' => $persistentFilters,
            'userAccountChanged' => Tinebase_Controller::getInstance()->userAccountChanged(),
            'sessionLifeTime' => Tinebase_Session_Abstract::getSessionLifetime(),
            'primarydomain' => isset($smtpConfig['primarydomain']) ? $smtpConfig['primarydomain'] : '',
            'secondarydomains' => isset($smtpConfig['secondarydomains']) ? $smtpConfig['secondarydomains'] : '',
            'additionaldomains' => isset($smtpConfig['additionaldomains']) ? $smtpConfig['additionaldomains'] : '',
            'smtpAliasesDispatchFlag' => Tinebase_EmailUser::smtpAliasesDispatchFlag(),
        );

        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' User registry: ' . print_r($userRegistryData, TRUE));

        return $userRegistryData;
    }

    /**
     * Returns registry data of all applications current user has access to
     * @see Tinebase_Application_Json_Abstract
     *
     * @return mixed array 'variable name' => 'data'
     * @throws Tinebase_Exception_AccessDenied
     */
    public function getAllRegistryData()
    {
        $registryData = array();
        
        if (Tinebase_Core::getUser()) {
            $userApplications = Tinebase_Core::getUser()->getApplications(/* $_anyRight */ TRUE);
            $clientConfig = Tinebase_Config::getInstance()->getClientRegistryConfig();
            
            if (Tinebase_Core::isLogLevel(Zend_Log::TRACE))
                /** @noinspection PhpUndefinedFieldInspection */
                Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
               . ' User applications to fetch registry for: ' . print_r($userApplications->name, TRUE));

            /** @noinspection PhpUndefinedFieldInspection */
            if (! in_array('Tinebase', $userApplications->name)) {
                Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' User has no permissions to run Tinebase.');
                $this->logout();
                throw new Tinebase_Exception_AccessDenied('User has no permissions to run Tinebase');
            }

            $allImportDefinitions = $this->_getImportDefinitions();
            
            foreach ($userApplications as $application) {
                $appRegistry = $this->_getAppRegistry($application, $clientConfig, $allImportDefinitions);

                $this->_logRegistrySize($appRegistry, $application->name);

                $registryData[$application->name] = $appRegistry;
            }
        } else {
            $registryData['Tinebase'] = $this->getRegistryData();
        }

        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Total registry size: ' . strlen(json_encode($registryData)));

        return $registryData;
    }

    protected function _logRegistrySize($appRegistry, $appName)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) {
            $total = 0;
            foreach ($appRegistry as $key => $value) {
                $size = strlen(json_encode($value));
                if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                    . ' Size of registry key ' . $key . ': ' . $size);
                $total += $size;
            }
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' App ' . $appName . ' total size: ' . $total);
        }

    }

    /**
     * @param Tinebase_Model_Application $application
     * @param Tinebase_Config_Struct $clientConfig
     * @param Tinebase_Record_RecordSet $allImportDefinitions
     * @return array
     */
    protected function _getAppRegistry($application, $clientConfig, $allImportDefinitions)
    {
        $appRegistry = array();
        try {
            $appRegistry['rights'] = Tinebase_Core::getUser()->getRights($application->name);
        } catch (Tinebase_Exception $te) {
            // no rights -> continue + skip app
            Tinebase_Exception::log($te);
            return [];
        }
        $appRegistry['allrights'] = Tinebase_Application::getInstance()->getAllRights($application->getId());
        $appRegistry['config'] = isset($clientConfig[$application->name])
            ? $clientConfig[$application->name]->toArray()
            : array();

        // @todo do this for all apps at once (see import definitions)
        $exportDefinitions = Tinebase_ImportExportDefinition::getInstance()->getExportDefinitionsForApplication($application);
        $appRegistry['exportDefinitions'] = array(
            'results'               => $exportDefinitions->toArray(),
            'totalcount'            => count($exportDefinitions),
        );

        $customfields = Tinebase_CustomField::getInstance()->getCustomFieldsForApplication($application);
        Tinebase_CustomField::getInstance()->resolveConfigGrants($customfields);
        $appRegistry['customfields'] = $customfields->toArray();

        // add preferences for app
        try {
            $prefRegistry = $this->_getAppPreferencesForRegistry($application);
            $appRegistry = array_merge_recursive($appRegistry, $prefRegistry);
        } catch (Tinebase_Exception_AccessDenied $tead) {
            // do not add prefs if user has no run right
        }

        $customAppRegistry = $this->_getCustomAppRegistry($application);
        if (empty($customAppRegistry)) {
            // TODO always get this from app controller (and remove from _getCustomAppRegistry)
            try {
                $appController = Tinebase_Core::getApplicationInstance($application->name);
                $models = $appController->getModels();
                $appRegistry['models'] = Tinebase_ModelConfiguration::getFrontendConfigForModels($models);
            } catch (Tinebase_Exception_AccessDenied $tead) {
                // do not add prefs if user has no run right
            }

        } else {
            $appRegistry = array_merge_recursive($appRegistry, $customAppRegistry);
        }

        if (! isset($appRegistry['importDefinitions'])) {
            $appRegistry = array_merge($appRegistry, $this->_getImportDefinitionRegistryData($allImportDefinitions, $application));
        }

        return $appRegistry;
    }

    /**
     * get app preferences for registry
     *
     * @param Tinebase_Model_Application $application
     * @return array
     * @throws Tinebase_Exception_NotFound
     */
    protected function _getAppPreferencesForRegistry(Tinebase_Model_Application $application)
    {
        $registryData = array();
        $appPrefs = Tinebase_Core::getPreference($application->name);
        if ($appPrefs !== NULL) {
            $allPrefs = $appPrefs->getAllApplicationPreferences();
            if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                . ' ' . print_r($allPrefs, TRUE));

            foreach ($allPrefs as $pref) {
                try {
                    $registryData['preferences'][$pref] = $appPrefs->{$pref};
                } catch (Exception $e) {
                    Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Could not get ' . $pref . '  preference: ' . $e);
                }
            }
        }

        return $registryData;
    }

    /**
     * get registry data from application frontend json class
     *
     * @param Tinebase_Model_Application $application
     * @return array
     * @throws Tinebase_Exception_InvalidArgument
     */
    protected function _getCustomAppRegistry(Tinebase_Model_Application $application)
    {
        $jsonAppName = $application->name . '_Frontend_Json';
        if (! class_exists($jsonAppName)) {
            return array();
        }

        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) {
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Getting registry data for app ' . $application->name);
        }

        try {
            $applicationJson = new $jsonAppName();
            $registryData = $applicationJson->getRegistryData();

        } catch (Exception $e) {
            Tinebase_Exception::log($e);
            if (! $e instanceof Tinebase_Exception_AccessDenied && ! in_array($application->name, array('Tinebase', 'Addressbook', 'Admin'))) {
                Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Disabling ' . $application->name . ': ' . $e);
                Tinebase_Application::getInstance()->setApplicationStatus(array($application->getId()), Tinebase_Application::DISABLED);
            }
            return array();
        }

        // TODO get this from app controller / modelconfig
        foreach ($applicationJson->getRelatableModels() as $relModel) {
            $registryData['relatableModels'][] = $relModel;
        }
        $registryData['models'] = $applicationJson->getModelsConfiguration();

        return $registryData;
    }
    
    /**
     * search / get custom field values
     *
     * @param  array $filter filter array
     * @param  array $paging pagination info
     * @return array
     */
    public function searchCustomFieldValues($filter, $paging)
    {
        $result = $this->_search($filter, $paging, Tinebase_CustomField::getInstance(), 'Tinebase_Model_CustomField_ValueFilter');
        return $result;
    }
    
    /************************ preferences functions ***************************/
    
    /**
     * search preferences
     *
     * @param  string $applicationName
     * @param  array  $filter json encoded
     * @return array
     */
    public function searchPreferencesForApplication($applicationName, $filter)
    {
        $decodedFilter = $this->_prepareParameter($filter);
        
        $filter = new Tinebase_Model_PreferenceFilter();
        if (! empty($decodedFilter)) {
            $filter->setFromArrayInUsersTimezone($decodedFilter);
        }
        $appId = Tinebase_Application::getInstance()->getApplicationByName($applicationName)->getId();
        $filter->addFilter($filter->createFilter(array('field'     => 'application_id',  'operator'  => 'equals', 'value'     => $appId)));
        
        $backend = Tinebase_Core::getPreference($applicationName);
        if ($backend) {
            $records = $backend->search($filter);
            
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                . ' Got ' . count($records) . ' preferences for app ' . $applicationName);
            if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
                . ' ' . print_r($records->toArray(), TRUE));
            
            $result = $this->_multipleRecordsToJson($records, $filter);
            
            // add translated labels and descriptions
            $translations = $backend->getTranslatedPreferences();
            foreach ($result as $key => $prefArray) {
                if (isset($translations[$prefArray['name']])) {
                    $result[$key] = array_merge($prefArray, $translations[$prefArray['name']]);
                } else {
                    $result[$key] = array_merge($prefArray, array('label' => $prefArray['name']));
                }
            }
            
            // sort prefs by definition
            $allPrefs = (array) $backend->getAllApplicationPreferences();
            usort($result, function($a, $b) use ($allPrefs) {
                $a = (int) array_search($a['name'], $allPrefs);
                $b = (int) array_search($b['name'], $allPrefs);
                
                if ($a == $b) {
                    return 0;
                }
                return ($a < $b) ? -1 : 1;
            });
            
        } else {
            $result = array();
        }
        
        return array(
            'results'       => $result,
            'totalcount'    => count($result)
        );
    }
    
    /**
     * save preferences for application
     *
     * @param string    $data       json encoded preferences data
     * @param bool      $adminMode  submit in admin mode?
     * @return array with the changed prefs
     *
     * @todo move saving of user values to preferences controller
     */
    public function savePreferences($data, $adminMode)
    {
        $decodedData = $this->_prepareParameter($data);
        
        $result = array();
        foreach ($decodedData as $applicationName => $data) {
            
            if ($applicationName == 'Tinebase.UserProfile') {
                $userProfileData = array();
                foreach($data as $fieldName => $valueArray) {
                    $userProfileData[$fieldName] = $valueArray['value'];
                }
                $this->updateUserProfile($userProfileData);
                
            } else {
                $backend = Tinebase_Core::getPreference($applicationName);
                if ($backend !== NULL) {
                    if ($adminMode) {
                        $backend->saveAdminPreferences($data);
                        // TODO return preference values?
                        $result = array();
                    } else {
                        // set user prefs
                        foreach ($data as $name => $value) {
                            try {
                                $backend->doSpecialJsonFrontendActions($this, $name, $value['value'], $applicationName);
                                $backend->$name = $value['value'];
                                $result[$applicationName][] = array('name' => $name, 'value' => $backend->$name);
                            } catch (Exception $e) {
                                if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE))
                                    Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' '
                                        . 'Could not save preference '. $name . ' -> ' . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }
        
        return array(
            'status'    => 'success',
            'results'   => $result
        );
    }

    /**
     * get profile of current user
     *
     * @param string $userId
     * @return array
     */
    public function getUserProfile($userId)
    {
        // NOTE: $userProfile is a contact where non readable fields are clearad out!
        $userProfile = Tinebase_UserProfile::getInstance()->get($userId);
        
        // NOTE: This hurts! We don't have methods to call in our frontends yet which convert
        //       a record to the json representaion :( Thus image link will be broken!
        $userProfile->setTimezone(Tinebase_Core::getUserTimezone());
        
        return array(
            'userProfile'      => $userProfile->toArray(),
            'readableFields'   => Tinebase_UserProfile::getInstance()->getReadableFields(),
            'updateableFields' => Tinebase_UserProfile::getInstance()->getUpdateableFields(),
        );
    }
    
    /**
     * update user profile
     *
     * @param  array $profileData
     * @return array
     */
    public function updateUserProfile($profileData)
    {
        $contact = new Addressbook_Model_Contact(array(), TRUE);
        $contact->setFromJsonInUsersTimezone($profileData);
        
        // NOTE: $userProfile is a contact where non readable fields are clearad out!
        $userProfile = Tinebase_UserProfile::getInstance()->update($contact);
        
        // NOTE: This hurts! We don't have methods to call in our frontends yet which convert
        //       a record to the json representaion :( Thus image link will be broken!
        $userProfile->setTimezone(Tinebase_Core::getUserTimezone());
        return $userProfile->toArray();
    }
    
    /**
     * dummy function to measure speed of framework initialization
     */
    public function void()
    {
        return array();
    }
    
    /**
     * gets the userProfile config
     *
     * @return @array
     */
    public function getUserProfileConfig()
    {
        return array(
            'possibleFields'   => array_values(Tinebase_UserProfile::getInstance()->getPossibleFields()),
            'readableFields'   => array_values(Tinebase_UserProfile::getInstance()->getReadableFields()),
            'updateableFields' => array_values(Tinebase_UserProfile::getInstance()->getUpdateableFields()),
        );
    }
    
    /**
     * saves userProfile config
     *
     * @param array $configData
     */
    public function setUserProfileConfig($configData)
    {
        Tinebase_UserProfile::getInstance()->setReadableFields($configData['readableFields']);
        Tinebase_UserProfile::getInstance()->setUpdateableFields($configData['updateableFields']);
    }
    
    /**
     * switch to another user's account
     * 
     * @param string $loginName
     * @return array
     */
    public function changeUserAccount($loginName)
    {
        $result = Tinebase_Controller::getInstance()->changeUserAccount($loginName);
        return array(
            'success' => $result
        );
    }
    
    /************************ department functions **************************/
    
    /**
     * search / get departments
     *
     * @param  array $filter filter array
     * @param  array $paging pagination info
     * @return array
     */
    public function searchDepartments($filter, $paging)
    {
        $result = $this->_search($filter, $paging, Tinebase_Department::getInstance(), 'Tinebase_Model_DepartmentFilter');
        return $result;
    }
    
    /************************* relation functions ***************************/
    
    /**
     * get all relations of a given record
     *
     * @param  string       $model         own model to get relations for
     * @param  string       $id            own id to get relations for
     * @param  string       $degree        only return relations of given degree
     * @param  array        $type          only return relations of given type
     * @param  string       $relatedModel  only return relations having this related model
     * @return array
     */
    public function getRelations($model, $id, $degree = NULL, $type = array(), $relatedModel = NULL)
    {
        if (! is_array($type)) {
            $type = array();
        }
        $relations = Tinebase_Relations::getInstance()->getRelations($model, 'Sql', $id, $degree, $type, false, $relatedModel);

        // @TODO we still have no converter for relations :-(
        // -> related records returned here are different to the records returned by the apps itself!
        // -> this problem also applies to to generic json converter!
        if (count($relations) > 0) {
            $relations->setTimezone(Tinebase_Core::getUserTimezone());
            $relations->bypassFilters = true;
            $result = $relations->toArray();
        } else {
            $result = array();
        }
        return array(
            'results'       => array_values($result),
            'totalcount'    => count($result),
        );
    }
    
    /************************ config functions ******************************/
    
    /**
     * get config settings for application
     *
     * @param string $id application name
     * @return array
     */
    public function getConfig($id)
    {
        $controllerName = $id . '_Controller';
        $appController = Tinebase_Controller_Abstract::getController($controllerName);
        
        return array(
            'id'        => $id,
            'settings'  => $appController->getConfigSettings(TRUE),
        );
    }
    
    /**
     * save application config
     *
     * @param array $recordData
     * @return array
     */
    public function saveConfig($recordData)
    {
        //if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($recordData, TRUE));
        
        $controllerName = $recordData['id'] . '_Controller';
        $appController = Tinebase_Controller_Abstract::getController($controllerName);
        $appController->saveConfigSettings($recordData['settings']);
        
        return $this->getConfig($recordData['id']);
    }
    
    /************************ tempFile functions ******************************/
    
    /**
     * joins all given tempfiles in given order to a single new tempFile
     *
     * @param array $tempFilesData of tempfiles arrays $tempFiles
     * @return array new tempFile
     */
    public function joinTempFiles($tempFilesData)
    {
        $tempFileRecords = new Tinebase_Record_RecordSet('Tinebase_Model_TempFile');
        foreach($tempFilesData as $tempFileData) {
            $record = new Tinebase_Model_TempFile(array(), TRUE);
            $record->setFromJsonInUsersTimezone($tempFileData);
            $tempFileRecords->addRecord($record);
        }
        
        $joinedTempFile = Tinebase_TempFile::getInstance()->joinTempFiles($tempFileRecords);
        
        return $joinedTempFile->toArray();
    }
    
    /************************ protected functions ***************************/
    
    /**
     * returns multiple records prepared for json transport
     *
     * @param Tinebase_Record_RecordSet $_records Tinebase_Record_Interface
     * @param Tinebase_Model_Filter_FilterGroup $_filter
     * @param Tinebase_Model_Pagination $_pagination
     * @return array data
     */
    protected function _multipleRecordsToJson(Tinebase_Record_RecordSet $_records, $_filter = NULL, $_pagination = NULL)
    {
        if (count($_records) == 0) {
            return array();
        }
        
        switch ($_records->getRecordClassName()) {
            case 'Tinebase_Model_Preference':
                $accountFilterArray = $_filter->getFilter('account')->toArray();
                $adminMode = ($accountFilterArray['value']['accountId'] == 0 && $accountFilterArray['value']['accountType'] == Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE);
                foreach ($_records as $record) {
                    if (! isset($app) || $record->application_id != $app->getId()) {
                        $app = Tinebase_Application::getInstance()->getApplicationById($record->application_id);
                    }
                    $preference = Tinebase_Core::getPreference($app->name, TRUE);
                    $preference->resolveOptions($record);
                    if ($record->type == Tinebase_Model_Preference::TYPE_DEFAULT || ! $adminMode && $record->type == Tinebase_Model_Preference::TYPE_ADMIN) {
                        $record->value = Tinebase_Model_Preference::DEFAULT_VALUE;
                    }
                }
                break;
        }
        
        $result = parent::_multipleRecordsToJson($_records, $_filter, $_pagination);
        return $result;
    }
    
    /**
     * return autocomplete suggestions for a given recordclass, the property and value
     *
     * @param string $appName
     * @param string $modelName
     * @param string $property
     * @param string $startswith
     * @return array
     * @throws Tinebase_Exception_InvalidArgument
     */
    public function autoComplete($appName, $modelName, $property, $startswith)
    {
        $recordClassName = $appName . '_Model_' . $modelName;
        $controller      = Tinebase_Core::getApplicationInstance($appName, $modelName);

        if (! class_exists($recordClassName)) {
            throw new Tinebase_Exception_InvalidArgument('A record class for the given appName and modelName does not exist!');
        }
        
        if (! $controller) {
            throw new Tinebase_Exception_InvalidArgument('A controller for the given appName and modelName does not exist!');
        }

        /** @var Tinebase_Model_Filter_FilterGroup $filter */
        $filter = Tinebase_Model_Filter_FilterGroup::getFilterForModel($recordClassName);
        $propFilter = $filter->createFilter(['field' => $property, 'operator' => 'startswith', 'value' => $startswith]);
        if (!$propFilter instanceof Tinebase_Model_Filter_Text && !$propFilter instanceof Tinebase_Model_Filter_Query) {
            throw new Tinebase_Exception_UnexpectedValue('bad property name');
        }
        $filter->addFilter($propFilter);

        $paging = new Tinebase_Model_Pagination(array('sort' => $property));
        
        $values = array_unique($controller->search($filter, $paging)->{$property});
        
        $result = array(
            'results'   => array(),
            'totalcount' => count($values)
        );
        
        foreach($values as $value) {
            $result['results'][] = array($property => $value);
        }
        
        return $result;
    }

    /**
     * Toogles advanced search preference
     *
     * @param $state
     * @return true
     */
    public function toogleAdvancedSearch($state)
    {
        Tinebase_Core::getPreference()->setValue(Tinebase_Preference::ADVANCED_SEARCH, (int)$state);
        return $state == Tinebase_Core::getPreference()->getValue(Tinebase_Preference::ADVANCED_SEARCH, 0);
    }

    public function restoreRevision($fileLocationSrc, $fileLocationTrgt = null)
    {
        $src = new Tinebase_Model_Tree_FileLocation($fileLocationSrc);
        $fs = Tinebase_FileSystem::getInstance();

        $transId = Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());
        // if $transId is not set to null, rollback. note the & pass-by-ref! otherwise it would not work
        $raii = (new Tinebase_RAII(function() use (&$transId) {
            if (null !== $transId) {
                Tinebase_TransactionManager::getInstance()->rollBack();
            }
        }))->setReleaseFunc(function () use (&$transId) {
            Tinebase_TransactionManager::getInstance()->commitTransaction($transId);
            $transId = null;
        });

        $node = $src->getNode();

        if (null === $fileLocationTrgt) {
            if ($node->getHighestRevision() > $node->revision && ($currentNode = $fs->get($node->getId()))->hash !==
                    $node->hash) {
                $currentNode->hash = $node->hash;
                $fs->update($currentNode);
            }
        } else {
            $trgt = new Tinebase_Model_Tree_FileLocation($fileLocationTrgt);
            $trgt->copyNodeTo($node);
        }

        $raii->release();
        return ['success' => true];
    }

    /**
     * returns the replication modification logs
     *
     * @param $sequence
     * @param int $limit
     * @return array
     * @throws Tinebase_Exception_AccessDenied
     */
    public function getReplicationModificationLogs($sequence, $limit = 100)
    {
        if (! Tinebase_Core::getUser()->hasRight('Tinebase', Tinebase_Acl_Rights::REPLICATION)) {
            throw new Tinebase_Exception_AccessDenied('you do not have access to modlogs');
        }

        $result = array(
            'results'   => Tinebase_Timemachine_ModificationLog::getInstance()->getReplicationModificationsByInstanceSeq($sequence, $limit)->toArray(),
        );
        $result['totalcount'] = count($result['results']);

        return $result;
    }

    /**
     * returns the replication modification logs
     *
     * @param $hash
     * @return array
     * @throws Tinebase_Exception_AccessDenied
     * @throws Tinebase_Exception_Backend
     * @throws Tinebase_Exception_NotFound
     */
    public function getBlob($hash)
    {
        if (! Tinebase_Core::getUser()->hasRight('Tinebase', Tinebase_Acl_Rights::REPLICATION)) {
            throw new Tinebase_Exception_AccessDenied('you do not have access to blobs');
        }

        $fileObject = new Tinebase_Model_Tree_FileObject(array('hash' => $hash), true);
        $path = $fileObject->getFilesystemPath();

        if (is_file($path)) {
            if (!($fh = fopen($path, 'r'))) {
                throw new Tinebase_Exception_Backend('could not open blob file: ' . $hash);
            }
            if (!stream_filter_append($fh, 'convert.base64-encode', STREAM_FILTER_READ)) {
                throw new Tinebase_Exception_Backend('could not append stream filter');
            }
            $data = stream_get_contents($fh);
            fclose($fh);
        } else {
            throw new Tinebase_Exception_NotFound('could not find blob: ' . $hash);
        }

        return array(
            'success' => true,
            'data'    => $data
        );
    }

    /**
     * @return array
     */
    public function reportPresence()
    {
        Tinebase_Presence::getInstance()->reportPresence();

        return array(
            'success' => true
        );
    }

    /**
     * @param string $filter
     * @param string $pagination
     * @return array
     * @throws Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_SystemGeneric
     */
    public function searchPaths($filter = null, $pagination = null)
    {
        if (!Tinebase_Config::getInstance()->featureEnabled(Tinebase_Config::FEATURE_SEARCH_PATH)) {
            throw new Tinebase_Exception_SystemGeneric('paths are not activated in this installation');
        }
        if (null !== $filter) {
            $tmpFilter = $this->_decodeFilter($filter, Tinebase_Model_PathFilter::class);
            foreach (['path', 'query', 'shadow_path'] as $field) {
                /** @var Tinebase_Model_Filter_Abstract $f */
                foreach ($tmpFilter->getFilter($field, true, true) as $f) {
                    if (empty($f->getValue())) {
                        $tmpFilter->removeFilter($f, true);
                    }
                }
            }

            $filter = $tmpFilter->toArray();
        }
        return $this->_search($filter, $pagination, Tinebase_Record_Path::getInstance(),
            Tinebase_Model_PathFilter::class);
    }
}
