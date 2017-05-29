<?php

class Payfort_Start_PaymentController extends Mage_Core_Controller_Front_Action
{   
    public function __construct(\Zend_Controller_Request_Abstract $request, \Zend_Controller_Response_Abstract $response, array $invokeArgs = array())
    {
        parent::__construct($request, $response, $invokeArgs);
    }
    public function indexAction()
    {
        return;
    }

    // The redirect action is triggered when someone places an order
    public function redirectAction()
    {
        
        
        $_order = $this->_getLastOrder();
        
        //Loading current layout
        $this->loadLayout();
        
        $paymentMethod = $_order->getPayment()->getMethodInstance();

        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $checkoutData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();

        $totals = $quote->getTotals(); //Total object
        //$checkoutData['grandTotal'] = $totals["grand_total"]->getValue(); //Grandtotal value
        $checkoutData['grandTotal'] = $_order->getGrandTotal(); //Grandtotal value
        
        //$checkoutData['email'] = $quote->getBillingAddress()->getEmail();
        $email = $_order->getBillingAddress()->getEmail();
        if(empty($email)) {
            $email = $_order->getCustomerEmail();
        }
        $checkoutData['email'] = $_order->getBillingAddress()->getEmail();

        $checkoutData['message'] = $paymentMethod->getConfigData('message');


        if (file_exists(MAGENTO_ROOT . '/data/currencies.json')) {
            $currency_json_data = json_decode(file_get_contents(MAGENTO_ROOT . '/data/currencies.json'), 1);
            $checkoutData['currency_multiplier'] = floatval($currency_json_data[$checkoutData['currency']]);
        } else {
            $checkoutData['currency_multiplier'] = floatval(100);
        }

        if ( $paymentMethod->getConfigData('test_mode') ){
            $checkoutData['key'] = $paymentMethod->getConfigData('test_open_key');
        }
        else{
            $checkoutData['key'] = $paymentMethod->getConfigData('live_open_key');
        }
        
        //Creating a new block
        $this->getLayout()->getBlock('head')->addJs('payfort/payfort_start.js');
        $template = 'gateway.phtml';
        
        $block = $this->getLayout()->createBlock(
                            'Mage_Core_Block_Template', 'payfort_start_block', array('template' => 'payfort/start/form/'.$template)
                    )
                    ->setData('checkoutData', $checkoutData);
        
        $this->getLayout()->getBlock('content')->append($block);

        //Now showing it with rendering of layout
        $this->renderLayout();
    }
    
    public function processpaymentAction()
    {
        $_order = $this->_getLastOrder();
        
        $payment = $_order->getPayment();
        
        $token = isset($_POST['payfortToken']) ? $_POST['payfortToken'] : false;
        $email = isset($_POST['payfortEmail']) ? $_POST['payfortEmail'] : false;
        
        if (!$token || !$email) {
            Mage_Core_Controller_Varien_Action::_redirect('checkout/cart', array('_secure' => true));
        }
        
        $payment->getMethodInstance()->authorize($payment, $_order->getTotalDue());
    }
    
    public function cancelpaymentAction()
    {
        $_order = $this->_getLastOrder();
        $payment = $_order->getPayment();
        $errorMsg = 'You have canceled the payment, please try again.';
        $payment->getMethodInstance()->declineOrder($_order, 'User canceled the payment');
        $payment->getMethodInstance()->refillCart($_order);
        $session = Mage::getSingleton('checkout/session');
        $session->addError($errorMsg);
        header('location:'.Mage::getUrl('checkout/cart', array('_secure' => true)));
        //$this->_redirect(Mage::getUrl('checkout/cart', array('_secure' => true)));
        exit;
    }
    
    private function _getLastOrder() 
    {
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $_order  = Mage::getModel('sales/order');
        $_order->loadByIncrementId($orderId);
        
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        
        if (!$_order || !$orderId || !$quote) {
            //Mage_Core_Controller_Varien_Action::_redirect('checkout/cart', array('_secure' => true));
            header('location:'.Mage::getUrl('checkout/cart', array('_secure' => true)));
            exit;
        }
        

        if ($_order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
//            $_order->setState(
//                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, (bool) Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $this->__('Payfort Start pending payment.')
//            )->save();
            //Mage_Core_Controller_Varien_Action::_redirect('checkout/cart', array('_secure' => true));
            header('location:'.Mage::getUrl('checkout/cart', array('_secure' => true)));
            exit;
        }
        
        return $_order;
    }

}
