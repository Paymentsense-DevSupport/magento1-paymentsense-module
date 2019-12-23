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
use Paymentsense_Payments_Model_Psgw_HpfResponses as HpfResponses;

/**
 * Trait for processing of transactions
 */
trait Paymentsense_Payments_Model_Traits_Transactions
{
    /**
     * System time threshold.
     * Specifies the maximal difference between the local time and the gateway time
     * in seconds where the system time is considered Ok.
     */
    protected $_systemTimeThreshold = 300;

    /**
     * @var array $dateTimePairs Pairs of local and remote timestamps
     * Used for the determination of the system time status
     */
    protected $_dateTimePairs = array();

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
        $result =  $psgw->performGetGatewayEntryPointsTxn($trxData);
        foreach ($result['ResponseHeaders'] as $url => $header) {
            $this->addDateTimePair($url, $this->getHelper()->retrieveDate($header));
        }

        return $result;
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

    /**
     * Checks whether the gateway settings are valid by performing a request to the Hosted Payment Form
     *
     * @return array
     */
    public function checkGatewaySettings()
    {
        $responseHeader = null;
        $result = HpfResponses::HPF_RESP_NO_RESPONSE;
        try {
            $psgw     = new Psgw();
            $fields   = $this->buildHpfFields();
            $postData = http_build_query($fields);
            $headers  = array(
                'User-Agent: ' . $this->getConfigHelper()->getUserAgent(),
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-UK,en;q=0.5',
                'Accept-Encoding: identity',
                'Connection: close',
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($postData)
            );
            $data = array(
                'url'     => Paymentsense_Payments_Model_Psgw_Endpoints::getPaymentFormUrl(),
                'method'  => Zend_Http_Client::POST,
                'headers' => $headers,
                'xml'     => $postData
            );

            $response = $psgw->executeHttpRequest($data);
            if (is_array($response)) {
                list($responseHeader, $responseBody) = $response;
                $this->addDateTimePair($data['url'], $this->getHelper()->retrieveDate($responseHeader));

                $hpfErrMsg = $this->getHpfErrorMessage($responseBody);
                if (is_string($hpfErrMsg)) {
                    switch (true) {
                        case $this->contains($hpfErrMsg, HpfResponses::HPF_RESP_HASH_INVALID):
                            $result = HpfResponses::HPF_RESP_HASH_INVALID;
                            break;
                        case $this->contains($hpfErrMsg, HpfResponses::HPF_RESP_MID_MISSING):
                            $result = HpfResponses::HPF_RESP_MID_MISSING;
                            break;
                        case $this->contains($hpfErrMsg, HpfResponses::HPF_RESP_MID_NOT_EXISTS):
                            $result = HpfResponses::HPF_RESP_MID_NOT_EXISTS;
                            break;
                        case $this->contains($hpfErrMsg, HpfResponses::HPF_DATE_TIME_EXPIRED):
                            $result = HpfResponses::HPF_DATE_TIME_EXPIRED;
                            break;
                        default:
                            $result = HpfResponses::HPF_RESP_NO_RESPONSE;
                    }
                } else {
                    $result = HpfResponses::HPF_RESP_OK;
                }
            }
        } catch (\Exception $e) {
            $this->getLogger()->error(
                'An error occurred while checking gateway settings through an HPF request: ' . $e->getMessage()
            );
            $result = HpfResponses::HPF_RESP_NO_RESPONSE;
        }

        return array(
            $responseHeader,
            $result
        );
    }

    /**
     * Builds the fields for the Hosted Payment Form as an associative array
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     *
     * @throws Exception
     */
    public function buildHpfFields($order = null)
    {
        $config = $this->getConfigHelper();
        $fields = $order ? $this->buildPaymentFields($order) : $this->buildSamplePaymentFields();
        $fields = array_map(
            function ($value) {
                return $value === null ? '' : $value;
            },
            $fields
        );

        $data  = 'MerchantID=' . $config->getMerchantId();
        $data .= '&Password=' . $config->getPassword();

        foreach ($fields as $key => $value) {
            $data .= '&' . $key . '=' . $value;
        };

        $gatewayHashMethod = ($this instanceof Paymentsense_Payments_Model_Hosted)
            ? $config->getHashMethod()
            : 'SHA1';

        $additionalFields = array(
            'HashDigest' => $this->calculateHashDigest(
                $data,
                $gatewayHashMethod,
                $config->getPresharedKey()
            ),
            'MerchantID' => $config->getMerchantId(),
        );

        $fields = array_merge($additionalFields, $fields);

        if ($order) {
            $orderId = $order->getRealOrderId();
            $this->getLogger()->info(
                'Preparing Hosted Payment Form redirect with ' . $config->getTransactionType() .
                ' transaction for order #' . $orderId
            );

            $order
                ->setState(
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT
                )
                ->save();
        }

        return $fields;
    }

    /**
     * Builds the redirect form action URL and the variables for the Hosted Payment Form
     *
     * @param  Mage_Sales_Model_Order $order
     * @return array|false
     *
     * @throws Varien_Exception
     */
    public function buildPaymentFields($order)
    {
        $result         = false;
        $config         = $this->getConfigHelper();
        $iso            = $this->getIsoCodesHelper();
        $orderId        = $order->getRealOrderId();
        $billingAddress = $order->getBillingAddress();
        if (!empty($orderId) && !empty($billingAddress)) {
            $result = array(
                'Amount'                    => $order->getBaseGrandTotal() * 100,
                'CurrencyCode'              => $iso->getCurrencyIsoCode($order->getOrderCurrencyCode()),
                'OrderID'                   => $orderId,
                'TransactionType'           => $config->getTransactionType(),
                'TransactionDateTime'       => Mage::getSingleton('core/date')->gmtDate(),
                'CallbackURL'               => $this->getHelper()->getHostedFormCallbackUrl(),
                'OrderDescription'          => $orderId . ': New order',
                'CustomerName'              => $billingAddress->getFirstname() . ' ' .
                    $billingAddress->getLastname(),
                'Address1'                  => $billingAddress->getStreet(1),
                'Address2'                  => $billingAddress->getStreet(2),
                'Address3'                  => $billingAddress->getStreet(3),
                'Address4'                  => $billingAddress->getStreet(4),
                'City'                      => $billingAddress->getCity(),
                'State'                     => $billingAddress->getRegionCode(),
                'PostCode'                  => $billingAddress->getPostcode(),
                'CountryCode'               => $iso->getCountryIsoCode($billingAddress-> getCountryId()),
                'EmailAddress'              => $order->getCustomerEmail(),
                'PhoneNumber'               => $billingAddress->getTelephone(),
                'EmailAddressEditable'      => $config->getEmailAddressEditable(),
                'PhoneNumberEditable'       => $config->getPhoneNumberEditable(),
                'CV2Mandatory'              => 'true',
                'Address1Mandatory'         => $config->getAddress1Mandatory(),
                'CityMandatory'             => $config->getCityMandatory(),
                'PostCodeMandatory'         => $config->getPostcodeMandatory(),
                'StateMandatory'            => $config->getStateMandatory(),
                'CountryMandatory'          => $config->getCountryMandatory(),
                'ResultDeliveryMethod'      => $config->getResultDeliveryMethod(),
                'ServerResultURL'           => ('SERVER' === $config->getResultDeliveryMethod())
                    ? $this->getHelper()->getHostedFormCallbackUrl()
                    : '',
                'PaymentFormDisplaysResult' => 'false'
            );
        }

        return $result;
    }

    /**
     * Builds the redirect form action URL and the variables for the Hosted Payment Form
     *
     * @return array
     *
     * @throws Varien_Exception
     */
    public function buildSamplePaymentFields()
    {
        $config = $this->getConfigHelper();
        $iso    = $this->getIsoCodesHelper();
        return array(
            'Amount'                    => 100,
            'CurrencyCode'              => $iso->getCurrencyIsoCode(null),
            'OrderID'                   => 'TEST-' . rand(1000000, 9999999),
            'TransactionType'           => $config->getTransactionType(),
            'TransactionDateTime'       => Mage::getSingleton('core/date')->gmtDate(),
            'CallbackURL'               => $this->getHelper()->getHostedFormCallbackUrl(),
            'OrderDescription'          => '',
            'CustomerName'              => '',
            'Address1'                  => '',
            'Address2'                  => '',
            'Address3'                  => '',
            'Address4'                  => '',
            'City'                      => '',
            'State'                     => '',
            'PostCode'                  => '',
            'CountryCode'               => '',
            'EmailAddress'              => '',
            'PhoneNumber'               => '',
            'EmailAddressEditable'      => 'true',
            'PhoneNumberEditable'       => 'true',
            'CV2Mandatory'              => 'true',
            'Address1Mandatory'         => 'false',
            'CityMandatory'             => 'false',
            'PostCodeMandatory'         => 'false',
            'StateMandatory'            => 'false',
            'CountryMandatory'          => 'false',
            'ResultDeliveryMethod'      => 'POST',
            'ServerResultURL'           => '',
            'PaymentFormDisplaysResult' => 'false'
        );
    }

    /**
     * Checks whether a string contains a needle.
     *
     * @param string $string the string.
     * @param string $needle the needle.
     *
     * @return bool
     */
    public function contains($string, $needle)
    {
        return false !== stripos($string, $needle);
    }

    /**
     * Determines whether the response message is about invalid merchant credentials
     *
     * @param string $msg Message.
     * @return bool
     */
    public function merchantCredentialsInvalid($msg)
    {
        return $this->contains($msg, 'Input variable errors')
            || $this->contains($msg, 'Invalid merchant details');
    }

    /**
     * Gets the error message from the Hosted Payment Form response (span id lbErrorMessageLabel)
     *
     * @param string $data HTML document.
     *
     * @return string
     */
    protected function getHpfErrorMessage($data)
    {
        $result = null;
        if (preg_match('/<span.*lbErrorMessageLabel[^>]*>(.*?)<\/span>/si', $data, $matches)) {
            $result = strip_tags($matches[1]);
        }

        return $result;
    }

    /**
     * Calculates the hash digest.
     * Supported hash methods: MD5, SHA1, HMACMD5, HMACSHA1
     *
     * @param string $data Data to be hashed.
     * @param string $hashMethod Hash method.
     * @param string $key Secret key to use for generating the hash.
     * @return string
     */
    public function calculateHashDigest($data, $hashMethod, $key)
    {
        $result     = '';
        $includeKey = in_array($hashMethod, array('MD5', 'SHA1'), true);
        if ($includeKey) {
            $data = 'PreSharedKey=' . $key . '&' . $data;
        }

        switch ($hashMethod) {
            case 'MD5':
                // @codingStandardsIgnoreLine
                $result = md5($data);
                break;
            case 'SHA1':
                $result = sha1($data);
                break;
            case 'HMACMD5':
                $result = hash_hmac('md5', $data, $key);
                break;
            case 'HMACSHA1':
                $result = hash_hmac('sha1', $data, $key);
                break;
        }

        return $result;
    }

    /**
     * Adds a pair of a local and a remote timestamp
     *
     * @param string $url Remote URL
     * @param DateTime $remoteDateTime Remote timestamp
     */
    public function addDateTimePair($url, $remoteDateTime)
    {
        $hostname                       = $this->getHelper()->getHostname($url);
        $this->_dateTimePairs[$hostname] = $this->getHelper()->buildDateTimePair($remoteDateTime);
    }

    /**
     * Gets the pairs of local and remote timestamps
     *
     * @return array
     */
    public function getDateTimePairs()
    {
        return $this->_dateTimePairs;
    }

    /**
     * Gets the system time status
     *
     * @return string
     */
    public function getSystemTimeStatus()
    {
        $timeDiff = $this->getSystemTimeDiff();
        if (is_numeric($timeDiff)) {
            $result = abs($timeDiff) <= $this->getSystemTimeThreshold()
                ? 'OK'
                : sprintf('Out of sync with %+d seconds', $timeDiff);
        } else {
            $result = 'Unknown';
        }

        return $result;
    }

    /**
     * Gets the difference between the system time and the gateway time in seconds
     *
     * @return string
     */
    public function getSystemTimeDiff()
    {
        $result = null;
        $dateTimePairs = $this->getDateTimePairs();
        if ($dateTimePairs) {
            $dateTimePair = array_shift($dateTimePairs);
            $result = $this->calculateDateDiff($dateTimePair);
        }

        return $result;
    }

    /**
     * Calculates the difference between DateTimes in seconds
     *
     * @param array $dateTimePair
     * @return int
     */
    public function calculateDateDiff($dateTimePair)
    {
        list($localDateTime, $remoteDateTime) = $dateTimePair;
        return $localDateTime->format('U') - $remoteDateTime->format('U');
    }

    /**
     * Gets the system time threshold
     *
     * @return int
     */
    public function getSystemTimeThreshold()
    {
        return $this->_systemTimeThreshold;
    }
}
