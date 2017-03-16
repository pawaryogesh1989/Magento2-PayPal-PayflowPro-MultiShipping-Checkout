<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Clarion\Payflowpro\Model\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 *
 * Authorize.net Payment Action Dropdown source
 */
class PaymentAction implements ArrayInterface
{

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => \Clarion\Payflowpro\Model\Payflowpro::ACTION_AUTHORIZE,
                'label' => __('Authorize Only'),
            ],
            [
                'value' => \Clarion\Payflowpro\Model\Payflowpro::ACTION_AUTHORIZE_CAPTURE,
                'label' => __('Authorize and Capture')
            ]
        ];
    }
}
