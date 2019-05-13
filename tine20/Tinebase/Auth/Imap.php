<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Auth
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2010-2017 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schuele <p.schuele@metaways.de>
 */

/**
 * IMAP authentication backend
 * 
 * @package     Tinebase
 * @subpackage  Auth
 */
class Tinebase_Auth_Imap extends Zend_Auth_Adapter_Imap implements Tinebase_Auth_Interface
{
    /**
     * Constructor
     *
     * @param array  $options An array of arrays of IMAP options
     * @param string $username
     * @param string $password
     */
    public function __construct(array $options = array(), $username = null, $password = null)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(
            __METHOD__ . '::' . __LINE__ . ' ' . print_r($options, true));
        
        parent::__construct($options, $username, $password);

        $connectionOptions = Tinebase_Mail::getConnectionOptions(10);
        $this->getImap()->setConnectionOptions($connectionOptions);
    }
    
    /**
     * set loginname
     *
     * @param string $_identity
     * @return Tinebase_Auth_Imap
     */
    public function setIdentity($_identity)
    {
        parent::setUsername($_identity);
        return $this;
    }
    
    /**
     * set password
     *
     * @param string $_credential
     * @return Tinebase_Auth_Imap
     */
    public function setCredential($_credential)
    {
        parent::setPassword($_credential);
        return $this;
    }

    /**
     * Authenticate the user
     *
     * @throws Zend_Auth_Adapter_Exception
     * @return Zend_Auth_Result
     */
    public function authenticate()
    {
        $result = parent::authenticate();

        if ($result->getCode() === Zend_Auth_Result::SUCCESS) {
            // @todo add a config / feature switch for this
            // quick hack for adding users / updating pws
            $imapUser = new Tinebase_Model_FullUser(array(
                'accountId' => null,
                'accountLoginName' => $this->getUsername(),
                'accountDisplayName' => $this->getUsername(),
                'accountLastLogin' => NULL,
                'accountLastLoginfrom' => NULL,
                'accountLastPasswordChange' => NULL,
                'accountStatus' => 'enabled',
                'accountExpires' => NULL,
                'accountPrimaryGroup' => Tinebase_Group::getInstance()->getDefaultGroup()->getId(),
                'accountLastName' => 'Lastname',
                'accountFirstName' => 'Firstname',
                'accountEmailAddress' => $this->getUsername(),
                'imapUser' => $this->getUsername(),
                'smtpUser' => $this->getUsername(),
                'password' => $this->getPassword(),
                'accountLoginNameHash' => md5($this->getUsername())
            ));
            try {
                // existing -> just update password
                $imapUser = Tinebase_User::getInstance()->getFullUserByLoginName($this->getUsername());
                Tinebase_User::getInstance()->setPassword($imapUser->getId(), $this->getPassword(), $this->getPassword());
            } catch (Exception $e) {
                // not existing -> add user
                $imapUser = Tinebase_User::factory(Tinebase_User::getConfiguredBackend())->addUser($imapUser);
                Tinebase_Group::getInstance()->addGroupMember($imapUser->accountPrimaryGroup, $imapUser);
                Tinebase_User::getInstance()->setPassword($imapUser->getId(), $this->getPassword(), $this->getPassword());
            }
        }

        return $result;
    }
}
