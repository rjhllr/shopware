<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Components\DependencyInjection\Bridge;

use Enlight_Event_EventManager as EnlightEventManager;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Components\Routing\Context;
use Shopware\Components\Routing\Router as RoutingRouter;
use Shopware\Components\Routing\RouterInterface;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class Router
{
    /**
     * @param EnlightEventManager $eventManager
     * @param array $matchers
     * @param array $generators
     * @param array $preFilters
     * @param array $postFilters
     * @return RouterInterface
     */
    public function factory(
        EnlightEventManager $eventManager,
        array $matchers,
        array $generators,
        array $preFilters,
        array $postFilters
    ) {
        $router = new RoutingRouter(
            Context::createEmpty(), // Request object will created on dispatch :/
            $matchers,
            $generators,
            $preFilters,
            $postFilters
        );

        /* Still better than @see \Shopware\Models\Shop\Shop::registerResources */
        $eventManager->addListener(
            'Enlight_Bootstrap_AfterRegisterResource_Shop',
            [$this, 'onAfterRegisterShop'],
            -100
        );

        $eventManager->addListener(
            'Enlight_Controller_Front_PreDispatch',
            [$this, 'onPreDispatch'],
            -100
        );

        return $router;
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     */
    public function onAfterRegisterShop(\Enlight_Event_EventArgs $args)
    {
        /** @var $container Container */
        $container = $args->getSubject();
        /** @var $router RouterInterface  */
        $router = $container->get('router');
        /** @var $shop \Shopware\Models\Shop\Shop */
        $shop = $container->get('shop');
        /** @var $config \Shopware_Components_Config */
        $config = $container->get('config');
        // Register the shop (we're to soon)
        $config->setShop($shop);

        $context = $router->getContext();
        $newContext = Context::createFromShop($shop, $config);
        // Reuse the host
        if ($newContext->getHost() === null) {
            $newContext->setHost($context->getHost());
            $newContext->setBaseUrl($context->getBaseUrl());
            // Reuse https
            if (!$newContext->isSecure()) {
                $newContext->setSecure($context->isSecure());
                $newContext->setSecureBaseUrl($context->getSecureBaseUrl());
            }
        }
        // Reuse the global params like controller and action
        $globalParams = $context->getGlobalParams();
        $newContext->setGlobalParams($globalParams);
        $router->setContext($newContext);
    }

    /**
     * @param \Enlight_Controller_EventArgs $args
     */
    public function onPreDispatch(\Enlight_Controller_EventArgs $args)
    {
        /** @var $front \Enlight_Controller_Front */
        $front = $args->getSubject();
        $request = $front->Request();
        /** @var $router RouterInterface */
        $router = $front->Router();
        // Fix context on forward
        $context = $router->getContext();
        $context->setGlobalParams($context::getGlobalParamsFromRequest($request));
    }
}
