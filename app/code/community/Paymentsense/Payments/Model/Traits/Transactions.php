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
use Paymentsense_Payments_Model_Psgw_TransactionType as TransactionType;
use Paymentsense_Payments_Model_Psgw_TransactionResultCode as TransactionResultCode;

/**
 * Trait for processing of Cross Reference Transactions
 */
trait Paymentsense_Payments_Model_Traits_Transactions
{
    /**
     * Performs a Reference Transaction (COLLECTION, REFUND, REFUND)
     *
     * @param Varien_Object $payment
     * @param array $trxData Transaction data
     * @return array
     */
    protected function processReferenceTransaction($payment, $trxData)
    {
        $psgw     = new Psgw();
        $response = $psgw->performCrossRefTxn($trxData);

        $this->getLogger()->info(
            'Reference transaction ' . $response['CrossReference'] .
            ' has been performed with status code "' . $response['StatusCode'] . '".'
        );

        if ($response['StatusCode'] !== false) {
            $payment
                ->setTransactionId($response['CrossReference'])
                ->setParentTransactionId($trxData['CrossReference'])
                ->setShouldCloseParentTransaction(true)
                ->setIsTransactionPending(false)
                ->setIsTransactionClosed(true)
                ->resetTransactionAdditionalInfo()
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $response
                    ),
                    null
                );
            $payment->save();
        }

        return $response;
    }

    /**
     * Performs COLLECTION
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @param Mage_Sales_Model_Order_Payment_Transaction|false $authTransaction
     * @return $this
     */
    protected function performCollection($payment, $amount, $authTransaction)
    {
        $config  = $this->getConfigHelper();
        $iso     = $this->getIsoCodesHelper();
        $order   = $payment->getOrder();
        $orderId = $order->getRealOrderId();
        $reason  = 'Collection';
        $xmlData = array(
            'MerchantID'       => $config->getMerchantId(),
            'Password'         => $config->getPassword(),
            'Amount'           => $amount * 100,
            'CurrencyCode'     => $iso->getCurrencyIsoCode($order->getOrderCurrencyCode()),
            'TransactionType'  => TransactionType::COLLECTION,
            'CrossReference'   => $authTransaction->getTxnId(),
            'OrderID'          => $orderId,
            'OrderDescription' => $orderId . ': ' . $reason,
        );

        $response = $this->processReferenceTransaction($payment, $xmlData);

        if ($response['StatusCode'] === TransactionResultCode::SUCCESS) {
            $this->getHelper()->getAdminSession()->addSuccess($response['Message']);
        } else {
            Mage::throwException(
                $this->getHelper()->__('COLLECTION transaction failed. ') .
                (($response['StatusCode'] !== false) ? $this->getHelper()->__('Payment gateway message: ') : '') .
                $response['Message']
            );
        }

        return $this;
    }

    /**
     * Performs REFUND
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @param Mage_Sales_Model_Order_Payment_Transaction|false $captureTransaction
     * @return $this
     */
    protected function performRefund($payment, $amount, $captureTransaction)
    {
        $config  = $this->getConfigHelper();
        $iso     = $this->getIsoCodesHelper();
        $order   = $payment->getOrder();
        $orderId = $order->getRealOrderId();
        $reason  = 'Refund';
        $xmlData = array(
            'MerchantID'       => $config->getMerchantId(),
            'Password'         => $config->getPassword(),
            'Amount'           => $amount * 100,
            'CurrencyCode'     => $iso->getCurrencyIsoCode($order->getOrderCurrencyCode()),
            'TransactionType'  => TransactionType::REFUND,
            'CrossReference'   => $captureTransaction->getTxnId(),
            'OrderID'          => $orderId,
            'OrderDescription' => $orderId . ': ' . $reason,
        );

        $response = $this->processReferenceTransaction($payment, $xmlData);

        if ($response['StatusCode'] === TransactionResultCode::SUCCESS) {
            $this->getHelper()->getAdminSession()->addSuccess($response['Message']);
        } else {
            Mage::throwException(
                $this->getHelper()->__('REFUND transaction failed. ') .
                (($response['StatusCode'] !== false) ? $this->getHelper()->__('Payment gateway message: ') : '') .
                $response['Message']
            );
        }

        return $this;
    }

    /**
     * Performs VOID
     *
     * @param Varien_Object $payment
     * @param Mage_Sales_Model_Order_Payment_Transaction|false $referenceTransaction
     * @return $this
     */
    protected function performVoid($payment, $referenceTransaction)
    {
        $config  = $this->getConfigHelper();
        $order   = $payment->getOrder();
        $orderId = $order->getRealOrderId();
        $reason  = 'Void';
        $xmlData = array(
            'MerchantID'       => $config->getMerchantId(),
            'Password'         => $config->getPassword(),
            'Amount'           => '',
            'CurrencyCode'     => '',
            'TransactionType'  => TransactionType::VOID,
            'CrossReference'   => $referenceTransaction->getTxnId(),
            'OrderID'          => $orderId,
            'OrderDescription' => $orderId . ': ' . $reason,
        );

        $response = $this->processReferenceTransaction($payment, $xmlData);

        if ($response['StatusCode'] === TransactionResultCode::SUCCESS) {
            $this->getHelper()->getAdminSession()->addSuccess($response['Message']);
        } else {
            Mage::throwException(
                $this->getHelper()->__('VOID transaction failed. ') .
                (($response['StatusCode'] !== false) ? $this->getHelper()->__('Payment gateway message: ') : '') .
                $response['Message']
            );
        }

        return $this;
    }

    /**
     * Refund handler
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $errorMessage = '';
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();

        $this->getLogger()->info('Preparing REFUND transaction for order #' . $orderId);

        $captureTransaction = $payment->lookupTransaction(
            null,
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE
        );

        if (isset($captureTransaction)) {
            try {
                $this->performRefund($payment, $amount, $captureTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            $errorMessage = 'REFUND transaction for order #' . $orderId .
                ' cannot be finished (No Capture Transaction exists)';
        }

        if ($errorMessage !== '') {
            $this->getLogger()->warning($errorMessage);
            Mage::throwException($errorMessage);
        }

        return $this;
    }

    /**
     * Capture handler
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     */
    public function capture(Varien_Object $payment, $amount)
    {
        $errorMessage = '';
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();

        $this->getLogger()->info('Preparing COLLECTION transaction for order #' . $orderId);

        $authTransaction = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);

        if (!empty($authTransaction)) {
            try {
                $this->performCollection($payment, $amount, $authTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            $errorMessage = 'COLLECTION transaction for order #' . $orderId .
                ' cannot be finished (No Authorize Transaction exists)';
        }

        if ($errorMessage !== '') {
            $this->getLogger()->warning($errorMessage);
            Mage::throwException($errorMessage);
        }

        return $this;
    }

    /**
     * Void handler
     *
     * @param Varien_Object $payment
     * @return $this
     */
    public function void(Varien_Object $payment)
    {
        $errorMessage = '';
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();

        $this->getLogger()->info('Preparing VOID transaction for order #' . $orderId);

        $authTransaction = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);

        if (isset($authTransaction)) {
            try {
                $this->performVoid($payment, $authTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            $errorMessage = 'VOID transaction for order #' . $orderId .
                ' cannot be finished (No Authorize Transaction exists)';
        }

        if ($errorMessage !== '') {
            $this->getLogger()->warning($errorMessage);
            Mage::throwException($errorMessage);
        }

        return $this;
    }

    /**
     * Cancel handler
     *
     * @param Varien_Object $payment
     * @return $this
     */
    public function cancel(Varien_Object $payment)
    {
        return $this->void($payment);
    }

    /**
     * Updates payment info and registers Card Details Transactions
     *
     * @param Mage_Sales_Model_Order
     * @param array $response An array containing transaction response data from the gateway
     */
    public function updatePayment($order, $response)
    {
        $transactionID = $response['CrossReference'];
        $payment = $order->getPayment();
        $lastTransactionId = $payment->getLastTransId();
        $config = $this->getConfigHelper();
        $transactionType = $config->getTransactionType();
        $payment
            ->setTransactionId($transactionID)
            ->setParentTransactionId($lastTransactionId)
            ->setShouldCloseParentTransaction(true)
            ->setIsTransactionPending(false)
            ->setIsTransactionClosed(
                ($response['StatusCode'] !== TransactionResultCode::SUCCESS) ||
                ($transactionType !== TransactionType::PREAUTH)
            )
            ->setTransactionAdditionalInfo(
                array(
                    Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $response
                ),
                null
            );
        if ($response['StatusCode'] === TransactionResultCode::SUCCESS) {
            if ($transactionType === TransactionType::SALE) {
                $payment->registerCaptureNotification($response['Amount'] / 100);
            } else {
                $payment->registerAuthorizationNotification($response['Amount'] / 100);
            }

            $order->queueNewOrderEmail();
        }

        $this->setOrderState($order, $response['StatusCode'], $response['Message']);
    }

    /**
     * Performs GetGatewayEntryPoints transaction
     *
     * @return array
     */
    public function performGetGatewayEntryPointsTxn()
    {
        $config  = $this->getConfigHelper();
        $trxData = array(
            'MerchantID' => $config->getMerchantId(),
            'Password'   => $config->getPassword(),
        );

        $psgw = new Psgw();
        $psgw->setTrxMaxAttempts(1);
        return $psgw->performGetGatewayEntryPointsTxn($trxData);
    }

    /**
     * Checks whether the plugin can connect to the gateway by performing GetGatewayEntryPoints transaction
     *
     * @return boolean
     */
    public function canConnect()
    {
        $response = $this->performGetGatewayEntryPointsTxn();
        return false !== $response['StatusCode'];
    }

    /**
     * Configures the availability of the cross reference transactions (COLLECTION, REFUND, VOID)
     * based on the configuration setting "Port 4430 is NOT open on my server"
     */
    public function configureCrossRefTxnAvailability()
    {
        $port4430IsNotOpen = (bool) $this->getConfigHelper()->getPort4430NotOpen();
        $this->_canCapture = $this->_canRefund = $this->_canVoid = ! $port4430IsNotOpen;
    }
}
