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
use App\Shared\Acl\AclHelper;
use App\Framework\Validation\BaseValidator;
use App\Framework\Config\Config;
use App\Framework\System\ShellExecutor;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Database\NestedSets\Service;
use App\Framework\Media\Ffmpeg;
use App\Framework\Media\MediaProperties;
use App\Framework\Media\MediaUrlSigner;
use App\Framework\Media\MimeTypeService;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Controller\MediaController;
use App\Modules\Mediapool\Controller\NodesController;
use App\Modules\Mediapool\Controller\ShowController;
use App\Modules\Mediapool\Controller\UploadController;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Repositories\NodesRepository;
use App\Modules\Mediapool\Services\AclValidator;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Mediapool\Services\UploadService;
use App\Modules\Mediapool\Utils\ImagickFactory;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;
use App\Modules\Mediapool\Utils\SsrfValidator;
use App\Modules\Mediapool\Utils\ZipFilesystemFactory;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;

$dependencies = [];

$dependencies[NodesRepository::class] = DI\factory(function (ContainerInterface $container)
{
	return new NodesRepository($container->get('SqlConnection'));
});
$dependencies[AclValidator::class] = DI\factory(function (ContainerInterface $container)
{
	return new AclValidator($container->get(AclHelper::class));
});
$dependencies[NodesService::class] = DI\factory(function (ContainerInterface $container)
{
	return new NodesService(
		$container->get(NodesRepository::class),
		$container->get(Service::class),
		$container->get(MediaService::class),
		$container->get(AclValidator::class),
		$container->get(UserSession::class)
	);
});
$dependencies[NodesController::class] = DI\factory(function (ContainerInterface $container)
{
	return new NodesController(
		new \App\Modules\Mediapool\Helper\Nodes\Orchestrator(
			new \App\Modules\Mediapool\Helper\Nodes\ResponseBuilder($container->get(JsonResponseHandler::class), $container->get(Translator::class)),
			$container->get(BaseValidator::class),
			$container->get(NodesService::class)
		)
	);
});
$dependencies[ShowController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ShowController(
		$container->get(NodesService::class),
		$container->get(UserSession::class),
		$container->get(Translator::class),
		$container->get(Config::class)
	);
});
$dependencies[MediaHandlerFactory::class] = DI\factory(function (ContainerInterface $container)
{
	return new MediaHandlerFactory(
		$container->get(Config::class),
		$container->get('LocalFileSystem'),
		new ZipFilesystemFactory(),
		new ImagickFactory(),
		new Ffmpeg(
			$container->get(Config::class),
			$container->get('LocalFileSystem'),
			new MediaProperties(),
			$container->get(ShellExecutor::class)
		)
	);
});
$dependencies[SsrfValidator::class] = DI\factory(function (ContainerInterface $container) {
	return new SsrfValidator();
});

$dependencies[UploadService::class] = DI\factory(function (ContainerInterface $container)
{
	return new UploadService(
		$container->get(MediaHandlerFactory::class),
		$container->get(NodesService::class),
		new Client(),
		new FilesRepository($container->get('SqlConnection')),
		$container->get(MimeTypeService::class),
		$container->get(SsrfValidator::class)
	);
});

$dependencies[UploadController::class] = DI\factory(function (ContainerInterface $container)
{
	return new UploadController(
		new \App\Modules\Mediapool\Helper\Uploads\Orchestrator(
			new \App\Modules\Mediapool\Helper\Uploads\ResponseBuilder($container->get(JsonResponseHandler::class), $container->get(Translator::class)),
			$container->get(UserSession::class),
			$container->get(BaseValidator::class),
			$container->get(UploadService::class)
		)
	);
});

$dependencies[MediaService::class] = DI\factory(function (ContainerInterface $container)
{
	return new MediaService(
		new FilesRepository($container->get('SqlConnection')),
		$container->get(NodesRepository::class),
		$container->get(AclValidator::class),
		$container->get(MediaUrlSigner::class),
		$container->get(UserSession::class)
	);
});
$dependencies[MediaController::class] = DI\factory(function (ContainerInterface $container)
{
	return new MediaController(
		new \App\Modules\Mediapool\Helper\Media\Orchestrator(
			new \App\Modules\Mediapool\Helper\Media\ResponseBuilder($container->get(JsonResponseHandler::class), $container->get(Translator::class)),
			$container->get(BaseValidator::class),
			$container->get(MediaService::class)
		)
	);
});

return $dependencies;
