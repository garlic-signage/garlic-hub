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

use App\Framework\Core\Acl\AclHelper;
use App\Framework\Core\Config\Config;
use App\Framework\Core\CsrfToken;
use App\Framework\Core\ShellExecutor;
use App\Framework\Database\NestedSets\Service;
use App\Framework\Media\Ffmpeg;
use App\Framework\Media\MediaProperties;
use App\Framework\Media\MimeTypeService;
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
		$container->get(AclValidator::class)
	);
});
$dependencies[NodesController::class] = DI\factory(function (ContainerInterface $container)
{
	return new NodesController($container->get(NodesService::class), $container->get(CsrfToken::class));
});
$dependencies[ShowController::class] = DI\factory(function (ContainerInterface $container)
{
	return new ShowController($container->get(NodesService::class));
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
$dependencies[UploadService::class] = DI\factory(function (ContainerInterface $container)
{
	return new UploadService(
		$container->get(MediaHandlerFactory::class),
		$container->get(NodesService::class),
		new Client(),
		new FilesRepository($container->get('SqlConnection')),
		$container->get(MimeTypeService::class),
		$container->get('ModuleLogger')
	);
});

$dependencies[UploadController::class] = DI\factory(function (ContainerInterface $container)
{
	return new UploadController($container->get(UploadService::class), $container->get(CsrfToken::class));
});

$dependencies[MediaService::class] = DI\factory(function (ContainerInterface $container)
{
	return new MediaService(
		new FilesRepository($container->get('SqlConnection')),
		$container->get(NodesRepository::class),
		$container->get(AclValidator::class),
		$container->get('ModuleLogger')
	);
});
$dependencies[MediaController::class] = DI\factory(function (ContainerInterface $container)
{
	return new MediaController($container->get(MediaService::class), $container->get(CsrfToken::class));
});

return $dependencies;
