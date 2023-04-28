<?php
/**
 * Copyright © 2020 BAFL. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bafl\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
final class WalletConfig implements ConfigProviderInterface
{
    const CODE = 'bafl_wallet';

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'redirectUrl' => ""
                ]
            ]
        ];
    }
}
