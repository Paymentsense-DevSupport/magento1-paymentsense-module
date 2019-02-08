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
 * Redirect block for Paymentsense Hosted
 */
class Paymentsense_Payments_Block_Redirect_Hosted extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('paymentsense/redirect/hosted.phtml');
    }

    /**
     * Generates Hosted Payment Form redirect form
     *
     * @return string
     */
    public function generateForm()
    {
        $form = new Varien_Data_Form();
        $form->setAction(Paymentsense_Payments_Model_Psgw_Endpoints::getPaymentFormUrl())
            ->setId('redirect_form')
            ->setName('redirect_form')
            ->setMethod('post')
            ->setUseContainer(true);
        $submitButton = new Varien_Data_Form_Element_Submit(
            array(
                'value' => $this->__('Click here, if you are not redirected within 10 seconds...')
            )
        );
        $submitButton->setId($this->getButtonId());
        $form->addElement($submitButton);
        $hosted = Mage::getModel('paymentsense/hosted');
        $data = $hosted->buildHostedFormData();
        if ($data) {
            foreach ($data as $name => $value) {
                $element = new Varien_Data_Form_Element_Hidden(
                    array(
                        'name' => $name,
                        'value' => $value
                    )
                );
                $element->setId($name);
                $form->addElement($element);
                unset($element);
            }
        }

        return $form->toHtml();
    }

    /**
     * Gets the submit button id
     *
     * @return string
     */
    public function getButtonId()
    {
        return 'submit';
    }
}
