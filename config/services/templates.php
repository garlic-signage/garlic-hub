<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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


use App\Framework\Core\Acl\AclHelper;
use App\Framework\Core\CsrfToken;
use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Controller\ShowDatatableController;
use App\Modules\Templates\Controller\ShowSettingsController;
use App\Modules\Templates\Helper\Datatable\DatatableBuilder;
use App\Modules\Templates\Helper\Datatable\DatatableFacade;
use App\Modules\Templates\Helper\Datatable\DatatablePreparer;
use App\Modules\Templates\Helper\Datatable\Parameters;
use App\Modules\Templates\Helper\Settings\CreateFormBuilder;
use App\Modules\Templates\Helper\Settings\CreateFormInputHandler;
use App\Modules\Templates\Helper\Settings\CreateFormWriter;
use App\Modules\Templates\Helper\Settings\FormElementsCreator;
use App\Modules\Templates\Helper\Settings\Orchestrator;
use App\Modules\Templates\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Templates\Helper\Settings\TemplatePreparer;
use App\Modules\Templates\Helper\Settings\Validator;
use App\Modules\Templates\Repositories\TemplatesRepository;
use App\Modules\Templates\Services\AclValidator;
use App\Modules\Templates\Services\TemplatesDatatableService;
use App\Modules\Templates\Services\TemplateService;
use Psr\Container\ContainerInterface;

$dependencies = [];

$dependencies[TemplatesRepository::class] = DI\factory(function (ContainerInterface $container)
{
	return new TemplatesRepository($container->get('SqlConnection'));
});
$dependencies[AclValidator::class] = DI\factory(function (ContainerInterface $container)
{
	return new AclValidator(
		$container->get(AclHelper::class),
	);
});
$dependencies[TemplateService::class] = DI\factory(function (ContainerInterface $container)
{
	return new TemplateService(
		$container->get(TemplatesRepository::class),
		$container->get(AclValidator::class),
		$container->get('ModuleLogger')
	);
});
$dependencies[\App\Modules\Templates\Helper\Settings\Parameters::class] = DI\factory(function (ContainerInterface $container)
{
	return new \App\Modules\Templates\Helper\Settings\Parameters(
		$container->get(Sanitizer::class),
		$container->get(Session::class)
	);
});
$dependencies[SettingsParametersPolicy::class] = DI\factory(function (ContainerInterface $container)
{
	return new SettingsParametersPolicy(
		$container->get(\App\Modules\Templates\Helper\Settings\Parameters::class),
		$container->get(AclValidator::class),
		$container->get(UserSession::class),
	);
});
$dependencies[Validator::class] = DI\factory(function (ContainerInterface $container)
{
	return new Validator(
		$container->get(Translator::class),
		$container->get(\App\Modules\Templates\Helper\Settings\Parameters::class),
		$container->get(CsrfToken::class),
	);
});
$dependencies[CreateFormWriter::class] = DI\factory(function (ContainerInterface $container)
{
	return new CreateFormWriter(
		$container->get(UserSession::class),
		$container->get(TemplateService::class),
	);
});
$dependencies[CreateFormInputHandler::class] = DI\factory(function (ContainerInterface $container)
{
	return new CreateFormInputHandler(
		$container->get(\App\Modules\Templates\Helper\Settings\Parameters::class),
		$container->get(Validator::class),
	);
});
$dependencies[\App\Modules\Templates\Helper\Settings\SettingsFormBuilder::class] = DI\factory(function (ContainerInterface $container)
{
	return new \App\Modules\Templates\Helper\Settings\SettingsFormBuilder(
		$container->get(\App\Modules\Templates\Helper\Settings\Parameters::class),
		$container->get(FormElementsCreator::class)
	);
});
$dependencies[CreateFormBuilder::class] = DI\factory(function (ContainerInterface $container)
{
	return new CreateFormBuilder(
		$container->get(UserSession::class),
		$container->get(\App\Modules\Templates\Helper\Settings\SettingsFormBuilder::class),
	);
});
$dependencies[Orchestrator::class] = DI\factory(function (ContainerInterface $container)
{
	return new Orchestrator(
		$container->get(CreateFormBuilder::class),
		$container->get(SettingsParametersPolicy::class),
		$container->get(CreateFormWriter::class),
		$container->get(CreateFormInputHandler::class)
	);
});
$dependencies[ShowSettingsController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ShowSettingsController(
		$container->get(Orchestrator::class),
		new TemplatePreparer(
			$container->get(Translator::class),
			$container->get(FormTemplatePreparer::class)
		)
	);
});


// Datatable
$dependencies[TemplatesDatatableService::class] = DI\factory(function (ContainerInterface $container)
{
	return new TemplatesDatatableService(
		$container->get(TemplatesRepository::class),
		$container->get(Parameters::class),
		$container->get(AclValidator::class),
		$container->get('ModuleLogger')
	);
});
$dependencies[Parameters::class] = DI\factory(function (ContainerInterface $container)
{
	return new Parameters(
		$container->get(Sanitizer::class),
		$container->get(Session::class)
	);
});
$dependencies[DatatableBuilder::class] = DI\factory(function (ContainerInterface $container)
{
	return new DatatableBuilder(
		$container->get(BuildService::class),
		$container->get(Parameters::class),
		$container->get(AclValidator::class)
	);
});
$dependencies[DatatablePreparer::class] = DI\factory(function (ContainerInterface $container)
{
	return new DatatablePreparer(
		$container->get(PrepareService::class),
		$container->get(Parameters::class)
	);
});
$dependencies[DatatableFacade::class] = DI\factory(function (ContainerInterface $container)
{
	return new DatatableFacade(
		$container->get(DatatableBuilder::class),
		$container->get(DatatablePreparer::class),
		$container->get(TemplatesDatatableService::class)
	);
});

$dependencies[ShowDatatableController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ShowDatatableController(
		$container->get(DatatableFacade::class),
		$container->get(DatatableTemplatePreparer::class),
	);
});


return $dependencies;