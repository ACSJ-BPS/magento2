<?php
/**
 * Class GeneralHandler
 *
 * @author      MundiPagg Embeddables Team <embeddables@mundipagg.com>
 * @copyright   2017 MundiPagg (http://www.mundipagg.com)
 * @license     http://www.mundipagg.com Copyright
 *
 * @link        http://www.mundipagg.com
 */

namespace MundiPagg\MundiPagg\Gateway\Transaction\TwoCreditCard\ResourceGateway\Create\Response;

use Magento\Framework\DataObject;
use Magento\Payment\Gateway\Response\HandlerInterface;
use MundiPagg\MundiPagg\Gateway\Transaction\Base\ResourceGateway\Response\AbstractHandler;
use MundiPagg\MundiPagg\Model\ChargesFactory;
use MundiPagg\MundiPagg\Helper\Logger;
use MundiPagg\MundiPagg\Gateway\Transaction\CreditCard\Config\Config as ConfigCreditCard;

class GeneralHandler extends AbstractHandler implements HandlerInterface
{
	/**
     * \MundiPagg\MundiPagg\Model\ChargesFactory
     */
	protected $modelCharges;

    /**
     * @var \MundiPagg\MundiPagg\Helper\Logger
     */
    protected $logger;

    /**
     * \MundiPagg\MundiPagg\Gateway\Transaction\CreditCard\Config\Config
     */
    protected $configCreditCard;

	/**
     * @return void
     */
    public function __construct(
        ConfigCreditCard $configCreditCard,
    	ChargesFactory $modelCharges,
        Logger $logger
    ) {
        $this->configCreditCard = $configCreditCard;
        $this->modelCharges = $modelCharges;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function _handle($payment, $response)
    {
        $this->logger->logger($response);
        $payment->setTransactionId($response->id);

        $this->setPaymentStateTwoCreditCards($payment, $response);

        $payment->setIsTransactionClosed(false);
        if($this->configCreditCard->getPaymentAction() == 'authorize_capture')  {
            $payment->setIsTransactionClosed(true);
            $payment->accept()
                ->setParentTransactionId($response->id);
        }

        foreach($response->charges as $charge)
        {
        	try {
        		$model = $this->modelCharges->create();
	            $model->setChargeId($charge->id);
	            $model->setCode($charge->code);
	            $model->setOrderId($payment->getOrder()->getIncrementId());
	            $model->setType($charge->paymentMethod);
	            $model->setStatus($charge->status);
	            $model->setAmount($charge->amount);
                
	            $model->setPaidAmount(0);
	            $model->setRefundedAmount(0);
	            $model->setCreatedAt(date("Y-m-d H:i:s"));
	            $model->setUpdatedAt(date("Y-m-d H:i:s"));
	            $model->save();
        	} catch (\Exception $e) {
        		return $e->getMessage();
        	}
        }

        return $this;
    }

    /**
     * @param $payment
     * @param $response
     */
    protected function setPaymentStateTwoCreditCards($payment, $response)
    {
        $capture = $this->configCreditCard->getPaymentAction() == 'authorize_capture' ? true : false;

        $stateMagento = new DataObject();

        $apiResponseStatus = $response->status;

        if (!$capture && $apiResponseStatus == 'pending') {
            $stateMagento->setState('pending_payment')->setStatus('pending_payment');
        }

        if ($capture && $apiResponseStatus == 'pending') {
            $stateMagento->setState('processing')->setStatus('processing');
        }

        if ($apiResponseStatus == 'failed') {
            $stateMagento->setState('canceled')->setStatus('canceled');
        }

        $payment->setData('custom_status', $stateMagento);
    }
}
