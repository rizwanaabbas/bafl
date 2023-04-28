<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bafl\Payment\Model;

/**
 * Class Account
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 *
 * @api
 * @since 100.0.2
 */
class Account extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_CHECKMO_CODE = 'bafl_account';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CHECKMO_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = \Magento\Payment\Block\Form::class;

    /**
     * @var string
     */
    protected $_infoBlockType = \Bafl\Payment\Block\Account::class;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;
}
