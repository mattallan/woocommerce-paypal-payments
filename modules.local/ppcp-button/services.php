<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\Button\Assets\DisabledSmartButton;
use Inpsyde\PayPalCommerce\Button\Assets\SmartButton;
use Inpsyde\PayPalCommerce\Button\Assets\SmartButtonInterface;
use Inpsyde\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\RequestData;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\Onboarding\State;

return [
    'button.smart-button' => static function (ContainerInterface $container): SmartButtonInterface {

        $state = $container->get('onboarding.state');
        /**
         * @var State $state
         */
        if ($state->currentState() < State::STATE_PROGRESSIVE) {
            return new DisabledSmartButton();
        }
        $settings = $container->get('wcgateway.settings');
        if (!$settings->has('enabled') || ! wc_string_to_bool($settings->get('enabled'))) {
            return new DisabledSmartButton();
        }
        $payeeRepository = $container->get('api.repository.payee');
        $identityToken = $container->get('api.endpoint.identity-token');
        $payerFactory = $container->get('api.factory.payer');
        return new SmartButton(
            $container->get('button.url'),
            $container->get('session.handler'),
            $settings,
            $payeeRepository,
            $identityToken,
            $payerFactory
        );

    },
    'button.url' => static function (ContainerInterface $container): string {
        return plugins_url(
            '/modules/ppcp-button/',
            dirname(__FILE__, 3) . '/woocommerce-paypal-commerce-gateway.php'
        );
    },
    'button.request-data' => static function (ContainerInterface $container): RequestData {
        return new RequestData();
    },
    'button.endpoint.change-cart' => static function (ContainerInterface $container): ChangeCartEndpoint {
        if (!\WC()->cart) {
            throw new RuntimeException('cant initialize endpoint at this moment');
        }
        $cart = WC()->cart;
        $shipping = WC()->shipping();
        $requestData = $container->get('button.request-data');
        $repository = $container->get('api.repository.cart');
        $dataStore = \WC_Data_Store::load('product');
        return new ChangeCartEndpoint($cart, $shipping, $requestData, $repository, $dataStore);
    },
    'button.endpoint.create-order' => static function (ContainerInterface $container): CreateOrderEndpoint {
        $requestData = $container->get('button.request-data');
        $repository = $container->get('api.repository.cart');
        $apiClient = $container->get('api.endpoint.order');
        $payerFactory = $container->get('api.factory.payer');
        return new CreateOrderEndpoint($requestData, $repository, $apiClient, $payerFactory);
    },
    'button.endpoint.approve-order' => static function (ContainerInterface $container): ApproveOrderEndpoint {
        $requestData = $container->get('button.request-data');
        $apiClient = $container->get('api.endpoint.order');
        $sessionHandler = $container->get('session.handler');
        return new ApproveOrderEndpoint($requestData, $apiClient, $sessionHandler);
    },
];
