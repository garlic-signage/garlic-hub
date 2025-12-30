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
use App\Framework\Core\Crypt;
use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\SimpleApiExecutor;
use App\Framework\TemplateEngine\AdapterInterface;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\TimeUnitsCalculator;
use App\Modules\Auth\UserSession;
use App\Modules\Player\Controller\PlayerController;
use App\Modules\Player\Controller\PlayerPlaylistController;
use App\Modules\Player\Controller\PlayerIndexController;
use App\Modules\Player\Controller\ShowDatatableController;
use App\Modules\Player\Dashboard\PlayerDashboard;
use App\Modules\Player\Entities\PlayerEntityFactory;
use App\Modules\Player\Helper\Datatable\ControllerFacade;
use App\Modules\Player\Helper\Datatable\DatatableBuilder;
use App\Modules\Player\Helper\Datatable\Parameters;
use App\Modules\Player\Helper\Index\FileUtils;
use App\Modules\Player\Helper\Index\IndexResponseHandler;
use App\Modules\Player\Helper\PlayerPlaylist\Orchestrator;
use App\Modules\Player\Helper\PlayerPlaylist\ResponseBuilder;
use App\Modules\Player\IndexCreation\Builder\Preparers\PreparerFactory;
use App\Modules\Player\IndexCreation\Builder\TemplatePreparer;
use App\Modules\Player\IndexCreation\IndexCreator;
use App\Modules\Player\IndexCreation\IndexFile;
use App\Modules\Player\IndexCreation\IndexProvider;
use App\Modules\Player\IndexCreation\IndexTemplateSelector;
use App\Modules\Player\IndexCreation\PlayerDataAssembler;
use App\Modules\Player\IndexCreation\PlayerDetector;
use App\Modules\Player\IndexCreation\UserAgentHandler;
use App\Modules\Player\Repositories\PlayerIndexRepository;
use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Player\Repositories\PlayerTokenRepository;
use App\Modules\Player\Services\AclValidator;
use App\Modules\Player\Helper\Datatable\DatatablePreparer;
use App\Modules\Player\Services\PlayerDatatableService;
use App\Modules\Player\Services\PlayerIndexService;
use App\Modules\Player\Services\PlayerRestAPIService;
use App\Modules\Player\Services\PlayerService;
use App\Modules\Player\Services\PlayerTokenService;
use App\Modules\Playlists\Collector\Builder\PlaylistBuilderFactory;
use App\Modules\Playlists\Services\PlaylistsService;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;

$dependencies = [];

$dependencies[PlayerRepository::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlayerRepository($container->get('SqlConnection'));
});
$dependencies[PlayerIndexRepository::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlayerIndexRepository($container->get('SqlConnection'));
});
$dependencies[AclValidator::class] = DI\factory(function (ContainerInterface $container)
{
	return new AclValidator($container->get(AclHelper::class));
});
$dependencies[PlayerIndexController::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlayerIndexController(
		$container->get(PlayerIndexService::class),
		new IndexResponseHandler(new FileUtils()),
		$container->get(Sanitizer::class)
	);
});
$dependencies[IndexCreator::class] = DI\factory(function (ContainerInterface $container)
{
	return new IndexCreator(
		$container->get(PlaylistBuilderFactory::class),
		new IndexTemplateSelector(),
		new IndexFile($container->get('LocalFileSystem'), $container->get('ModuleLogger')),
		new TemplatePreparer(new PreparerFactory()),
		$container->get(AdapterInterface::class)
	);
});

$dependencies[IndexProvider::class] = DI\factory(function (ContainerInterface $container)
{
	return new IndexProvider($container->get(Config::class), $container->get(IndexCreator::class));
});
$dependencies[PlayerDataAssembler::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlayerDataAssembler(
		new UserAgentHandler(new PlayerDetector($container->get(Config::class))),
		$container->get(PlayerIndexRepository::class),
		$container->get(Config::class),
		new PlayerEntityFactory($container->get(Config::class))
	);
});
$dependencies[PlayerIndexService::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlayerIndexService(
		$container->get(PlayerDataAssembler::class),
		$container->get(IndexProvider::class),
		$container->get('ModuleLogger'));
});
$dependencies[PlayerService::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlayerService(
		$container->get(PlayerRepository::class),
		$container->get(PlaylistsService::class),
		$container->get(AclValidator::class),
		$container->get('ModuleLogger'));
});


// Datatable
$dependencies[PlayerDatatableService::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlayerDatatableService(
		$container->get(PlayerRepository::class),
		$container->get(Parameters::class),
		$container->get(AclValidator::class),
		$container->get('ModuleLogger')
	);
});$dependencies[Parameters::class] = DI\factory(function (ContainerInterface $container)
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
		$container->get(Parameters::class),
		$container->get(TimeUnitsCalculator::class)
	);
});
$dependencies[ControllerFacade::class] = DI\factory(function (ContainerInterface $container)
{
	return new ControllerFacade(
		$container->get(DatatableBuilder::class),
		$container->get(DatatablePreparer::class),
		$container->get(PlayerDatatableService::class)
	);
});
$dependencies[ShowDatatableController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ShowDatatableController(
		$container->get(ControllerFacade::class),
		$container->get(DatatableTemplatePreparer::class)
	);
});
$dependencies[PlayerDashboard::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlayerDashboard(
		$container->get(PlayerService::class),
		$container->get(Translator::class)
	);
});
$dependencies[PlayerRestAPIService::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlayerRestAPIService(
		new SimpleApiExecutor(new Client(), $container->get('ModuleLogger')),
		new PlayerTokenService(
			new PlayerTokenRepository($container->get('SqlConnection')),
			$container->get(Crypt::class),
			$container->get('ModuleLogger')
		),
		$container->get('ModuleLogger'),
	);
});
$dependencies[Orchestrator::class] = DI\factory(function (ContainerInterface $container)
{
	return new Orchestrator(
		new ResponseBuilder($container->get(JsonResponseHandler::class), $container->get(Translator::class)),
		$container->get(UserSession::class),
		$container->get(BaseValidator::class),
		$container->get(PlayerService::class),
		$container->get(PlayerRestAPIService::class)
	);
});
$dependencies[PlayerPlaylistController::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlayerPlaylistController($container->get(Orchestrator::class));
});
$dependencies[PlayerController::class] = DI\factory(function (ContainerInterface $container)
{
	return new PlayerController(
		$container->get(JsonResponseHandler::class),
		$container->get(UserSession::class),
		$container->get(PlayerService::class)
	);
});


return $dependencies;