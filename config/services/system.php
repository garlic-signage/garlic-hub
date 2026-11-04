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
use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Http\Session;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Validation\Sanitizer;
use App\Modules\System\Controller\ShowAuditController;
use App\Modules\System\Helper\Audit\AuditFilterDefinition;
use App\Modules\System\Helper\Audit\DatatableBuilder;
use App\Modules\System\Helper\Audit\DatatablePreparer;
use App\Modules\System\Helper\Audit\Orchestrator;
use App\Modules\System\Helper\Audit\Parameters;
use App\Modules\System\Services\AclValidator;
use App\Modules\System\Services\AuditDatatableService;
use App\Shared\Acl\AclHelper;
use App\Shared\Acl\AclUnitProvider;
use App\Shared\Acl\EditionAclModules;
use App\Modules\System\Controller\ShowSettingsController;
use App\Modules\System\Scheduler\AuditCleanupTask;
use App\Modules\System\Scheduler\SystemScheduledTasks;
use App\Shared\Audit\AuditLog;
use App\Shared\Audit\AuditLogRepository;
use App\Shared\Settings\SettingsService;
use Psr\Container\ContainerInterface;
use Slim\Flash\Messages;

$dependencies = [];

$dependencies[SystemScheduledTasks::class] = DI\factory(function (ContainerInterface $container)
{
	return new SystemScheduledTasks(new AuditCleanupTask($container->get(AuditLogRepository::class), $container->get(Config::class)));
});
$dependencies[AclValidator::class] = DI\factory(function (ContainerInterface $container)
{
	return new AclValidator($container->get(AclHelper::class));
});
$dependencies[Parameters::class] = DI\factory(function (ContainerInterface $container)
{
	return new Parameters($container->get(Sanitizer::class), $container->get(Session::class));
});
$dependencies[AuditDatatableService::class] = DI\factory(function (ContainerInterface $container)
{
	// the names of the shared units come from the providers of the modules
	$unitProviders = [];
	foreach (EditionAclModules::getModules($container->get(Config::class)->getEdition()) as $module)
	{
		if ($container->has(AclUnitProvider::class . '.' . $module))
			$unitProviders[$module] = $container->get(AclUnitProvider::class . '.' . $module);
	}

	return new AuditDatatableService(
		new AuditFilterDefinition(),
		$container->get(DatatableQuery::class),
		$container->get(Parameters::class),
		$container->get(AuditLogRepository::class),
		$unitProviders,
		$container->get('ModuleLogger')
	);
});
$dependencies[Orchestrator::class] = DI\factory(function (ContainerInterface $container)
{
	return new Orchestrator(
		new DatatableBuilder($container->get(BuildService::class), $container->get(Parameters::class), $container->get(AclValidator::class), $container->get(Translator::class)),
		new DatatablePreparer($container->get(PrepareService::class), $container->get(Parameters::class), $container->get(Translator::class)),
		$container->get(AuditDatatableService::class),
		$container->get(UserSession::class)
	);
});
$dependencies[ShowAuditController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ShowAuditController(
		$container->get(Orchestrator::class),
		$container->get(DatatableTemplatePreparer::class),
		$container->get(AclValidator::class),
		$container->get(UserSession::class),
		$container->get(AuditLog::class),
		$container->get(Translator::class),
		$container->get(Messages::class)
	);
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
