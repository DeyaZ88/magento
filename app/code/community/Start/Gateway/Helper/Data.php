<?php

/**
 * start gateway Data Helper
 *
 * @category   Start
 * @package    Start_Gateway
 * @author     Haris Ahmed <haris.ahmed@eweberinc.com>
 */
class Start_Gateway_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Return URL for admin area
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getAdminUrl($route, $params)
    {
        return Mage::getModel('adminhtml/url')->getUrl($route, $params);
    }

    /**
     * Set secure url checkout is secure for current store.
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    protected function _getUrl($route, $params = array())
    {
        $params['_type'] = Mage_Core_Model_Store::URL_TYPE_LINK;
        if (isset($params['is_secure'])) {
            $params['_secure'] = (bool)$params['is_secure'];
        } elseif (Mage::app()->getStore()->isCurrentlySecure()) {
            $params['_secure'] = true;
        }
        return parent::_getUrl($route, $params);
    }

    /**
     * Retrieve save order url params
     *
     * @param string $controller
     * @return array
     */
    public function getSaveOrderUrlParams($controller)
    {
        $route = array();
        switch ($controller) {
            case 'onepage':
                $route['action'] = 'saveOrder';
                $route['controller'] = 'onepage';
                $route['module'] = 'checkout';
                break;

            case 'sales_order_create':
            case 'sales_order_edit':
                $route['action'] = 'save';
                $route['controller'] = 'sales_order_create';
                $route['module'] = 'admin';
                break;

            default:
                break;
        }

        return $route;
    }

}
