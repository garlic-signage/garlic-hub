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

use App\Framework\Core\Config\Config;
use App\Framework\Core\Cookie;
use App\Framework\Core\CsrfToken;
use App\Framework\OAuth2\ClientsRepository;
use App\Framework\OAuth2\ScopeRepository;
use App\Framework\OAuth2\TokensRepository;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Modules\Auth\LoginController;
use App\Modules\Auth\OAuth2Controller;
use App\Modules\Profile\Services\UserTokenService;
use App\Modules\Users\Services\UsersService;
use Defuse\Crypto\Key;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use Psr\Container\ContainerInterface;

$dependencies = [];

$dependencies[AutoLoginService::class] = DI\factory(function (ContainerInterface $container)
{
	return new AutoLoginService(
		$container->get(UserTokenService::class),
		$container->get(Cookie::class),
		$container->get('ModuleLogger')
	);
});
$dependencies[AuthService::class] = DI\factory(function (ContainerInterface $container)
{
	return new AuthService(
		$container->get(UsersService::class),
		$container->get(AutoLoginService::class),
		$container->get('ModuleLogger')
	);
});
$dependencies[LoginController::class] = DI\factory(function (ContainerInterface $container)
{
	return new LoginController($container->get(AuthService::class), $container->get(CsrfToken::class));
});

$dependencies['AuthorizationServer'] = DI\factory(function (ContainerInterface $container) {
	$clientRepository       = new ClientsRepository($container->get('SqlConnection'));
	$tokensRepository       = new TokensRepository($container->get('SqlConnection'));
	$scopeRepository        = new ScopeRepository($container->get('SqlConnection'));

	$config        = $container->get(Config::class);
	$keysDir       = $config->getPaths('keysDir');
	$keyPath  = $keysDir.'/private.key';
	$privateKey    = new CryptKey($keyPath);
	$encryptionKey = Key::loadFromAsciiSafeString(file_get_contents($keysDir.'/encryption.key'));

	$server = new AuthorizationServer(
		$clientRepository,
		$tokensRepository,
		$scopeRepository,
		$privateKey,
		$encryptionKey
	);

	// authorization codes will expire after 10 minutes
	$grant = new AuthCodeGrant($tokensRepository, $tokensRepository, new DateInterval('PT10M'));
	$grant->setRefreshTokenTTL(new DateInterval('P1M')); // refresh tokens will expire after 1 month
	$server->enableGrantType($grant, new DateInterval('PT1H')); // Tokens expire after 1 hour

	return $server;
});

$dependencies[OAuth2Controller::class] = DI\factory(function (ContainerInterface $container)
{
	return new OAuth2Controller(
		$container->get(AuthService::class),
		$container->get('AuthorizationServer')
	);
});

return $dependencies;