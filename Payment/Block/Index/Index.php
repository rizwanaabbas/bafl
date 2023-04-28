<?php

namespace Bafl\Payment\Block\Index;

use Bafl\Payment\Helper\Config as HelperData;

class Index extends \Magento\Framework\View\Element\Template
{

    //Bank Alfalah Trasaction Type IDs
    const WALLET_ID = '1';
    const ACCOUNT_ID = '2';
    const CARD_ID = '3';

    // Payment Methods
    const PAYMENT_WALLET = 'bafl_wallet';
    const PAYMENT_ACCOUNT = 'bafl_account';
    const PAYMENT_CARD = 'bafl_card';

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    /**
     * @var \Bafl\Payment\Helper\Config as HelperData
     */
    private $helperData;
    /**
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    /**
     *
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Bafl\Payment\Helper\Config as HelperData $helperData
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     *
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        HelperData $helperData,
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->helperData = $helperData;
        $this->_coreRegistry = $coreRegistry;
        parent::__construct($context,$data);
    }

    /**
     * Return Order
     * @return mixed
     */
    public function getOrder()
    {
        return $this->_coreRegistry->registry('baflorder');
    }

    /**
     * Return payment code
     * @return mixed
     */
    public function getPaymentCode()
    {
        $payment = $this->_coreRegistry->registry('baflpayment');
        if($payment == self::PAYMENT_WALLET){
            return self::WALLET_ID;
        } elseif($payment == self::PAYMENT_ACCOUNT){
            return self::ACCOUNT_ID;
        } else{
            return self::CARD_ID;
        }
    }

    /**
     * Return Merchant ID
     * @return mixed
     */
    public function getMerchantId()
    {
        return $this->helperData->getMerchantId();
    }

    /**
     * Return Store ID
     * @return mixed
     */
    public function getStoreId()
    {
        return $this->helperData->getStoreId();
    }

    /**
     * Return Merchant Hash
     * @return mixed
     */
    public function getMerchantHash()
    {
        return $this->helperData->getMerchantHash();
    }

    /**
     * Return Merchant Username
     * @return mixed
     */
    public function getMerchantUsername()
    {
        return $this->helperData->getMerchantUsername();
    }

    /**
     * Return Merchant Password
     * @return mixed
     */
    public function getMerchantPassword()
    {
        return $this->helperData->getMerchantPassword();
    }

    /**
     * Return key1
     * @return mixed
     */
    public function getKey1()
    {
        return $this->helperData->getKey1();
    }

    /**
     * Return key2
     * @return mixed
     */
    public function getKey2()
    {
        return $this->helperData->getKey2();
    }

    /**
     * Return HandShakeRequestUrl
     * @return mixed
     */
    public function getHandshakeRequestUrl()
    {
        return $this->helperData->getHandshakeRequestUrl();
    }

    /**
     * Return PaymentRequestUrl
     * @return mixed
     */
    public function getPaymentRequestUrl()
    {
        return $this->helperData->getPaymentRequestUrl();
    }

    /**
     * Return HandShake AuthToken
     * @return mixed
     */
    public function getBaflAuthToken()
    {
        return $this->_coreRegistry->registry('AuthToken');
    }

    /**
     * Return Payment RequestHash
     * @return mixed
     */
    public function getPaymentRequestHash()
    {
        $order = $this->getOrder();
        $HS_ChannelId = 1001;
        $AuthToken = $this->getBaflAuthToken();
        $post = [];
        $post['AuthToken'] = $AuthToken;
        $post['ChannelId'] = $HS_ChannelId;
        $post['Currency'] = "PKR";
        $post['ReturnURL'] = $this->getUrl('checkout/onepage/success');
        $post['MerchantId'] = $this->getMerchantId();
        $post['StoreId'] = $this->getStoreId();
        $post['MerchantHash'] = $this->getMerchantHash();
        $post['MerchantUsername'] = $this->getMerchantUsername();
        $post['MerchantPassword'] = $this->getMerchantPassword();
        $post['TransactionTypeId'] = $this->getPaymentCode();
        $post['TransactionReferenceNumber'] = $order->getIncrementId();
        $post['TransactionAmount'] = number_format($order->getGrandTotal());

        $data = [];
        foreach($post as $k => $v) {
            $data[] = implode("=", [$k, $v]);
        }

        $mapString = implode('&', $data);

        $cipher="aes-128-cbc";
        $cipher_text = openssl_encrypt(utf8_encode($mapString), $cipher, utf8_encode($this->getKey1()),   OPENSSL_RAW_DATA , utf8_encode($this->getKey2()));
        $cipher_text64 =  base64_encode($cipher_text);

        return $cipher_text64 ;
    }
}
