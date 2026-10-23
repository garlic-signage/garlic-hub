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

namespace Tests\Integration\Support;

use App\Framework\Config\Config;
use App\Framework\Config\IniConfigLoader;
use App\Framework\Http\Cookie;
use App\Framework\Http\Session;
use DI\ContainerBuilder;
use Defuse\Crypto\Key;
use Doctrine\DBAL\Connection;
use FilesystemIterator;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Builds the real dependency container of the application (config/services/*)
 * for tests. Only the parts which touch the outside world are replaced:
 * database connection, session, cookies, user cache and logging.
 */
final class TestContainerFactory
{
	private const string ROOT = __DIR__ . '/../../..';

	/**
	 * @throws \Exception
	 */
	public static function create(Connection $connection, string $runtimeDir): ContainerInterface
	{
		$root  = (string) realpath(self::ROOT);
		$paths = [
			'systemDir'      => $root,
			'varDir'         => $runtimeDir,
			'cacheDir'       => $runtimeDir . '/cache',
			'logDir'         => $runtimeDir . '/logs',
			'keysDir'        => $runtimeDir . '/keys',
			'templateDir'    => $root . '/templates',
			'translationDir' => $root . '/translations',
			'configDir'      => $root . '/config',
			'migrationDir'   => $root . '/migrations',
			'commandDir'     => $root . '/src/Commands',
		];
		$env = [
			'APP_ENV'              => 'test',
			'APP_DEBUG'            => 'false',
			'APP_PLATFORM_EDITION' => 'edge',
			'DB_MASTER_DRIVER'     => 'pdo_sqlite',
		];

		// Crypt reads the key from the keys directory
		if (!is_dir($paths['keysDir']))
			mkdir($paths['keysDir'], 0775, true);
		file_put_contents($paths['keysDir'] . '/encryption.key', Key::createNewRandomKey()->saveToAsciiSafeString());

		$builder = new ContainerBuilder();
		$builder->addDefinitions([
			Config::class => new Config(new IniConfigLoader($paths['configDir'] . '/settings'), $paths, $env),
		]);
		$builder->addDefinitions($root . '/config/services/_default.php'); // must be the first file

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/config/services', FilesystemIterator::SKIP_DOTS)) as $file)
		{
			if (fnmatch('*.php', $file->getFilename()) && $file->getFilename() !== '_default.php')
				$builder->addDefinitions($file->getPathname());
		}

		// definitions added later win
		$builder->addDefinitions([
			'SqlConnection'     => $connection,
			Session::class      => new Session(), // not started: it works on $_SESSION
			Cookie::class       => new InMemoryCookie(),
			Psr16Adapter::class => new Psr16Adapter('Memstatic'),
			'ModuleLogger'      => new NullLogger(),
			'FrameworkLogger'   => new NullLogger(),
			'AppLogger'         => new NullLogger(),
		]);

		return $builder->build();
	}
}
