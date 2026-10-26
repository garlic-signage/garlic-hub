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
use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Http\Cookie;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Middleware\CsrfMiddleware;
use App\Framework\Security\CsrfToken;
use App\Framework\Http\Session;
use App\Framework\Middleware\LocaleMiddleware;
use App\Framework\Middleware\SecurityHeadersMiddleware;
use App\Modules\Auth\AuthMiddleware;
use App\Modules\Auth\AuthService;
use Slim\Flash\Messages;

return function (ContainerInterface $container, $start_time, $start_memory): App
{
	/** @var App<ContainerInterface> $app */
	$app = $container->get(App::class);

	require_once __DIR__ . '/route.php';

	// every POST, PUT, PATCH and DELETE needs the CSRF token, checked after the locale (the message is translated).
	// Token and flash messages are created when needed: a request which changes nothing starts no session.
	$app->add(new CsrfMiddleware(
		new ReflectionClass(CsrfToken::class)->newLazyProxy(static fn(): CsrfToken => $container->get(CsrfToken::class)),
		new ReflectionClass(Messages::class)->newLazyProxy(static fn(): Messages => $container->get(Messages::class)),
		$container->get(Translator::class),
		$container->get(JsonResponseHandler::class)
	));

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

	// outermost, so error responses get the headers too
	$app->add(new SecurityHeadersMiddleware());

	return $app;
};
