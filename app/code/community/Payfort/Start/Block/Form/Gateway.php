<?php

class Payfort_Start_Block_Form_Gateway extends Mage_Payment_Block_Form
{
  protected function _construct()
  {
        parent::_construct();
        $this->setTemplate('payfort/start/form/gateway.phtml');
  }
  protected function _prepareLayout()
    {
        if(Mage::app()->getLayout()->getBlock('head'))
            Mage::app()->getLayout()->getBlock('head')->addJs('payfort/payfort_start.js');

        return parent::_prepareLayout();
    }
}