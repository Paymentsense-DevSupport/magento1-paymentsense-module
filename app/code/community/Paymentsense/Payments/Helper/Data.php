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
 * Helper class containing functions for getting sessions and URLs and a function for restoring quote
 */
class Paymentsense_Payments_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Gets checkout session
     *
     * @return Mage_Checkout_Model_Session|Mage_Core_Model_Abstract
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Gets admin session
     *
     * @return Mage_Adminhtml_Model_Session|Mage_Core_Model_Abstract
     */
    public function getAdminSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * Gets the redirect URL to the Hosted Payment Form
     *
     * @return string
     */
    public function getHostedFormRedirectUrl()
    {
        return Mage::getUrl('paymentsense/hosted/redirect', array('_secure' => true));
    }

    /**
     * Gets the Callback URL
     *
     * @return string
     */
    public function getHostedFormCallbackUrl()
    {
        return Mage::getUrl('paymentsense/hosted/callback', array('_secure' => true));
    }

    /**
     * Gets the redirect URL to the ACS
     *
     * @return string
     */
    public function getAcsRedirectUrl()
    {
        return Mage::getUrl('paymentsense/direct/redirect', array('_secure' => true));
    }

    /**
     * Gets the callback URL where the customer will be redirected from the ACS
     *
     * @return string
     */
    public function getAcsCallbackUrl()
    {
        return Mage::getUrl('paymentsense/direct/callback', array('_secure' => true));
    }

    /**
     * Restores the quote
     *
     * @return Mage_Sales_Model_Quote|Mage_Core_Model_Abstract|null
     */
    public function restoreQuote()
    {
        $quote = null;
        $order = $this->getCheckoutSession()->getLastRealOrder();
        if ($order->getId()) {
            $quoteId = $order->getQuoteId();
            if ($quoteId) {
                $quote = Mage::getModel('sales/quote')->load($quoteId);
                if ($quote->getId()) {
                    $quote->setIsActive(1)
                        ->setReservedOrderId(null)
                        ->save();
                    $this->getCheckoutSession()
                        ->replaceQuote($quote)
                        ->unsLastRealOrderId();
                }
            }
        }

        return $quote;
    }

    /**
     * Converts an array to string
     *
     * @param array $arr
     * @return string
     */
    public function convertArrayToString($arr)
    {
        $result = '';
        foreach ($arr as $key => $value) {
            if ($result !== '') {
                $result .= PHP_EOL;
            }

            $result .= $key . ': ' . $value;
        }

        return $result;
    }
}
