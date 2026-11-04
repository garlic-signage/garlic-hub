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

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use App\Modules\Auth\UserSession;
use App\Framework\Config\Config;
use App\Modules\System\Controller\ShowSettingsController;
use App\Modules\System\Scheduler\AuditCleanupTask;
use App\Modules\System\Scheduler\SystemScheduledTasks;
use App\Shared\Audit\AuditLogRepository;
use App\Shared\Settings\SettingsService;
use Psr\Container\ContainerInterface;
use Slim\Flash\Messages;

$dependencies = [];

$dependencies[SystemScheduledTasks::class] = DI\factory(function (ContainerInterface $container)
{
	return new SystemScheduledTasks(new AuditCleanupTask($container->get(AuditLogRepository::class), $container->get(Config::class)));
});
$dependencies[ShowSettingsController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ShowSettingsController(
		$container->get(SettingsService::class),
		$container->get(UserSession::class),
		$container->get(Translator::class),
		$container->get(CsrfToken::class),
		$container->get(Messages::class)
	);
});

return $dependencies;
