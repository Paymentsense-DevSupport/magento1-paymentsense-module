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
 * Base Status block for Paymentsense
 */
class Paymentsense_Payments_Block_Adminhtml_System_Config_Status_Base
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
     /**
      * Template path
      *
      * @var string
      */
     protected $_template;

     /**
      * Render fieldset html
      *
      * @param Varien_Data_Form_Element_Abstract $element
      * @return string
      *
      * @throws \Exception
      */
     public function render(Varien_Data_Form_Element_Abstract $element)
     {
         $columns = ($this->getRequest()->getParam('website') || $this->getRequest()->getParam('store')) ? 5 : 4;
         return $this->_decorateRowHtml($element, "<td colspan='$columns'>" . $this->toHtml() . '</td>');
     }
}
