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
 * Paymentsense Gateway Endpoints
 *
 * Defines the gateway and Hosted Payment Form URLs
 */
class Paymentsense_Payments_Model_Psgw_Endpoints
{
    protected static $_paymentFormUrl  = 'https://mms.paymentsensegateway.com/Pages/PublicPages/PaymentForm.aspx';
    protected static $_paymentGateways = array(
        1 => 'https://gw1.paymentsensegateway.com:4430/',
        2 => 'https://gw2.paymentsensegateway.com:4430/'
    );

    /**
     * Gets Hosted Payment Form URL
     *
     * @return string
     */
    public static function getPaymentFormUrl()
    {
        return self::$_paymentFormUrl;
    }

    /**
     * Gets Payment Gateway URL
     *
     * @param int $gatewayId Gateway ID.
     * @return string|false
     */
    public static function getPaymentGatewayUrl($gatewayId)
    {
        $result = false;
        if (array_key_exists($gatewayId, self::$_paymentGateways)) {
            $result = self::$_paymentGateways[$gatewayId];
        }

        return $result;
    }
}
