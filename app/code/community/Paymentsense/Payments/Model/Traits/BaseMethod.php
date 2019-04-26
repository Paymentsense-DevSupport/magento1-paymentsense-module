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

use Paymentsense_Payments_Model_Psgw_TransactionResultCode as TransactionResultCode;

/**
 * Trait containing class methods common for all payment methods
 */
trait Paymentsense_Payments_Model_Traits_BaseMethod
{
    use Paymentsense_Payments_Model_Traits_Transactions;

    protected $_method = null;
    protected $_helper = null;
    protected $_logger = null;

    /**
     * Gets the payment model method
     *
     * @param $method
     * @return mixed
     */
    public function getMethod($method)
    {
        if (!($this->_method instanceof Mage_Core_Model_Abstract)) {
            $this->_method = Mage::getModel($method);
        }

        return $this->_method;
    }

    /**
     * Gets the Data helper
     *
     * @param string $helper Name of the helper, if empty, defaults to the Data helper
     * @return Paymentsense_Payments_Helper_Data|mixed
     */
    public function getHelper($helper = 'paymentsense')
    {
        return Mage::helper($helper);
    }

    /**
     * Gets the Config helper
     *
     * @return Paymentsense_Payments_Helper_Config
     */
    public function getConfigHelper()
    {
        if (!($this->_helper instanceof Paymentsense_Payments_Helper_Config)) {
            $this->_helper = $this->getHelper('paymentsense/Config');
            $this->_helper->init($this);
        }

        return $this->_helper;
    }

    /**
     * Gets the Logger helper
     *
     * @return Paymentsense_Payments_Helper_Logger
     */
    public function getLogger()
    {
        if (!($this->_logger instanceof Paymentsense_Payments_Helper_Logger)) {
            $this->_logger = $this->getHelper('paymentsense/Logger');
            $this->_logger->init($this);
        }

        return $this->_logger;
    }

    /**
     * Gets the ISO Codes helper
     *
     * @return Paymentsense_Payments_Helper_IsoCodes|mixed
     */
    public function getIsoCodesHelper()
    {
        return $this->getHelper('paymentsense/IsoCodes');
    }

    /**
     * Sets an order status based on transaction status
     *
     * @param Mage_Sales_Model_Order $order
     * @param string $status
     * @param string $message
     *
     * @throws Exception
     */
    public function setOrderState($order, $status, $message = '')
    {
        switch ($status) {
            case TransactionResultCode::SUCCESS:
                $order
                    ->setState(
                        Mage_Sales_Model_Order::STATE_PROCESSING,
                        Mage_Sales_Model_Order::STATE_PROCESSING,
                        $message,
                        false
                    )
                    ->save();
                break;

            case TransactionResultCode::INCOMPLETE:
            case TransactionResultCode::REFERRED:
                $order
                    ->setState(
                        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                        $message,
                        false
                    )
                    ->save();
                break;

            case TransactionResultCode::DECLINED:
            case TransactionResultCode::FAILED:
                foreach ($order->getInvoiceCollection() as $invoice) {
                    $invoice->cancel();
                }

                $order
                    ->registerCancellation($message)
                    ->setCustomerNoteNotify(true)
                    ->save();
                break;

            default:
                $order->save();
                break;
        }
    }

    /**
     * Determines whether the payment method is configured
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->getConfigHelper()->isMethodActive();
    }

    /**
     * Determines whether the payment method is configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        return $this->getConfigHelper()->isMethodConfigured();
    }

    /**
     * Determines whether the store is secure
     *
     * @return bool
     *
     * @throws Mage_Core_Model_Store_Exception
     */
    public function isSecure()
    {
        return Mage::app()->getStore()->isCurrentlySecure();
    }
}
