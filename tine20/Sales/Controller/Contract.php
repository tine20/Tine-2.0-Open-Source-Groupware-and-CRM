<?php
/**
 * contract controller for Sales application
 * 
 * @package     Sales
 * @subpackage  Controller
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2013 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * contract controller class for Sales application
 * 
 * @package     Sales
 * @subpackage  Controller
 */
class Sales_Controller_Contract extends Sales_Controller_NumberableAbstract
{
    /**
     * the number gets prefixed zeros until this amount of chars is reached
     *
     * @var integer
     */
    protected $_numberZerofill = 5;
    
    /**
     * the prefix for the invoice
     *
     * @var string
     */
    protected $_numberPrefix = 'V-';
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct()
    {
        $this->_applicationName = 'Sales';
        $this->_backend = new Sales_Backend_Contract();
        $this->_modelName = 'Sales_Model_Contract';
        // TODO this should be done automatically if model has customfields (hasCustomFields)
        $this->_resolveCustomFields = true;
    }

    /**
     * holds the callbacks to call after modifications are done
     * @var array
     */
    protected $_afterModifyCallbacks = array();
    
    /**
     * holds the instance of the singleton
     *
     * @var Sales_Controller_Contract
     */
    private static $_instance = NULL;
    
    /**
     * the singleton pattern
     *
     * @return Sales_Controller_Contract
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new self();
        }
        
        return self::$_instance;
    }

    /****************************** overwritten functions ************************/

    /**
     * get by id
     *
     * @param string $_id
     * @param int $_containerId
     * @param bool         $_getRelatedData
     * @return Tinebase_Record_RecordSet
     */
    public function get($_id, $_containerId = NULL, $_getRelatedData = TRUE)
    {
        $containerId = $_containerId !== null ? $_containerId : $this->getSharedContractsContainer();
        
        return parent::get($_id, $containerId, $_getRelatedData);
    }
    

    /**
     * @see Tinebase_Controller_Record_Abstract::update()
     */
    public function update(Tinebase_Record_Interface $_record, $_duplicateCheck = TRUE)
     {
        if ($_duplicateCheck) {
            $this->_checkNumberUniquity($_record, true);
        }
        $this->_checkNumberType($_record);
        return parent::update($_record, $_duplicateCheck);
    }
    
    /**
     * add one record
     *
     * @param   Tinebase_Record_Interface $_record
     * @return  Sales_Model_Contract
     */
    public function create(Tinebase_Record_Interface $_record)
    {
        // add container
        $_record->container_id = self::getSharedContractsContainer()->getId();

        if (Sales_Config::getInstance()->get(Sales_Config::CONTRACT_NUMBER_GENERATION, 'auto') == 'auto') {
            // add number if configured auto
            $this->_addNextNumber($_record);
        } else {
            // check uniquity if not autogenerated
            $this->_checkNumberUniquity($_record, false);
        }
        
        // check type
        $this->_checkNumberType($_record);
        
        return parent::create($_record);
    }

    /**
     * Checks if number is unique if manual generated
     *
     * @param Tinebase_Record_Interface $r
     * @throws Tinebase_Exception_Record_Validation
     */
    protected function _checkNumberType($record)
    {
        $number = $record->number;
    
        if (empty($number)) {
            throw new Tinebase_Exception_Record_Validation('Please use a contract number!');
        } elseif ((Sales_Config::getInstance()->get('contractNumberValidation', 'string') == 'integer') && (! is_numeric($number))) {
            throw new Tinebase_Exception_Record_Validation('Please use a decimal number as contract number!');
        }
    }
    
    /**
     * get (create if it does not exist) container for shared contracts
     * 
     * @return Tinebase_Model_Container|NULL
     * 
     * @todo use Tinebase_Container::createSystemContainer()
     */
    public static function getSharedContractsContainer()
    {
        $sharedContracts = NULL;
        $appId = Tinebase_Application::getInstance()->getApplicationByName('Sales')->getId();
        
        try {
            $sharedContractsId = Sales_Config::getInstance()->get(Sales_Model_Config::SHAREDCONTRACTSID);
            $sharedContracts = Tinebase_Container::getInstance()->get($sharedContractsId);
        } catch (Tinebase_Exception_NotFound $tenf) {
            $newContainer = new Tinebase_Model_Container(array(
                'name'              => 'Shared Contracts',
                'type'              => Tinebase_Model_Container::TYPE_SHARED,
                'backend'           => 'Sql',
                'application_id'    => $appId,
                'model'             => 'Sales_Model_Contract'
            ));
            $sharedContracts = Tinebase_Container::getInstance()->addContainer($newContainer, NULL, TRUE);
            
            Sales_Config::getInstance()->set(Sales_Model_Config::SHAREDCONTRACTSID, $sharedContracts->getId());
            
            // add grants for groups
            $groupsBackend = Tinebase_Group::factory(Tinebase_Group::SQL);
            $adminGroup = $groupsBackend->getDefaultAdminGroup();
            $userGroup  = $groupsBackend->getDefaultGroup();
            Tinebase_Container::getInstance()->addGrants($sharedContracts, Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP, $userGroup, array(
                Tinebase_Model_Grants::GRANT_READ,
                Tinebase_Model_Grants::GRANT_EDIT
            ), TRUE);
            Tinebase_Container::getInstance()->addGrants($sharedContracts, Tinebase_Acl_Rights::ACCOUNT_TYPE_GROUP, $adminGroup, array(
                Tinebase_Model_Grants::GRANT_ADD,
                Tinebase_Model_Grants::GRANT_READ,
                Tinebase_Model_Grants::GRANT_EDIT,
                Tinebase_Model_Grants::GRANT_DELETE,
                Tinebase_Model_Grants::GRANT_ADMIN
            ), TRUE);
        }
        
        return $sharedContracts;
    }
    
    /**
     * get next date to bill the contract given.
     * 
     * @param Sales_Model_Contract $contract
     * @return Tinebase_DateTime
     */
    public function getNextBill($contract)
    {
        // is null, if this is the first time to bill the contract
        $lastBilled = ($contract->last_autobill === NULL) ? NULL : clone $contract->last_autobill;
        
        // if the contract has been billed already, add the interval
        if ($lastBilled) {
            $nextBill = $lastBilled->addMonth($contract->interval);
        } else {
            // it hasn't been billed already, so take the start_date of the contract as date
            $nextBill = clone $contract->start_date;
        
            // add the interval to the date if the billing point is at the end of the period
            if ($contract->billing_point == 'end') {
                $nextBill->addMonth($contract->interval);
            }
        }
        
        // assure creating the last bill if a contract has been terminated
        if (($contract->end_date !== NULL) && $nextBill->isLater($contract->end_date)) {
            $nextBill = clone $contract->end_date;
        }
        
        $nextBill->setTime(0,0,0);
        
        return $nextBill;
    }

    public function addAfterModifyCallback($key, $callable)
    {
        $this->_afterModifyCallbacks[$key] = $callable;
    }

    protected function _afterModifyCallbacks()
    {
        foreach($this->_afterModifyCallbacks as $callable)
        {
            call_user_func($callable[0], $callable[1]);
        }
        $this->_afterModifyCallbacks = array();
    }

    /**
     * inspect creation of one record (after create)
     *
     * @param   Tinebase_Record_Interface $_createdRecord
     * @param   Tinebase_Record_Interface $_record
     * @return  void
     */
    protected function _inspectAfterCreate($_createdRecord, Tinebase_Record_Interface $_record)
    {
        parent::_inspectAfterCreate($_createdRecord, $_record);

        $this->_afterModifyCallbacks();
    }

    /**
     * inspect update of one record (before update)
     *
     * @param   Tinebase_Record_Interface $_record      the update record
     * @param   Tinebase_Record_Interface $_oldRecord   the current persistent record
     * @return  void
     */
    protected function _inspectAfterUpdate($_updatedRecord, $_record, $_oldRecord)
    {
        parent::_inspectAfterUpdate($_updatedRecord, $_record, $_oldRecord);

        $this->_afterModifyCallbacks();
    }

    /**
     * merges source contracts into the target contract (relations and products)
     * 
     * @param Sales_Model_Contract $targetContract
     * @param Tinebase_Record_RecordSet $sourceContracts
     */
    public function mergeContracts(Sales_Model_Contract $targetContract, Tinebase_Record_RecordSet $sourceContracts)
    {
        // handle relations (duplicates get skipped)
        foreach($sourceContracts as $sourceContract) {
            Tinebase_Relations::getInstance()->transferRelations($sourceContract->getId(), $targetContract->getId(), 'Sales_Model_Contract');
        }
        
        // handle products
        $filter = new Sales_Model_ProductAggregateFilter(array());
        $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'contract_id', 'operator' => 'in', 'value' => $sourceContracts->getId())));
        $products = Sales_Controller_ProductAggregate::getInstance()->search($filter);
        
        foreach($products as $product) {
            $product->contract_id = $targetContract->getId();
            Sales_Controller_ProductAggregate::getInstance()->update($product);
        }
        
        return true;
    }

    /**
     * allows to transfer or update billing information
     * 
     * @param boolen $update
     */
    public function transferBillingInformation($update = FALSE)
    {
        $filter = new Sales_Model_ContractFilter(array());
        
        $iterator = new Tinebase_Record_Iterator(array(
                'iteratable' => $this,
                'controller' => Sales_Controller_Contract::getInstance(),
                'filter'     => $filter,
                'options'    => array(
                    'getRelations' => TRUE,
                    'limit' => 20
                ),
                'function'   => ($update ? 'processUpdateBillingInformation' : 'processTransferBillingInformation'),
        ));
        
        $iterator->iterate();
    }
    
    /**
     * processUpdateBillingInformation
     * 
     * @param Tinebase_Record_RecordSet $contracts
     */
    public function processUpdateBillingInformation($contracts)
    {
        $billingPoints = array(
                'Timetracker_Model_Timeaccount'         => 'end',
                'Sales_Model_Product'                   => 'end',
        );
        
        $allProducts = Sales_Controller_Product::getInstance()->getAll();
        
        foreach($contracts as $contract) {
            $filter = new Sales_Model_ProductAggregateFilter(array());
            $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'contract_id', 'operator' => 'equals','value' => $contract->getId())));
            $usedPAs = Sales_Controller_ProductAggregate::getInstance()->search($filter);
        
            foreach($usedPAs as $pa) {
                if (! $pa->billing_point) {
                    
                    $product = $allProducts->filter('id', $pa->product_id)->getFirstRecord();
                    
                    if(array_key_exists($product->accountable, $billingPoints)) {
                        $pa->billing_point = $billingPoints[$product->accountable];
                        Sales_Controller_ProductAggregate::getInstance()->update($pa);
                    }   
                }
            }
        }
    }
    
    /**
     * processTransferBillingInformation
     *
     * @param Tinebase_Record_RecordSet $contracts
     */
    public function processTransferBillingInformation($contracts)
    {
        $billingPoints = array(
            'Timetracker_Model_Timeaccount'         => 'end',
            'Sales_Model_Product'                   => 'end',
        );
        
        foreach($contracts as $contract) {
            // iterate relations, look for customer, cost center and accountables
            // find accountables
            $models = array();
            
            $filter = new Sales_Model_ProductAggregateFilter(array());
            $filter->addFilter(new Tinebase_Model_Filter_Text(array('field' => 'contract_id', 'operator' => 'equals','value' => $contract->getId())));
            $usedPAs = Sales_Controller_ProductAggregate::getInstance()->search($filter);
            
            foreach ($contract->relations as $relation) {
                
                if (in_array('Sales_Model_Accountable_Interface', class_implements($relation->related_record))) {
                    $models[] = $relation->related_model;
                }
                
                $models = array_unique($models);
            }
            
            foreach($models as $model) {
                $filter = new Sales_Model_ProductFilter(array(array('field' => 'accountable', 'operator' => 'equals', 'value' => $model)));
                $product = Sales_Controller_Product::getInstance()->search($filter)->getFirstRecord();
                
                if (! $product) {
                    if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) {
                        Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' '
                                . ' Create Product for ' . $relation->related_model);
                    }
                    $product = Sales_Controller_Product::getInstance()->create(new Sales_Model_Product(array(
                            'name' => $model,
                            'accountable' => $model,
                            'description' => 'auto generated for invoicing',
                    )));
                }
                
                if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) {
                    Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' '
                            . ' Create ProductAggregate for ' . $model . ' contract: ' . $contract->getId());
                }
                if ($usedPAs->filter('product_id', $product->getId())->count() < 1) {
                    $productAggregate = Sales_Controller_ProductAggregate::getInstance()->create(new Sales_Model_ProductAggregate(array(
                        'product_id' => $product->getId(),
                        'contract_id' => $contract->getId(),
                        'interval' => $contract->interval,
                        'last_autobill' => $contract->last_autobill ? clone $contract->last_autobill : NULL,
                        'quantity' => 1,
                        'billing_point' => $billingPoints[$model],
                    )));
                }
            }
        }
    }
    
    /**
     * updates last autobill of product aggregates
     *
     * @param boolen $update
     */
    public function updateLastAutobillOfProductAggregates()
    {
        $filter = new Sales_Model_ContractFilter(array());
    
        $iterator = new Tinebase_Record_Iterator(array(
                'iteratable' => $this,
                'controller' => Sales_Controller_Contract::getInstance(),
                'filter'     => $filter,
                'options'    => array(
                        'getRelations' => TRUE,
                        'limit' => 20
                ),
                'function'   => 'processUpdateLastAutobillOfProductAggregates',
        ));
    
        $iterator->iterate();
    }
    
    /**
     * processUpdateBillingInformation
     * 
     * @param Tinebase_Record_RecordSet $contracts
     */
    
    public function processUpdateLastAutobillOfProductAggregates(Tinebase_Record_RecordSet $contracts)
    {
        $now = Tinebase_DateTime::now();
        
        $billingPoints = array(
            'Timetracker_Model_Timeaccount'         => 'end',
            'Sales_Model_Product'                   => 'end',
            'WebAccounting_Model_BackupPath'        => 'end',
            'WebAccounting_Model_StoragePath'       => 'end',
            'WebAccounting_Model_MailAccount'       => 'end',
            'WebAccounting_Model_DReg'              => 'begin',
            'WebAccounting_Model_CertificateDomain' => 'begin',
            'WebAccounting_Model_IPNet'             => 'end',
            // Fallback for Sales_Model_Product
            ''                                      => 'end',
            'Sales_Model_ProductAgregate'           => 'end',
        );
        
        foreach($contracts as $contract) {
            if ($contract->end_date && $contract->end_date < $now) {
                continue;
            }
            
            // find product aggregates for this contract
            $filter = new Sales_Model_ProductAggregateFilter(array());
            $filter->addFilter(new Tinebase_Model_Filter_Text(
                    array('field' => 'contract_id', 'operator' => 'equals', 'value' => $contract->getId())
            ));
            $productAggregates = Sales_Controller_ProductAggregate::getInstance()->search($filter);
            
            foreach($productAggregates as $pa) {
                // find all invoices for the contract
                $filter = new Sales_Model_InvoiceFilter(array(
                    array('field' => 'contract', 'operator' => 'AND', 'value' => array(array(
                        'field' =>  ':id', 'operator' => 'equals', 'value' => $contract->getId()
                    ))),
                ));
                
                $invoices = Sales_Controller_Invoice::getInstance()->search($filter);
                
                // find last invoice position for this aggregate
                $filter = new Sales_Model_InvoicePositionFilter();
                $filter->addFilter(new Tinebase_Model_Filter_Text(
                        array('field' => 'invoice_id', 'operator' => 'in', 'value' => $invoices->getArrayOfIds())
                ));
                $pagination = new Tinebase_Model_Pagination(array('limit' => 1, 'sort' => 'month', 'dir' => 'DESC'));
                
                $lastInvoicePosition = Sales_Controller_InvoicePosition::getInstance()->search($filter, $pagination)->getFirstRecord();
                
                // set billing_point, if none given
                if (! $pa->billing_point) {
                    $pa->billing_point = $billingPoints[$lastInvoicePosition->model];
                }
                
                if (! $lastInvoicePosition) {
                    // if no invoice position has been found, this is a new contract, so set start_date to the first day of the month of the contracts start_date
                    $date = clone $contract->start_date;
                    $date->setTimezone(Tinebase_Core::getUserTimezone());
                    $date->setTime(0,0,0);
                    $date->setDate($date->format('Y'), $date->format('m'), 1);
                    $date->setTimezone('UTC');
                    
                    $startDate = clone $date;
                    $labDate   = NULL;
                } else {
                    $split = explode('-', $lastInvoicePosition->month);
                    $date = Tinebase_DateTime::now();
                    $date->setTimezone(Tinebase_Core::getUserTimezone());
                    $date->setTime(0,0,0);
                    $date->setDate($split[0], $split[1], 1);
                    
                    // set to next billing date
                    $date->addMonth(1);
                    
                    // if the billing point is at the begin of the interval, set date back one interval
                    if ($pa->billing_point == 'begin') {
                        $date->subMonth($pa->interval);
                    }
                    
                    $date->setTimezone('UTC');
                    
                    $labDate   = clone $date;
                    
                    // find first invoice position to calculate start_date
                    $pagination = new Tinebase_Model_Pagination(array('limit' => 1, 'sort' => 'month', 'dir' => 'ASC'));
                    $firstInvoicePosition = Sales_Controller_InvoicePosition::getInstance()->search($filter, $pagination)->getFirstRecord();
                    $split = explode('-', $firstInvoicePosition->month);
                    
                    $startDate = Tinebase_DateTime::now()->setTimezone(Tinebase_Core::getUserTimezone());
                    $startDate->setTime(0,0,0);
                    $startDate->setDate($split[0], $split[1], 1);
                    
                    $startDate->setTimezone('UTC');
                }
                
                $pa->start_date    = $startDate;
                $pa->last_autobill = $labDate;
                
                Sales_Controller_ProductAggregate::getInstance()->update($pa);
            }
        }
    }
}
