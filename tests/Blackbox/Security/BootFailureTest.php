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

namespace Tests\Blackbox\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Blackbox\Support\BlackboxTestCase;

/**
 * When the application can not start (broken .env, broken service definition), there is no logger and no error
 * middleware yet. The visitor gets a plain 500 without paths or messages, the reason is in the error log of PHP.
 */
#[Group('blackbox')]
class BootFailureTest extends BlackboxTestCase
{
	private const string BROKEN_SERVICES = '/config/services/zz_broken.php';

	protected function tearDown(): void
	{
		$file = $this->deployment()->directory() . self::BROKEN_SERVICES;
		if (is_file($file))
			unlink($file);

		parent::tearDown();
	}

	private function assertPlainFailure(string $reasonInLog): void
	{
		$logBefore = strlen($this->deployment()->serverLog());

		$response = $this->rawGet('/login');

		static::assertSame(500, $response['status']);
		static::assertSame('The application could not be started.', $response['body']);
		static::assertStringStartsWith('text/plain', $response['headers']['content-type']);
		static::assertStringNotContainsString($this->deployment()->directory(), $response['body']);
		static::assertStringContainsString($reasonInLog, substr($this->deployment()->serverLog(), $logBefore));
	}

	/**
	 * @return array<string,array{array<string,string|null>,string}> the changes of the .env and what the log names
	 */
	public static function brokenEnvironments(): array
	{
		return [
			'APP_ENV missing'      => [['APP_ENV' => null], 'APP_ENV'],
			'APP_ENV empty'        => [['APP_ENV' => ''], 'APP_ENV'],
			'APP_ENV unknown'      => [['APP_ENV' => 'staging'], 'APP_ENV'],
			'APP_DEBUG missing'    => [['APP_DEBUG' => null], 'APP_DEBUG'],
			'APP_DEBUG no boolean' => [['APP_DEBUG' => 'maybe'], 'APP_DEBUG'],
		];
	}

	/**
	 * @param array<string,string|null> $changes
	 */
	#[DataProvider('brokenEnvironments')]
	public function testABrokenEnvironmentGivesAPlainFailure(array $changes, string $reasonInLog): void
	{
		$this->deployment()->changeEnvironment($changes);

		$this->assertPlainFailure($reasonInLog);
	}

	/**
	 * PHP-DI reads a definition file when a service is needed first, so the failure shows up while the app is created.
	 */
	public function testABrokenServiceDefinitionGivesAPlainFailure(): void
	{
		file_put_contents($this->deployment()->directory() . self::BROKEN_SERVICES, "<?php\nreturn 'no definitions';\n");

		$this->assertPlainFailure('zz_broken.php');
	}

	public function testTheApplicationStartsAgainWithTheOriginalEnvironment(): void
	{
		$this->deployment()->changeEnvironment(['APP_ENV' => null]);
		static::assertSame(500, $this->rawGet('/login')['status']);

		$this->deployment()->resetState();

		static::assertSame(200, $this->rawGet('/login')['status']);
	}
}
