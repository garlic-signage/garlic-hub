<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

use App\Framework\Security\Crypt;
use App\Framework\Validation\Sanitizer;
use App\Framework\Http\Session;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Modules\Account\Controller\EditLocalesController;
use App\Modules\Account\Helper\Password\Parameters;
use App\Modules\Account\Services\AccountService;
use App\Shared\User\UserTokenService;
use App\Shared\User\Repositories\UserRepositoryFactory;
use Psr\Container\ContainerInterface;

$dependencies = [];

$dependencies[UserTokenService::class] = DI\factory(function (ContainerInterface $container)
{
	$factory      = $container->get(UserRepositoryFactory::class);
	$repositories = $factory->create();

	return new UserTokenService(
		$repositories['tokens'],
		$container->get(Crypt::class),
		$container->get('ModuleLogger')
	);
});

$dependencies[AccountService::class] = DI\factory(function (ContainerInterface $container)
{
	$factory      = $container->get(UserRepositoryFactory::class);
	$repositories = $factory->create();

	return new AccountService(
		$repositories['main'],
		$container->get(UserTokenService::class),
		$container->get(Crypt::class),
		$container->get(Transactions::class)
	);
});
$dependencies[EditLocalesController::class] = DI\factory(function (ContainerInterface $container)
{
	return new EditLocalesController($container->get(AccountService::class), $container->get(Session::class));
});
$dependencies[Parameters::class] = DI\factory(function (ContainerInterface $container)
{
	return new Parameters(
		$container->get(Sanitizer::class),
		$container->get(Session::class)
	);
});


return $dependencies;