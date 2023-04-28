<?php
namespace Bafl\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    //Bank Alfalah Parameters for API and Configuration Value
    const XML_DEFAULT_PATH = 'payment/general/';
    const TRANSACTION_TYPE = 'transaction_type';
    const MERCHANT_ID = 'HS_MerchantId';
    const STORE_ID = 'HS_StoreId';
    const MERCHANT_HASH = 'HS_MerchantHash';
    const MERCHANT_USERNAME = 'HS_MerchantUsername';
    const MERCHANT_PASSWORD = 'HS_MerchantPassword';
    const KEY_1 = 'key_1';
    const KEY_2 = 'key_2';
    const DEBUG = 'debug';

    //Handshake and Payment request URLs
    const HANDSHAKE_REQUEST_ACTION = 'HS/api/HSAPI/HSAPI';
    const PAYMENT_REQUEST_ACTION = 'SSO/SSO/SSO';
    //IP URL
    const IPN_URL = 'HS/api/IPN/OrderStatus/';

    /**
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $encryptor;

    /**
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     *
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->encryptor = $encryptor;
    }

    /**
     * Return store configuration value of your template field that which id you set for template
     *
     * @param string $path
     * @param int $storeId
     * @return mixed
     */
    private function getConfigValue($path, $storeId)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return store
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStore()
    {
        return $this->storeManager->getStore();
    }

    /**
     * Return Merchant ID
     * @return mixed
     */
    public function getMerchantId()
    {
        return $this->getConfigValue(self::XML_DEFAULT_PATH.self::MERCHANT_ID, $this->getStore()->getStoreId());
    }

    /**
     * Return Store ID
     * @return mixed
     */
    public function getStoreId()
    {
        return $this->getConfigValue(self::XML_DEFAULT_PATH.self::STORE_ID, $this->getStore()->getStoreId());
    }

    /**
     * Return Merchant Hash
     * @return mixed
     */
    public function getMerchantHash()
    {
        $value = $this->getConfigValue(self::XML_DEFAULT_PATH.self::MERCHANT_HASH, $this->getStore()->getStoreId());
        return $this->encryptor->decrypt($value);
    }

    /**
     * Return Merchant Username
     * @return mixed
     */
    public function getMerchantUsername()
    {
        return $this->getConfigValue(self::XML_DEFAULT_PATH.self::MERCHANT_USERNAME, $this->getStore()->getStoreId());
    }

    /**
     * Return Merchant Password
     * @return mixed
     */
    public function getMerchantPassword()
    {
        $value = $this->getConfigValue(self::XML_DEFAULT_PATH.self::MERCHANT_PASSWORD, $this->getStore()->getStoreId());
        return $this->encryptor->decrypt($value);
    }

    /**
     * Return key1
     * @return mixed
     */
    public function getKey1()
    {
        return $this->getConfigValue(self::XML_DEFAULT_PATH.self::KEY_1, $this->getStore()->getStoreId());
    }

    /**
     * Return key1
     * @return mixed
     */
    public function getKey2()
    {
        return $this->getConfigValue(self::XML_DEFAULT_PATH.self::KEY_2, $this->getStore()->getStoreId());
    }

    /**
     * Return debug
     * @return mixed
     */
    public function getDebug()
    {
        return $this->getConfigValue(self::XML_DEFAULT_PATH.self::DEBUG, $this->getStore()->getStoreId());
    }

    /**
     * Return debug
     * @return Handshake URL
     */
    public function getHandshakeRequestUrl()
    {
        if ($this->getConfigValue(self::XML_DEFAULT_PATH.self::TRANSACTION_TYPE, $this->getStore()->getStoreId()) == "sandbox"){
            return "https://sandbox.bankalfalah.com/".self::HANDSHAKE_REQUEST_ACTION;
        } else {
            return "https://payments.bankalfalah.com/".self::HANDSHAKE_REQUEST_ACTION;
        }
    }

    /**
     * Return getPaymentRequestUrl
     * @return Transaction URL
     */
    public function getPaymentRequestUrl()
    {
        if ($this->getConfigValue(self::XML_DEFAULT_PATH.self::TRANSACTION_TYPE, $this->getStore()->getStoreId()) == "sandbox"){
            return "https://sandbox.bankalfalah.com/".self::PAYMENT_REQUEST_ACTION;
        } else {
            return "https://payments.bankalfalah.com/".self::PAYMENT_REQUEST_ACTION;
        }
    }

    /**
     * Return getStoreUrl
     * @return Store URL
     */
    public function getStoreUrl($route)
    {
        return $this->storeManager->getStore()->getUrl($route);
    }

    /**
     * Return getIpnUrl
     * @return Ipn URL
     */
    public function getIpnUrl()
    {
        if ($this->getConfigValue(self::XML_DEFAULT_PATH.self::TRANSACTION_TYPE, $this->getStore()->getStoreId()) == "sandbox"){
            return "https://sandbox.bankalfalah.com/".self::IPN_URL.$this->getMerchantId()."/".$this->getStoreId()."/";
        } else {
            return "https://payments.bankalfalah.com/".self::IPN_URL.$this->getMerchantId()."/".$this->getStoreId()."/";
        }
    }

}