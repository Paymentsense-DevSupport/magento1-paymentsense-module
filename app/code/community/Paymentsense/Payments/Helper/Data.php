<?php
/*
 * Copyright (C) 2020 Paymentsense Ltd.
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
 * @copyright   2020 Paymentsense Ltd.
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
     * Determines whether the format of the merchant ID matches the ABCDEF-1234567 format
     *
     * @param string $merchantId Merchant ID
     * @return bool
     */
    public function isMerchantIdFormatValid($merchantId)
    {
        return (bool) preg_match('/^[a-zA-Z]{6}-[0-9]{7}$/', $merchantId);
    }

    /**
     * Retrieves the hostname from an URL
     *
     * @param string $url URL
     * @return string
     */
    public function getHostname($url)
    {
        $modelUrl = new Mage_Core_Model_Url();
        $modelUrl->parseUrl($url);
        return $modelUrl->getHost();
    }

    /**
     * Builds a pair of a local and a remote timestamp
     *
     * @param DateTime $remoteDateTime
     * @return array
     */
    public function buildDateTimePair($remoteDateTime)
    {
        $localDateTime = DateTime::createFromFormat('Y-m-d H:i:s', Mage::getSingleton('core/date')->gmtDate());
        return array($localDateTime, $remoteDateTime);
    }

    /**
     * Retrieves the value of the Date field from an HTTP header
     *
     * @param string $header
     * @return DateTime|false
     */
    public function retrieveDate($header)
    {
        $result = false;
        if (preg_match('/Date: (.*)\b/', $header, $matches)) {
            $date = strip_tags($matches[1]);
            $result = DateTime::createFromFormat('D, d M Y H:i:s e', $date);
        }

        return $result;
    }
}
