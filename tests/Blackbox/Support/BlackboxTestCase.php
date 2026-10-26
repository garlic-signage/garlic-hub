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

use App\Framework\Database\Migration\Repository;
use App\Framework\Database\Migration\Runner;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Base class of the tests over HTTP. One server per test class, a fresh database for every test:
 * the schema comes from the real migrations, the data from the same SQL scripts as in the integration tests.
 *
 * PHPUnit does not inherit groups: every concrete class needs #[Group('blackbox')] itself.
 */
abstract class BlackboxTestCase extends TestCase
{
	private static ?MiniDeployment $deployment = null;

	protected Connection $connection;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$deployment = new MiniDeployment();
		self::$deployment->start();
	}

	public static function tearDownAfterClass(): void
	{
		self::$deployment?->stop();
		self::$deployment = null;
		parent::tearDownAfterClass();
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->deployment()->resetState();
		$file = $this->deployment()->databaseFile();
		foreach ([$file, $file . '-journal'] as $old)
		{
			if (is_file($old))
				unlink($old);
		}

		$this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $file]);
		(new Runner(
			new Repository($this->connection),
			new Filesystem(new LocalFilesystemAdapter(__DIR__ . '/../../../migrations/edge'))
		))->execute();
	}

	protected function tearDown(): void
	{
		$this->connection->close();
		parent::tearDown();
	}

	protected function deployment(): MiniDeployment
	{
		if (self::$deployment === null)
			self::fail('The test server is not running.');

		return self::$deployment;
	}

	protected function browser(): Browser
	{
		return new Browser($this->deployment()->baseUrl());
	}

	protected function loadFixture(string $name): void
	{
		$sql = file_get_contents(__DIR__ . '/../../resources/sql/' . $name);
		if ($sql === false)
			self::fail('Fixture not found: ' . $name);

		$this->connection->executeStatement($sql);
	}

	/**
	 * A request with the path exactly as given: the HTTP client would clean up dot segments, like a browser does,
	 * but an attacker sends what he wants.
	 *
	 * @param array<string,string> $headers
	 * @return array{status:int,headers:array<string,string>,body:string}
	 */
	protected function rawGet(string $path, array $headers = []): array
	{
		$port   = (int) parse_url($this->deployment()->baseUrl(), PHP_URL_PORT);
		$socket = stream_socket_client('tcp://127.0.0.1:' . $port, $errorCode, $errorText, 10);
		static::assertNotFalse($socket, (string) $errorText);
		$request = "GET $path HTTP/1.1\r\nHost: garlic-hub.test\r\nConnection: close\r\n";
		foreach ($headers as $name => $value)
			$request .= "$name: $value\r\n";
		fwrite($socket, $request . "\r\n");
		$answer = (string) stream_get_contents($socket);
		fclose($socket);

		[$head, $body] = array_pad(explode("\r\n\r\n", $answer, 2), 2, '');
		$lines   = explode("\r\n", $head);
		$status  = (int) (explode(' ', $lines[0])[1] ?? 0);
		$parsed  = [];
		foreach (array_slice($lines, 1) as $line)
		{
			[$name, $value] = array_pad(explode(':', $line, 2), 2, '');
			$parsed[strtolower(trim($name))] = trim($value);
		}

		return ['status' => $status, 'headers' => $parsed, 'body' => $body];
	}
}
