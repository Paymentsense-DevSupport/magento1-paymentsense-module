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

/**
 * Module Status Controller
 */
class Paymentsense_Payments_StatusController extends Mage_Core_Controller_Front_Action
{
    protected $_method = null;

    /**
     * Handles the request for status for Paymentsense Hosted
     */
    public function HostedAction()
    {
        $this->_method = Mage::getModel('paymentsense/hosted');
        $this->ProcessStatusRequest();
    }

    /**
     * Handles the request for status for Paymentsense Direct
     */
    public function DirectAction()
    {
        $this->_method = Mage::getModel('paymentsense/direct');
        $this->ProcessStatusRequest();
    }

    /**
     * Handles the request for status for Paymentsense MOTO
     */
    public function MotoAction()
    {
        $this->_method = Mage::getModel('paymentsense/moto');
        $this->ProcessStatusRequest();
    }

    /**
     * Processes the request for status
     */
    protected function ProcessStatusRequest()
    {
        $this->outputInfo($this->getMessages());
    }

    /**
     * Gets the messages
     *
     * @return array
     */
    protected function getMessages()
    {
        $result = $this->getStatusMessage();
        $result = array_merge($result, $this->getConnectionMessage());
        $result = array_merge($result, $this->getSettingsMessage());
        $result = array_merge($result, $this->getSystemTimeMessage());
        return $result;
    }

    /**
     * Gets the payment method status message
     *
     * @return array
     */
    protected function getStatusMessage()
    {
        return $this->_method->getMessageHelper()->getStatusMessage(
            $this->_method->isConfigured(),
            $this->_method instanceof Paymentsense_Payments_Model_Hosted || $this->_method->isSecure()
        );
    }

    /**
     * Gets the gateway connection status message
     *
     * @return array
     */
    protected function getConnectionMessage()
    {
        $connectionSuccessful = $this->_method->canConnect();
        return $this->_method->getMessageHelper()->getConnectionMessage($connectionSuccessful);
    }

    /**
     * Gets the settings message
     *
     * @return array
     */
    protected function getSettingsMessage()
    {
        return $this->_method->getSettingsMessage(false);
    }

    /**
     * Gets the system time message if the time difference exceeds the threshold
     *
     * @return array
     */
    protected function getSystemTimeMessage()
    {
        $result   = array();
        $timeDiff = $this->_method->getSystemTimeDiff();
        if (is_numeric($timeDiff) && (abs($timeDiff) > $this->_method->getSystemTimeThreshold())) {
            $result = $this->_method->getMessageHelper()->buildErrorSystemTimeMessage(
                $timeDiff
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
