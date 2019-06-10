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
 * Front Controller for Paymentsense MOTO
 */
class Paymentsense_Payments_MotoController extends Mage_Core_Controller_Front_Action
{
    /**
     * Payment method model
     */
    const MODEL = 'paymentsense/moto';

    /**
     * Shows the module information report
     *
     * @throws Varien_Exception
     */
    public function infoAction()
    {
        $infoModel = Mage::getModel('paymentsense/info');
        $info = $infoModel->getFormattedModuleInfo(self::MODEL);
        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store', true)
            ->setHeader('Pragma', 'no-cache', true)
            ->setHeader('Content-Type', $info['content-type'])
            ->setBody($info['body']);
    }
}