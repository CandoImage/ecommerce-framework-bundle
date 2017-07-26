<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\EcommerceFrameworkBundle\VoucherService;

use Pimcore\Bundle\EcommerceFrameworkBundle\CartManager\ICart;
use Pimcore\Bundle\EcommerceFrameworkBundle\Exception\VoucherServiceException;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder;

class DefaultService implements IVoucherService
{
    public $sysConfig;

    public function __construct($config)
    {
        $this->sysConfig = $config;
    }

    /**
     * @param string $code
     * @param ICart $cart
     *
     * @return bool
     *
     * @throws VoucherServiceException
     */
    public function checkToken($code, ICart $cart)
    {
        if ($tokenManager = $this->getTokenManager($code)) {
            return $tokenManager->checkToken($code, $cart);
        }
        throw new VoucherServiceException('No Token for code ' .$code . ' exists.', 3);
    }

    /**
     * @param string $code
     * @param ICart $cart
     *
     * @return bool
     */
    public function reserveToken($code, ICart $cart)
    {
        if ($tokenManager = $this->getTokenManager($code)) {
            return $tokenManager->reserveToken($code, $cart);
        }

        return false;
    }

    /**
     * @param string $code
     * @param ICart $cart
     *
     * @return bool
     */
    public function releaseToken($code, ICart $cart)
    {
        if ($tokenManager = $this->getTokenManager($code)) {
            return $tokenManager->releaseToken($code, $cart);
        }

        return false;
    }

    /**
     * @param string $code
     * @param ICart $cart
     * @param AbstractOrder $order
     *
     * @return bool
     */
    public function applyToken($code, ICart $cart, AbstractOrder $order)
    {
        if ($tokenManager = $this->getTokenManager($code)) {
            if ($orderToken = $tokenManager->applyToken($code, $cart, $order)) {
                $voucherTokens = $order->getVoucherTokens();
                $voucherTokens[] = $orderToken;
                $order->setVoucherTokens($voucherTokens);

                $this->releaseToken($code, $cart);

                return true;
            }
        }

        return false;
    }

    /**
     * Gets the correct token manager and calls removeAppliedTokenFromOrder(), which cleans up the
     * token usage and the ordered token object if necessary, removes the token object from the order.
     *
     * @param \Pimcore\Model\Object\OnlineShopVoucherToken $tokenObject
     * @param AbstractOrder $order
     *
     * @return bool
     */
    public function removeAppliedTokenFromOrder(\Pimcore\Model\Object\OnlineShopVoucherToken $tokenObject, AbstractOrder $order)
    {
        if ($tokenManager = $tokenObject->getVoucherSeries()->getTokenManager()) {
            $tokenManager->removeAppliedTokenFromOrder($tokenObject, $order);

            $voucherTokens = $order->getVoucherTokens();

            $newVoucherTokens = [];
            foreach ($voucherTokens as $voucherToken) {
                if ($voucherToken->getId() != $tokenObject->getId()) {
                    $newVoucherTokens[] = $voucherToken;
                }
            }

            $order->setVoucherTokens($newVoucherTokens);

            return true;
        }

        return false;
    }

    /**
     * @param null $seriesId
     *
     * @return bool
     */
    public function cleanUpReservations($seriesId = null)
    {
        if (isset($seriesId)) {
            return Reservation::cleanUpReservations($this->sysConfig->reservations->duration, $seriesId);
        } else {
            return Reservation::cleanUpReservations($this->sysConfig->reservations->duration);
        }
    }

    /**
     * @param \Pimcore\Model\Object\OnlineShopVoucherSeries $series
     *
     * @return bool
     */
    public function cleanUpVoucherSeries(\Pimcore\Model\Object\OnlineShopVoucherSeries $series)
    {
        return Token\Listing::cleanUpAllTokens($series->getId());
    }

    /**
     * @param null|string $seriesId
     *
     * @return bool
     */
    public function cleanUpStatistics($seriesId = null)
    {
        if (isset($seriesId)) {
            return Statistic::cleanUpStatistics($this->sysConfig->statistics->duration, $seriesId);
        } else {
            return Statistic::cleanUpStatistics($this->sysConfig->statistics->duration);
        }
    }

    /**
     * @param $code
     *
     * @return bool|TokenManager\ITokenManager
     */
    public function getTokenManager($code)
    {
        if ($token = Token::getByCode($code)) {
            if ($series = \Pimcore\Model\Object\OnlineShopVoucherSeries::getById($token->getVoucherSeriesId())) {
                return $series->getTokenManager();
            }
        }

        return false;
    }
}
