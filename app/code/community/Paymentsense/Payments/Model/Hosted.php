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

use Paymentsense_Payments_Model_Psgw_TransactionResultCode as TransactionResultCode;
use Paymentsense_Payments_Model_Psgw_TransactionStatus as TransactionStatus;
use Paymentsense_Payments_Model_Psgw_HpfResponses as HpfResponses;

/**
 * Paymentsense Hosted Payment Method Model
 */
class Paymentsense_Payments_Model_Hosted extends Mage_Payment_Model_Method_Abstract
{
    use Paymentsense_Payments_Model_Traits_BaseMethod;

    /**
     * Request Types
     */
    const REQ_NOTIFICATION      = '0';
    const REQ_CUSTOMER_REDIRECT = '1';

    protected $_code = 'paymentsense_hosted';

    protected $_formBlockType = 'paymentsense/form_hosted';
    protected $_infoBlockType = 'paymentsense/info_hosted';

    protected $_isGateway               = true;
    protected $_canOrder                = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canRefund               = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_isInitializeNeeded      = false;
    protected $_canUseForMultishipping  = false;
    protected $_canFetchTransactionInfo = false;

    public function __construct()
    {
        parent::__construct();
        $this->configureCrossRefTxnAvailability();
    }

    /**
     * Order handler
     *
     * Stores Order ID in the checkout session. Runs on ACTION_ORDER.
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     */
    public function order(Varien_Object $payment, $amount)
    {
        $this->getLogger()->info('ACTION_ORDER has been triggered.');
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);
        $orderId = $order->getRealOrderId();
        $this->getLogger()->info('New order #' . $orderId . ' with amount ' . $amount . ' has been created.');
        $this->getHelper()->getCheckoutSession()->setPaymentsenseOrderId($orderId);
        return $this;
    }

    /**
     * Checks whether the payment method is available
     *
     * @param Mage_Sales_Model_Quote|null $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        return parent::isAvailable($quote) &&
            $this->getConfigHelper()->isMethodActive() &&
            $this->getConfigHelper()->isMethodConfigured();
    }

    /**
     * Checks whether the order is available
     *
     * @return bool
     */
    public function isOrderAvailable()
    {
        $result = false;
        $checkoutSession = $this->getHelper()->getCheckoutSession();
        $order = $checkoutSession->getLastRealOrder();
        if (!empty($order)) {
            $orderId = $order->getRealOrderId();
            $billingAddress = $order->getBillingAddress();
            $result = !empty($orderId) && !empty($billingAddress);
        }

        return $result;
    }

    /**
     * Gets the Result Delivery Method
     *
     * @return string|null
     */
    public function getResultDeliveryMethod()
    {
        $config = $this->getConfigHelper();
        return $config->getResultDeliveryMethod();
    }

    /**
     * Gets the transaction status and message received by the Hosted Payment Form
     *
     * @param string $requestType Type of the request (notification or customer redirect)
     * @param array $data POST/GET data received with the request from the payment gateway
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    // phpcs:ignore Generic.Metrics.CyclomaticComplexity
    public function getTrxStatusAndMessage($requestType, $data)
    {
        $message   = '';
        $trxStatus = TransactionStatus::INVALID;

        if ($this->isHashDigestValid($requestType, $data)) {
            $message = $data['Message'];
            switch ($data['StatusCode']) {
                case TransactionResultCode::SUCCESS:
                    $trxStatus = TransactionStatus::SUCCESS;
                    break;
                case TransactionResultCode::DUPLICATE:
                    if (TransactionResultCode::SUCCESS === $data['PreviousStatusCode']) {
                        if (array_key_exists('PreviousMessage', $data)) {
                            $message = $data['PreviousMessage'];
                        }

                        $trxStatus = TransactionStatus::SUCCESS;
                    } else {
                        $trxStatus = TransactionStatus::FAILED;
                    }
                    break;
                case TransactionResultCode::REFERRED:
                case TransactionResultCode::DECLINED:
                case TransactionResultCode::FAILED:
                    $trxStatus = TransactionStatus::FAILED;
                    break;
            }

            $this->getLogger()->info(
                'Card details transaction ' . $data['CrossReference'] .
                ' has been performed with status code "' . $data['StatusCode'] . '".'
            );
        } else {
            $this->getLogger()->warning('Callback request with invalid hash digest has been received.');
        }

        return array(
            'TrxStatus' => $trxStatus,
            'Message'   => $message
        );
    }

    /**
     * Gets the transaction status and message from an Order
     *
     * @param array $data POST/GET data received with the request from the payment gateway
     * @return array
     */
    public function loadTrxStatusAndMessage($data)
    {
        $trxStatus = TransactionStatus::INVALID;
        $message   = '';

        if (array_key_exists('OrderID', $data)) {
            $order = $this->getOrder($data);
            if ($order) {
                foreach ($order->getStatusHistoryCollection() as $_item) {
                    $orderStatus = $_item->getStatus();
                    $trxStatus =  ($orderStatus === Mage_Sales_Model_Order::STATE_PROCESSING)
                        ? TransactionStatus::SUCCESS
                        : TransactionStatus::FAILED;
                    if ($_item->getComment()) {
                        $message = $_item->getComment();
                    }

                    break;
                }
            }
        }

        return array(
            'TrxStatus' => $trxStatus,
            'Message'   => $message
        );
    }

    /**
     * Gets Sales Order
     *
     * @param array $response An array containing transaction response data from the gateway
     * @return Mage_Sales_Model_Order
     */
    public function getOrder($response)
    {
        $result         = null;
        $orderId        = null;
        $gatewayOrderId = null;
        $sessionOrderId = $this->getHelper()->getCheckoutSession()->getLastRealOrderId();
        if (array_key_exists('OrderID', $response)) {
            $gatewayOrderId = $response['OrderID'];
        }

        switch (true) {
            case empty($gatewayOrderId):
                $this->getLogger()->error('OrderID returned by the gateway is empty.');
                break;
            case empty($sessionOrderId):
                $this->getLogger()->warning(
                    'Session OrderID is empty. OrderID returned by the gateway (' . $gatewayOrderId .
                    ') will be used.'
                );
                $orderId = $gatewayOrderId;
                break;
            case $sessionOrderId !== $gatewayOrderId:
                $this->getLogger()->error(
                    'Session OrderID (' . $sessionOrderId . ') differs from the OrderID (' . $gatewayOrderId .
                    ') returned by the gateway.'
                );
                break;
            default:
                $orderId = $gatewayOrderId;
                break;
        }

        if ($orderId) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if ($order) {
                if ($order->getId()) {
                    $result = $order;
                }
            }
        }

        return $result;
    }

    /**
     * Checks whether the hash digest received from the payment gateway is valid
     *
     * @param string $requestType Type of the request (notification or customer redirect)
     * @param array $data POST/GET data received with the request from the payment gateway
     * @return bool
     */
    public function isHashDigestValid($requestType, $data)
    {
        $result = false;
        $dataString = $this->buildDataString($requestType, $data);
        if ($dataString) {
            $config = $this->getConfigHelper();
            $hashDigestReceived   = $data['HashDigest'];
            $hashDigestCalculated = $this->calculateHashDigest(
                $dataString,
                $config->getHashMethod(),
                $config->getPresharedKey()
            );
            $result = strToUpper($hashDigestReceived) === strToUpper($hashDigestCalculated);
        }

        return $result;
    }

    /**
     * Builds a string containing the expected fields from the request received from the payment gateway
     *
     * @param string $requestType Type of the request (notification or customer redirect)
     * @param array $data POST/GET data received with the request from the payment gateway
     * @return bool
     */
    public function buildDataString($requestType, $data)
    {
        $result = false;
        $fields = array(
            // Variables for hash digest calculation for notification requests (excluding configuration variables)
            self::REQ_NOTIFICATION      => array(
                'StatusCode',
                'Message',
                'PreviousStatusCode',
                'PreviousMessage',
                'CrossReference',
                'Amount',
                'CurrencyCode',
                'OrderID',
                'TransactionType',
                'TransactionDateTime',
                'OrderDescription',
                'CustomerName',
                'Address1',
                'Address2',
                'Address3',
                'Address4',
                'City',
                'State',
                'PostCode',
                'CountryCode',
                'EmailAddress',
                'PhoneNumber'
            ),
            // Variables for hash digest calculation for customer redirects (excluding configuration variables)
            self::REQ_CUSTOMER_REDIRECT => array(
                'CrossReference',
                'OrderID',
            ),
        );
        $config = $this->getConfigHelper();
        if (array_key_exists($requestType, $fields)) {
            $result = 'MerchantID=' . $config->getMerchantId() . '&Password=' . $config->getPassword();
            foreach ($fields[$requestType] as $field) {
                $result .= '&' . $field . '=' . str_replace('&amp;', '&', $data[$field]);
            }
        }

        return $result;
    }

    /**
     * Gets the gateway settings message
     *
     * @param bool $textFormat Specifies whether the format of the message is text
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    // phpcs:ignore Generic.Metrics.CyclomaticComplexity
    public function getSettingsMessage($textFormat)
    {
        $result = array();
        try {
            $config = $this->getConfigHelper();
            $merchantIdFormatValid = $this->getHelper()->isMerchantIdFormatValid($config->getMerchantId());
        } catch (\Exception $e) {
            $merchantIdFormatValid = false;
        }

        if (! $merchantIdFormatValid) {
            $result = $this->getMessageHelper()->buildErrorSettingsMessage(
                'Gateway MerchantID is invalid. '
                . 'Please make sure the Gateway MerchantID matches the ABCDEF-1234567 format.'
            );
        } else {
            $ggepResult = $this->performGetGatewayEntryPointsTxn();

            list($hpfHeader, $hpfResult) = $this->checkGatewaySettings();

            $merchantCredentialsValid = null;
            $trxStatusCode = $ggepResult['StatusCode'];
            if (TransactionResultCode::SUCCESS === $trxStatusCode) {
                $merchantCredentialsValid = true;
            } elseif (TransactionResultCode::FAILED === $trxStatusCode) {
                if ($this->merchantCredentialsInvalid($ggepResult['Message'])) {
                    $merchantCredentialsValid = false;
                }
            }

            switch ($hpfResult) {
                case HpfResponses::HPF_RESP_OK:
                    $result = $this->getMessageHelper()->buildSuccessSettingsMessage(
                        'Gateway MerchantID, Gateway Password, '
                        . 'Gateway PreSharedKey and Gateway Hash Method are valid.'
                    );
                    break;
                case HpfResponses::HPF_RESP_MID_MISSING:
                case HpfResponses::HPF_RESP_MID_NOT_EXISTS:
                    $result = $this->getMessageHelper()->buildErrorSettingsMessage(
                        'Gateway MerchantID is invalid.'
                    );
                    break;
                case HpfResponses::HPF_RESP_HASH_INVALID:
                    if (true === $merchantCredentialsValid) {
                        $result = $this->getMessageHelper()->buildErrorSettingsMessage(
                            'Gateway PreSharedKey or/and Gateway Hash Method are invalid.'
                        );
                    } elseif (false === $merchantCredentialsValid) {
                        $result = $this->getMessageHelper()->buildErrorSettingsMessage(
                            'Gateway Password is invalid.'
                        );
                    } else {
                        $result = $this->getMessageHelper()->buildErrorSettingsMessage(
                            'Gateway Password, Gateway PreSharedKey or/and Gateway Hash Method are invalid.'
                        );
                    }
                    break;
                case HpfResponses::HPF_DATE_TIME_EXPIRED:
                case HpfResponses::HPF_RESP_NO_RESPONSE:
                    if (true === $merchantCredentialsValid) {
                        $result = $this->getMessageHelper()->buildWarningSettingsMessage(
                            'Gateway PreSharedKey and Gateway Hash Method cannot be validated at this time.'
                        );
                    } elseif (false === $merchantCredentialsValid) {
                        $result = $this->getMessageHelper()->buildErrorSettingsMessage(
                            'Gateway MerchantID or/and Gateway Password are invalid.'
                        );
                    } else {
                        $result = $this->getMessageHelper()->buildWarningSettingsMessage(
                            'The gateway settings cannot be validated at this time.'
                        );
                    }
                    break;
            }
        }

        if ($textFormat) {
            $result = $this->getMessageHelper()->getSettingsTextMessage($result);
        }

        return $result;
    }
}
