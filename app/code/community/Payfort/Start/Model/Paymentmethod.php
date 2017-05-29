<?php

/**
 * Start Gateway Model
 *
 * @category   Start
 * @package    Payfort_Start
 */
class Payfort_Start_Model_Paymentmethod extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'start';
    //protected $_formBlockType = 'payfort_start/form_gateway';
    //protected $_infoBlockType = 'payfort_start/info_gateway';

    const REQUEST_TYPE_AUTH_CAPTURE = 'AUTH_CAPTURE';
    const REQUEST_TYPE_AUTH_ONLY = 'AUTH_ONLY';
    const REQUEST_TYPE_CAPTURE_ONLY = 'CAPTURE_ONLY';
    const REQUEST_TYPE_PRIOR_AUTH_CAPTURE = 'PRIOR_AUTH_CAPTURE';
    const PLUGIN_VERSION = '0.2.4';

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
        $Currency = Mage::app()->getStore()->getBaseCurrencyCode(); 
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
        $currency = !isset($Currency) ? 'AED' : $Currency;
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
        $billing_data = $order->getBillingAddress()->getData();
        if (is_object($order->getShippingAddress())) {
            $shipping_data = $order->getShippingAddress();
            $shipping_address = array(
                "first_name" => $shipping_data->getData('firstname'),
                "last_name" => $shipping_data->getData('lastname'),
                "country" => $shipping_data->getData('country_id'),
                "city" => $shipping_data->getData('city'),
                "address" => $shipping_data->getData('customer_address'),
                "phone" => $shipping_data->getData('telephone'),
                "postcode" => $shipping_data->getData('postcode')
            );
        } else {
            $shipping_address = array();
        }

        $billing_address = array(
            "first_name" => $shipping_data->getData('firstname'),
            "last_name" => $shipping_data->getData('lastname'),
            "country" => $shipping_data->getData('country_id'),
            "city" => $shipping_data->getData('city'),
            "address" => $shipping_data->getData('customer_address'),
            "phone" => $shipping_data->getData('telephone'),
            "postcode" => $shipping_data->getData('postcode')
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
        $version = $ver->getVersion();
        $userAgent = 'Magento ' . $version . ' / Start Plugin ' . self::PLUGIN_VERSION;
        Start::setUserAgent($userAgent);

        $method = $payment->getMethodInstance();
        if ($method->getConfigData('test_mode') == 1)
            Start::setApiKey($method->getConfigData('test_secret_key'));
        else
            Start::setApiKey($method->getConfigData('live_secret_key'));

        $paymentSuccess = true;
        try {
            // Charge the token
            $charge = Start_Charge::create($charge_args);
            //need to process charge as success or failed
            
        } catch (Start_Error $e) {
            $error_code = $e->getErrorCode();

            if ($error_code === "card_declined") {
                $errorMsg = 'Charge was declined. Please, contact you bank for more information or use a different card.';
            } else {
                $errorMsg = $e->getMessage();
            }
            $paymentSuccess = false;
 	//throw new Mage_Payment_Model_Info_Exception($errorMsg);
        }
        if(!$paymentSuccess) {
            $r = $this->declineOrder($order, 'Payfort has declined the payment.');
            $this->refillCart($order);
            $session = Mage::getSingleton('checkout/session');
            $session->addError($errorMsg);
            header('location:'.Mage::getUrl('checkout/cart', array('_secure' => true)));
            exit;
        }
        else{
            $this->successOrder($payment, $order, $charge);
            
            $payment->setTransactionId($charge["id"]);
            if ($capture) {
                $this->capture($payment, $amount);
                $payment->setIsTransactionClosed(1);
            } else {
                $payment->setIsTransactionClosed(0);
            }
            header('location:'.Mage::getUrl('checkout/onepage/success', array('_secure' => true)));
            exit;

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
     * @return Payfort_Start_Model_Paymentmethod
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
        
        return $this;
    }

    public function validate() {
        parent::validate();

//        $token = isset($_POST['payfortToken']) ? $_POST['payfortToken'] : false;
//        $email = isset($_POST['payfortEmail']) ? $_POST['payfortEmail'] : false;
//        
//        if (!$token || !$email) {
//            Mage::throwException('Invalid Token');
//        }
//
//        if (isset($errorMsg)) {
//            Mage::throwException($errorMsg);
//        }

        return $this;
    }

    /**
     * Instantiate state and set it to state object
     *
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject) {

        $payment = $this->getInfoInstance();
        
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);
        
        $stateObject->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
        return $this;
    }
    
    public function getOrderPlaceRedirectUrl()
    {
        //$session = Mage::getSingleton('checkout/session');
        //$session->getQuote()->setIsActive(1)->save();
        return Mage::getUrl('start/payment/redirect', array('_secure' => true));
    }
    
    
    public function declineOrder($order, $reason = '') {
        $status = Mage_Sales_Model_Order::STATE_CANCELED;
        if($this->getStatusId() == $status) {
            return true;
        }
        
        $session = Mage::getSingleton('checkout/session');
        try {
            if($order->getId()){
                //$incrementId = $session->getLastRealOrderId();
                $session->getQuote()->setIsActive(false)->save();
                $session->clear();
                try {
                    $order->cancel()->setState($status, true, $reason)->save();
                } catch (Mage_Core_Exception $e) {
                    Mage::logException($e);
                    return false;
                }
                //$this->refillCart($order);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
            return false;
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
        return true;
    }
    
    public function successOrder($payment, $order, $response_params) {
        $status = Mage_Sales_Model_Order::STATE_PROCESSING;
        
        $this->createInvoice($order, $response_params);
        $order->setState($status, true, 'Payfort has accepted the payment.');

        $order->sendNewOrderEmail();
        $order->setEmailSent(true);
        $order->save();
        
        return true;
    }
    
    /**
     *Create Order Invoice
     * 
     * @param boolean Returns true if an invoice has been created.
     */
    public function createInvoice($order, $response_params)
    {
        $result               = false;
        try {
            if (!$order->hasInvoices()) {
                $invoice = $order->prepareInvoice();
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                
                $invoice->register();
                
                $invoice->setTransactionId($response_params['id']);
                $transactionSave = Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder());
                $transactionSave->save();
                
                $order->addRelatedObject($invoice);
                
                $invoice->sendEmail();

                $result = true;
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
        return $result;
    }
    
    public function refillCart($order)
    {
        if (empty($order)) {
            return;
        }
        $session  = Mage::getSingleton('checkout/session');
        $cart     = Mage::getSingleton('checkout/cart');
        $cart_qty = (int) $cart->getQuote()->getItemsQty();
        if ($cart_qty) {
            return;
        }
        if ($order->getId()) {
            $items = $order->getItemsCollection();
            foreach ($items as $item) {
                try {
                    $cart->addOrderItem($item);
                } catch (Mage_Core_Exception $e) {
                    $session->addError($e->getMessage());
                    Mage::logException($e);
                    continue;
                }
            }
            $cart->save();
            $cart_qty = (int) $cart->getQuote()->getItemsQty();
        }
    }

}