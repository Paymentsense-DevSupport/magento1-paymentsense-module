<?php
/*
 * Copyright (C) 2018 Paymentsense Ltd.
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
 * @copyright   2018 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

use Paymentsense_Payments_Model_Psgw_TransactionStatus as TransactionStatus;

/**
 * Front Controller for Paymentsense Hosted
 */
class Paymentsense_Payments_HostedController extends Mage_Core_Controller_Front_Action
{
    /** @var $_hosted Paymentsense_Payments_Model_Hosted */
    protected $_hosted;

    /** @var $_helper Paymentsense_Payments_Helper_Data */
    protected $_helper;

    protected function _construct()
    {
        $this->_hosted = Mage::getModel('paymentsense/hosted');
        $this->_helper = Mage::helper('paymentsense');
    }

    /**
     * Redirects to the Hosted Payment Form
     *
     * @throws Varien_Exception
     */
    public function redirectAction()
    {
        $this->_helper->getCheckoutSession()->setPaymentsenseOrderId(null);
        if (!$this->_hosted->isOrderAvailable()) {
            $this->executeFailureAction(
                $this->_helper->__('Your session has expired or you have no items in your shopping cart.')
            );
        } else {
            $this->getResponse()->setBody(
                $this->getLayout()->createBlock('paymentsense/redirect_hosted')->toHtml()
            );
        }
    }

    /**
     * Processes the response from the Hosted Payment Form
     *
     * @throws Zend_Controller_Exception
     */
    public function callbackAction()
    {
        $this->_hosted->getLogger()->info('Callback request from the Hosted Payment Form has been received.');
        if (!$this->getRequest()->isPost()) {
            $this->_hosted->getLogger()->warning('Non-POST callback request triggering HTTP status code 400.');
            $this->getResponse()->setHttpResponseCode(
                Mage_Api2_Model_Server::HTTP_BAD_REQUEST
            );
            return;
        }

        $trxStatusAndMessage = $this->_hosted->getTrxStatusAndMessage($this->getRequest()->getPost());
        $this->processActions($trxStatusAndMessage);
        $this->_hosted->getLogger()->info('Callback request from the Hosted Payment Form has been processed.');
    }

    /**
     * Processes actions based on the transaction status
     *
     * @param array $trxStatusAndMessage Array containing transaction status and message
     *
     * @throws Zend_Controller_Exception
     */
    protected function processActions($trxStatusAndMessage)
    {
        switch ($trxStatusAndMessage['TrxStatus']) {
            case TransactionStatus::SUCCESS:
                $this->executeSuccessAction();
                break;
            case TransactionStatus::FAILED:
                $this->executeFailureAction($trxStatusAndMessage['Message']);
                break;
            case TransactionStatus::INVALID:
                $this->getResponse()->setHttpResponseCode(Mage_Api2_Model_Server::HTTP_UNAUTHORIZED);
                break;
        }
    }

    /**
     * Redirects to the Checkout Success Page
     */
    public function executeSuccessAction()
    {
        $this->_hosted->getLogger()->info('Success Action has been triggered.');
        $this->_redirect('checkout/onepage/success', array('_secure' => true));
        $this->_hosted->getLogger()->info('A redirect to the Checkout Success Page has been set.');
    }

    /**
     * Redirects to the Checkout Cart
     *
     * @param string $message
     */
    public function executeFailureAction($message)
    {
        $this->_hosted->getLogger()->info('Failure Action with message "' . $message . '" has been triggered.');
        $this->_helper->restoreQuote();
        $this->_helper->getCheckoutSession()->addError($message);
        $this->_redirect('checkout/cart', array('_secure' => true));
        $this->_hosted->getLogger()->info('A redirect to the Checkout Cart has been set.');
    }
}
