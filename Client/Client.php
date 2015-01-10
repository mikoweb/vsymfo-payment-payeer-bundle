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

namespace vSymfo\Payment\PayeerBundle\Client;

use Symfony\Component\HttpFoundation\Request;

/**
 * Klient Payeer
 * @author Rafał Mikołajun <rafal@vision-web.pl>
 * @package vSymfoPaymentPayeerBundle
 */
class Client
{
    /**
     * Url do formularza
     */
    const FORM_URL = 'https://payeer.com/merchant/';

    /**
     * The identifier of shop registered in Payeer system on which will be made payment.
     * @var string
     */
    private $shopId;

    /**
     * Look to shoop settings
     * @var string
     */
    private $secretKey;

    /**
     * @param string $shopId
     * @param string $secretKey
     */
    function __construct($shopId, $secretKey)
    {
        $this->shopId = $shopId;
        $this->secretKey = $secretKey;
    }


    /**
     * @return string
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * @return string
     */
    public function getShopId()
    {
        return $this->shopId;
    }

    /**
     * Generowanie parametru m_sign do formularza
     * @param array $data
     * @return string
     */
    public function createFormHash(array $data)
    {
        return strtoupper(hash('sha256', implode(':', $data)));
    }

    /**
     * Uzyskaj dane płatności na podstawie obiektu Request
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getPaymentResponse(Request $request)
    {
        $post = $request->request;

        if (is_null($post->get('m_operation_id', null)) || is_null($post->get('m_sign', null))) {
            throw new \Exception('Invalid response', 1);
        }

        $data = array(
            'm_operation_id'        => $post->get('m_operation_id', null),
            'm_operation_ps'        => $post->get('m_operation_ps', null),
            'm_operation_date'      => $post->get('m_operation_date', null),
            'm_operation_pay_date'  => $post->get('m_operation_pay_date', null),
            'm_shop'                => $post->get('m_shop', null),
            'm_orderid'             => $post->get('m_orderid', null),
            'm_amount'              => $post->get('m_amount', null),
            'm_curr'                => $post->get('m_curr', null),
            'm_desc'                => $post->get('m_desc', null),
            'm_status'              => $post->get('m_status', null),
            'm_sign'                => $post->get('m_sign', null),
        );

        $sign_hash = strtoupper(hash('sha256', implode(':', array(
            $data['m_operation_id'],
            $data['m_operation_ps'],
            $data['m_operation_date'],
            $data['m_operation_pay_date'],
            $data['m_shop'],
            $data['m_orderid'],
            $data['m_amount'],
            $data['m_curr'],
            $data['m_desc'],
            $data['m_status'],
            $this->getSecretKey()
        ))));

        if ($post->get('m_sign', null) != $sign_hash) {
            throw new \Exception('Invalid hash', 2);
        }

        return $data;
    }
}
