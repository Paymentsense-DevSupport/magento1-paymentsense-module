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

use Paymentsense_Payments_Model_Psgw_Psgw as Psgw;
use Paymentsense_Payments_Model_Psgw_TransactionResultCode as TransactionResultCode;
use Paymentsense_Payments_Model_Psgw_TransactionType as TransactionType;

/**
 * Abstract Card class used by the Direct and MOTO payment methods
 */
abstract class Paymentsense_Payments_Model_Card extends Mage_Payment_Model_Method_Cc
{
    use Paymentsense_Payments_Model_Traits_BaseMethod;

    protected $_code;
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canRefund               = true;
    protected $_canVoid                 = true;
    protected $_isInitializeNeeded      = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canUseForMultishipping  = false;
    protected $_canSaveCc               = false;

    /**
     * Checks if the payment method is available
     *
     * @param Mage_Sales_Model_Quote|null $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        return parent::isAvailable($quote) &&
            $this->getConfigHelper()->isMethodActive() &&
            $this->getConfigHelper()->isMethodConfigured() &&
            Mage::app()->getStore()->isCurrentlySecure();
    }

    /**
     * Determines whether the processing of 3-D Secure enrolled cards is supported
     *
     * @return bool
     */
    public function is3dsSupported()
    {
        return $this->_canUseCheckout;
    }

    /**
     * Assigns the card data to internal variables
     *
     * @param mixed $data
     * @return $this
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();
        $info->setCcOwner($data->getCcOwner())
             ->setCcNumber($data->getCcNumber())
             ->setCcCid($data->getCcCid())
             ->setCcExpMonth($data->getCcExpMonth())
             ->setCcExpYear($data->getCcExpYear())
             ->setCcType($data->getCcType());
        return $this;
    }

    /**
     * Builds the data for the form redirecting to the ACS
     *
     * @return array
     */
    public function buildAcsFormData()
    {
        $fields = null;
        $checkoutSession = $this->getHelper()->getCheckoutSession();
        if (!empty($checkoutSession)) {
            $order = $checkoutSession->getLastRealOrder();
            if (!empty($order)) {
                $orderId = $order->getRealOrderId();
                if ($orderId === $checkoutSession->getPaymentsenseOrderId()) {
                    $termUrl = $this->getHelper()->getAcsCallbackUrl();
                    $fields  = array(
                        'url'      => $checkoutSession->getPaymentsenseAcsUrl(),
                        'elements' => array(
                            'PaReq'   => $checkoutSession->getPaymentsensePaReq(),
                            'MD'      => $checkoutSession->getPaymentsenseMD(),
                            'TermUrl' => $termUrl
                        )
                    );
                    $this->getLogger()->info('Preparing ACS redirect for order #' . $orderId);
                }
            }
        }

        return $fields;
    }

    /**
     * Gets the payment action based on the transaction type
     *
     * @return string
     */
    public function getConfigPaymentAction()
    {
        $action = null;
        $config = $this->getConfigHelper();
        $transactionType = $config->getTransactionType();
        switch ($transactionType) {
            case TransactionType::PREAUTH:
                $action = Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
                break;
            case TransactionType::SALE:
                $action = Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE;
                break;
            default:
                $message = sprintf($this->getHelper()->__('Transaction type is "%s" not supported', $transactionType));
                $this->getLogger()->error($message);
                Mage::throwException($message);
        }

        return $action;
    }

    /**
     * Authorize handler
     *
     * Performs PREAUTH transaction for new orders placed using the "ACTION_AUTHORIZE" action
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     *
     * @throws \Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $this->getLogger()->info('ACTION_AUTHORIZE has been triggered.');
        $order = $payment->getOrder();
        if ($this->_canUseCheckout) {
            $order->setCanSendNewEmailFlag(false);
        }
        $orderId = $order->getIncrementId();
        $this->getLogger()->info('Preparing PREAUTH transaction for order #' . $orderId);
        return $this->processInitialTransaction($payment, $amount);
    }

    /**
     *  Capture handler
     *
     *  Performs SALE transaction for new orders placed using the "ACTION_AUTHORIZE_CAPTURE" action
     *  and COLLECTION transaction for existing orders
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     *
     * @throws \Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        $errorMessage = '';
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();

        $authTransaction = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);

        if ($authTransaction) {
            // Existing order
            try {
                $this->getLogger()->info('Preparing COLLECTION transaction for order #' . $orderId);
                $this->performCollection($payment, $amount, $authTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            // New order
            $this->getLogger()->info('ACTION_AUTHORIZE_CAPTURE has been triggered.');
            if ($this->_canUseCheckout) {
                $order->setCanSendNewEmailFlag(false);
            }

            $config = $this->getConfigHelper();
            $this->getLogger()->info(
                'Preparing ' . $config->getTransactionType() . ' transaction for order #' . $orderId
            );
            return $this->processInitialTransaction($payment, $amount);
        }

        if ($errorMessage !== '') {
            $this->getLogger()->warning($errorMessage);
            Mage::throwException($errorMessage);
        }

        return $this;
    }

    /**
     * Builds the input data array for the initial transaction (Card Details Transaction)
     *
     * @param Varien_Object $payment
     * @param $amount
     * @return array
     */
    protected function buildInitialTransactionData($payment, $amount)
    {
        $config         = $this->getConfigHelper();
        $order          = $payment->getOrder();
        $orderId        = $order->getRealOrderId();
        $billingAddress = $order->getBillingAddress();

        if (empty($billingAddress)) {
            Mage::throwException($this->getHelper()->__('Billing address is empty.'));
        }

        $transactionType = $config->getTransactionType();

        $ccOwner = $payment->getCcOwner();

        $cardName = !empty($ccOwner)
            ? $ccOwner
            : $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname();

        return array(
            'MerchantID'       => $config->getMerchantId(),
            'Password'         => $config->getPassword(),
            'Amount'           => $amount * 100,
            'CurrencyCode'     => $this->getIsoCodesHelper()->getCurrencyIsoCode($order->getOrderCurrencyCode()),
            'TransactionType'  => $transactionType,
            'OrderID'          => $orderId,
            'OrderDescription' => $order->getRealOrderId() . ': New order',
            'CardName'         => $cardName,
            'CardNumber'       => $payment->getCcNumber(),
            'ExpMonth'         => $payment->getCcExpMonth(),
            'ExpYear'          => substr($payment->getCcExpYear(), -2),
            'CV2'              => $payment->getCcCid(),
            'IssueNumber'      => '',
            'Address1'         => $billingAddress->getStreet(1),
            'Address2'         => $billingAddress->getStreet(2),
            'Address3'         => $billingAddress->getStreet(3),
            'Address4'         => $billingAddress->getStreet(4),
            'City'             => $billingAddress->getCity(),
            'State'            => $billingAddress->getRegionCode(),
            'PostCode'         => $billingAddress->getPostcode(),
            'CountryCode'      => $this->getIsoCodesHelper()->getCountryIsoCode($billingAddress->getCountryId()),
            'EmailAddress'     => $order->getCustomerEmail(),
            'PhoneNumber'      => $billingAddress->getTelephone(),
            'IPAddress'        => $order->getRemoteIp()
        );
    }

    /**
     * Processes initial transaction (Card Details Transaction)
     *
     * @param Varien_Object $payment
     * @param $amount
     * @return $this
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    // phpcs:ignore Generic.Metrics.CyclomaticComplexity
    protected function processInitialTransaction($payment, $amount)
    {
        $config          = $this->getConfigHelper();
        $order           = $payment->getOrder();
        $orderId         = $order->getRealOrderId();
        $transactionType = $config->getTransactionType();
        $trxData         = $this->buildInitialTransactionData($payment, $amount);

        try {
            $errorMessage = null;
            $psgw         = new Psgw();
            $response     = $psgw->performCardDetailsTxn($trxData);
            $status       = $response['StatusCode'];
            if ($status !== false) {
                $payment
                    ->setTransactionId($response['CrossReference'])
                    ->setIsTransactionPending($status === TransactionResultCode::INCOMPLETE)
                    ->setIsTransactionClosed(
                        ($status !== TransactionResultCode::SUCCESS) ||
                        ($transactionType !== TransactionType::PREAUTH)
                    )
                    ->setTransactionAdditionalInfo(
                        array(
                            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $response
                        ),
                        null
                    );
                switch ($status) {
                    case TransactionResultCode::SUCCESS:
                        $isTransactionFailed = false;
                        break;
                    case TransactionResultCode::INCOMPLETE:
                        if ($this->is3dsSupported()) {
                            $isTransactionFailed = empty($response['ACSURL']) ||
                                empty($response['PaReq']) ||
                                empty($response['CrossReference']);
                            $errorMessage = $this->getHelper()->__(
                                'Transaction failed. ACSURL, PaReq or CrossReference is empty.'
                            );
                        } else {
                            $isTransactionFailed = true;
                            $errorMessage = $this->getHelper()->__(
                                'Please ensure you are using a Paymentsense MOTO account for MOTO '
                            ) .
                            $this->getHelper()->__(
                                'transactions (this will have a different merchant id to your ECOM account).'
                            );
                        }
                        break;
                    default:
                        $isTransactionFailed = true;
                        $errorMessage = 'Transaction failed. Payment Gateway Message: ' . $response['Message'];
                }
            } else {
                $isTransactionFailed = true;
                $errorMessage = 'Transaction failed. ' . $response['Message'];
            }

            if ($isTransactionFailed) {
                Mage::throwException($errorMessage);
            }

            if ($status === TransactionResultCode::INCOMPLETE) {
                $this->getHelper()->getCheckoutSession()->setPaymentsenseOrderId($orderId);
                $this->getHelper()->getCheckoutSession()->setPaymentsenseAcsUrl($response['ACSURL']);
                $this->getHelper()->getCheckoutSession()->setPaymentsensePaReq($response['PaReq']);
                $this->getHelper()->getCheckoutSession()->setPaymentsenseMD($response['CrossReference']);
            } else {
                $this->getHelper()->getCheckoutSession()->setPaymentsenseOrderId(null);
                $this->getHelper()->getCheckoutSession()->setPaymentsenseAcsUrl(null);
                $this->getHelper()->getCheckoutSession()->setPaymentsensePaReq(null);
                $this->getHelper()->getCheckoutSession()->setPaymentsenseMD(null);
                $this->setOrderState($order, $status);
                if ($this->_canUseCheckout && ($status === TransactionResultCode::SUCCESS)) {
                    $order->queueNewOrderEmail();
                }
            }

            $this->getLogger()->info(
                $transactionType . ' transaction ' . $response['CrossReference'] .
                ' has been performed with status code "' . $response['StatusCode'] . '".'
            );
        } catch (\Exception $e) {
            $logInfo = $transactionType . ' transaction for order #' . $orderId .
                ' failed with message "' . $e->getMessage() . '"';
            $this->getLogger()->warning($logInfo);
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    /**
     * Processes the 3-D Secure response from the ACS
     *
     * @param Mage_Sales_Model_Order
     * @param array $postData The POST variables received from the ACS
     * @return array Array containing StatusCode and Message
     */
    public function process3dsResponse($order, $postData)
    {
        $orderId = $order->getRealOrderId();
        $config  = $this->getConfigHelper();
        $status  = null;
        $message = "Invalid response received from the ACS.";
        if (array_key_exists('PaRes', $postData) && array_key_exists('MD', $postData)) {
            $trxData = array(
                'MerchantID'     => $config->getMerchantId(),
                'Password'       => $config->getPassword(),
                'CrossReference' => $postData['MD'],
                'PaRES'          => $postData['PaRes'],
            );
            try {
                $this->getLogger()->info(
                    'Preparing 3-D Secure authentication for order #' . $orderId
                );

                $psgw     = new Psgw();
                $response = $psgw->perform3dsAuthTxn($trxData);

                $this->getLogger()->info(
                    '3-D Secure authentication transaction ' . $response['CrossReference'] .
                    ' has been performed with status code "' . $response['StatusCode'] . '".'
                );

                if ($response['StatusCode'] !== false) {
                    $status = $response['StatusCode'];
                } else {
                    $response['StatusCode'] = TransactionResultCode::INCOMPLETE;
                }

                $isTransactionFailed = $status !== TransactionResultCode::SUCCESS;
                $payment             = $order->getPayment();
                $payment
                    ->setTransactionId($response['CrossReference'])
                    ->setIsTransactionPending(false)
                    ->setIsTransactionClosed($isTransactionFailed)
                    ->setTransactionAdditionalInfo(
                        array(
                            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $response
                        ),
                        null
                    );

                if ($isTransactionFailed) {
                    $message = $response['Message'];
                    $response['Message']= '3-D Secure Authentication failed. Payment Gateway Message: ' .
                        $response['Message'];
                } else {
                    $message = '';
                }

                $response['OrderID']         = $orderId;
                $response['Amount']          = $order->getTotalDue() * 100;
                $response['TransactionType'] = $config->getTransactionType();
                $this->updatePayment($order, $response);
            } catch (\Exception $e) {
                $logInfo = '3-D Secure Authentication for order #' . $orderId .
                    ' failed with message "' . $e->getMessage() . '"';
                $message = $logInfo;
                $this->getLogger()->warning($logInfo);
            }
        }

        return array(
            'StatusCode' => $status,
            'Message'    => $message
        );
    }
}
