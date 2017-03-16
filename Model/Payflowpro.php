<?php
/**
 * Payflowpro method model
 */

namespace Clarion\Payflowpro\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;

class Payflowpro extends \Magento\Payment\Model\Method\Cc
{

    const CODE = 'clarion_payflowpro';

    /**
     * Transaction action codes
     */
    const TRXTYPE_AUTH_ONLY = 'A';
    const TRXTYPE_SALE = 'S';
    const TRXTYPE_CREDIT = 'C';
    const TRXTYPE_DELAYED_CAPTURE = 'D';
    const TRXTYPE_DELAYED_VOID = 'V';
    const TRXTYPE_DELAYED_VOICE = 'F';
    const TRXTYPE_DELAYED_INQUIRY = 'I';
    const TRXTYPE_ACCEPT_DENY = 'U';
    const UPDATEACTION_APPROVED = 'APPROVE';
    const UPDATEACTION_DECLINED_BY_MERCHANT = 'FPS_MERCHANT_DECLINE';
    const MULTI_PAGE_CHECKOUT = "multishipping/options/checkout_multiple";

    /**
     * Tender type codes
     */
    const TENDER_CC = 'C';

    /**
     * Gateway request URLs
     */
    const TRANSACTION_URL = 'https://payflowpro.paypal.com/transaction';
    const TRANSACTION_URL_TEST_MODE = 'https://pilot-payflowpro.paypal.com/transaction';

    /*     * #@+
     * Response code
     */
    const RESPONSE_CODE_APPROVED = 0;
    const RESPONSE_CODE_INVALID_AMOUNT = 4;
    const RESPONSE_CODE_FRAUDSERVICE_FILTER = 126;
    const RESPONSE_CODE_DECLINED = 12;
    const RESPONSE_CODE_DECLINED_BY_FILTER = 125;
    const RESPONSE_CODE_DECLINED_BY_MERCHANT = 128;
    const RESPONSE_CODE_CAPTURE_ERROR = 111;
    const RESPONSE_CODE_VOID_ERROR = 108;
    const PNREF = 'pnref';

    /*     * #@- */

    /**
     * Response params mappings
     *
     * @var array
     */
    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_payflowpro = false;
    protected $_multipage;
    protected $_countryFactory;
    protected $_scopeConfig;
    protected $_environment;
    protected $_paymentAction;
    protected $_encryptor;
    protected $_supportedCurrencyCodes = ['USD'];
    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];

    /**
     * Constructor of Class
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Clarion\Payflowpro\Gateway\PayFlowRun $payflowpro,
        EncryptorInterface $encryptor,
        array $data = []
    ) {
    
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

        $this->_countryFactory = $countryFactory;
        $this->_scopeConfig = $scopeConfig;

        $this->_environment = $this->getConfigData('clarion_environment');
        $this->_paymentAction = $this->getConfigData('payment_action');
        $this->_multipage = $this->_scopeConfig->getValue(
            self::MULTI_PAGE_CHECKOUT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $this->_payflowpro = $payflowpro;
        $this->_encryptor = $encryptor;
    }

    /**
     * Authorize payment
     *
     * @param InfoInterface|Payment|Object $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {

        $request = $this->_prepareGatewayData($payment, $amount);

        $request->setAmt(round($amount, 2));
        $order = $payment->getOrder();
        $request->setCurrency($order->getBaseCurrencyCode());
        $request->setTrxtype(self::TRXTYPE_AUTH_ONLY);

        $request = $this->_prepareCustomerData($request, $payment);
        $request = $this->_addRequestOrderInfo($request, $order);
        $gatewaystatus = $this->_getGatewayStatus();
        $response = $this->_payflowpro->postRequest($request, $gatewaystatus);
        $response = $this->_payflowpro->_formatResponse($response);

        $this->_processErrors($response);
        $this->_setTranssactionStatus($payment, $response);

        return $this;
    }

    /**
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {

        if ($payment->getAdditionalInformation(self::PNREF)) {
            $request = $this->_prepareBasicGatewayData();
            $request->setAmt(round($amount, 2));
            $request->setTrxtype(self::TRXTYPE_SALE);
            $request->setOrigid($payment->getAdditionalInformation(self::PNREF));
            $payment->unsAdditionalInformation(self::PNREF);
        } elseif ($payment->getParentTransactionId()) {
            $request = $this->_prepareBasicGatewayData();
            $request->setOrigid($payment->getParentTransactionId());
            $captureAmount = $this->_getCaptureAmount($amount);
            if ($captureAmount) {
                $request->setAmt($captureAmount);
            }
            
            $trxType = $this->getInfoInstance()->hasAmountPaid() ? self::TRXTYPE_SALE : self::TRXTYPE_DELAYED_CAPTURE;
            $request->setTrxtype($trxType);
        } else {
            $request = $this->_prepareGatewayData($payment, $amount);
            $request->setTrxtype(self::TRXTYPE_SALE);
            $request->setAmt(round($amount, 2));
            $order = $payment->getOrder();
            $request->setCurrency($order->getBaseCurrencyCode());
            $request = $this->_prepareCustomerData($request, $payment);
            $request = $this->_addRequestOrderInfo($request, $order);
        }

        $request = $this->_addRequestOrderInfo($request, $payment->getOrder());
        $gatewaystatus = $this->_getGatewayStatus();

        $response = $this->_payflowpro->postRequest($request, $gatewaystatus);
        $response = $this->_payflowpro->_formatResponse($response);

        $this->_processErrors($response);
        $this->_setTranssactionStatus($payment, $response);

        return $this;
    }

    /**
     * Payment refund
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $request = $this->_prepareBasicGatewayData();
        $request->setTrxtype(self::TRXTYPE_CREDIT);
        $request->setOrigid($payment->getParentTransactionId());
        $request->setAmt(round($amount, 2));
        $gatewaystatus = $this->_getGatewayStatus();

        $response = $this->_payflowpro->postRequest($request, $gatewaystatus);
        $response = $this->_payflowpro->_formatResponse($response);
        $this->_processErrors($response);

        if ($response['RESULT'] == self::RESPONSE_CODE_APPROVED) {
            $payment->setTransactionId($response['PNREF'])->setIsTransactionClosed(true);
        }
        
        return $this;
    }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($this->_multipage == 1) {
            return parent::isAvailable($quote);
        }
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        
        return true;
    }

    /**
     * Void payment
     *
     * @param InfoInterface|Payment|Object $payment
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        $request = $this->_prepareBasicGatewayData();
        $request->setTrxtype(self::TRXTYPE_DELAYED_VOID);
        $request->setOrigid($payment->getParentTransactionId());
        $gatewaystatus = $this->_getGatewayStatus();

        $response = $this->_payflowpro->postRequest($request, $gatewaystatus);
        $response = $this->_payflowpro->_formatResponse($response);
        $this->_processErrors($response);

        if ($response['RESULT'] == self::RESPONSE_CODE_APPROVED) {
            $payment->setTransactionId(
                $response['PNREF']
            )->setIsTransactionClosed(
                1
            )->setShouldCloseParentTransaction(
                1
            );
        }

        return $this;
    }

    /**
     * Check void availability
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function canVoid()
    {
        if ($this->getInfoInstance()->getAmountPaid()) {
            $this->_canVoid = false;
        }

        return $this->_canVoid;
    }

    /**
     * Attempt to void the authorization on cancelling
     *
     * @param InfoInterface|Object $payment
     * @return $this
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        if (!$payment->getOrder()->getInvoiceCollection()->count()) {
            return $this->void($payment);
        }

        return false;
    }

    /**
     * @param DataObject $order
     * @param DataObject $request
     * @return DataObject
     */
    public function fillCustomerContacts(DataObject $order, DataObject $request)
    {
        $billing = $order->getBillingAddress();
        if (!empty($billing)) {
            $request = $this->setBilling($request, $billing);
            $request->setEmail($order->getCustomerEmail());
        }
        
        $shipping = $order->getShippingAddress();
        if (!empty($shipping)) {
            $request = $this->setShipping($request, $shipping);
            return $request;
        }
        
        return $request;
    }

    /**
     * Return request object with basic information for gateway request
     *
     * @return DataObject
     */
    public function _prepareGatewayData($payment, $amount)
    {

        $request = new DataObject();
        $request->setPartner($this->getConfigData('clarion_partner'));
        $request->setVendor($this->getConfigData('clarion_vendor'));
        $request->setUser($this->_encryptor->decrypt($this->getConfigData('clarion_user')));
        $request->setPwd($this->_encryptor->decrypt($this->getConfigData('clarion_password')));
        $request->setVerbosity("MEDIUM");
        $request->setTender(self::TENDER_CC);
        $request = $this->_prepareCardDetails($request, $payment, $amount);

        return $request;
    }

    /**
     * Return request object with basic information Of Card Details
     *
     * @return DataObject
     */
    public function _prepareCardDetails($request, $payment, $amount)
    {
        $amountfinal = $amount;
        $request->setAcct($payment->getCcNumber());
        $request->setExpdate(sprintf('%02d', $payment->getCcExpMonth()) . substr($payment->getCcExpYear(), -2, 2));
        $request->setCvv2($payment->getCcCid());

        return $request;
    }

    /**
     * @param DataObject $request
     * @param DataObject $shipping
     *
     * @return Object
     */
    public function _setShippingAddress($request, $shipping)
    {

        $request->setShiptofirstname(
            $shipping->getFirstname()
        )->setShiptolastname(
            $shipping->getLastname()
        )->setShiptostreet(
            implode(' ', $shipping->getStreet())
        )->setShiptocity(
            $shipping->getCity()
        )->setShiptostate(
            $shipping->getRegionCode()
        )->setShiptozip(
            $shipping->getPostcode()
        )->setShiptocountry(
            $shipping->getCountryId()
        );
        return $request;
    }

    /**
     * @param DataObject $request
     * @param DataObject $billing
     *
     * @return Object
     */
    public function _setBilling($request, $billing)
    {
        $request->setFirstname(
            $billing->getFirstname()
        )->setLastname(
            $billing->getLastname()
        )->setStreet(
            implode(' ', $billing->getStreet())
        )->setCity(
            $billing->getCity()
        )->setState(
            $billing->getRegionCode()
        )->setZip(
            $billing->getPostcode()
        )->setCountry(
            $billing->getCountryId()
        );
        return $request;
    }

    /**
     * @param DataObject $payment
     * @param DataObject $request
     * @return DataObject
     */
    public function _prepareCustomerData($request, $payment)
    {

        $order = $payment->getOrder();

        $shipping = $order->getShippingAddress();
        if (!empty($shipping)) {
            $request = $this->_setShippingAddress($request, $shipping);
        }

        $billing = $order->getBillingAddress();
        if (!empty($billing)) {
            $request = $this->_setBilling($request, $billing);
            $request->setEmail($order->getCustomerEmail());
        }

        return $request;
    }

    public function _getGatewayStatus()
    {
        if ($this->_environment == "sandbox") {
            return self::TRANSACTION_URL_TEST_MODE;
        } else {
            return self::TRANSACTION_URL;
        }
    }

    /**
     * If response is failed throw exception
     *
     * @param DataObject $response
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function _processErrors($response)
    {
        if ($response['RESULT'] == self::RESPONSE_CODE_VOID_ERROR) {
            throw new \Magento\Framework\Exception\State\InvalidTransitionException(__('You cannot void a verification transaction.'));
        } elseif ($response['RESULT'] != self::RESPONSE_CODE_APPROVED &&
            $response['RESULT'] != self::RESPONSE_CODE_FRAUDSERVICE_FILTER
        ) {
            throw new \Magento\Framework\Exception\LocalizedException(__($response['RESPMSG']));
        } elseif ($response['RESULT'] == self::RESPONSE_CODE_FRAUDSERVICE_FILTER) {
            throw new \Magento\Framework\Exception\LocalizedException(__($response['RESPMSG']));
        }
    }

    /**
     * @param DataObject $payment
     * @param DataObject $response
     *
     * @return Object
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function _setTranssactionStatus($payment, $response)
    {
        if ($payment instanceof InfoInterface) {
            $this->errorHandler->handle($payment, $response);
        }

        switch ($response['RESULT']) {
            case self::RESPONSE_CODE_APPROVED:
                $payment->setTransactionId($response['PNREF'])->setIsTransactionClosed(0);
                break;
            case self::RESPONSE_CODE_DECLINED_BY_FILTER:
            case self::RESPONSE_CODE_FRAUDSERVICE_FILTER:
                $payment->setTransactionId($response['PNREF'])->setIsTransactionClosed(0);
                $payment->setIsTransactionPending(true);
                $payment->setIsFraudDetected(true);
                break;
            case self::RESPONSE_CODE_DECLINED:
                throw new \Magento\Framework\Exception\LocalizedException(__($response['RESPMSG']));
            default:
                break;
        }
        
        return $payment;
    }

    /**
     * Add order details to payment request
     * @param DataObject $request
     * @param Order $order
     * @return void
     */
    public function _addRequestOrderInfo($request, $order)
    {
        $id = $order->getId();
        // for auth request order id is not exists yet
        if (!empty($id)) {
            $request->setPonum($id);
        }
        
        $orderIncrementId = $order->getIncrementId();
        return $request->setCustref($orderIncrementId)
                ->setInvnum($orderIncrementId)
                ->setComment1($orderIncrementId);
    }

    /**
     * Return request object with basic information for gateway request
     *
     * @return DataObject
     */
    public function _prepareBasicGatewayData()
    {

        $request = new DataObject();
        $request->setPartner($this->getConfigData('clarion_partner'));
        $request->setVendor($this->getConfigData('clarion_vendor'));
        $request->setUser($this->_encryptor->decrypt($this->getConfigData('clarion_user')));
        $request->setPwd($this->_encryptor->decrypt($this->getConfigData('clarion_password')));
        $request->setVerbosity("MEDIUM");
        $request->setTender(self::TENDER_CC);

        return $request;
    }

    /**
     * Get capture amount
     *
     * @param float $amount
     * @return float
     */
    protected function _getCaptureAmount($amount)
    {
        $infoInstance = $this->getInfoInstance();
        $amountToPay = round($amount, 2);
        $authorizedAmount = round($infoInstance->getAmountAuthorized(), 2);
        return $amountToPay != $authorizedAmount ? $amountToPay : 0;
    }
}
