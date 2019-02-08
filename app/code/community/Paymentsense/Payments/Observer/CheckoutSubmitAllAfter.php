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
 * Observer handling the redirects to the Hosted Payment Form or the ACS
 *
 * Called after placing orders ('checkout_submit_all_after' event)
 */
class Paymentsense_Payments_Observer_CheckoutSubmitAllAfter
{
    /**
     * Observer method running on 'checkout_submit_all_after' event
     * Sets redirects to the Hosted Payment Form or the ACS
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function handleAction($observer)
    {
        $helper          = Mage::helper('paymentsense');
        $checkoutSession = $helper->getCheckoutSession();
        $orderId         = $observer->getOrder()->getRealOrderId();
        if ($orderId === $checkoutSession->getPaymentsenseOrderId()) {
            $method = $observer->getOrder()->getPayment()->getMethodInstance();
            switch (true) {
                case $method instanceof Paymentsense_Payments_Model_Hosted:
                    $checkoutSession->setRedirectUrl($helper->getHostedFormRedirectUrl());
                    break;
                case $method instanceof Paymentsense_Payments_Model_Direct:
                    if ($checkoutSession->getPaymentsenseAcsUrl()) {
                        $checkoutSession->setRedirectUrl($helper->getAcsRedirectUrl());
                    }
                    break;
                default:
                    // no action is required
                    break;
            }
        }

        return $this;
    }
}
