<?php

declare(strict_types=1);

namespace MyBB\Http\Routes;

use MyBB\Http\Controllers\AuthController;
use Symfony\Component\Routing\Route;

use function MyBB\app;

return new Route(
    '/login/{providerIdentifier}/complete',
    [
        '_controller' => function (array $params): void {
            /** @var AuthController $controller */
            $controller = app()->get(AuthController::class);

            $controller->oauthLoginComplete($params['providerIdentifier']);
        },
    ],
    ['providerIdentifier' => 'discord|drupal|facebook|github|google|linkedin|microsoft|paypal|spotify|wordpress'],
    methods: ['GET'],
);
