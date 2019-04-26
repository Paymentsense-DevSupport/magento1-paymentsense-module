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
class Paymentsense_Payments_Model_Info
{
    const MODULE_NAME = 'Paymentsense Module for Magento 1 Open Source';

    /**
     * Gets module information
     *
     * @return array
     */
    public function getInfo()
    {
        $info = array(
            'Module Name'              => $this->getModuleName(),
            'Module Installed Version' => $this->getModuleInstalledVersion(),
        );

        if ('true' === Mage::app()->getRequest()->getParam('extended_info')) {
            $extendedInfo = array(
                'Module Latest Version' => $this->getModuleLatestVersion(),
                'Magento Version'       => $this->getMagentoVersion(),
                'PHP Version'           => $this->getPHPVersion(),
                'Connectivity'          => $this->getConnectivityStatus(),
            );
            $info = array_merge($info, $extendedInfo);
        }

        return $info;
    }

    /**
     * Gets module name
     *
     * @return string
     */
    protected function getModuleName()
    {
        return self::MODULE_NAME;
    }

    /**
     * Gets module installed version
     *
     * @return string
     */
    protected function getModuleInstalledVersion()
    {
       return Mage::getConfig()->getNode('modules/Paymentsense_Payments/version');
    }

    /**
     * Gets module latest version
     *
     * @return string
     */
    protected function getModuleLatestVersion()
    {
        $result = 'N/A';
        $psgw = new Psgw();

        $headers = array(
            'User-Agent: ' . $this->getModuleName() . ' v.' . $this->getModuleInstalledVersion(),
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
        if ($response) {
            // @codingStandardsIgnoreLine
            $jsonObject = @json_decode($response);
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
    protected function getMagentoVersion()
    {
        return Mage::getVersion();
    }

    /**
     * Gets PHP version
     *
     * @return string
     */
    protected function getPHPVersion()
    {
        return phpversion();
    }

    /**
     * Gets the gateway connectivity status
     *
     * @return string
     */
    protected function getConnectivityStatus()
    {
        $model  = 'paymentsense/direct';
        $method = Mage::getModel($model);
        if (is_object($method)) {
            try {
                $response = $method->performGetGatewayEntryPointsTxn();
                if (TransactionResultCode::SUCCESS === $response['StatusCode']) {
                    $result = 'Successful';
                } else {
                    $result = $response['Message'];
                }
            } catch (\Varien_Exception $e) {
                $errorMsg = $e->getMessage();
                $result   = 'An error occurred while performing GetGatewayEntryPoints transaction: ' . $errorMsg;
            }
        } else {
            $result = "Error instantiating $model model";
        }

        return $result;
    }
}
