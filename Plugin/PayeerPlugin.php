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
            throw $this->createEgopayRedirect($transaction);
        }

        $this->approve($transaction, $retry);
        $this->deposit($transaction, $retry);
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @return ActionRequiredException
     */
    public function createEgopayRedirect(FinancialTransactionInterface $transaction)
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
        /*if (!$data->has('sStatus') || !$data->has('sId') || !$data->has('fAmount')) {
            throw new BlockedException("Awaiting extended data from Payeer");
        }*/
    }

    /**
     * {@inheritdoc}
     */
    public function approve(FinancialTransactionInterface $transaction, $retry)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function deposit(FinancialTransactionInterface $transaction, $retry)
    {
    }
}
