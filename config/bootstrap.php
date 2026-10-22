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

use App\Framework\Config\Config;
use App\Framework\Config\IniConfigLoader;
use App\Framework\Security\CsrfToken;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\App;
use Symfony\Component\Console\Application;

$start_time   = microtime(true);
$start_memory = memory_get_usage();

/* @var App $app */
$systemDir = realpath(__DIR__. '/../');
define('INSTALL_LOCK_FILE', $systemDir . '/var/install.lock');

// allow only change of language
if (!file_exists(INSTALL_LOCK_FILE) && isset($_SERVER['REQUEST_URI']) && !str_starts_with($_SERVER['REQUEST_URI'], '/create-initial') &&
	!str_starts_with($_SERVER['REQUEST_URI'], '/set-locales'))
{
	header('Location: /create-initial');
	exit;
}

try
{
	if (!is_string($systemDir) || !file_exists($systemDir))
		throw new RuntimeException('Invalid or non-existent system directory provided: ' . var_export($systemDir, true));

	require $systemDir.'/vendor/autoload.php';
	$dotenv = Dotenv\Dotenv::createImmutable($systemDir);
	$dotenv->load();
}
catch (Throwable $e)
{
	die('Error during initialization: ' . $e->getMessage());
}

if ($_ENV['APP_ENV'] === 'dev')
	require __DIR__ . '/bootstrap_dev.php';

$paths = [
	'systemDir' => $systemDir,
	'varDir' => $systemDir . '/var',
	'cacheDir' => $systemDir . '/var/cache',
	'logDir' => $systemDir . '/var/logs',
	'keysDir' => $systemDir . '/var/keys',
	'templateDir' => $systemDir . '/templates',
	'translationDir' => $systemDir . '/translations',
	'configDir' => $systemDir . '/config',
	'migrationDir' => $systemDir . '/migrations',
	'commandDir' => $systemDir . '/src/Commands'
];

$containerBuilder = new ContainerBuilder();
// The Config class has to load first
$containerBuilder->addDefinitions([
	Config::class => new Config(
		new IniConfigLoader($paths['configDir'].'/settings'),
		$paths,
		$_ENV
	),
]);

$containerBuilder->addDefinitions($systemDir . '/config/services/_default.php'); // must be the first file
$directoryIterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($systemDir . '/config/services', FilesystemIterator::SKIP_DOTS)
);
foreach ($directoryIterator as $file)
{
	if (fnmatch('*.php', $file->getFilename()))
	{
		$containerBuilder->addDefinitions($file->getPathname());
	}
}
try
{
	$container = $containerBuilder->build();

	$csrfToken = $container->get(CsrfToken::class);
}
catch (Exception $e)
{

}
if (php_sapi_name() !== 'cli')
{
	$middlewareLoader = require $systemDir.'/config/middleware.php';
	/** @var ContainerInterface $container  */
	$app = $middlewareLoader($container, $start_time, $start_memory);
}
else
{
	/** @var ContainerInterface $container  */
	$app              = $container->get(Application::class);
	$config           = $container->get(Config::class);
	$commandDirectory = $config->getPaths('commandDir');

	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($commandDirectory)) as $file)
	{
		if (!$file->isFile() || $file->getExtension() !== 'php')
			continue;

		$class = 'App\\Commands\\' . $file->getBasename('.php');
		if (class_exists($class))
			$app->add($container->get($class));

	}
}

return $app;