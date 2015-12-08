<?php

/**
 * Start Gateway Model
 *
 * @category   Start
 * @package    Start_Gateway
 * @author     Haris Ahmed <haris.ahmed@eweberinc.com>
 */
class Start_Gateway_Model_Paymentmethod extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'gateway';
    protected $_formBlockType = 'gateway/form_gateway';
    protected $_infoBlockType = 'gateway/info_gateway';

    const REQUEST_TYPE_AUTH_CAPTURE = 'AUTH_CAPTURE';
    const REQUEST_TYPE_AUTH_ONLY = 'AUTH_ONLY';
    const REQUEST_TYPE_CAPTURE_ONLY = 'CAPTURE_ONLY';
    const REQUEST_TYPE_PRIOR_AUTH_CAPTURE = 'PRIOR_AUTH_CAPTURE';
    const PLUGIN_VERSION = '0.2';

    /**
     * Availability options
     */
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canSaveCc = false;
    protected $_isInitializeNeeded = true;
    protected $_canFetchTransactionInfo = false;

    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canRefund() {
        return $this->_canRefund;
    }

    /**
     * Check void availability
     *
     * @param   Varien_Object $invoicePayment
     * @return  bool
     */
    public function canVoid(Varien_Object $payment) {
        return $this->_canVoid;
    }

    public function assignData($data) {
        $info = $this->getInfoInstance();

        if (isset($_POST['payfortToken'])) {
            $info->setPayfortToken($_POST['payfortToken']);
        }

        if (isset($_POST['payfortEmail'])) {
            $info->setPayfortToken($_POST['payfortEmail']);
        }

        return $this;
    }

    public function getFormBlockType() {
        return $this->_formBlockType;
    }

    public function collectPayment(\Mage_Payment_Model_Info $payment, $amount, $capture = true) {

        require_once(MAGENTO_ROOT . '/lib/Start/autoload.php'); # At the top of your PHP file

        $token = isset($_POST['payfortToken']) ? $_POST['payfortToken'] : false;
        $email = isset($_POST['payfortEmail']) ? $_POST['payfortEmail'] : false;
        if (!$token || !$email) {
            //this block will be executed if the order was authorized earlier and now trying to capture amount
            $token_array = $payment->getAdditionalInformation('token');
            $token = $token_array['token'];
            $email = $token_array['email'];
        }

        if (!$token || !$email) {
            Mage::throwException('Invalid Token');
        }
        $store = Mage::app()->getStore();
        $currency = $store->getCurrentCurrency();
        $currency = !isset($currency) ? 'AED' : $currency->getCurrencyCode();
        if (file_exists(MAGENTO_ROOT . '/data/currencies.json')) {
            $currency_json_data = json_decode(file_get_contents(MAGENTO_ROOT . '/data/currencies.json'), 1);
            $currency_multiplier = $currency_json_data[$currency];
        } else {
            $currency_multiplier = 100;
        }
        $amount_in_cents = $amount * $currency_multiplier;
        $order = $payment->getOrder();
        $order_items_array_full = array();
        foreach ($order->getAllVisibleItems() as $value) {
            $order_items_array['title'] = $value->getName();
            $order_items_array['amount'] = round($value->getPrice(), 2) * $currency_multiplier;
            $order_items_array['quantity'] = $value->getQtyOrdered();
            array_push($order_items_array_full, $order_items_array);
        }
        $shipping_amount = $order->getShippingAmount();
        $shipping_amount = $shipping_amount * $currency_multiplier;
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $username = $customer->getName();
            $registered_at = date(DATE_ISO8601, strtotime($customer->getCreatedAt()));
        } else {
            $username = "Guest";
            $registered_at = date(DATE_ISO8601, strtotime(date("Y-m-d H:i:s")));
        }
        $shiping_data = $order->getShippingAddress()->getData();
        $shipping_address = array(
            "first_name" => $shiping_data['firstname'],
            "last_name" => $shiping_data['lastname'],
            "country" => $shiping_data['country_id'],
            "city" => $shiping_data['city'],
            "address" => $shiping_data['customer_address'],
            "phone" => $shiping_data['telephone'],
            "postcode" => $shiping_data['postcode']
        );
        $billing_data = $order->getBillingAddress()->getData();
        $billing_address = array(
            "first_name" => $billing_data['firstname'],
            "last_name" => $billing_data['lastname'],
            "country" => $billing_data['country_id'],
            "city" => $billing_data['city'],
            "address" => $billing_data['customer_address'],
            "phone" => $billing_data['telephone'],
            "postcode" => $billing_data['postcode']
        );

        $shopping_cart_array = array(
            'user_name' => $username,
            'registered_at' => $registered_at,
            'items' => $order_items_array_full,
            'billing_address' => $billing_address,
            'shipping_address' => $shipping_address
        );
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $charge_args = array(
            'description' => "Magento charge for " . $email,
            'card' => $token,
            'currency' => $currency,
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'amount' => $amount_in_cents,
            'capture' => $capture,
            'shipping_amount' => $shipping_amount,
            'shopping_cart' => $shopping_cart_array,
            'metadata' => array('reference_id' => $orderId)
        );

        $ver = new Mage;
        $version=$ver->getVersion();
        $userAgent = 'Magento ' . $version . ' / Start Plugin ' . self::PLUGIN_VERSION;
        Start::setUserAgent($userAgent);
        
        $method = $payment->getMethodInstance();
        if ($method->getConfigData('test_mode') == 1)
            Start::setApiKey($method->getConfigData('test_secret_key'));
        else
            Start::setApiKey($method->getConfigData('live_secret_key'));
        
        try {
            // Charge the token
            $charge = Start_Charge::create($charge_args);
            //need to process charge as success or failed
        } catch (Start_Error $e) {
            $error_code = $e->getErrorCode();

            if ($error_code === "card_declined") {
                $errorMsg = $e->getMessage() . ' Please, return to "Payment Information" step and try with another card.';
            } else {
                $errorMsg = $e->getMessage();
            }

            Mage::throwException($errorMsg);
        }
        //need to process charge as success or failed
    }

    public function authorize(Varien_Object $payment, $amount) {
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for capture.'));
        }

        $method = $payment->getMethodInstance();
        $capture = false;
        if ($method->getConfigData('payment_action') == self::ACTION_AUTHORIZE_CAPTURE)
            $capture = true;

        $this->collectPayment($payment, $amount, $capture);

        $token = isset($_POST['payfortToken']) ? $_POST['payfortToken'] : false;
        $email = isset($_POST['payfortEmail']) ? $_POST['payfortEmail'] : false;

        $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
        $payment->setAdditionalInformation('token', array('token' => $token, 'email' => $email));
        return $this;
    }

    /**
     * Send capture request to gateway
     *
     * @param Varien_Object $payment
     * @param decimal $amount
     * @return Start_Gateway_Model_Paymentmethod
     * @throws Mage_Core_Exception
     */
    public function capture(Varien_Object $payment, $amount) {
        if ($amount <= 0) {
            Mage::throwException('Invalid amount for capture.');
        }

        $payment->setAmount($amount);

        if ($payment->getParentTransactionId()) {
            $payment->setAnetTransType(self::REQUEST_TYPE_PRIOR_AUTH_CAPTURE);
            $payment->setXTransId($this->_getRealParentTransactionId($payment));
        } else {
            $payment->setAnetTransType(self::REQUEST_TYPE_AUTH_CAPTURE);
        }

        //please call this function or some function to call API with token and email to capture authorized amount
        //$this->collectPayment($payment, $amount);
        //returning this as all good but it should return $this in case success and throw exception in case of error
        return $this;
    }

    public function validate() {
        parent::validate();

        $token = isset($_POST['payfortToken']) ? $_POST['payfortToken'] : false;
        $email = isset($_POST['payfortEmail']) ? $_POST['payfortEmail'] : false;

        if (!$token || !$email) {
            Mage::throwException('Invalid Token');
        }

        if (isset($errorMsg)) {
            Mage::throwException($errorMsg);
        }

        return $this;
    }

    /**
     * Instantiate state and set it to state object
     *
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject) {
        switch ($paymentAction) {
            case self::ACTION_AUTHORIZE:
                $payment = $this->getInfoInstance();

                $order = $payment->getOrder();
                $order->setCanSendNewEmailFlag(false);
                $payment->authorize(true, $order->getBaseTotalDue()); // base amount will be set inside
                $payment->setAmountAuthorized($order->getTotalDue());

                $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, 'pending_payment', '', false);

                $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                $stateObject->setStatus('pending_payment');
                $stateObject->setIsNotified(false);
                break;
            case self::ACTION_AUTHORIZE_CAPTURE:
                $payment = $this->getInfoInstance();

                $order = $payment->getOrder();
                $order->setCanSendNewEmailFlag(false);
                $payment->authorize(true, $order->getBaseTotalDue()); // base amount will be set inside
                $payment->setAmountAuthorized($order->getTotalDue());

                $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, 'pending_payment', '', false);

                $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                $stateObject->setStatus('pending_payment');
                $stateObject->setIsNotified(false);
                break;
            default:
                break;
        }
    }

}
