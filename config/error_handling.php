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

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Middleware\JsonErrorHandler;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

set_error_handler(/** @throws ErrorException */ function ($errNumber, $errorString, $errorFile, $errorLine)
{
	if (!(error_reporting() & $errNumber)) 	// ignore errors when suppressed via @
		return false;

	throw new ErrorException($errorString, 0, $errNumber, $errorFile, $errorLine);
});


/**
 * @var ContainerInterface $container
 * @var App $app
 */
$app                 = $container->get(App::class);
$logger              = $container->get('AppLogger');
$errorMiddleware     = $app->addErrorMiddleware($_ENV['APP_DEBUG']==='true', true, true, $container->get('AppLogger'));
$defaultErrorHandler = $errorMiddleware->getDefaultErrorHandler();

$jsonErrorHandler = new JsonErrorHandler(
	$app->getResponseFactory(),
	$container->get(JsonResponseHandler::class),
	$container->get(Translator::class),
	$logger
);

$myErrorHandler = function (
	ServerRequestInterface $request,
	Throwable $exception,
	bool $displayErrorDetails,
	bool $logErrors,
	bool $logErrorDetails
) use ($jsonErrorHandler, $defaultErrorHandler): ResponseInterface
{
	$path = $request->getUri()->getPath();

	if (str_starts_with($path, '/api') || str_starts_with($path, '/async'))
		return $jsonErrorHandler($request, $exception, $displayErrorDetails);

	// for normal Web UI operations
	return $defaultErrorHandler->__invoke($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails);
};

$errorMiddleware->setDefaultErrorHandler($myErrorHandler);
