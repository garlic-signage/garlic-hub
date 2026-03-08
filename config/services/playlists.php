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

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Core\Acl\AclHelper;
use App\Framework\Core\BaseValidator;
use App\Framework\Core\Config\Config;
use App\Framework\Core\CsrfToken;
use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\TemplateEngine\AdapterInterface;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Utils\Widget\ConfigXML;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Playlists\Collector\Builder\BuildHelper;
use App\Modules\Playlists\Collector\Builder\PlaylistBuilderFactory;
use App\Modules\Playlists\Collector\ContentReader;
use App\Modules\Playlists\Collector\ExternalContentReader;
use App\Modules\Playlists\Collector\SimplePlaylistStructureFactory;
use App\Modules\Playlists\Controller\ConditionalPlayController;
use App\Modules\Playlists\Controller\ExportController;
use App\Modules\Playlists\Controller\ItemsController;
use App\Modules\Playlists\Controller\PlaylistsController;
use App\Modules\Playlists\Controller\ShowComposeController;
use App\Modules\Playlists\Controller\ShowDatatableController;
use App\Modules\Playlists\Controller\ShowSettingsController;
use App\Modules\Playlists\Controller\TriggerController;
use App\Modules\Playlists\Controller\WidgetsController;
use App\Modules\Playlists\Helper\Compose\RightsChecker;
use App\Modules\Playlists\Helper\Compose\UiTemplatesPreparer;
use App\Modules\Playlists\Helper\ConditionalPlay\Orchestrator;
use App\Modules\Playlists\Helper\ConditionalPlay\ResponseBuilder;
use App\Modules\Playlists\Helper\ConditionalPlay\TemplatePreparer;
use App\Modules\Playlists\Helper\Datatable\ControllerFacade;
use App\Modules\Playlists\Helper\Datatable\DatatableBuilder;
use App\Modules\Playlists\Helper\Datatable\DatatablePreparer;
use App\Modules\Playlists\Helper\ExportSmil\items\ItemsFactory;
use App\Modules\Playlists\Helper\ExportSmil\LocalWriter;
use App\Modules\Playlists\Helper\ExportSmil\PlaylistContent;
use App\Modules\Playlists\Helper\Settings\Builder;
use App\Modules\Playlists\Helper\Settings\Facade;
use App\Modules\Playlists\Helper\Settings\FormElementsCreator;
use App\Modules\Playlists\Helper\Settings\Parameters;
use App\Modules\Playlists\Helper\Settings\Validator;
use App\Modules\Playlists\Helper\Trigger\TriggerPreparer;
use App\Modules\Playlists\Helper\Widgets\ContentDataPreparer;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Repositories\PlaylistsRepository;
use App\Modules\Playlists\Services\AclValidator;
use App\Modules\Playlists\Services\ConditionalPlayService;
use App\Modules\Playlists\Services\ExportService;
use App\Modules\Playlists\Services\InsertItems\InsertItemFactory;
use App\Modules\Playlists\Services\ItemsService;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsDatatableService;
use App\Modules\Playlists\Services\PlaylistsService;
use App\Modules\Playlists\Services\PlaylistUsageService;
use App\Modules\Playlists\Services\TriggerService;
use App\Modules\Playlists\Services\WidgetsService;
use App\Modules\Templates\Helper\Composer\ExportImage;
use App\Modules\Templates\Services\TemplatesService;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;

$dependencies = [];

$dependencies[PlaylistsRepository::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlaylistsRepository($container->get('SqlConnection'));
});
$dependencies[ItemsRepository::class] = DI\factory(function (ContainerInterface $container)
{
	return new ItemsRepository($container->get('SqlConnection'));
});
$dependencies[AclValidator::class] = DI\factory(function (ContainerInterface $container)
{
	return new AclValidator($container->get(AclHelper::class));
});
$dependencies[PlaylistsService::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlaylistsService(
		$container->get(PlaylistsRepository::class),
		$container->get(PlaylistMetricsCalculator::class),
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
$dependencies[Validator::class] = DI\factory(function (ContainerInterface $container)
{
	return new Validator(
		$container->get(Translator::class),
		$container->get(Parameters::class),
		$container->get(CsrfToken::class)
	);
});
$dependencies[Builder::class] = DI\factory(function (ContainerInterface $container)
{
	return new Builder(
		$container->get(AclValidator::class),
		$container->get(Parameters::class),
		$container->get(Validator::class),
		new FormElementsCreator($container->get(FormBuilder::class), $container->get(Translator::class)),
	);
});
$dependencies[Facade::class] = DI\factory(function (ContainerInterface $container)
{
	return new Facade(
		$container->get(Builder::class),
		$container->get(PlaylistsService::class),
		$container->get(Parameters::class)
	);
});
$dependencies[ShowSettingsController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ShowSettingsController(
		$container->get(Facade::class),
		$container->get(FormTemplatePreparer::class)
	);
});

// Datatable
$dependencies[PlaylistsDatatableService::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlaylistsDatatableService(
		$container->get(PlaylistsRepository::class),
		$container->get(\App\Modules\Playlists\Helper\Datatable\Parameters::class),
		$container->get(AclValidator::class),
		new PlaylistUsageService(
			$container->get(PlayerRepository::class),
			$container->get(ItemsRepository::class)),
		$container->get('ModuleLogger')
	);
});
$dependencies[\App\Modules\Playlists\Helper\Datatable\Parameters::class] = DI\factory(function (ContainerInterface $container)
{
	return new \App\Modules\Playlists\Helper\Datatable\Parameters(
		$container->get(Sanitizer::class),
		$container->get(Session::class)
	);
});
$dependencies[DatatableBuilder::class] = DI\factory(function (ContainerInterface $container)
{
	return new DatatableBuilder(
		$container->get(BuildService::class),
		$container->get(\App\Modules\Playlists\Helper\Datatable\Parameters::class),
		$container->get(AclValidator::class)
	);
});
$dependencies[DatatablePreparer::class] = DI\factory(function (ContainerInterface $container)
{
	return new DatatablePreparer(
		$container->get(PrepareService::class),
		$container->get(AclValidator::class),
		$container->get(\App\Modules\Playlists\Helper\Datatable\Parameters::class)
	);
});
$dependencies[ControllerFacade::class] = DI\factory(function (ContainerInterface $container)
{
	return new ControllerFacade(
		$container->get(DatatableBuilder::class),
		$container->get(DatatablePreparer::class),
		$container->get(PlaylistsDatatableService::class)
	);
});

$dependencies[ShowDatatableController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ShowDatatableController(
		$container->get(ControllerFacade::class),
		$container->get(DatatableTemplatePreparer::class)
	);
});
$dependencies[ShowComposeController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ShowComposeController(
		$container->get(PlaylistsService::class),
		new UiTemplatesPreparer(
			$container->get(Translator::class),
			new RightsChecker(
				$container->get(Translator::class), $container->get(AclValidator::class))
		)
	);
});
$dependencies[PlaylistsController::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlaylistsController(
		$container->get(PlaylistsService::class),
		$container->get(PlaylistsDatatableService::class),
		$container->get(\App\Modules\Playlists\Helper\Datatable\Parameters::class),
		$container->get(CsrfToken::class)
	);
});

// Items
$dependencies[InsertItemFactory::class] = DI\factory(function (ContainerInterface $container)
{
	return new InsertItemFactory(
		$container->get(MediaService::class),
		$container->get(ItemsRepository::class),
		$container->get(PlaylistsService::class),
		$container->get(TemplatesService::class),
		$container->get(Config::class),
		$container->get('LocalFileSystem'),
		$container->get(PlaylistMetricsCalculator::class),
		$container->get(WidgetsService::class),
		$container->get('ModuleLogger')
	);
});
$dependencies[ItemsController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ItemsController(
		$container->get(ItemsService::class),
		$container->get(InsertItemFactory::class),
		$container->get(CsrfToken::class)
	);
});
$dependencies[PlaylistMetricsCalculator::class] = DI\factory(function (ContainerInterface $container)
{
	return 		new PlaylistMetricsCalculator(
		$container->get(ItemsRepository::class),
		$container->get(AclValidator::class),
		$container->get(Config::class),
	);
});
$dependencies[ItemsService::class] = DI\factory(function (ContainerInterface $container)
{
	return new ItemsService(
		$container->get(ItemsRepository::class),
		$container->get(MediaService::class),
		$container->get(PlaylistsService::class),
		$container->get(PlaylistMetricsCalculator::class),
		$container->get('ModuleLogger')
	);
});
$dependencies[ExportService::class] = DI\factory(function (ContainerInterface $container)
{
	return new ExportService(
		$container->get(Config::class),
		$container->get(PlaylistsService::class),
		$container->get(ItemsService::class),
		new LocalWriter($container->get(Config::class), $container->get('LocalFileSystem')),
		new PlaylistContent(new ItemsFactory($container->get(Config::class)), $container->get(Config::class)),
		$container->get('ModuleLogger')
	);
});
$dependencies[ExportController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ExportController(
		$container->get(ExportService::class),
		$container->get(UserSession::class),
		$container->get(CsrfToken::class)
	);
});

$dependencies[BuildHelper::class] = DI\factory(function (ContainerInterface $container)
{
	$config = $container->get(Config::class);
	return new BuildHelper(
		new ContentReader(
			$config,
			$container->get('LocalFileSystem')
		),
		new ExternalContentReader(
			$container->get('LocalFileSystem'),
			new Client(),
			$config->getConfigValue('path_playlists', 'playlists')
		),
		$container->get('ModuleLogger')
	);
});
$dependencies[PlaylistBuilderFactory::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlaylistBuilderFactory(
		$container->get(BuildHelper::class),
		new SimplePlaylistStructureFactory()
	);
});
$dependencies[WidgetsService::class] = DI\factory(function (ContainerInterface $container)
{
	return new WidgetsService(
		$container->get(ItemsService::class),
		new ContentDataPreparer(new ConfigXML()),
		$container->get('ModuleLogger')
	);
});
$dependencies[WidgetsController::class] = DI\factory(function (ContainerInterface $container)
{
	return new WidgetsController(
		$container->get(WidgetsService::class),
		$container->get(CsrfToken::class),
	);
});
$dependencies[ConditionalPlayService::class] = DI\factory(function (ContainerInterface $container)
{
	return new ConditionalPlayService(
		$container->get(ItemsService::class),
		$container->get('ModuleLogger')
	);
});
$dependencies[ConditionalPlayController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ConditionalPlayController(
		new Orchestrator(
			new ResponseBuilder($container->get(JsonResponseHandler::class), $container->get(Translator::class)),
			$container->get(UserSession::class),
			$container->get(BaseValidator::class),
			new TemplatePreparer($container->get(Translator::class), $container->get(AdapterInterface::class)),
			$container->get(ConditionalPlayService::class),
		)
	);
});
$dependencies[TriggerController::class] = DI\factory(function (ContainerInterface $container)
{
	return new TriggerController(
		new \App\Modules\Playlists\Helper\Trigger\Orchestrator(
			new ResponseBuilder($container->get(JsonResponseHandler::class), $container->get(Translator::class)),
			$container->get(UserSession::class),
			$container->get(BaseValidator::class),
			new \App\Modules\Playlists\Helper\Trigger\TemplatePreparer($container->get(AdapterInterface::class), new TriggerPreparer($container->get(Translator::class))),
			new TriggerService($container->get(ItemsService::class), $container->get('ModuleLogger'))
		)
	);
});
$dependencies[\App\Modules\Playlists\Helper\Templates\Orchestrator::class] = DI\factory(function (ContainerInterface $container)
{
	return new \App\Modules\Playlists\Helper\Templates\Orchestrator(
		$container->get(ItemsService::class),
		$container->get(UserSession::class),
		$container->get(ExportImage::class),
		$container->get(\App\Modules\Templates\Helper\Composer\TemplatePreparer::class),
		$container->get(Config::class),

	);
});

return $dependencies;
