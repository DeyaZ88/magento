<?php

class Start_Gateway_Block_Info_Gateway extends Mage_Payment_Block_Info
{
  protected function _prepareSpecificInformation($transport = null)
  {
    if (null !== $this->_paymentSpecificInformation)
    {
      return $this->_paymentSpecificInformation;
    }

    $data = array();
    if ($this->getInfo()->getPayfortToken())
    {
      $data[Mage::helper('payment')->__('token')] = $this->getInfo()->getPayfortToken();
    }

    if ($this->getInfo()->getPayfortEmail())
    {
      $data[Mage::helper('payment')->__('email')] = $this->getInfo()->getPayfortEmail();
    }

    $transport = parent::_prepareSpecificInformation($transport);

    return $transport->setData(array_merge($data, $transport->getData()));
  }
}