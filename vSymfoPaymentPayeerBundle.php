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

namespace vSymfo\Payment\PayeerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use vSymfo\Payment\PayeerBundle\DependencyInjection\vSymfoPaymentPayeerExtension;

/**
 * @author Rafał Mikołajun <rafal@vision-web.pl>
 * @package vSymfoPaymentPayeerBundle
 */
class vSymfoPaymentPayeerBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = new vSymfoPaymentPayeerExtension();
        }

        return $this->extension;
    }
}
