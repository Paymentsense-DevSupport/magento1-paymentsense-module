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
 * Hash Method Model Source
 */
class Paymentsense_Payments_Model_Admin_Options_Hash_Method
{
    /**
     * Builds the options for the select control in the admin panel
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'SHA1', 'label' => 'SHA1'),
            array('value' => 'MD5', 'label' => 'MD5'),
            array('value' => 'HMACSHA1', 'label' => 'HMACSHA1'),
            array('value' => 'HMACMD5', 'label' => 'HMACMD5')
        );
    }
}
