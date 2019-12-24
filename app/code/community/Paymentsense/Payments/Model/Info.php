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

/**
 * Paymentsense Info Model
 */
class Paymentsense_Payments_Model_Info extends Paymentsense_Payments_Model_Report
{
    /**
     * @var Paymentsense_Payments_Model_Hosted|Paymentsense_Payments_Model_Direct|Paymentsense_Payments_Model_Moto
     */
    protected $_method;

    /**
     * Gets module name
     *
     * @return string
     */
    protected function getModuleName()
    {
        return $this->_method->getConfigHelper()->getModuleName();
    }

    /**
     * Gets module HTTP user agent
     * Used for performing cURL requests
     *
     * @return string
     */
    protected function getUserAgent()
    {
        return $this->_method->getConfigHelper()->getUserAgent();
    }

    /**
     * Gets module installed version
     *
     * @return string
     */
    protected function getModuleInstalledVersion()
    {
        return $this->_method->getConfigHelper()->getModuleInstalledVersion();
    }

    /**
     * Gets module latest version
     *
     * @return string
     */
    public function getModuleLatestVersion()
    {
        $result = 'N/A';
        $psgw = new Psgw();

        $headers = array(
            'User-Agent: ' . $this->getUserAgent(),
            'Content-Type: text/plain; charset=utf-8',
            'Accept: text/plain, */*',
            'Accept-Encoding: identity',
            'Connection: close'
        );

        $data = array(
            'url'     => 'https://api.github.com/repos/'.
                'Paymentsense-DevSupport/magento1-paymentsense-module/releases/latest',
            'method'  => Zend_Http_Client::GET,
            'headers' => $headers,
            'xml'     => ''
        );

        $response = $psgw->executeHttpRequest($data);
        if (is_array($response)) {
            // @codingStandardsIgnoreLine
            $jsonObject = @json_decode($response[1]);
            if (is_object($jsonObject) && property_exists($jsonObject, 'tag_name')) {
                // @codingStandardsIgnoreLine
                $result = $jsonObject->tag_name;
            }
        }

        return $result;
    }

    /**
     * Gets Magento version
     *
     * @return string
     */
    public function getMagentoVersion()
    {
        return Mage::getVersion();
    }

    /**
     * Gets PHP version
     *
     * @return string
     */
    public function getPHPVersion()
    {
        return phpversion();
    }

    /**
     * Gets the gateway connectivity status
     *
     * @return string
     */
    public function getConnectivityStatus()
    {
        try {
            $response = $this->_method->performGetGatewayEntryPointsTxn();
            if (TransactionResultCode::SUCCESS === $response['StatusCode']) {
                $result = 'Successful';
            } elseif (is_numeric($response['StatusCode'])) {
                $result = sprintf(
                    'Successful with error (StatusCode:%1$s, Message:%2$s)',
                    $response['StatusCode'],
                    $response['Message']
                );
            } else {
                $result = 'Unsuccessful';
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $result   = 'An error occurred while performing GetGatewayEntryPoints transaction: ' . $errorMsg;
        }

        return $result;
    }

    /**
     * Gets formatted module information
     *
     * @param string $model Payment method model
     * @return array
     */
    public function getFormattedModuleInfo($model)
    {
        $info = $this->getModuleInfo($model);
        return $this->formatOutput($info);
    }

    /**
     * Gets module information
     *
     * @param string $model Payment method model
     * @return array
     */
    public function getModuleInfo($model)
    {
        try {
            $this->_method = Mage::getModel($model);
            if (is_object($this->_method)) {
                $result   = array(
                    'Module Name'              => $this->getModuleName(),
                    'Module Installed Version' => $this->getModuleInstalledVersion(),
                );

                if ('true' === Mage::app()->getRequest()->getParam('extended_info')) {
                    $settingsMessage  = $this->_method->getSettingsMessage(true);
                    $systemTimeStatus = $this->_method->getSystemTimeStatus();
                    $extendedInfo     = array(
                        'Module Latest Version'     => $this->getModuleLatestVersion(),
                        'Magento Version'           => $this->getMagentoVersion(),
                        'PHP Version'               => $this->getPHPVersion(),
                        'Connectivity on port 4430' => $this->getConnectivityStatus(),
                        'System Time'               => $systemTimeStatus,
                        'Gateway settings message'  => $settingsMessage
                    );
                    $result = array_merge($result, $extendedInfo);
                }
            } else {
                $result = array(
                    'Error' => "An error occurred while trying to initialise $model model"
                );
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $result = array(
                'Error' => "An error occurred while trying to retrieve module information: " . $errorMessage
            );
        }

        return $result;
    }
}
