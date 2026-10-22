<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Slim\App;
use App\Framework\Http\Cookie;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Http\Session;
use App\Framework\Middleware\LocaleMiddleware;
use App\Modules\Auth\AuthMiddleware;
use App\Modules\Auth\AuthService;

return function (ContainerInterface $container, $start_time, $start_memory): App
{
	/** @var App<ContainerInterface> $app */
	$app = $container->get(App::class);

	require_once __DIR__ . '/route.php';

	// determines the locale after the auth middleware (auto login writes it into the session)
	$app->add(new LocaleMiddleware($container->get(Locales::class)));

	$app->add(new AuthMiddleware(
		$container->get(AuthService::class),
		$container->get(Session::class),
		$container->get(Cookie::class)));

	// Timing Middleware
	$app->add(function ($request, $handler) use ($start_time, $start_memory) {
		$request = $request->withAttribute('start_time', $start_time);
		$request = $request->withAttribute('start_memory', $start_memory);
		return $handler->handle($request);
	});

	$app->addRoutingMiddleware();

	require __DIR__ . '/error_handling.php'; // call error middleware as last

	return $app;
};
