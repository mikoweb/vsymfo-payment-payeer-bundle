<?php

/*
 * This file is part of the vSymfo package.
 *
 * website: www.vision-web.pl
 * (c) Rafał Mikołajun <rafal@vision-web.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vSymfo\Payment\PayeerBundle\Plugin;

use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\Exception\BlockedException;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use JMS\Payment\CoreBundle\Util\Number;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Router;
use vSymfo\Component\Payments\EventDispatcher\PaymentEvent;
use vSymfo\Payment\PayeerBundle\Client\Client;

/**
 * Plugin płatności Payeer
 * @author Rafał Mikołajun <rafal@vision-web.pl>
 * @package vSymfoPaymentPayeerBundle
 */
class PayeerPlugin extends AbstractPlugin
{
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILS = 'fail';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @param Router $router The router
     * @param Client $client
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(Router $router, Client $client, EventDispatcherInterface $dispatcher)
    {
        $this->client = $client;
        $this->router = $router;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Nazwa płatności
     * @return string
     */
    public function getName()
    {
        return 'payeer_payment';
    }

    /**
     * {@inheritdoc}
     */
    public function processes($name)
    {
        return $this->getName() === $name;
    }

    /**
     * {@inheritdoc}
     */
    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        if ($transaction->getState() === FinancialTransactionInterface::STATE_NEW) {
            throw $this->createPayeerRedirect($transaction);
        }

        $this->approve($transaction, $retry);
        $this->deposit($transaction, $retry);
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @return ActionRequiredException
     */
    public function createPayeerRedirect(FinancialTransactionInterface $transaction)
    {
        $actionRequest = new ActionRequiredException('Redirecting to Payeer.');
        $actionRequest->setFinancialTransaction($transaction);
        $instruction = $transaction->getPayment()->getPaymentInstruction();
        $extendedData = $transaction->getExtendedData();

        $m_shop = $this->client->getShopId();
        $m_orderid = $instruction->getId();
        $m_amount = number_format($transaction->getRequestedAmount(), 2, '.', '');
        $m_curr = $instruction->getCurrency();
        $m_desc = base64_encode(($extendedData->has('description') ? $extendedData->get('description') : ''));
        $m_key = $this->client->getSecretKey();

        $data = array(
            'm_shop'    => $m_shop,
            'm_orderid' => $m_orderid,
            'm_amount'  => $m_amount,
            'm_curr'    => $m_curr,
            'm_desc'    => $m_desc,
            'm_process' => 'send',
            'm_sign'    => $this->client->createFormHash(array(
                $m_shop,
                $m_orderid,
                $m_amount,
                $m_curr,
                $m_desc,
                $m_key
            ))
        );

        $actionRequest->setAction(new VisitUrl(http_build_url(Client::FORM_URL
            , array('query' => http_build_query($data))
            , HTTP_URL_STRIP_AUTH | HTTP_URL_JOIN_PATH | HTTP_URL_JOIN_QUERY | HTTP_URL_STRIP_FRAGMENT
        )));

        return $actionRequest;
    }

    /**
     * Check that the extended data contains the needed values
     * before approving and depositing the transation
     *
     * @param ExtendedDataInterface $data
     * @throws BlockedException
     */
    protected function checkExtendedDataBeforeApproveAndDeposit(ExtendedDataInterface $data)
    {
        if (!$data->has('m_status') || !$data->has('m_operation_id') || !$data->has('m_amount') || !$data->has('m_curr')) {
            throw new BlockedException("Awaiting extended data from Payeer");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function approve(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();
        $this->checkExtendedDataBeforeApproveAndDeposit($data);

        if ($data->get('m_status') == self::STATUS_SUCCESS) {
            $transaction->setReferenceNumber($data->get('m_operation_id'));
            $transaction->setProcessedAmount($data->get('m_amount'));
            $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
            $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
        } else {
            $e = new FinancialException('Payment status unknow: ' . $data->get('m_status'));
            $e->setFinancialTransaction($transaction);
            $transaction->setResponseCode('Unknown');
            $transaction->setReasonCode($data->get('m_status'));
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deposit(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();

        if ($transaction->getResponseCode() !== PluginInterface::RESPONSE_CODE_SUCCESS
            || $transaction->getReasonCode() !== PluginInterface::REASON_CODE_SUCCESS
        ) {
            $e = new FinancialException('Peyment is not completed');
            $e->setFinancialTransaction($transaction);
            throw $e;
        }

        // różnica kwoty zatwierdzonej i kwoty wymaganej musi być równa zero
        // && nazwa waluty musi się zgadzać
        if (Number::compare($transaction->getProcessedAmount(), $transaction->getRequestedAmount()) === 0
            && $transaction->getPayment()->getPaymentInstruction()->getCurrency() == $data->get('m_curr')
        ) {
            // wszystko ok
            // można zakakceptować zamówienie
            $event = new PaymentEvent($this->getName(), $transaction, $transaction->getPayment()->getPaymentInstruction());
            $this->dispatcher->dispatch('deposit', $event);
        } else {
            // coś się nie zgadza, nie można tego zakaceptować
            $e = new FinancialException('The deposit has not passed validation');
            $e->setFinancialTransaction($transaction);
            $transaction->setResponseCode('Unknown');
            $transaction->setReasonCode($data->get('m_status'));
            throw $e;
        }
    }
}
