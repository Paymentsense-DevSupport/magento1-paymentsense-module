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

use Paymentsense_Payments_Model_Psgw_TransactionType as TransactionType;
use Paymentsense_Payments_Model_Psgw_TransactionResultCode as TransactionResultCode;

/**
 * Trait for post-processing of Card Details Transactions
 */
trait Paymentsense_Payments_Model_Traits_CardDetailsTransactions
{
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
}
