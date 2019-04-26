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
 * Front Controller for Paymentsense Direct
 */
class Paymentsense_Payments_DirectController extends Mage_Core_Controller_Front_Action
{
    /** @var $_direct Paymentsense_Payments_Model_Direct */
    protected $_direct;

    /** @var $_helper Paymentsense_Payments_Helper_Data */
    protected $_helper;

    protected function _construct()
    {
        $this->_direct = Mage::getModel('paymentsense/direct');
        $this->_helper = Mage::helper('paymentsense');
    }

    /**
     * Redirects to the ACS
     */
    public function redirectAction()
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('paymentsense/redirect_direct')->toHtml()
        );
    }

    /**
     * Processes the response from the ACS (Access Control Server)
     */
    public function callbackAction()
    {
        $transactionResult = array(
            'StatusCode' => '',
            'Message'    => ''
        );
        $this->_direct->getLogger()->info('Callback request from the ACS has been received.');
        $checkoutSession = $this->_helper->getCheckoutSession();
        $checkoutSession->setPaymentsenseOrderId(null);
        $checkoutSession->setPaymentsenseAcsUrl(null);
        $checkoutSession->setPaymentsensePaReq(null);
        $checkoutSession->setPaymentsenseMD(null);
        $orderId = $checkoutSession->getLastRealOrderId();
        $order   = $checkoutSession->getLastRealOrder();
        if (isset($order) && isset($orderId)) {
            $postData          = $this->getRequest()->getPost();
            $transactionResult = $this->_direct->process3dsResponse($order, $postData);
        } else {
            $transactionResult['Message'] = "Order not found. Has your session expired?";
        }

        if ($transactionResult['Message'] == '') {
            $this->executeSuccessAction();
        } else {
            $this->executeFailureAction($transactionResult['Message']);
        }

        $this->_direct->getLogger()->info(
            'Callback request from the ACS has been processed. ' .
            'Transaction status code was "' . $transactionResult['StatusCode'] . '".'
        );
    }

    /**
     * Customer landing page for successful payment
     */
    public function executeSuccessAction()
    {
        $this->_direct->getLogger()->info('Success Action has been triggered.');
        $this->_redirect('checkout/onepage/success', array('_secure' => true));
        $this->_direct->getLogger()->info('A redirect to the Checkout Success Page has been set.');
    }

    /**
     * Customer landing page for unsuccessful payment
     *
     * @param string $message
     */
    public function executeFailureAction($message)
    {
        $this->_direct->getLogger()->info('Failure Action with message "' . $message . '" has been triggered.');
        $quote = $this->_helper->restoreQuote();
        if ($quote) {
            Mage::helper('checkout')->sendPaymentFailedEmail($quote, $message);
        }

        $this->_helper->getCheckoutSession()->addError($message);
        $this->_redirect('checkout/cart', array('_secure' => true));
        $this->_direct->getLogger()->info('A redirect to the Checkout Cart has been set.');
    }
}
