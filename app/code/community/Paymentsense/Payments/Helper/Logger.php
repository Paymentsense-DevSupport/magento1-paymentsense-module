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
 * Log functions
 */
class Paymentsense_Payments_Helper_Logger extends Mage_Core_Helper_Abstract
{
    protected $_filename = null;
    protected $_logLevel = null;

    /**
     * Initialisation
     *
     * @param string $method
     */
    public function init($method)
    {
        if ($method instanceof Mage_Payment_Model_Method_Abstract) {
            $this->_filename = $method->getCode() . '.log';
            $this->_logLevel = $method->getConfigHelper()->getLogLevel();
        } else {
            Mage::logException(
                new Exception(
                    'An error occurred while trying to initialise logger helper: Invalid payment method.'
                )
            );
        }
    }

    /**
     * Logs error messages to the Paymentsense log
     *
     * Requires Log Level 1 or higher
     *
     * @param string $message
     */
    public function error($message)
    {
        if ($this->_logLevel>=1) {
            self::log($message, Zend_Log::ERR);
        }
    }

    /**
     * Logs warning messages to the Paymentsense log
     *
     * Requires Log Level 2 or higher
     *
     * @param string $message
     */
    public function warning($message)
    {
        if ($this->_logLevel>=2) {
            self::log($message, Zend_Log::WARN);
        }
    }

    /**
     * Logs info messages to the Paymentsense log
     *
     * Requires Log Level 3 or higher
     *
     * @param string $message
     */
    public function info($message)
    {
        if ($this->_logLevel>=3) {
            self::log($message, Zend_Log::INFO);
        }
    }

    /**
     * Logs debug messages to the Paymentsense log
     *
     * Does not depend on the Log Level configuration.
     * For debugging only. Do not use in production.
     *
     * @param string $message
     */
    public function debug($message)
    {
        self::log($message, Zend_Log::DEBUG);
    }

    /**
     * Logs messages to the Paymentsense log
     *
     * @param string $message
     * @param integer $level
     */
    public function log($message, $level = null)
    {
        if (empty($this->_filename)) {
            Mage::log(
                'The Paymentsense log is not initialised. The Magento system log (this log) will be used instead.',
                Zend_Log::ERR,
                '',
                true
            );
            $message = 'Paymentsense: ' . $message;
        }

        Mage::log($message, $level, $this->_filename, true);
    }
}
