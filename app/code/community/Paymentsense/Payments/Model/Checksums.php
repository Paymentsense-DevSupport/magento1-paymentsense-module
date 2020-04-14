<?php
/*
 * Copyright (C) 2020 Paymentsense Ltd.
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
 * @copyright   2020 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Paymentsense Checksums Model
 */
class Paymentsense_Payments_Model_Checksums extends Paymentsense_Payments_Model_Report
{
    /**
     * Gets file checksums
     *
     * @return array
     */
    protected function getFileChecksums()
    {
        $result   = array();
        $rootPath = MAGENTO_ROOT;
        $fileList = Mage::app()->getRequest()->getParam('data');
        if (is_array($fileList)) {
            foreach ($fileList as $key => $file) {
                $filename     = $rootPath . '/' . $file;
                // @codingStandardsIgnoreLine
                $result[$key] = is_file($filename)
                    ? sha1_file($filename)
                    : null;
            }
        }

        return $result;
    }

    /**
     * Gets formatted file checksums
     *
     * @return array
     */
    public function getFormattedChecksumsInfo()
    {
        $info = $this->getChecksumsInfo();
        return $this->formatOutput($info);
    }

    /**
     * Gets file checksums
     *
     * @return array
     */
    public function getChecksumsInfo()
    {
        $result = array(
            'Checksums' => $this->getFileChecksums()
        );
        return $result;
    }
}
