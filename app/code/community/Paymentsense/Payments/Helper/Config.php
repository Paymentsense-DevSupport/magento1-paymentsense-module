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
 * Config Helper
 * Used for retrieving configuration data
 */
class Paymentsense_Payments_Helper_Config extends Mage_Core_Helper_Abstract
{
    protected $_method = null;

    /**
     * Initialises the the payment model method
     *
     * @param string $method
     */
    public function init($method)
    {
        if ($method instanceof Mage_Payment_Model_Method_Abstract) {
            $this->_method = $method;
        } else {
            Mage::logException(
                new Exception(
                    'An error occurred while trying to initialise configuration helper: Invalid payment method.'
                )
            );
        }
    }

    /**
     * Retrieves configuration information
     *
     * @param string $field
     * @return mixed
     */
    public function getConfigData($field)
    {
        $result = null;
        $function = 'getConfigData';
        if ($this->_method instanceof Mage_Payment_Model_Method_Abstract &&
            is_callable(array($this->_method, $function))) {
            $result = $this->_method->$function($field);
        } else {
            Mage::logException(
                new Exception(
                    'An error occurred while trying to get configuration data: Payment method invalid or not set.'
                )
            );
        }

        return $result;
    }

    /**
     * Retrieves boolean configuration information
     *
     * @param string $field
     * @return string
     */
    public function getBoolConfigData($field)
    {
        return $this->getBool($this->getConfigData($field));
    }

    /**
     * Converts boolean to string
     *
     * @param bool $value
     * @return string
     */
    public static function getBool($value)
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Checks whether the payment method is active
     *
     * @return bool
     */
    public function isMethodActive()
    {
        $result = (bool) $this->getConfigData('active');
        if (!$result) {
            $this->_method->getLogger()->info('Payment method is not active.');
        }

        return $result;
    }

    /**
     * Checks whether the payment gateway credentials are configured
     *
     * @return bool
     */
    public function isMethodConfigured()
    {
	    $merchantId      = $this->getMerchantId();
	    $password        = $this->getPassword();
	    $transactionType = $this->getTransactionType();
	    $presharedKey    = $this->getPresharedKey();

	    $result = !empty($merchantId) &&
		    !empty($password) &&
		    !empty($transactionType) &&
		    ($this->_method instanceof Paymentsense_Payments_Model_Card || !empty($presharedKey));

        if (!$result) {
            $this->_method->getLogger()->info('Payment method is not configured.');
        }

        return $result;
    }

    /**
     * Gets payment gateway Merchant ID
     *
     * @return string|null
     */
    public function getMerchantId()
    {
        return $this->getConfigData('merchant_id');
    }

    /**
     * Gets payment gateway Password
     *
     * @return string|null
     */
    public function getPassword()
    {
        return $this->getConfigData('password');
    }

    /**
     * Gets payment gateway Pre-shared Key
     *
     * @return string|null
     */
    public function getPresharedKey()
    {
        return $this->getConfigData('preshared_key');
    }

    /**
     * Gets payment gateway Hash Method
     *
     * @return string|null
     */
    public function getHashMethod()
    {
        return $this->getConfigData('hash_method');
    }

    /**
     * Gets Transaction Type
     *
     * @return string|null
     */
    public function getTransactionType()
    {
        return $this->getConfigData('transaction_type');
    }

    /**
     * Gets Result Delivery Method
     *
     * @return string|null
     */
    public function getResultDeliveryMethod()
    {
        return $this->getConfigData('result_delivery_method');
    }

    /**
     * Gets Email Address Editable
     *
     * @return string
     */
    public function getEmailAddressEditable()
    {
        return $this->getBoolConfigData('email_address_editable');
    }

    /**
     * Gets Phone Number Editable
     *
     * @return string
     */
    public function getPhoneNumberEditable()
    {
        return $this->getBoolConfigData('phone_number_editable');
    }

    /**
     * Gets Address1 Mandatory
     *
     * @return string
     */
    public function getAddress1Mandatory()
    {
        return $this->getBoolConfigData('address1_mandatory');
    }

    /**
     * Gets City Mandatory
     *
     * @return string
     */
    public function getCityMandatory()
    {
        return $this->getBoolConfigData('city_mandatory');
    }

    /**
     * Gets State Mandatory
     *
     * @return string
     */
    public function getStateMandatory()
    {
        return $this->getBoolConfigData('state_mandatory');
    }

    /**
     * Gets Postcode Mandatory
     *
     * @return string
     */
    public function getPostcodeMandatory()
    {
        return $this->getBoolConfigData('postcode_mandatory');
    }

    /**
     * Gets Country Mandatory
     *
     * @return string
     */
    public function getCountryMandatory()
    {
        return $this->getBoolConfigData('country_mandatory');
    }

    /**
     * Gets Credit Card Types
     *
     * @return string|null
     */
    public function getCardTypes()
    {
        return $this->getConfigData('cctypes');
    }

    /**
     * Gets Log Level
     *
     * @return int
     */
    public function getLogLevel()
    {
        return (int) $this->getConfigData('log_level');
    }

    /**
     * Gets Port 4430 is NOT open on my server
     *
     * @return string
     */
    public function getPort4430NotOpen()
    {
        return $this->getConfigData('port_4430_not_open');
    }
}
