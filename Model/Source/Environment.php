<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Clarion\Payflowpro\Model\Source;

/**
 * Class Environment
 * @codeCoverageIgnore
 */
class Environment implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * @ Class constant
     */
    const PRODUCTION = 'production';
    const SANDBOX = 'sandbox';

    /**
     * Possible environment types
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::SANDBOX,
                'label' => 'Sandbox',
            ],
            [
                'value' => self::PRODUCTION,
                'label' => 'Production'
            ]
        ];
    }
}
