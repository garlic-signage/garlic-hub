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

use App\Commands\MigrateCommand;
use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Core\Acl\AclHelper;
use App\Framework\Core\BaseValidator;
use App\Framework\Core\Config\Config;
use App\Framework\Core\Cookie;
use App\Framework\Core\Crypt;
use App\Framework\Core\CsrfToken;
use App\Framework\Core\Locales\Locales;
use App\Framework\Core\Locales\SessionLocaleExtractor;
use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Core\ShellExecutor;
use App\Framework\Core\SystemStats;
use App\Framework\Core\Translate\IniTranslationLoader;
use App\Framework\Core\Translate\MessageFormatterFactory;
use App\Framework\Core\Translate\Translator;
use App\Framework\Dashboards\SystemDashboard;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Database\Migration\Repository;
use App\Framework\Database\Migration\Runner;
use App\Framework\Database\NestedSets\Calculator;
use App\Framework\Database\NestedSets\Service;
use App\Framework\Exceptions\CoreException;
use App\Framework\Media\DataUrlDecoder;
use App\Framework\Media\FileInfoWrapper;
use App\Framework\Media\MimeTypeDetector;
use App\Framework\Media\MimeTypeExtensionMapper;
use App\Framework\Media\MimeTypeService;
use App\Framework\Middleware\FinalRenderMiddleware;
use App\Framework\TemplateEngine\AdapterInterface;
use App\Framework\TemplateEngine\MustacheAdapter;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Framework\Utils\Datatable\Paginator\Builder;
use App\Framework\Utils\Datatable\Paginator\Preparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\BodyPreparer;
use App\Framework\Utils\Datatable\Results\HeaderPreparer;
use App\Framework\Utils\Datatable\Results\TranslatorManager;
use App\Framework\Utils\Datatable\TimeUnitsCalculator;
use App\Framework\Utils\Datatable\UrlBuilder;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FormBuilder;
use App\Modules\Auth\UserSession;
use App\Modules\Users\Services\AclValidator;
use App\Modules\Users\Services\UsersService;
use Defuse\Crypto\Key;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Mustache\Engine;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Flash\Messages;
use Symfony\Component\Console\Application;

$dependencies = [];
$dependencies['ModuleLogger'] = DI\factory(function (ContainerInterface $container)
{
	$logger = new Logger('modules');
	$config = $container->get(Config::class);
	$logger->pushHandler(new StreamHandler($config->getPaths('logDir') . '/module.log', $config->getLogLevel()));

	return $logger;
});
$dependencies['FrameworkLogger'] = DI\factory(function (ContainerInterface $container)
{
	$logger = new Logger('modules');
	$config = $container->get(Config::class);
	$logger->pushHandler(new StreamHandler($config->getPaths('logDir') . '/framework.log', $config->getLogLevel()));

	return $logger;
});
$dependencies['AppLogger'] = DI\factory(function (ContainerInterface $container)
{
	$logger = new Logger('app');
	$config = $container->get(Config::class);
	$logger->pushHandler(new StreamHandler($config->getPaths('logDir') . '/app.log', $config->getLogLevel()));

	return $logger;
});
$dependencies[FinalRenderMiddleware::class] = DI\factory(function (ContainerInterface $container)
{
	return new FinalRenderMiddleware(
		$container->get(AdapterInterface::class),
		$container->get(AclValidator::class),
		$container->get(CsrfToken::class)
	);
});
$dependencies[App::class] = Di\factory([AppFactory::class, 'createFromContainer']); // Slim App
$dependencies[Application::class] = DI\factory(function (){ return new Application();}); // symfony console app
$dependencies[Session::class] = DI\factory(function ()
{
	$session = new Session();
	$session->start();
	return $session;
});
$dependencies[Messages::class] = DI\factory(function (){return new Messages();});
$dependencies[Crypt::class] = DI\factory(function (ContainerInterface $container)
{
	/** @var Config $config */
	$config = $container->get(Config::class);
	$keyString = file_get_contents($config->getPaths('varDir').'/keys/encryption.key');
	if (!$keyString)
		throw new CoreException('Encryption key file not found');

	$keyString = Key::loadFromAsciiSafeString(trim($keyString));
	return new Crypt($keyString);
});
$dependencies[Cookie::class] = DI\factory(function (ContainerInterface $container)
{
	return new Cookie($container->get(Crypt::class));
});

$dependencies[Locales::class] = DI\factory(function (ContainerInterface $container)
{
	return new Locales(
		$container->get(Config::class),
		new SessionLocaleExtractor($container->get(Session::class))
	);
});
$dependencies[Psr16Adapter::class] = DI\factory(function (){return new Psr16Adapter('Files');});
$dependencies[Translator::class] = DI\factory(function (ContainerInterface $container)
{
	$translationDir = $container->get(Config::class)->getPaths('translationDir');
	return new Translator(
		$container->get(Locales::class),
		new IniTranslationLoader($translationDir),
		new MessageFormatterFactory(),
		$container->get(Psr16Adapter::class)
	);
});
$dependencies[AdapterInterface::class] = DI\factory(function ()
{
	$mustacheEngine = new Engine([
		'loader' => new Mustache\Loader\FilesystemLoader(__DIR__ . '/../../templates'),
    	'partials_loader' => new Mustache\Loader\FilesystemLoader(__DIR__ . '/../../templates/generic')
	]);
	return new MustacheAdapter($mustacheEngine);
});
$dependencies['SqlConnection'] = DI\factory(function (ContainerInterface $container)
{
	$config = $container->get(Config::class);
	$driver = strtolower($config->getEnv('DB_MASTER_DRIVER'));
	switch ($driver) {
		case 'pdo_sqlite':
		case 'sqlite3':
			$connectionParams = [
				'path' => $config->getEnv('DB_MASTER_PATH'), // Für SQLite: Dateipfad
				'driver' => $driver,
			];
			break;
		case 'pdo_mysql':
		case 'mysqli':
		case 'pdo_pgsql':
		case 'pgsql':
			$connectionParams = [
				'dbname' => $config->getEnv('DB_MASTER_NAME'),
				'user' => $config->getEnv('DB_MASTER_USER'),
				'password' => $config->getEnv('DB_MASTER_PASSWORD'),
				'host' => $config->getEnv('DB_MASTER_HOST'),
				'port' => $config->getEnv('DB_MASTER_PORT'),
				'driver' => $driver,
			];
			if ($driver === 'pdo_mysql' || $driver === 'mysqli')
				$connectionParams['charset'] = 'utf8mb4';
			break;
		default:
			throw new InvalidArgumentException('Unsupported DBAL driver: ' . $driver);
	}

	$logger = new Logger('dbal');
	$logger->pushHandler(new StreamHandler($config->getPaths('logDir') . '/dbal.log', $config->getLogLevel()));
	$dbalConfig = new Configuration();
	$dbalConfig->setMiddlewares([new Middleware($logger)]);

	return DriverManager::getConnection($connectionParams, $dbalConfig);
});
$dependencies[Transactions::class] = DI\factory(function (ContainerInterface $container)
{
	return new Transactions($container->get('SqlConnection'));
});
$dependencies[Service::class] = DI\factory(function (ContainerInterface $container)
{
	return new Service(
		new \App\Framework\Database\NestedSets\Repository($container->get('SqlConnection'), '', ''),
		new Calculator(),
		$container->get(Transactions::class),
		$container->get('FrameworkLogger'),
	);
});
$dependencies['LocalFileSystem'] = DI\factory(function (ContainerInterface $container)
{
	$systemDir = $container->get(Config::class)->getPaths('systemDir');
	return new Filesystem(new LocalFilesystemAdapter($systemDir));
});
if (php_sapi_name() === 'cli')
{
	$dependencies[Repository::class] = DI\factory(function (ContainerInterface $container)
	{
		return new Repository($container->get('SqlConnection'));
	});
	$dependencies[Runner::class] = DI\factory(function (ContainerInterface $container)
	{

		$config = $container->get(Config::class);
		$path = $config->getPaths('migrationDir') . '/' . $config->getEnv('APP_PLATFORM_EDITION') . '/';
		return new Runner(
			$container->get(Repository::class),
			new Filesystem(new LocalFilesystemAdapter($path))
		);
	});
	$dependencies[MigrateCommand::class] = DI\factory(function (ContainerInterface $container)
	{
		return new MigrateCommand($container->get(Runner::class));
	});
}
$dependencies[FormBuilder::class] = DI\factory(function (ContainerInterface $container)
{
	return new FormBuilder(
		new FieldsFactory(),
		new FieldsRenderFactory(),
		$container->get(CsrfToken::class)
	);
});
$dependencies[Sanitizer::class] = DI\factory(function (ContainerInterface $container)
{
	$allowedTags = $container->get(Config::class)->getConfigValue('allowed_tags', 'main');
	return new Sanitizer($allowedTags);
});
$dependencies[BuildService::class] = DI\factory(function (ContainerInterface $container)
{
	return new BuildService(
		$container->get(FormBuilder::class),
		new Builder(),
		new \App\Framework\Utils\Datatable\Results\Builder()
	);
});
$dependencies[AclHelper::class] = DI\factory(function (ContainerInterface $container)
{
	return new AclHelper(
		$container->get(UsersService::class),
		$container->get(Config::class)
	);
});
$dependencies[PrepareService::class] = DI\factory(function (ContainerInterface $container)
{
	$urlBuilder = new UrlBuilder();
	return new PrepareService(
		$container->get(FormBuilder::class),
		new Preparer($urlBuilder),
		new HeaderPreparer(
			new TranslatorManager($container->get(Translator::class)),
			$urlBuilder
		),
		new BodyPreparer()
	);
});
$dependencies[DatatableTemplatePreparer::class] = DI\factory(function (ContainerInterface $container)
{
	return new DatatableTemplatePreparer($container->get(Translator::class));
});
$dependencies[FormTemplatePreparer::class] = DI\factory(function ()
{
	return new FormTemplatePreparer();
});
$dependencies[TimeUnitsCalculator::class] = DI\factory(function (ContainerInterface $container)
{
	return new TimeUnitsCalculator($container->get(Translator::class));
});
$dependencies[ShellExecutor::class] = DI\factory(function ()
{
	return new ShellExecutor();
});
$dependencies[SystemStats::class] = DI\factory(function (ContainerInterface $container)
{
	return new SystemStats($container->get(ShellExecutor::class));
});
$dependencies[SystemDashboard::class] = DI\factory(function (ContainerInterface $container)
{
	return new SystemDashboard(
		$container->get(SystemStats::class),
		$container->get(Translator::class),
	);
});
$dependencies[CsrfToken::class] = DI\factory(function (ContainerInterface $container)
{
	return new CsrfToken(
		$container->get(Crypt::class),
		$container->get(Session::class),
	);
});
$dependencies[JsonResponseHandler::class] = DI\factory(function ()
{
	return new JsonResponseHandler();
});
$dependencies[UserSession::class] = DI\factory(function (ContainerInterface $container)
{
	return new UserSession($container->get(Session::class));
});
$dependencies[BaseValidator::class] = DI\factory(function (ContainerInterface $container)
{
	return new BaseValidator($container->get(Translator::class), $container->get(CsrfToken::class));
});
$dependencies[MimeTypeDetector::class] = DI\factory(function (ContainerInterface $container)
{
	return new MimeTypeDetector(new FileInfoWrapper());
});
$dependencies[MimeTypeExtensionMapper::class] = DI\factory(function (ContainerInterface $container)
{
	return new MimeTypeExtensionMapper();
});
$dependencies[MimeTypeService::class] = DI\factory(function (ContainerInterface $container)
{
	return new MimeTypeService(
		$container->get(MimeTypeDetector::class),
		$container->get(MimeTypeExtensionMapper::class),
	);
});

$dependencies[DataUrlDecoder::class] = DI\factory(function (ContainerInterface $container)
{
	return new DataUrlDecoder($container->get(MimeTypeService::class));
});


return $dependencies;