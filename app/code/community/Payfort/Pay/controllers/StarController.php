<?php

class Payfort_Fort_StarController extends Mage_Core_Controller_Front_Action
{   
    public function __construct(\Zend_Controller_Request_Abstract $request, \Zend_Controller_Response_Abstract $response, array $invokeArgs = array())
    {
        
        parent::__construct($request, $response, $invokeArgs);
    }
    
    public function indexAction()
    {
        echo "<pre>";
        var_dump('dsadsa');
        echo "</pre>";
        exit;
        return;
    }
    

}
