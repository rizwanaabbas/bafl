<?php

namespace Bafl\Payment\Model\Config\Source;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;


class IpnUrl extends Field
{
    /**
     * Version constructor.
     *
     * @param Context $context
     * @param PackageInfo $packageInfo
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->getBaseUrl()."baflp/ipn/index";
    }
}
