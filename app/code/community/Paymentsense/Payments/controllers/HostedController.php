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

use Paymentsense_Payments_Model_Psgw_TransactionStatus as TransactionStatus;

/**
 * Front Controller for Paymentsense Hosted
 */
class Paymentsense_Payments_HostedController extends Mage_Core_Controller_Front_Action
{
    /**
     * Request Types
     */
    const REQ_NOTIFICATION      = '0';
    const REQ_CUSTOMER_REDIRECT = '1';

    /**
     * Response Status Codes (used in the processing of the notification of the SERVER result delivery method)
     */
    const STATUS_CODE_OK    = '0';
    const STATUS_CODE_ERROR = '30';

    /**
     * Response Messages (used in the processing of the notification of the SERVER result delivery method)
     */
    const MSG_SUCCESS              = 'Request processed successfully.';
    const MSG_NON_POST_HTTP_METHOD = 'Non-POST HTTP Method.';
    const MSG_HASH_DIGEST_ERROR    = 'Invalid Hash Digest.';
    const MSG_INVALID_ORDER        = 'Invalid Order.';

    /** @var $_hosted Paymentsense_Payments_Model_Hosted */
    protected $_hosted;

    /** @var $_helper Paymentsense_Payments_Helper_Data */
    protected $_helper;

    /**
     * An array containing the status code and message outputted on the response of the gateway callbacks
     *
     * @var array
     */
    protected $_responseVars = array(
        'status_code' => '',
        'message'     => '',
    );

    protected function _construct()
    {
        $this->_hosted = Mage::getModel('paymentsense/hosted');
        $this->_helper = Mage::helper('paymentsense');
    }

    /**
     * Redirects to the Hosted Payment Form
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
     * Processes the callbacks received from the Hosted Payment Form
     */
    public function callbackAction()
    {
        switch ($this->_hosted->getResultDeliveryMethod()) {
            case 'POST':
                $this->processPostResponse();
                break;
            case 'SERVER':
                switch ($this->getRequestType()) {
                    case self::REQ_NOTIFICATION:
                        $this->processServerNotification();
                        break;
                    case self::REQ_CUSTOMER_REDIRECT:
                        $this->processServerCustomerRedirect();
                        break;
                }
                break;
            default:
                $this->_hosted->getLogger()->info('Unsupported Result Delivery Method.');
                break;
        }
    }

    /**
     * Gets the request type (notification or customer redirect)
     *
     * @return string
     */
    public function getRequestType()
    {
        $postData = array();
        if ($this->getRequest()->isPost()) {
            $postData = $this->getRequest()->getPost();
        }

        return array_key_exists('StatusCode', $postData) && is_numeric($postData['StatusCode'])
            ? self::REQ_NOTIFICATION
            : self::REQ_CUSTOMER_REDIRECT;
    }

    /**
     * Processes the response of the POST result delivery method
     */
    public function processPostResponse()
    {
        $this->_hosted->getLogger()->info('POST Callback request from the Hosted Payment Form has been received.');
        if (!$this->getRequest()->isPost()) {
            $this->_hosted->getLogger()->warning('Non-POST HTTP Method triggering HTTP status code 400.');
            $this->getResponse()->setHttpResponseCode(
                Mage_Api2_Model_Server::HTTP_BAD_REQUEST
            );
            return;
        }

        $data = $this->getRequest()->getPost();

        $trxStatusAndMessage = $this->_hosted->getTrxStatusAndMessage($this->getRequestType(), $data);

        if ($trxStatusAndMessage['TrxStatus'] !== TransactionStatus::INVALID) {
            $order = $this->_hosted->getOrder($data);
            if ($order) {
                $this->_hosted->updatePayment($order, $data);
            }
        }

        $this->processActions($trxStatusAndMessage);
        $this->_hosted->getLogger()->info('POST Callback request from the Hosted Payment Form has been processed.');
    }

    /**
     * Processes the notification of the SERVER result delivery method
     */
    public function processServerNotification()
    {
        $this->_hosted->getLogger()->info('SERVER Notification from the Hosted Payment Form has been received.');
        if (!$this->getRequest()->isPost()) {
            $this->_hosted->getLogger()->warning('Non-POST HTTP Method responding with an error to the gateway.');
            $this->setError(self::MSG_NON_POST_HTTP_METHOD);
        } else {
            $data = $this->getRequest()->getPost();

            $trxStatusAndMessage = $this->_hosted->getTrxStatusAndMessage($this->getRequestType(), $data);

            if ($trxStatusAndMessage['TrxStatus'] !== TransactionStatus::INVALID) {
                $order = $this->_hosted->getOrder($data);
                if ($order) {
                    $this->_hosted->updatePayment($order, $data);
                    $this->setSuccess();
                } else {
                    $this->setError(self::MSG_INVALID_ORDER);
                }
            } else {
                $this->setError(self::MSG_HASH_DIGEST_ERROR);
            }

            $this->outputResponse();
            $this->_hosted->getLogger()->info('SERVER Notification from the Hosted Payment Form has been processed.');
        }
    }

    /**
     * Processes the customer redirect of the SERVER result delivery method
     */
    public function processServerCustomerRedirect()
    {
        $this->_hosted->getLogger()->info('SERVER Customer Redirect from the Hosted Payment Form has been received.');

        $data = $this->getRequest()->getQuery();

        if ($this->_hosted->isHashDigestValid($this->getRequestType(), $data)) {
            $trxStatusAndMessage = $this->_hosted->loadTrxStatusAndMessage($data);
        } else {
            $this->_hosted->getLogger()->warning('Callback request with invalid hash digest has been received.');
            $trxStatusAndMessage = array(
                'TrxStatus' => TransactionStatus::INVALID,
                'Message'   => ''
            );
        }

        $this->processActions($trxStatusAndMessage);
        $this->_hosted->getLogger()->info('SERVER Customer Redirect from the Hosted Payment Form has been processed.');
    }

    /**
     * Processes actions based on the transaction status
     *
     * @param array $trxStatusAndMessage Array containing transaction status and message
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
        $quote = $this->_helper->restoreQuote();
        if ($quote) {
            Mage::helper('checkout')->sendPaymentFailedEmail($quote, $message);
        }
        
        $this->_helper->getCheckoutSession()->addError($message);
        $this->_redirect('checkout/cart', array('_secure' => true));
        $this->_hosted->getLogger()->info('A redirect to the Checkout Cart has been set.');
    }

    /**
     * Sets the success response message and status code
     */
    protected function setSuccess()
    {
        $this->setResponse(self::STATUS_CODE_OK, self::MSG_SUCCESS);
    }

    /**
     * Sets the error response message and status code
     *
     * @param string $message Response message.
     */
    protected function setError($message)
    {
        $this->setResponse(self::STATUS_CODE_ERROR, $message);
    }

    /**
     * Sets the response variables
     *
     * @param string $statusCode Response status code.
     * @param string $message Response message.
     */
    protected function setResponse($statusCode, $message)
    {
        $this->_responseVars['status_code'] = $statusCode;
        $this->_responseVars['message']     = $message;
    }

    /**
     * Outputs the response
     */
    protected function outputResponse()
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock(
                'paymentsense/response_hosted',
                '',
                $this->_responseVars
            )->toHtml()
        );
    }
}
