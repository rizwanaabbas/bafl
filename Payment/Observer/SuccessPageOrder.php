<?php
namespace Bafl\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Bafl\Payment\Logger\Logger;
use Bafl\Payment\Helper\Config as HelperData;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\RequestInterface;
use Zend\Http\Client;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;

class SuccessPageOrder implements ObserverInterface
{

    // Payment Methods
    const PAYMENT_WALLET = 'bafl_wallet';
    const PAYMENT_ACCOUNT = 'bafl_account';
    const PAYMENT_CARD = 'bafl_card';

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
    const SESSIONENDED_TRANSACTION_STATUS = 'SessionEnded';

    /**
     * @var Bafl\Payment\Logger\Logger
     */
    private $logger;
    /**
     * @var Bafl\Payment\Helper\Config as HelperData
     */
    private $helperData;
    /**
     * @var Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;
    /**
     * @var Magento\Framework\App\RequestInterface
     */
    protected $request;
    /**
     * @var \Zend\Http\Client
     */
    protected $zendClient;
    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    protected $transactionBuilder;

    /**
     *
     * @param \Bafl\Payment\Logger\Logger $logger
     * @param \Bafl\Payment\Helper\Config as HelperData $helperData
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\App\RequestInterface $request
     *
     */
    public function __construct(
        Logger $logger,
        HelperData $helperData,
        ManagerInterface $messageManager,
        RequestInterface $request,
        Client $zendClient,
        BuilderInterface $transactionBuilder
    )
    {
        $this->logger = $logger;
        $this->helperData = $helperData;
        $this->_messageManager = $messageManager;
        $this->request = $request;
        $this->zendClient = $zendClient;
        $this->transactionBuilder = $transactionBuilder;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        //getting order, status, and payment
        $order = $observer->getEvent()->getOrder();
        $orderIn = $order->getIncrementId();
        $order_status = $order->getStatus();
        $payment = $order->getPayment()->getMethod();
        $params = $this->request->getParams();
        //"?RC=00&RD=PS&TS=P&O=000000184"

        // setting order status if not done By IPN
        if($order_status == "pending_payment"){
            if($payment == self::PAYMENT_ACCOUNT || $payment == self::PAYMENT_CARD || $payment == self::PAYMENT_WALLET){
                if($params && isset($params['RC'])){
                    //get
                    try
                    {
                        $this->zendClient->reset();
                        $this->zendClient->setUri($this->helperData->getIpnUrl().$orderIn);
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
                            // set transaction details
                            $this->addTransactionToOrder($order, $content);
                            if($content['TransactionStatus'] == self::SUCCESS_TRANSACTION_STATUS){
                                // set order status for paid
                                $order->setState("processing")->setStatus("processing");
                                $order->save();
                                $this->_messageManager->addSuccessMessage(__('Congratulations! Payment Successful.'));
                                // debug
                                if($this->helperData->getDebug()){
                                    $this->logger->info('Order Success, IPN paid. '.$response->getContent());
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
                                $this->_messageManager->addErrorMessage(__('Sorry! Payment Failed. But your order has been placed. Our representative will contact you soon.'));
                                // debug
                                if($this->helperData->getDebug()){
                                    $this->logger->info('Order Success, IPN failed. '.$response->getContent());
                                }
                            }
                        } else {
                            $this->_messageManager->addErrorMessage(__('Sorry! Payment Failed. But your order has been placed. Our representative will contact you soon.'));
                            // debug
                            if($this->helperData->getDebug()){
                                $this->logger->info('Order Success, IPN , order not found. '.$response->getContent());
                            }
                        }
                    }
                    catch (\Zend\Http\Exception\RuntimeException $runtimeException)
                    {
                        $this->_messageManager->addErrorMessage(__('Sorry! Payment Failed. But your order has been placed. Our representative will contact you soon.'));
                        // debug
                        if($this->helperData->getDebug()){
                            $this->logger->info('Order Success, IPN Call, '.$runtimeException->getMessage());
                        }
                    }
                }
            }
        } else {
            if($payment == self::PAYMENT_ACCOUNT || $payment == self::PAYMENT_CARD || $payment == self::PAYMENT_WALLET) {
                if($params && isset($params['RC']) && $params['RC'] == '00'){
                    if($params['TS'] == 'P'){
                        $this->_messageManager->addSuccessMessage(__('Congratulations! Payment Successful.'));
                    } else {
                        $this->_messageManager->addErrorMessage(__('Sorry! Payment Failed. But your order has been placed. Our representative will contact you soon.'));
                    }
                } else {
                    $this->_messageManager->addErrorMessage(__('Sorry! Payment Failed. But your order has been placed. Our representative will contact you soon.'));
                }
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

            $txnId = !empty($paymentData['TransactionId'])? $paymentData['TransactionId']: $paymentData['TransactionReferenceNumber'];
            // Prepare payment object
            $payment = $order->getPayment();
            $payment->setLastTransId($txnId);
            $payment->setTransactionId($txnId);
            $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]);

            // Formatted price
            $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());

            // Prepare transaction
            $transaction = $this->transactionBuilder->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($txnId)
                ->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData])
                ->setFailSafe(true)
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

            // Add transaction to payment
            $payment->addTransactionCommentsToOrder($transaction, __('The authorized amount is %1. Transaction status is %2.', $formatedPrice, $paymentData['TransactionStatus']));
            $payment->setParentTransactionId(null);

            // Save payment, transaction and order
            $paymentArray = array(self::PAYMENT_WALLET=>self::WALLET_ID, self::PAYMENT_ACCOUNT=>self::ACCOUNT_ID, self::PAYMENT_CARD=>self::CARD_ID);
            $reversePaymentArray = array(self::WALLET_ID=>self::PAYMENT_WALLET, self::ACCOUNT_ID=>self::PAYMENT_ACCOUNT, self::CARD_ID=>self::PAYMENT_CARD);

            if($paymentArray[$payment->getMethod()] != $paymentData['TransactionTypeId']){
                $payment->setMethod($reversePaymentArray[$paymentData['TransactionTypeId']]);
            }
            $payment->save();
            $order->save();
            $transaction->save();
        } catch (\Zend\Http\Exception\RuntimeException $e) {
            if($this->helperData->getDebug()) {
                $this->logger->info($e->getMessage());
            }
        }
    }
}