<?php

namespace Bafl\Payment\Model\Config\Source;

class TransactionType implements \Magento\Framework\Data\OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'sandbox', 'label' => __('Sandbox')],
            ['value' => 'live', 'label' => __('Live')]
        ];
    }
}