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
 * Paymentsense Transaction Types
 */
final class Paymentsense_Payments_Model_Psgw_TransactionType
{
    const SALE       = 'SALE';
    const PREAUTH    = 'PREAUTH';
    const COLLECTION = 'COLLECTION';
    const REFUND     = 'REFUND';
    const VOID       = 'VOID';
}
