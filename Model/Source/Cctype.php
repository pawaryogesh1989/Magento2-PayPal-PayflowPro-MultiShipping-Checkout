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
class Cctype extends \Magento\Payment\Model\Source\Cctype
{

    /**
     * @return array
     */
    public function getAllowedTypes()
    {
        return ['VI', 'MC', 'AE', 'DI', 'JCB', 'OT'];
    }
}
