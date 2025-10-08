<?php
/**
 * Shared constants for storefront preview query parameters.
 *
 * @category IdeallyStudio
 * @package  IdeallyStudio_MerchantToolkit
 * @author   Ideally Studio
 * @license  See LICENSE file for license details.
 * @link     https://www.ideallystudio.com/
 */

namespace IdeallyStudio\MerchantToolkit\Model;

/**
 * Shared parameter names used to support storefront preview links.
 *
 * @license  See LICENSE file for license details.
 * @link     https://www.ideallystudio.com/
 */
class ProductPreviewParameters
{
    /**
     * Flag indicating that the request should be treated as a preview.
     */
    public const FLAG = 'ist_preview';

    /**
     * Token parameter granting temporary access to the product.
     */
    public const TOKEN = 'ist_preview_token';
}
