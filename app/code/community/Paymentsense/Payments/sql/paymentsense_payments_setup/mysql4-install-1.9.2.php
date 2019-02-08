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
 * Assigns the quote payments created by the previous Paymentsense modules to the current payment methods
 */

Mage::log('Paymentsense install script started.');
$installer = $this;
$installer->startSetup();
$installer->run(
    "UPDATE {$installer->getTable('sales_flat_quote_payment')} 
SET method='paymentsense_hosted' WHERE method='payhosted';
UPDATE {$installer->getTable('sales_flat_quote_payment')} 
SET method='paymentsense_direct' WHERE method='pay' OR method='Paymentsense';
UPDATE {$installer->getTable('sales_flat_quote_payment')} 
SET method='paymentsense_moto' WHERE method='paymoto';
UPDATE {$installer->getTable('sales_flat_order_payment')} 
SET method='paymentsense_hosted' WHERE method='payhosted';
UPDATE {$installer->getTable('sales_flat_order_payment')} 
SET method='paymentsense_direct' WHERE method='pay' OR method='Paymentsense';
UPDATE {$installer->getTable('sales_flat_order_payment')} 
SET method='paymentsense_moto' WHERE method='paymoto';"
);
$installer->endSetup();
Mage::log('Paymentsense install script ended.');
