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

/**
 * Klient Payeer
 * @author Rafał Mikołajun <rafal@vision-web.pl>
 * @package vSymfoPaymentPayeerBundle
 */
class Client
{
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
}
