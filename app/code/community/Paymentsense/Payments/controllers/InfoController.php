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
 * Module Info Controller
 */
class Paymentsense_Payments_InfoController extends Mage_Core_Controller_Front_Action
{
    const TYPE_APPLICATION_JSON = 'application/json';
    const TYPE_TEXT_PLAIN       = 'text/plain';

    /**
     * Supported content types of the output of the module information
     *
     * @var array
     */
    protected $_contentTypes = array(
        'json' => self::TYPE_APPLICATION_JSON,
        'text' => self::TYPE_TEXT_PLAIN
    );

    /** @var $_info Paymentsense_Payments_Model_Info */
    protected $_info;

    /** @var $_helper Paymentsense_Payments_Helper_Data */
    protected $_helper;

    protected function _construct()
    {
        $this->_info = Mage::getModel('paymentsense/info');
        $this->_helper = Mage::helper('paymentsense');
    }

    /**
     * Handles the request
     */
    public function IndexAction()
    {
        $info = $this->_info->getInfo();
        $this->outputInfo($info);
    }

    /**
     * Outputs module information
     *
     * @param array $info
     */
    protected function outputInfo($info)
    {
        $output = Mage::app()->getRequest()->getParam('output');
        $contentType = array_key_exists($output, $this->_contentTypes)
            ? $this->_contentTypes[$output]
            : self::TYPE_TEXT_PLAIN;

        switch ($contentType) {
            case self::TYPE_APPLICATION_JSON:
                $body = json_encode($info);
                break;
            case self::TYPE_TEXT_PLAIN:
            default:
                $body = $this->_helper->convertArrayToString($info);
                break;
        }

        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store', true)
            ->setHeader('Pragma', 'no-cache', true)
            ->setHeader('Content-Type', $contentType)
            ->setBody($body);
    }
}
