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
 * Module Status Controller
 */
class Paymentsense_Payments_StatusController extends Mage_Core_Controller_Front_Action
{
    /**
     * Handles the request for status for Paymentsense Hosted
     */
    public function HostedAction()
    {
        $method = Mage::getModel('paymentsense/hosted');
        switch (true) {
            case !$method->isConfigured():
                $statusInfo = array(
                    'statusText' => __('Unavailable (Payment method not configured)'),
                    'statusClassName' => 'red-text'
                );
                break;
            default:
                $statusInfo = array(
                    'statusText' => __('Enabled'),
                    'statusClassName' => 'green-text'
                );
                break;
        }

        $connectionInfo = $this->getConnection();
        $info = array_merge($statusInfo, $connectionInfo);
        $this->outputInfo($info);
    }

    /**
     * Handles the request for status for Paymentsense Direct
     */
    public function DirectAction()
    {
        $method = Mage::getModel('paymentsense/direct');
        switch (true) {
            case !$method->isConfigured():
                $statusInfo = array(
                    'statusText' => __('Unavailable (Payment method not configured)'),
                    'statusClassName' => 'red-text'
                );
                break;
            case !$method->isSecure():
                $statusInfo = array(
                    'statusText' => __('Unavailable (SSL/TLS not configured)'),
                    'statusClassName' => 'red-text'
                );
                break;
            default:
                $statusInfo = array(
                    'statusText' => __('Enabled'),
                    'statusClassName' => 'green-text'
                );
                break;
        }

        $connectionInfo = $this->getConnection();
        $info = array_merge($statusInfo, $connectionInfo);
        $this->outputInfo($info);
    }

    /**
     * Handles the request for status for Paymentsense MOTO
     */
    public function MotoAction()
    {
        $method = Mage::getModel('paymentsense/moto');
        switch (true) {
            case !$method->isConfigured():
                $statusInfo = array(
                    'statusText' => __('Unavailable (Payment method not configured)'),
                    'statusClassName' => 'red-text'
                );
                break;
            case !$method->isSecure():
                $statusInfo = array(
                    'statusText' => __('Unavailable (SSL/TLS not configured)'),
                    'statusClassName' => 'red-text'
                );
                break;
            default:
                $statusInfo = array(
                    'statusText' => __('Enabled'),
                    'statusClassName' => 'green-text'
                );
                break;
        }

        $connectionInfo = $this->getConnection();
        $info = array_merge($statusInfo, $connectionInfo);
        $this->outputInfo($info);
    }

    /**
     * Gets the gateway connection status
     */
    protected function getConnection()
    {
        $method               = Mage::getModel('paymentsense/direct');
        $connectionSuccessful = $method->canConnect();
        if ($connectionSuccessful) {
            $result = array(
                'connectionText' => __('Successful'),
                'connectionClassName' => 'green-text'
            );
        } else {
            $result = array(
                'connectionText' => __('Unavailable (No Connection to the gateway. Please check outbound port 4430).'),
                'connectionClassName' => 'red-text'
            );
        }

        return $result;
    }

    /**
     * Outputs module information
     *
     * @param array $info
     */
    protected function outputInfo($info)
    {
        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store', true)
            ->setHeader('Pragma', 'no-cache', true)
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode($info));
    }
}
