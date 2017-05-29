<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @author     Valerii Demidov
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) Qualiteam Software Ltd. <info@qtmsoft.com>. All rights reserved.
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Rewrite for success block for failed order transaction.
*/

class Cdev_XPaymentsConnector_Block_Checkout_Onepage_Success extends Mage_Checkout_Block_Onepage_Success
{

    protected function _prepareLastOrder()
    {
        $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        if ($orderId) {
            $order = Mage::getModel('sales/order')->load($orderId);
            if ($order->getId()) {
                $isVisible = !in_array($order->getState(),
                    Mage::getSingleton('sales/order_config')->getInvisibleOnFrontStates());
                $this->addData(array(
                    'is_order_visible' => $isVisible,
                    'view_order_id' => $this->getUrl('sales/order/view/', array('order_id' => $orderId)),
                    'print_url' => $this->getUrl('sales/order/print', array('order_id'=> $orderId)),
                    'can_print_order' => $isVisible,
                    'can_view_order'  => Mage::getSingleton('customer/session')->isLoggedIn() && $isVisible,
                    'order_id'  => $order->getIncrementId(),
                    'order_status'=> $order->getStatus(),
                    'order_entity_id' => $orderId
                ));
            }
        }
    }

    /**
     * This function check (Is the order was successful?)
     * @return bool
     */
    public function checkOrderSuccess()
    {
        if (Mage_Sales_Model_Order::STATE_CANCELED == $this->_getData('order_status')) {
            return false;
        }
        return true;

    }

    /**
     * @return string
     */
    public function getButtonUrl()
    {
        if ($this->getOrderId()) {
            if (!$this->checkOrderSuccess()) {
                return $this->getUrl('sales/order/reorder',
                    array('order_id' => $this->getData('order_entity_id'), '_secure' => true));
            }
        }
        return $this->getUrl();
    }

    /**
     * @return string
     */
    public function getButtonLabel()
    {
        if ($this->checkOrderSuccess()) {
            return $this->__('Continue Shopping');
        } else {
            return $this->__('Return to checkout');
        }
    }

}
