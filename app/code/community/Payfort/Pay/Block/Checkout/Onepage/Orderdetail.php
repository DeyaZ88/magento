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
 * Block for additional information  about  'recurring items'
 */

class Payfort_Pay_Block_Checkout_Onepage_Orderdetail extends Mage_Core_Block_Template
{

    public function getRecurringQuoteItems(){
        $checkoutSession = Mage::getSingleton('checkout/session');
        $quoteItems = $checkoutSession->getQuote()->getAllItems();
        $result = array();

        foreach ($quoteItems as $quoteItem) {
            if ($quoteItem) {
                $product = $quoteItem->getProduct();
                $issetRecurringOreder = (bool)$product->getIsRecurring();
                if ($issetRecurringOreder) {
                    $result[] = $quoteItem;
                }
            }
        }

        return $result;
    }

    /**
     * @param Mage_Sales_Model_Quote_Item $recQuoteItem
     * @return mixed
     */
    public function getRecurringItemMessage(Mage_Sales_Model_Quote_Item $recQuoteItem){

        $recurringItemMessage = '';
        $initialFeeMessage = '';
        $rowTotal = $recQuoteItem->getNominalRowTotal();

        $currency_symbol = Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol();

        $product = $recQuoteItem->getProduct();
        $productAdditionalInfo = unserialize($product->getCustomOption('info_buyRequest')->getValue());
        $deferredDateStamp = strtotime($productAdditionalInfo['recurring_profile_start_datetime']);
        if($deferredDateStamp){
            $initialFeeMessage = $this->__(" (initial fee only, 1st recurring fee will be charged on %s)",date('d-M-Y',$deferredDateStamp));
            $rowTotal = $recQuoteItem->getXpRecurringInitialFee() + $recQuoteItem->getInitialfeeTaxAmount();
        }

        $recurringItemMessage = $this
            ->__("%s%s for recurring product '%s' in your cart.",
                number_format($rowTotal, 2, '.', ''), $currency_symbol,$recQuoteItem->getName());

        $mainMessage = $recurringItemMessage.$initialFeeMessage;

        return $mainMessage;

    }

    public function getSimpleQuoteItemMessage(){
        $checkoutSession = Mage::getSingleton('checkout/session');
        $quot = $checkoutSession->getQuote();
        $simpleQuoteItemMessage = '';
        if($quot->getGrandTotal()){
            $currency_symbol = Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol();
            $simpleQuoteItemMessage = $this->__("%s%s for all non-recurring products in your cart.",number_format($quot->getGrandTotal(), 2, '.', ''), $currency_symbol);
        }

        return $simpleQuoteItemMessage;

    }


}
