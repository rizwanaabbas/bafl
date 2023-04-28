<?php

namespace Bafl\Payment\Controller\Index;

use Bafl\Payment\Helper\Config as HelperData;
use Bafl\Payment\Logger\Logger;
use Magento\Checkout\Model\Session;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use Zend\Http\Client;

class Index extends \Magento\Framework\App\Action\Action {

    //Bank Alfalah Channel IDs and Cipher Encryption
    const REST_CHANNEL_ID = '1002';
    const REDIRECT_CHANNEL_ID = '1001';
    const CIPHER = 'aes-128-cbc';

    const HANDSHAKE_FAILED_STATUS = 'baflpayment_failed';
    const WALLET_FAILED_STATUS = 'baflw_failed';
    const ACCOUNT_FAILED_STATUS = 'bafla_failed';
    const CARD_FAILED_STATUS = 'baflc_failed';

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
	protected $resultPageFactory;
    /**
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;
    /**
     * @var Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    /**
     * @var Bafl\Payment\Logger\Logger
     */
    private $logger;
    /**
     * @var Bafl\Payment\Helper\Config as HelperData
     */
    private $helperData;
    /**
     * @var Zend\Http\Client
     */
    protected $zendClient;
    /**
     * @var Magento\Sales\Api\Data\OrderInterfaceFactory
     */
    protected $orderFactory;
    /**
	 *
	 * @param \Magento\Framework\App\Action\Context $context
	 * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
	 * @param \Magento\Framework\Registry $coreRegistry
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 * @param \Bafl\Payment\Helper\Config as HelperData $helperData
	 * @param \Zend\Http\Client $zendClient
	 * @param \Bafl\Payment\Logger\Logger $logger
	 * @param \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory
	 *
	 */
	public function __construct(
	    Context $context,
        PageFactory $resultPageFactory,
        Registry $coreRegistry,
        Session $checkoutSession,
        HelperData $helperData,
        Client $zendClient,
        Logger $logger,
        OrderInterfaceFactory $orderFactory
    )
	{
        $this->_coreRegistry = $coreRegistry;
		$this->resultPageFactory = $resultPageFactory;
        $this->checkoutSession = $checkoutSession;
        $this->helperData = $helperData;
        $this->zendClient = $zendClient;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
		parent::__construct ( $context );
	}

	/**
	 * Default Execute
	 *
	 * @return void
	 *
	 */

	public function execute() {
        //get order from session
        $order = $this->checkoutSession->getLastRealOrder();
        $incrementId = $order->getIncrementId();
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        //set order status
        $order->setState("pending_payment")->setStatus("pending_payment");
        $order->save();

        //set order and payment method
        $this->_coreRegistry->register('baflorder', $order);
        $this->_coreRegistry->register('baflpayment', $order->getPayment()->getMethod());

        //Get Configurations
        $HS_ChannelId = self::REDIRECT_CHANNEL_ID;
        $HS_MerchantId = $this->helperData->getMerchantId();
        $HS_StoreId = $this->helperData->getStoreId();
        $HS_ReturnURL = $this->helperData->getStoreUrl("checkout/onepage/success");
        $HS_MerchantHash = $this->helperData->getMerchantHash();
        $HS_MerchantUsername = $this->helperData->getMerchantUsername();
        $HS_MerchantPassword = $this->helperData->getMerchantPassword();
        $Key1 = $this->helperData->getKey1();
        $Key2 = $this->helperData->getKey2();

        //Encryption
        //Creating String
        $mapString =
            "HS_IsRedirectionRequest=1&HS_ChannelId=$HS_ChannelId"
            . "&HS_MerchantId=$HS_MerchantId"
            . "&HS_StoreId=$HS_StoreId"
            . "&HS_ReturnURL=$HS_ReturnURL"
            . "&HS_MerchantHash=$HS_MerchantHash"
            . "&HS_MerchantUsername=$HS_MerchantUsername"
            . "&HS_MerchantPassword=$HS_MerchantPassword"
            . "&HS_TransactionReferenceNumber=$incrementId&handshake=";


        //Generating Hash
        $cipher = self::CIPHER;
        $cipher_text = openssl_encrypt($mapString, $cipher, $Key1,   OPENSSL_RAW_DATA , $Key2);
        $HS_RequestHash =  base64_encode($cipher_text);

        //Preparing Data For API
        $data = [
            'HS_RequestHash' => $HS_RequestHash,
            'HS_IsRedirectionRequest' => '1',
            'HS_ChannelId' => $HS_ChannelId,
            'HS_ReturnURL' => $HS_ReturnURL,
            'HS_MerchantId' => $HS_MerchantId,
            'HS_StoreId' => $HS_StoreId,
            'HS_MerchantHash' => $HS_MerchantHash,
            'HS_MerchantUsername' => $HS_MerchantUsername,
            'HS_MerchantPassword' => $HS_MerchantPassword,
            'HS_TransactionReferenceNumber' => $incrementId
        ];
        //Request API Handshake
        try
        {
            $this->zendClient->reset();
            $this->zendClient->setUri($this->helperData->getHandshakeRequestUrl());
            $this->zendClient->setMethod(\Zend\Http\Request::METHOD_POST);
            $this->zendClient->setHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);
            $this->zendClient->setRawBody(json_encode($data));
            $httpClientOptions = array(
                'sslverifypeer' => false,
                'timeout' => 60,
            );
            $this->zendClient->setOptions($httpClientOptions);
            $this->zendClient->send();
            $response = $this->zendClient->getResponse();
            // response
            $content = json_decode(json_decode($response->getContent()),true);
            // set order status for failed
            if($content['success'] == "true"){
                $this->_coreRegistry->register('AuthToken', $content['AuthToken']);
                // debug
                if($this->helperData->getDebug()){
                    $this->logger->info('Handshake successful. '.$response->getContent());
                }
                return $this->resultPageFactory->create();
            } else {
                $this->_coreRegistry->register('AuthToken', false);
                // change order status
                $order->setState(self::HANDSHAKE_FAILED_STATUS)->setStatus(self::HANDSHAKE_FAILED_STATUS);
                $order->save();
                // debug
                if($this->helperData->getDebug()){
                    $this->logger->info('Handshake was not successful. '.$response->getContent());
                }
                return $this->_redirect('checkout/onepage/success');
            }
        }
        catch (\Zend\Http\Exception\RuntimeException $runtimeException)
        {
            $this->_coreRegistry->register('AuthToken', false);
            // change order status
            $order->setState(self::HANDSHAKE_FAILED_STATUS)->setStatus(self::HANDSHAKE_FAILED_STATUS);
            $order->save();
            // debug
            if($this->helperData->getDebug()){
                $this->logger->info('Handshake was not successful. '.$runtimeException->getMessage());
            }
            return $this->_redirect('checkout/onepage/success');
        }
    }

}