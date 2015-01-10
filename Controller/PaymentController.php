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

namespace vSymfo\Payment\PayeerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kontroler płatności
 * @author Rafał Mikołajun <rafal@vision-web.pl>
 * @package vSymfoPaymentPayeerBundle
 */
class PaymentController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     * @throws \RuntimeException
     */
    public function callbackAction(Request $request)
    {
        $instruction = $this->getDoctrine()
            ->getRepository('JMSPaymentCoreBundle:PaymentInstruction')
            ->find((int)$request->request->get('m_orderid'))
        ;

        if (is_null($instruction)) {
            $this->createNotFoundException('Instruction not found');
        }

        $client = $this->get('payment.payeer.client');
        $response = $client->getPaymentResponse($request);

        if (null === $transaction = $instruction->getPendingTransaction()) {
            throw new \RuntimeException('No pending transaction found for the payment instruction');
        }

        $em = $this->getDoctrine()->getManager();
        $extendedData = $transaction->getExtendedData();
        $extendedData->set('m_operation_id', $response['m_operation_id']);
        $extendedData->set('m_operation_ps', $response['m_operation_ps']);
        $extendedData->set('m_operation_date', $response['m_operation_date']);
        $extendedData->set('m_operation_pay_date', $response['m_operation_pay_date']);
        $extendedData->set('m_shop', $response['m_shop']);
        $extendedData->set('m_orderid', $response['m_orderid']);
        $extendedData->set('m_amount', $response['m_amount']);
        $extendedData->set('m_curr', $response['m_curr']);
        $extendedData->set('m_desc', $response['m_desc']);
        $extendedData->set('m_status', $response['m_status']);
        $extendedData->set('m_sign', $response['m_sign']);
        $em->persist($transaction);

        $payment = $transaction->getPayment();
        $result = $this->get('payment.plugin_controller')->approveAndDeposit($payment->getId(), (float)$response['m_amount']);
        if (is_object($ex = $result->getPluginException())) {
            throw $ex;
        }

        $em->flush();

        return new Response('OK');
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function successAction(Request $request)
    {
        $instruction = $this->getDoctrine()
            ->getRepository('JMSPaymentCoreBundle:PaymentInstruction')
            ->find((int)$request->query->get('m_orderid'))
        ;

        if (is_null($instruction)) {
            $this->createNotFoundException('Instruction not found');
        }

        return $this->redirect($this->generateUrl($this->container->getParameter('vsymfo_payment_payeer.route_success'), array(
            'id' => $instruction->getId()
        )));
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function failAction(Request $request)
    {
        $instruction = $this->getDoctrine()
            ->getRepository('JMSPaymentCoreBundle:PaymentInstruction')
            ->find((int)$request->query->get('m_orderid'))
        ;

        if (is_null($instruction)) {
            $this->createNotFoundException('Instruction not found');
        }

        return $this->redirect($this->generateUrl($this->container->getParameter('vsymfo_payment_payeer.route_fail'), array(
            'id' => $instruction->getId()
        )));
    }
}
