<?php

class Start_Gateway_Block_Form_Gateway extends Mage_Payment_Block_Form
{
  protected function _construct()
  {
        parent::_construct();
        $this->setTemplate('gateway/form/gateway.phtml');
  }
  protected function _prepareLayout()
    {
        if(Mage::app()->getLayout()->getBlock('head'))
            Mage::app()->getLayout()->getBlock('head')->addJs('gateway.js');

        return parent::_prepareLayout();
    }
}