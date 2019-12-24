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
 * Paymentsense Info Model
 */
class Paymentsense_Payments_Model_Report
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

    /**
     * Formats the output of the module information and file checksums
     *
     * @param array $info
     * @return array
     */
    public function formatOutput($info)
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
                $body = $this->convertArrayToString($info);
                break;
        }

        return array(
            'content-type' => $contentType,
            'body'         => $body
        );
    }

    /**
     * Converts an array to string
     *
     * @param array  $arr An associative array.
     * @param string $ident Identation.
     * @return string
     */
    protected function convertArrayToString($arr, $ident = '')
    {
        $result       = '';
        $identPattern = '  ';
        foreach ($arr as $key => $value) {
            if ('' !== $result) {
                $result .= PHP_EOL;
            }

            if (is_array($value)) {
                $value = PHP_EOL . $this->convertArrayToString($value, $ident . $identPattern);
            }

            $result .= $ident . $key . ': ' . $value;
        }

        return $result;
    }
}
