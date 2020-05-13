<?php
/**
 * Tine 2.0
 *
 * @package     Felamimail
 * @subpackage  Sieve
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Paul Mehrer <p.mehrer@metaways.de>
 * @copyright   Copyright (c) 2019-2019 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * class to manage addressbook list shared email accounts sieve rules
 *
 * @package     Felamimail
 * @subpackage  Sieve
 */
class Felamimail_Sieve_AdbList
{
    protected $_allowExternal = false;
    protected $_allowOnlyGroupMembers = false;
    protected $_keepCopy = false;
    protected $_forwardOnlySystem = false;
    protected $_receiverList = [];

    public function __toString()
    {
        $result = 'require ["envelope", "copy", "reject"];' . PHP_EOL;
        $rejectMsg = Felamimail_Config::getInstance()->{Felamimail_Config::SIEVE_MAILINGLIST_REJECT_REASON};
        $translation = Tinebase_Translation::getTranslation('Felamimail');
        $rejectMsg = $translation->_($rejectMsg);

        if ($this->_allowExternal) {
            $this->_addRecieverList($result);
            if (!$this->_keepCopy) {
                $result .= 'discard;' . PHP_EOL;
            }

        } else {
            if ($this->_allowOnlyGroupMembers && !empty($this->_receiverList)) {
                $result .= 'if address :is :all "from" ["' . join('","', $this->_receiverList) . '"] {' . PHP_EOL;
            } else {
                // only internal email addresses are allowed to mail!
                if (empty($internalDomains = Tinebase_EmailUser::getAllowedDomains())) {
                    throw new Tinebase_Exception_UnexpectedValue('allowed domains list is empty');
                }
                $result .= 'if address :is :domain "from" ["' . join('","', $internalDomains) . '"] {' . PHP_EOL;
            }

            $this->_addRecieverList($result);

            // we keep msg by default, only if the condition was not met we discard?
            // always discard non-allowed msgs?!?
            $result .= '} else { reject "' . $rejectMsg . '"; }' . PHP_EOL;
        }

        return $result;
    }

    protected function _addRecieverList(&$result)
    {
        if ($this->_forwardOnlySystem && empty($internalDomains = Tinebase_EmailUser::getAllowedDomains())) {
            throw new Tinebase_Exception_UnexpectedValue('allowed domains list is empty');
        }

        foreach ($this->_receiverList as $email) {
            if ($this->_forwardOnlySystem) {
                $match = false;
                foreach ($internalDomains as $domain) {
                    if (preg_match('/@' . preg_quote($domain, '/') . '$/', $email)) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    continue;
                }
            }
            $result .= 'redirect :copy "' . $email . '";' . PHP_EOL;
        }
    }

    /**
     * @param Addressbook_Model_List $_list
     * @return Felamimail_Sieve_AdbList
     */
    static public function createFromList(Addressbook_Model_List $_list)
    {
        $sieveRule = new self();

        $oldAcl = Addressbook_Controller_Contact::getInstance()->doContainerACLChecks(false);
        $raii = new Tinebase_RAII(function() use($oldAcl) {
            Addressbook_Controller_Contact::getInstance()->doContainerACLChecks($oldAcl);
        });

        $sieveRule->_receiverList = array_filter(array_keys(Addressbook_Controller_Contact::getInstance()->search(
            new Addressbook_Model_ContactFilter([
                ['field' => 'id', 'operator' => 'in', 'value' => $_list->members],
                ['field' => 'showDisabled', 'operator' => 'equals', 'value' => false],
            ]), null, false, ['email'])));

        // for unused variable check
        unset($raii);

        if (isset($_list->xprops()[Addressbook_Model_List::XPROP_SIEVE_KEEP_COPY]) && $_list
                ->xprops()[Addressbook_Model_List::XPROP_SIEVE_KEEP_COPY]) {
            $sieveRule->_keepCopy = true;
        }

        if (isset($_list->xprops()[Addressbook_Model_List::XPROP_SIEVE_ALLOW_EXTERNAL]) && $_list
                ->xprops()[Addressbook_Model_List::XPROP_SIEVE_ALLOW_EXTERNAL]) {
            $sieveRule->_allowExternal = true;
        }

        if (isset($_list->xprops()[Addressbook_Model_List::XPROP_SIEVE_ALLOW_ONLY_MEMBERS]) && $_list
                ->xprops()[Addressbook_Model_List::XPROP_SIEVE_ALLOW_ONLY_MEMBERS]) {
            if ($sieveRule->_allowExternal) {
                $translation = Tinebase_Translation::getTranslation('Felamimail');
                throw new Tinebase_Exception_SystemGeneric($translation->_('Can not combine "allow external" and "allow only members"'));
            }
            $sieveRule->_allowOnlyGroupMembers = true;
        }

        if (isset($_list->xprops()[Addressbook_Model_List::XPROP_SIEVE_FORWARD_ONLY_SYSTEM]) && $_list
                ->xprops()[Addressbook_Model_List::XPROP_SIEVE_FORWARD_ONLY_SYSTEM]) {
            $sieveRule->_forwardOnlySystem = true;
        }

        return $sieveRule;
    }

    static public function setScriptForList(Addressbook_Model_List $list)
    {
        $oldValue = Felamimail_Controller_Account::getInstance()->doContainerACLChecks(false);
        $raii = new Tinebase_RAII(function() use ($oldValue) {
            Felamimail_Controller_Account::getInstance()->doContainerACLChecks($oldValue);});

        $account = Felamimail_Controller_Account::getInstance()->getAccountForList($list);

        if (! $account) {
            throw new Tinebase_Exception_NotFound('account of list not found');
        }

        $sieveRule = static::createFromList($list)->__toString();

        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' .
            __LINE__ . ' add sieve script: ' . $sieveRule);

        Felamimail_Controller_Sieve::getInstance()->setAdbListScript($account,
            Felamimail_Model_Sieve_ScriptPart::createFromString(
                Felamimail_Model_Sieve_ScriptPart::TYPE_ADB_LIST, $list->getId(), $sieveRule));

        // for unused variable check only
        unset($raii);

        return true;
    }

    static public function getSieveScriptForAdbList(Addressbook_Model_List $list)
    {
        $account = Felamimail_Controller_Account::getInstance()->getAccountForList($list);
        if ($account) {
            return Felamimail_Controller_Sieve::getInstance()->getSieveScript($account);
        } else {
            return null;
        }
    }
}
