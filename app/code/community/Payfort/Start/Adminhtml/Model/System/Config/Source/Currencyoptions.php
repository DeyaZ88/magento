<?php

class Payfort_Start_Adminhtml_Model_System_Config_Source_Currencyoptions {
    /*     * */

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        return array(
            array('value' => 'USD', 'label' => Mage::helper('adminhtml')->__('USD')),
            array('value' => 'AED', 'label' => Mage::helper('adminhtml')->__('AED')),
            //array('value' => 'no_currency', 'label' => Mage::helper('adminhtml')->__('Use Default')),
        );
    }

}
