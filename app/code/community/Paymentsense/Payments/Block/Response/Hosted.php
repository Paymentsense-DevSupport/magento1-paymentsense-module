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
 * Response block for Paymentsense Hosted
 */
class Paymentsense_Payments_Block_Response_Hosted extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('paymentsense/response/hosted.phtml');
    }

    /**
     * Gets the response variable status_code
     *
     * @return string
     */
    public function getStatusCode()
    {
        return $this->getData('status_code');
    }

    /**
     * Gets the response variable message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->getData('message');
    }
}
