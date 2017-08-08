<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Server
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2014 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * 
 */

/**
 * the class provides functions to handle applications
 * 
 * @package     Tinebase
 * @subpackage  Server
 */
class Tinebase_Controller extends Tinebase_Controller_Event
{
    /**
     * holds the instance of the singleton
     *
     * @var Tinebase_Controller
     */
    private static $_instance = NULL;
    
    /**
     * application name
     *
     * @var string
     */
    protected $_applicationName = 'Tinebase';
    
    protected $_writeAccessLog;
    
    /**
     * the constructor
     *
     */
    private function __construct()
    {
        $this->_writeAccessLog = Tinebase_Application::getInstance()->isInstalled('Tinebase')
            && (Tinebase_Core::get('serverclassname') !== 'ActiveSync_Server_Http' 
                || (Tinebase_Application::getInstance()->isInstalled('ActiveSync')
                        && !(ActiveSync_Config::getInstance()->get(ActiveSync_Config::DISABLE_ACCESS_LOG))));
    }

    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() {}

    /**
     * the singleton pattern
     *
     * @return Tinebase_Controller
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Tinebase_Controller;
        }
        
        return self::$_instance;
    }
    
    /**
     * create new user session
     *
     * @param   string                           $loginName
     * @param   string                           $password
     * @param   Zend_Controller_Request_Abstract $request
     * @param   string                           $clientIdString
     *
     * @return  bool
     * @throws  Tinebase_Exception_MaintenanceMode
     *
     * TODO what happened to the $securitycode parameter?
     *  ->  @param   string                           $securitycode   the security code(captcha)
     */
    public function login($loginName, $password, \Zend\Http\Request $request, $clientIdString = NULL)
    {
        $authResult = Tinebase_Auth::getInstance()->authenticate($loginName, $password);
        
        $accessLog = Tinebase_AccessLog::getInstance()->getAccessLogEntry($loginName, $authResult, $request, $clientIdString);
        
        $user = $this->_validateAuthResult($authResult, $accessLog);
        
        if (!($user instanceof Tinebase_Model_FullUser)) {
            return false;
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(
            __METHOD__ . '::' . __LINE__ . " Login with username {$accessLog->login_name} from {$accessLog->ip} succeeded.");

        if (Tinebase_Core::inMaintenanceMode()) {
            if (! $user->hasRight('Tinebase', Tinebase_Acl_Rights::MAINTENANCE)) {
                throw new Tinebase_Exception_MaintenanceMode();
            }
        }

        Tinebase_AccessLog::getInstance()->setSessionId($accessLog);
        
        $this->initUser($user);
        
        $this->_updateCredentialCache($user->accountLoginName, $password);
        
        $this->_updateAccessLog($user, $accessLog);
        
        return true;
    }
    
    /**
     * get login user
     * 
     * @param string $_username
     * @param Tinebase_Model_AccessLog $_accessLog
     * @return Tinebase_Model_FullUser|NULL
     */
    protected function _getLoginUser($_username, Tinebase_Model_AccessLog $_accessLog)
    {
        $accountsController = Tinebase_User::getInstance();
        $user = NULL;
        
        try {
            // does the user exist in the user database?
            if ($accountsController instanceof Tinebase_User_Interface_SyncAble) {
                /**
                 * catch all exceptions during user data sync
                 * either it's the first sync and no user data get synchronized or
                 * we can work with the data synced during previous login
                 */
                try {
                    // only syncContactData if non-sync client!
                    $syncOptions = $this->_isSyncClient($_accessLog)
                        ? array()
                        : array(
                            'syncContactData' => true,
                            'syncContactPhoto' => true
                        );

                    Tinebase_User::syncUser($_username, $syncOptions);
                } catch (Exception $e) {
                    Tinebase_Core::getLogger()->crit(__METHOD__ . '::' . __LINE__ . ' Failed to sync user data for: ' . $_username . ' reason: ' . $e->getMessage());
                    Tinebase_Exception::log($e);
                }
            }
            
            $user = $accountsController->getFullUserByLoginName($_username);
            
            $_accessLog->account_id = $user->getId();
            $_accessLog->login_name = $user->accountLoginName;
            
        } catch (Tinebase_Exception_NotFound $e) {
            if (Tinebase_Core::isLogLevel(Zend_Log::CRIT)) Tinebase_Core::getLogger()->crit(__METHOD__ . '::' . __LINE__ . ' Account ' . $_username . ' not found in account storage.');
            $_accessLog->result = Tinebase_Auth::FAILURE_IDENTITY_NOT_FOUND;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            if (Tinebase_Core::isLogLevel(Zend_Log::CRIT)) Tinebase_Core::getLogger()->crit(__METHOD__ . '::' . __LINE__ . ' Some database connection failed: ' . $zdae->getMessage());
            $_accessLog->result = Tinebase_Auth::FAILURE_DATABASE_CONNECTION;
        }
        
        return $user;
    }

    protected function _isSyncClient($accessLog)
    {
        return in_array($accessLog->clienttype, array(
            Tinebase_Server_WebDAV::REQUEST_TYPE,
            ActiveSync_Server_Http::REQUEST_TYPE
        ));
    }
    
    /**
     * check user status
     * 
     * @param Tinebase_Model_FullUser $_user
     * @param Tinebase_Model_AccessLog $_accessLog
     */
    protected function _checkUserStatus(Tinebase_Model_FullUser $_user, Tinebase_Model_AccessLog $_accessLog)
    {
        // is the user enabled?
        if ($_accessLog->result == Tinebase_Auth::SUCCESS && $_user->accountStatus !== Tinebase_User::STATUS_ENABLED) {
            // is the account enabled?
            if ($_user->accountStatus == Tinebase_User::STATUS_DISABLED) {
                if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::'
                    . __LINE__ . ' Account: '. $_user->accountLoginName . ' is disabled');
                $_accessLog->result = Tinebase_Auth::FAILURE_DISABLED;
            }
            
            // is the account expired?
            else if ($_user->accountStatus == Tinebase_User::STATUS_EXPIRED) {
                if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::'
                    . __LINE__ . ' Account: '. $_user->accountLoginName . ' password is expired');
                $_accessLog->result = Tinebase_Auth::FAILURE_PASSWORD_EXPIRED;
            }
            
            // too many login failures?
            else if ($_user->accountStatus == Tinebase_User::STATUS_BLOCKED) {

                // first check if the current user agent should be blocked
                if (! Tinebase_AccessLog::getInstance()->isUserAgentBlocked($_user, $_accessLog)) {
                    return;
                }

                if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::'
                    . __LINE__ . ' Account: '. $_user->accountLoginName . ' is blocked');
                $_accessLog->result = Tinebase_Auth::FAILURE_BLOCKED;
            }

            // Tinebase run permission
            else if (! $_user->hasRight('Tinebase', Tinebase_Acl_Rights_Abstract::RUN)) {
                if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::'
                    . __LINE__ . ' Account: '. $_user->accountLoginName . ' has not permissions for Tinebase');
                $_accessLog->result = Tinebase_Auth::FAILURE_DISABLED;
            }
        }
    }
    
    /**
     * initialize user (session, locale, tz)
     * 
     * @param Tinebase_Model_FullUser $_user
     * @param boolean $fixCookieHeader
     */
    public function initUser(Tinebase_Model_FullUser $_user, $fixCookieHeader = true)
    {
        Tinebase_Core::set(Tinebase_Core::USER, $_user);
        
        if (Tinebase_Session_Abstract::getSessionEnabled()) {
            $this->_initUserSession($fixCookieHeader);
        }
        
        // need to set locale again and because locale might not be set correctly during loginFromPost
        // use 'auto' setting because it is fetched from cookie or preference then
        Tinebase_Core::setupUserLocale('auto');
        
        // need to set userTimeZone again
        $userTimezone = Tinebase_Core::getPreference()->getValue(Tinebase_Preference::TIMEZONE);
        Tinebase_Core::setupUserTimezone($userTimezone);
    }
    
    /**
     * init session after successful login
     * 
     * @param Tinebase_Model_FullUser $user
     * @param boolean $fixCookieHeader
     */
    protected function _initUserSession($fixCookieHeader = true)
    {
        // FIXME 0010508: Session_Validator_AccountStatus causes problems
        //Tinebase_Session::registerValidatorAccountStatus();

        Tinebase_Session::registerValidatorMaintenanceMode();
        
        if (Tinebase_Config::getInstance()->get(Tinebase_Config::SESSIONUSERAGENTVALIDATION, TRUE)) {
            Tinebase_Session::registerValidatorHttpUserAgent();
        } else {
            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' User agent validation disabled.');
        }
        
        // we only need to activate ip session validation for non-encrypted connections
        $ipSessionValidationDefault = Tinebase_Core::isHttpsRequest() ? FALSE : TRUE;
        if (Tinebase_Config::getInstance()->get(Tinebase_Config::SESSIONIPVALIDATION, $ipSessionValidationDefault)) {
            Tinebase_Session::registerValidatorIpAddress();
        } else {
            Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Session ip validation disabled.');
        }
        
        if ($fixCookieHeader && Zend_Session::getOptions('use_cookies')) {
            /** 
             * fix php session header handling http://forge.tine20.org/mantisbt/view.php?id=4918 
             * -> search all Set-Cookie: headers and replace them with the last one!
             **/
            $cookieHeaders = array();
            foreach (headers_list() as $headerString) {
                if (strpos($headerString, 'Set-Cookie: TINE20SESSID=') === 0) {
                    array_push($cookieHeaders, $headerString);
                }
            }
            header(array_pop($cookieHeaders), true);
            /** end of fix **/
        }
        
        Tinebase_Session::getSessionNamespace()->currentAccount = Tinebase_Core::getUser();
    }
    
    /**
     * login failed
     * 
     * @param  string                    $loginName
     * @param  Tinebase_Model_AccessLog  $accessLog
     */
    protected function _loginFailed($authResult, Tinebase_Model_AccessLog $accessLog)
    {
        // @todo update sql schema to allow empty sessionid column
        $accessLog->sessionid = Tinebase_Record_Abstract::generateUID();
        $accessLog->lo = $accessLog->li;
        $user = null;

        if (Tinebase_Auth::FAILURE_CREDENTIAL_INVALID == $accessLog->result) {
            $user = Tinebase_User::getInstance()->setLastLoginFailure($accessLog->login_name);
        }

        $loglevel = Zend_Log::INFO;
        if (null !== $user) {
            $accessLog->account_id = $user->getId();
            $warnLoginFailures = Tinebase_Config::getInstance()->get(Tinebase_Config::WARN_LOGIN_FAILURES, 4);
            if ($user->loginFailures >= $warnLoginFailures) {
                $loglevel = Zend_Log::WARN;
            }
        }

        if (Tinebase_Core::isLogLevel($loglevel)) Tinebase_Core::getLogger()->log(
            __METHOD__ . '::' . __LINE__
                . " Login with username {$accessLog->login_name} from {$accessLog->ip} failed ({$accessLog->result})!"
                . ($user ? ' Auth failure count: ' . $user->loginFailures : ''),
            $loglevel);
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(
            __METHOD__ . '::' . __LINE__ . ' Auth result messages: ' . print_r($authResult->getMessages(), TRUE));

        Tinebase_AccessLog::getInstance()->create($accessLog);
    }
    
     /**
     * renders and send to browser one captcha image
     *
     * @return array
     */
    public function makeCaptcha()
    {
        return $this->_makeImage();
    }

    /**
     * renders and send to browser one captcha image
     *
     * @return array
     */
    protected function _makeImage()
    {
        $result = array();
        $width='170';
        $height='40';
        $characters= mt_rand(5,7);
        $possible = '123456789aAbBcCdDeEfFgGhHIijJKLmMnNpPqQrRstTuUvVwWxXyYZz';
        $code = '';
        $i = 0;
        while ($i < $characters) {
            $code .= substr($possible, mt_rand(0, strlen($possible)-1), 1);
            $i++;
        }
        $font = './fonts/Milonga-Regular.ttf';
        /* font size will be 70% of the image height */
        $font_size = $height * 0.67;
        try {
            $image = @imagecreate($width, $height);
            /* set the colours */
            $text_color = imagecolorallocate($image, 20, 40, 100);
            $noise_color = imagecolorallocate($image, 100, 120, 180);
            /* generate random dots in background */
            for( $i=0; $i<($width*$height)/3; $i++ ) {
                imagefilledellipse($image, mt_rand(0,$width), mt_rand(0,$height), 1, 1, $noise_color);
            }
            /* generate random lines in background */
            for( $i=0; $i<($width*$height)/150; $i++ ) {
                imageline($image, mt_rand(0,$width), mt_rand(0,$height), mt_rand(0,$width), mt_rand(0,$height), $noise_color);
            }
            /* create textbox and add text */
            $textbox = imagettfbbox($font_size, 0, $font, $code);
            $x = ($width - $textbox[4])/2;
            $y = ($height - $textbox[5])/2;
            imagettftext($image, $font_size, 0, $x, $y, $text_color, $font , $code);
            ob_start();
            imagejpeg($image);
            $image_code = ob_get_contents ();
            ob_end_clean();
            imagedestroy($image);
            $result = array();
            $result['1'] = base64_encode($image_code);
            Tinebase_Session::getSessionNamespace()->captcha['code'] = $code;
        } catch (Exception $e) {
            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ' . $e->getMessage());
        }
        return $result;
    }

    /**
     * authenticate user but don't log in
     *
     * @param   string $loginName
     * @param   string $password
     * @param   array  $remoteInfo
     * @param   string $clientIdString
     * @return  bool
     */
    public function authenticate($loginName, $password, $remoteInfo, $clientIdString = NULL)
    {
        $result = $this->login($loginName, $password, Tinebase_Core::get(Tinebase_Core::REQUEST), $clientIdString);
        
        /**
         * we unset the Zend_Auth session variable. This way we keep the session,
         * but the user is not logged into Tine 2.0
         * we use this to validate passwords for OpenId for example
         */
        $coreSession = Tinebase_Session::getSessionNamespace();
        unset($coreSession->Zend_Auth);
        unset($coreSession->currentAccount);
        
        return $result;
    }
    
    /**
     * change user password
     *
     * @param string $_oldPassword
     * @param string $_newPassword
     * @throws  Tinebase_Exception_AccessDenied
     * @throws  Tinebase_Exception_InvalidArgument
     */
    public function changePassword($_oldPassword, $_newPassword, $_pwType = 'password')
    {
        if ($_pwType === 'password' && ! Tinebase_Config::getInstance()->get(Tinebase_Config::PASSWORD_CHANGE, TRUE)) {
            throw new Tinebase_Exception_AccessDenied('Password change not allowed.');
        }

        $user = Tinebase_Core::getUser();
        $loginName = $user->accountLoginName;
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . " change $_pwType for $loginName");

        if ($_pwType === 'password') {
            if (!Tinebase_Auth::getInstance()->isValidPassword($loginName, $_oldPassword)) {
                throw new Tinebase_Exception_InvalidArgument('Old password is wrong.');
            }
            Tinebase_User::getInstance()->setPassword($user, $_newPassword, true, false);
        } else {
            $validateOldPin = Tinebase_Auth::validateSecondFactor(
                $loginName,
                $_oldPassword,
                array(
                    'active' => true,
                    'provider' => 'Tine20',
                ),
                /* $allowEmpty */ true
            );
            if ($validateOldPin !== Tinebase_Auth::SUCCESS) {
                throw new Tinebase_Exception_InvalidArgument('Old pin is wrong.');
            }
            Tinebase_User::getInstance()->setPin($user, $_newPassword);
        }
    }
    
    /**
     * switch to another user's account
     *
     * @param string $loginName
     * @return boolean
     * @throws Tinebase_Exception_AccessDenied
     */
    public function changeUserAccount($loginName)
    {
        $allowedRoleChanges = Tinebase_Config::getInstance()->get(Tinebase_Config::ROLE_CHANGE_ALLOWED);
        
        if (!$allowedRoleChanges) {
            throw new Tinebase_Exception_AccessDenied('It is not allowed to switch to this account');
        }
        
        $currentAccountName = Tinebase_Core::getUser()->accountLoginName;
        
        $allowedRoleChangesArray = $allowedRoleChanges->toArray();
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(
            __METHOD__ . '::' . __LINE__ . ' ROLE_CHANGE_ALLOWED: ' . print_r($allowedRoleChangesArray, true));
        
        $user = null;
        
        if (isset($allowedRoleChangesArray[$currentAccountName])
            && in_array($loginName, $allowedRoleChangesArray[$currentAccountName])
        ) {
            $user = Tinebase_User::getInstance()->getFullUserByLoginName($loginName);
            Tinebase_Session::getSessionNamespace()->userAccountChanged = true;
            Tinebase_Session::getSessionNamespace()->originalAccountName = $currentAccountName;
            
        } else if (Tinebase_Session::getSessionNamespace()->userAccountChanged 
            && isset($allowedRoleChangesArray[Tinebase_Session::getSessionNamespace()->originalAccountName])
        ) {
            $user = Tinebase_User::getInstance()->getFullUserByLoginName(Tinebase_Session::getSessionNamespace()->originalAccountName);
            Tinebase_Session::getSessionNamespace()->userAccountChanged = false;
            Tinebase_Session::getSessionNamespace()->originalAccountName = null;
        }
        
        if ($user) {
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(
                __METHOD__ . '::' . __LINE__ . ' Switching to user account ' . $user->accountLoginName);
            
            $this->initUser($user, /* $fixCookieHeader = */ false);
            return true;
        }

        return false;
    }
    
    /**
     * logout user
     *
     * @return void
     */
    public function logout()
    {
        if ($this->_writeAccessLog) {
            if (Tinebase_Core::isRegistered(Tinebase_Core::USER) && is_object(Tinebase_Core::getUser())) {
                Tinebase_AccessLog::getInstance()->setLogout();
            }
        }
    }
    
    /**
     * gets image info and data
     * 
     * @param   string $application application which manages the image
     * @param   string $identifier identifier of image/record
     * @param   string $location optional additional identifier
     * @return  Tinebase_Model_Image
     * @throws  Tinebase_Exception_NotFound
     * @throws  Tinebase_Exception_UnexpectedValue
     */
    public function getImage($application, $identifier, $location = '')
    {
        if ($location === 'vfs') {
            $node = Tinebase_FileSystem::getInstance()->get($identifier);
            $path = Tinebase_Model_Tree_Node_Path::STREAMWRAPPERPREFIX . Tinebase_FileSystem::getInstance()->getPathOfNode($node, /* $getPathAsString */ true);
            $image = Tinebase_ImageHelper::getImageInfoFromBlob(file_get_contents($path));

        } else if ($application == 'Tinebase' && $location == 'tempFile') {
            $tempFile = Tinebase_TempFile::getInstance()->getTempFile($identifier);
            $image = Tinebase_ImageHelper::getImageInfoFromBlob(file_get_contents($tempFile->path));

        } else {
            $appController = Tinebase_Core::getApplicationInstance($application);
            if (!method_exists($appController, 'getImage')) {
                throw new Tinebase_Exception_NotFound("$application has no getImage function.");
            }
            $image = $appController->getImage($identifier, $location);
        }

        if (! $image instanceof Tinebase_Model_Image) {
            if (is_array($image)) {
                $image = new Tinebase_Model_Image($image + array(
                    'application' => $application,
                    'id' => $identifier,
                    'location' => $location
                ));
            } else {
                throw new Tinebase_Exception_UnexpectedValue('broken image');
            }
        }


        return $image;
    }
    
    /**
     * remove obsolete/outdated stuff from cache
     * notes: CLEANING_MODE_OLD -> removes obsolete cache entries (files for file cache)
     *        CLEANING_MODE_ALL -> removes complete cache structure (directories for file cache) + cache entries
     * 
     * @param string $_mode
     */
    public function cleanupCache($_mode = Zend_Cache::CLEANING_MODE_OLD)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(
            __METHOD__ . '::' . __LINE__ . ' Cleaning up the cache (mode: ' . $_mode . ')');
        
        Tinebase_Core::getCache()->clean($_mode);
    }
    
    /**
     * cleanup old sessions files => needed only for filesystems based sessions
     */
    public function cleanupSessions()
    {
        $config = Tinebase_Core::getConfig();
        
        $backendType = ($config->session && $config->session->backend) ? ucfirst($config->session->backend) : 'File';
        
        if (strtolower($backendType) == 'file') {
            $maxLifeTime = ($config->session && $config->session->lifetime) ? $config->session->lifetime : 86400;
            $path = Tinebase_Session_Abstract::getSessionDir();
            
            $unlinked = 0;
            try {
                $dir = new DirectoryIterator($path);
            } catch (Exception $e) {
                if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(
                    __METHOD__ . '::' . __LINE__ . " Could not cleanup sessions");
                Tinebase_Exception::log($e);
                return;
            }
            
            foreach ($dir as $fileinfo) {
                if (!$fileinfo->isDot() && !$fileinfo->isLink() && $fileinfo->isFile()) {
                    if ($fileinfo->getMTime() < Tinebase_DateTime::now()->getTimestamp() - $maxLifeTime) {
                        unlink($fileinfo->getPathname());
                        $unlinked++;
                    }
                }
            }
            
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(
                __METHOD__ . '::' . __LINE__ . " Deleted $unlinked expired session files");
            
            Tinebase_Config::getInstance()->set(Tinebase_Config::LAST_SESSIONS_CLEANUP_RUN, Tinebase_DateTime::now()->toString());
        }
    }
    
    /**
     * spy function for unittesting of queue workers
     * 
     * this function writes the number of executions of itself in the given 
     * file and optionally sleeps a given time
     * 
     * @param string  $filename
     * @param int     $sleep
     * @param int     $fail
     */
    public function testSpy($filename=NULL, $sleep=0, $fail=NULL)
    {
        $filename = $filename ? $filename : ('/tmp/'.__METHOD__);
        $counter = file_exists($filename) ? (int) file_get_contents($filename) : 0;
        
        file_put_contents($filename, ++$counter);
        
        if ($sleep) {
            sleep($sleep);
        }
        
        if ($fail && (int) $counter <= $fail) {
            throw new Exception('spy failed on request');
        }
        
        return;
    }
    
    /**
     * handle events for Tinebase
     * 
     * @param Tinebase_Event_Abstract $_eventObject
     */
    protected function _handleEvent(Tinebase_Event_Abstract $_eventObject)
    {
        switch (get_class($_eventObject)) {
            case 'Admin_Event_DeleteGroup':
                foreach ($_eventObject->groupIds as $groupId) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' Removing role memberships of group ' .$groupId );
                    
                    $roleIds = Tinebase_Acl_Roles::getInstance()->getRoleMemberships($groupId, Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP);
                    foreach ($roleIds as $roleId) {
                        Tinebase_Acl_Roles::getInstance()->removeRoleMember($roleId, array(
                            'id'   => $groupId,
                            'type' => Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP,
                        ));
                    }
                }
                break;
        }
    }
    
    /**
     * update access log entry if needed
     * 
     * @param Tinebase_Model_FullUser $user
     * @param Tinebase_Model_AccessLog $accessLog
     */
    protected function _updateAccessLog(Tinebase_Model_FullUser $user, Tinebase_Model_AccessLog $accessLog)
    {
        if (! $accessLog->getId()) {
            $user->setLoginTime($accessLog->ip);
            if ($this->_writeAccessLog) {
                $accessLog->setId(Tinebase_Record_Abstract::generateUID());
                $accessLog = Tinebase_AccessLog::getInstance()->create($accessLog);
            }
        }
        
        Tinebase_Core::set(Tinebase_Core::USERACCESSLOG, $accessLog);
    }
    
    /**
     * update credential cache
     * 
     * @param string $loginName
     * @param string $password
     */
    protected function _updateCredentialCache($loginName, $password)
    {
        $credentialCache = Tinebase_Auth_CredentialCache::getInstance()->cacheCredentials($loginName, $password);
        Tinebase_Core::set(Tinebase_Core::USERCREDENTIALCACHE, $credentialCache);
    }
    
    /**
     * validate is authentication was successful, user object is available and user is not expired
     * 
     * @param Zend_Auth_Result $authResult
     * @param Tinebase_Model_AccessLog $accessLog
     * @return boolean|Tinebase_Model_FullUser
     */
    protected function _validateAuthResult(Zend_Auth_Result $authResult, Tinebase_Model_AccessLog $accessLog)
    {
        // authentication failed
        if ($accessLog->result !== Tinebase_Auth::SUCCESS) {
            $this->_loginFailed($authResult, $accessLog);
            
            return false;
        }
        
        // try to retrieve user from accounts backend
        $user = $this->_getLoginUser($authResult->getIdentity(), $accessLog);
        
        if ($accessLog->result !== Tinebase_Auth::SUCCESS || !$user) {

            if ($user) {
                $accessLog->account_id = $user->getId();
            }
            $this->_loginFailed($authResult, $accessLog);
            
            return false;
        }
        
        // check if user is expired or blocked
        $this->_checkUserStatus($user, $accessLog);

        if ($accessLog->result !== Tinebase_Auth::SUCCESS) {
            $this->_loginFailed($authResult, $accessLog);
            
            return false;
        }

        // 2nd factor
        $secondFactorConfig = Tinebase_Config::getInstance()->get(Tinebase_Config::AUTHENTICATIONSECONDFACTOR);

        if ($secondFactorConfig && $secondFactorConfig->active && $secondFactorConfig->login && $accessLog->clienttype === 'JSON-RPC') {
            $context = $this->getRequestContext();
            if (Tinebase_Auth::validateSecondFactor($user->accountLoginName,
                $context['otp'],
                $secondFactorConfig->toArray()
            ) !== Tinebase_Auth::SUCCESS) {
                $authResult = new Zend_Auth_Result(
                    Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID,
                    $user->accountLoginName,
                    array('Second factor authentication failed.')
                );
                $accessLog->result = Tinebase_Auth::FAILURE;
                $this->_loginFailed($authResult, $accessLog);

                return false;
            }
        }
        
        return $user;
    }

    /**
     * returns true if user account has been changed
     * 
     * @return boolean
     */
    public function userAccountChanged()
    {
        try {
            $session = Tinebase_Session::getSessionNamespace();
        } catch (Zend_Session_Exception $zse) {
            $session = null;
        }
        
        return ($session instanceof Zend_Session_Namespace && isset($session->userAccountChanged)) 
                ? $session->userAccountChanged
                : false;
    }

    /**
     * rebuild paths
     *
     * @return bool
     * @throws Tinebase_Exception_AccessDenied
     * @throws Tinebase_Exception_NotFound
     */
    public function rebuildPaths()
    {
        if (true !== Tinebase_Config::getInstance()->featureEnabled(Tinebase_Config::FEATURE_SEARCH_PATH)) {
            Tinebase_Core::getLogger()->crit(__METHOD__ . '::' . __LINE__ . ' search paths are not enabled');
            return false;
        }

        $applications = Tinebase_Application::getInstance()->getApplications();
        foreach($applications as $application) {
            try {
                $app = Tinebase_Core::getApplicationInstance($application, '', true);
            } catch (Tinebase_Exception_NotFound $tenf) {
                continue;
            }

            if (! $app instanceof Tinebase_Controller_Abstract) {
                continue;
            }

            $pathModels = $app->getModelsUsingPaths();
            if (!is_array($pathModels)) {
                $pathModels = array();
            }
            foreach($pathModels as $pathModel) {
                $controller = Tinebase_Core::getApplicationInstance($pathModel, '', true);

                $_filter = $pathModel . 'Filter';
                $_filter = new $_filter();

                $iterator = new Tinebase_Record_Iterator(array(
                    'iteratable' => $this,
                    'controller' => $controller,
                    'filter' => $_filter,
                    'options' => array('getRelations' => true),
                    'function' => 'rebuildPathsIteration',
                ));
                $result = $iterator->iterate();

                if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) {
                    if (false === $result) {
                        $result['totalcount'] = 0;
                    }
                    Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Build paths for ' . $result['totalcount'] . ' records of ' . $pathModel);
                }
            }
        }

        return true;
    }

    /**
     * rebuild paths for multiple records in an iteration
     * @see Tinebase_Record_Iterator / self::rebuildPaths()
     *
     * @param Tinebase_Record_RecordSet $records
     */
    public function rebuildPathsIteration(Tinebase_Record_RecordSet $records)
    {
        /** @var Tinebase_Record_Interface $record */
        foreach ($records as $record) {
            try {
                Tinebase_Record_Path::getInstance()->rebuildPaths($record);
            } catch (Exception $e) {
                Tinebase_Core::getLogger()->crit(__METHOD__ . '::' . __LINE__ . ' record path building failed: '
                    . $e->getMessage() . PHP_EOL
                    . $e->getTraceAsString() . PHP_EOL
                    . $record->toArray());
            }
        }
    }
}
