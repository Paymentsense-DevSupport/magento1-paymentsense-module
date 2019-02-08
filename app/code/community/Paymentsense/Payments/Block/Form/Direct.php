<?php
/*
 * Copyright (C) 2019 Paymentsense Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      Paymentsense
 * @copyright   2019 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Form block for Paymentsense Direct
 */
class Paymentsense_Payments_Block_Form_Direct extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('paymentsense/form/direct.phtml');
    }

    /**
     * Gets payment configuration
     *
     * @return Mage_Payment_Model_Config|Mage_Core_Model_Abstract
     */
    protected function _getConfig()
    {
        return Mage::getSingleton('payment/config');
    }

    /**
     * Gets credit card types
     *
     * @return array
     */
    public function getCcAvailableTypes()
    {
        $types = $this->_getConfig()->getCcTypes();
        if ($method = $this->getMethod()) {
            $availableTypes = $method->getConfigHelper()->getCardTypes();
            if ($availableTypes) {
                $availableTypes = explode(',', $availableTypes);
                $codes = array_keys($types);
                foreach ($codes as $code) {
                    if (!in_array($code, $availableTypes)) {
                        unset($types[$code]);
                    }
                }
            }
        }

        return $types;
    }

    /**
     * Gets credit card expiration months
     *
     * @return array
     */
    public function getCcMonths()
    {
        return array_merge(array($this->__('Month')), $this->_getConfig()->getMonths());
    }

    /**
     * Gets credit card expiration years
     *
     * @return array
     */
    public function getCcYears()
    {
        return array(0 => $this->__('Year')) + $this->_getConfig()->getYears();
    }

    /**
     * Gets the CV2 check requirement. Always "true".
     *
     * @return true
     */
    public function hasVerification()
    {
        return true;
    }
}
