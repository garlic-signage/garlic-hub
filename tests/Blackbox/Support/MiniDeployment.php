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

namespace Tests\Blackbox\Support;

use Defuse\Crypto\Key;
use RuntimeException;

/**
 * A small deployment of the application in a temporary directory with its own PHP web server.
 * The blackbox tests talk to it over HTTP like a browser or a player does: real routing,
 * real middleware, real sessions and cookies, real headers. Nothing is replaced.
 *
 * The directory has its own var/ (database file, keys, logs, smil files), so the tests never
 * touch the data of the developer. The code comes from the project: config, templates, translations
 * and migrations are copied, vendor is linked.
 */
final class MiniDeployment
{
	private const string ROOT = __DIR__ . '/../../..';

	private string $directory = '';
	private int $port = 0;
	/** @var resource|null */
	private $process = null;

	public function start(): void
	{
		$root = (string) realpath(self::ROOT);
		$this->directory = sys_get_temp_dir() . '/garlic-hub-blackbox-' . bin2hex(random_bytes(4));
		mkdir($this->directory, 0775, true);

		foreach (['config', 'templates', 'translations', 'migrations'] as $folder)
			$this->copyDirectory($root . '/' . $folder, $this->directory . '/' . $folder);

		mkdir($this->directory . '/public');
		foreach (['index.php', 'media.php', 'delivery.php'] as $script)
			copy($root . '/public/' . $script, $this->directory . '/public/' . $script);
		$this->copyDirectory($root . '/public/smil', $this->directory . '/public/smil');
		symlink($root . '/vendor', $this->directory . '/vendor');

		$this->createVarDirectory();
		file_put_contents($this->directory . '/.env', $this->environment());
		file_put_contents($this->directory . '/router.php', $this->router());

		$this->port = $this->findFreePort();
		$this->startServer();
	}

	public function stop(): void
	{
		if (is_resource($this->process))
		{
			proc_terminate($this->process);
			proc_close($this->process);
			$this->process = null;
		}

		if ($this->directory !== '' && is_dir($this->directory))
			$this->removeDirectory($this->directory);

		$this->directory = '';
	}

	public function baseUrl(): string
	{
		return 'http://127.0.0.1:' . $this->port;
	}

	public function databaseFile(): string
	{
		return $this->directory . '/var/hub.sqlite';
	}

	/**
	 * Empties the cache of the server (user cache, login lock) and the sessions and writes the original .env:
	 * every test starts clean.
	 */
	public function resetState(): void
	{
		file_put_contents($this->directory . '/.env', $this->environment());

		foreach (['tmp', 'var/sessions', 'var/cache'] as $folder)
		{
			$path = $this->directory . '/' . $folder;
			if (is_dir($path))
				$this->removeDirectory($path);

			mkdir($path, 0775, true);
		}
	}

	/**
	 * The .env is read with every request, a change counts at once. resetState() writes the original again.
	 *
	 * @param array<string,string|null> $changes new values, null removes the variable
	 */
	public function changeEnvironment(array $changes): void
	{
		file_put_contents($this->directory . '/.env', $this->environment($changes));
	}

	public function directory(): string
	{
		return $this->directory;
	}

	/**
	 * What the server wrote to its error log: for the message when a test fails.
	 */
	public function serverLog(): string
	{
		$file = $this->directory . '/server.log';

		return is_file($file) ? (string) file_get_contents($file) : '';
	}

	private function createVarDirectory(): void
	{
		foreach (['cache', 'logs', 'logs/smil', 'weblogs', 'sessions', 'keys', 'smil/indexes', 'smil/playlists',
					'storage/mediapool/thumbs', 'storage/mediapool/originals', 'storage/mediapool/uploads',
					'storage/templates/thumbs', 'storage/templates/originals',
					'storage/playlists/items/thumbs', 'storage/playlists/items/originals'] as $folder)
			mkdir($this->directory . '/var/' . $folder, 0775, true);

		file_put_contents($this->directory . '/var/keys/encryption.key', Key::createNewRandomKey()->saveToAsciiSafeString());
		file_put_contents($this->directory . '/var/install.lock', 'blackbox');
	}

	/**
	 * @param array<string,string|null> $changes
	 */
	private function environment(array $changes = []): string
	{
		$values = array_merge([
			'APP_NAME'             => '"Garlic-Hub"',
			'APP_ENV'              => 'test',
			'APP_DEBUG'            => 'false',
			'APP_PLATFORM_EDITION' => 'edge',
			'APP_SECRET'           => 'blackbox-secret-0123456789abcdef',
			'DB_MASTER_PATH'       => '"' . $this->databaseFile() . '"',
			'DB_MASTER_DRIVER'     => '"PDO_SQLITE"',
			'DB_MASTER_USER'       => '""',
			'DB_MASTER_PASSWORD'   => '""',
			'DB_MASTER_HOST'       => '""',
			'DB_MASTER_PORT'       => '""',
			'DB_MASTER_NAME'       => '""',
		], $changes);

		$lines = [];
		foreach ($values as $name => $value)
		{
			if ($value !== null)
				$lines[] = $name . '=' . $value;
		}

		return implode("\n", $lines) . "\n";
	}

	/**
	 * Files that exist (static files) are served by the web server itself, media.php and delivery.php get their
	 * path info, everything else goes to the front controller, like the rewrite rules of Apache do.
	 */
	private function router(): string
	{
		return <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__ . '/public' . $path))
	return false;

// media.php/<variant>/<name>.<ext> and delivery.php/...: the web server sets PATH_INFO (decoded) for them
foreach (['/media.php', '/delivery.php'] as $script)
{
	if (str_starts_with($path, $script . '/'))
	{
		$_SERVER['PATH_INFO']       = rawurldecode(substr($path, strlen($script)));
		$_SERVER['SCRIPT_NAME']     = $script;
		$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public' . $script;
		require __DIR__ . '/public' . $script;
		return true;
	}
}

$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/index.php';
require __DIR__ . '/public/index.php';
PHP;
	}

	private function startServer(): void
	{
		$log = $this->directory . '/server.log';
		$command = [PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' . $log, '-d', 'session.save_path=' . $this->directory . '/var/sessions',
			'-S', '127.0.0.1:' . $this->port, '-t', $this->directory . '/public', $this->directory . '/router.php'];

		mkdir($this->directory . '/tmp', 0775, true);
		// the server uses its own temporary directory: the cache of phpfastcache and the sessions stay inside
		$environment = array_merge(getenv(), ['TMPDIR' => $this->directory . '/tmp']);

		$descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->directory . '/stdout.log', 'w'], 2 => ['file', $this->directory . '/stderr.log', 'w']];
		$process = proc_open($command, $descriptors, $pipes, $this->directory, $environment);
		if (!is_resource($process))
			throw new RuntimeException('The test server could not be started.');

		$this->process = $process;
		for ($i = 0; $i < 100; $i++)
		{
			$socket = @fsockopen('127.0.0.1', $this->port, $errorCode, $errorMessage, 0.2);
			if ($socket !== false)
			{
				fclose($socket);

				return;
			}
			usleep(50_000);
		}

		$this->stop();
		throw new RuntimeException('The test server did not answer on port ' . $this->port . '.');
	}

	private function findFreePort(): int
	{
		$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
		if ($server === false)
			throw new RuntimeException('No free port: ' . $errorMessage);

		$name = (string) stream_socket_get_name($server, false);
		fclose($server);

		return (int) substr($name, (int) strrpos($name, ':') + 1);
	}

	private function copyDirectory(string $source, string $target): void
	{
		mkdir($target, 0775, true);
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
		foreach ($iterator as $item)
		{
			if (!$item instanceof \SplFileInfo)
				continue;

			$destination = $target . substr($item->getPathname(), strlen($source));
			if ($item->isDir())
				mkdir($destination, 0775, true);
			else
				copy($item->getPathname(), $destination);
		}
	}

	private function removeDirectory(string $directory): void
	{
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $item)
		{
			if (!$item instanceof \SplFileInfo)
				continue;

			if ($item->isLink() || $item->isFile())
				unlink($item->getPathname());
			else
				rmdir($item->getPathname());
		}
		rmdir($directory);
	}
}
