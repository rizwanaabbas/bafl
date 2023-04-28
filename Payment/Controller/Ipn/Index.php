<?php

namespace Bafl\Payment\Controller\Ipn;

use Bafl\Payment\Logger\Logger;
use Bafl\Payment\Helper\Config as HelperData;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Zend\Http\Client;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;

class Index extends \Magento\Framework\App\Action\Action
{
    //Bank Alfalah Trasaction Type IDs and Transaction Failed Statuses
    const WALLET_ID = '1';
    const ACCOUNT_ID = '2';
    const CARD_ID = '3';

    const WALLET_FAILED_STATUS = 'baflw_failed';
    const ACCOUNT_FAILED_STATUS = 'bafla_failed';
    const CARD_FAILED_STATUS = 'baflc_failed';

    //Bank Alfalah IPN TransactionStatus
    const SUCCESS_TRANSACTION_STATUS = 'Paid';
    const FAILURE_TRANSACTION_STATUS = 'Failed';

    /**
     * @var \Bafl\Payment\Helper\Config as HelperData
     */
    private $helperData;
    /**
     * @var \Zend\Http\Client
     */
    protected $zendClient;
    /**
     * @var \Bafl\Payment\Logger\Logger
     */
    private $logger;
    /**
     * @var \Magento\Sales\Api\Data\OrderInterfaceFactory
     */
    protected $orderFactory;
    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    protected $transactionBuilder;

	/**
	 *
	 * @param \Magento\Framework\App\Action\Context $context
	 * @param \Bafl\Payment\Helper\Config as HelperData $helperData
	 * @param \Zend\Http\Client $zendClient
	 * @param \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory
	 * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
	 * @param \Bafl\Payment\Logger\Logger $logger
	 *
	 */
	public function __construct(
        Context $context,
        HelperData $helperData,
        Client $zendClient,
        OrderInterfaceFactory $orderFactory,
        BuilderInterface $transactionBuilder,
        Logger $logger
    ){
        $this->orderFactory = $orderFactory;
        $this->helperData = $helperData;
        $this->zendClient = $zendClient;
        $this->transactionBuilder = $transactionBuilder;
        $this->logger = $logger;
		parent::__construct ( $context );
	}

	/**
	 * Default execute
	 *
	 * @return void
	 *
	 */
	public function execute() {
		die();
        // get params URL
        $params = $this->getRequest()->getParams();
        //IPN Call GET URL
        if(!empty($params) && isset($params['url']) && !empty($params['url'])){
            try
            {
                $this->zendClient->reset();
                $this->zendClient->setUri($params['url']);
                $this->zendClient->setMethod(\Zend\Http\Request::METHOD_GET);
                $httpClientOptions = array(
                    'sslverifypeer' => false,
                    'timeout' => 60,
                );
                $this->zendClient->setOptions($httpClientOptions);
                $this->zendClient->send();
                $response = $this->zendClient->getResponse();
                // response
                $content = json_decode(json_decode($response->getContent()),true);
                // if response is success Paid/Failed
                if($content['ResponseCode'] == '00'){
                    // getting order from orderid in IPN call
                    $order = $this->orderFactory->create()->loadByIncrementId($content['TransactionReferenceNumber']);
                    // set transaction details
                    $this->addTransactionToOrder($order, $content);
                    if($content['TransactionStatus'] == self::SUCCESS_TRANSACTION_STATUS){
                        // set order status for paid
                        $order->setState("processing")->setStatus("processing");
                        $order->save();
                        // debug
                        if($this->helperData->getDebug()){
                            $this->logger->info('IPN Call, paid. '.$response->getContent());
                        }
                    } else {
                        // set order status for failed
                        if($content['TransactionTypeId'] == self::WALLET_ID){
                            $order->setState(self::WALLET_FAILED_STATUS)->setStatus(self::WALLET_FAILED_STATUS);
                        } elseif ($content['TransactionTypeId'] == self::ACCOUNT_ID){
                            $order->setState(self::ACCOUNT_FAILED_STATUS)->setStatus(self::ACCOUNT_FAILED_STATUS);
                        } elseif ($content['TransactionTypeId'] == self::CARD_ID){
                            $order->setState(self::CARD_FAILED_STATUS)->setStatus(self::CARD_FAILED_STATUS);
                        }
                        $order->save();
                        // debug
                        if($this->helperData->getDebug()){
                            $this->logger->info('IPN Call, failed. '.$response->getContent());
                        }
                    }
                } else {
                    // debug
                    if($this->helperData->getDebug()){
                        $this->logger->info('IPN Call, order not found. '.$response->getContent());
                    }
                }
            }
            catch (\Zend\Http\Exception\RuntimeException $runtimeException)
            {
                // debug
                if($this->helperData->getDebug()){
                    $this->logger->info('IPN Call, '.$runtimeException->getMessage());
                }
            }
        } else {
            // debug
            if($this->helperData->getDebug()){
                $this->logger->info('Bank Alfalah IPN parameters missing.');
            }
        }
	}
    /**
     * Add Transaction In Order
     *
     * @return void
     *
     */
    public function addTransactionToOrder($order, $paymentData = array()) {
        try {
            // Prepare payment object
            $payment = $order->getPayment();
            $payment->setLastTransId($paymentData['TransactionId']);
            $payment->setTransactionId($paymentData['TransactionId']);
            $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]);

            // Formatted price
            $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());

            // Prepare transaction
            $transaction = $this->transactionBuilder->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($paymentData['TransactionId'])
                ->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData])
                ->setFailSafe(true)
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

            // Add transaction to payment
            $payment->addTransactionCommentsToOrder($transaction, __('The authorized amount is %1. Transaction status is %2.', $formatedPrice, $paymentData['TransactionStatus']));
            $payment->setParentTransactionId(null);

            // Save payment, transaction and order
            $payment->save();
            $transaction->save();
        } catch (\Zend\Http\Exception\RuntimeException $e) {
            if($this->helperData->getDebug()) {
                $this->logger->info($e->getMessage());
            }
        }
    }
}