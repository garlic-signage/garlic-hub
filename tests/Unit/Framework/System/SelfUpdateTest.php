<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\System;

use App\Framework\Exceptions\CoreException;
use App\Framework\System\SelfUpdate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The PHP side of the update from the web page: the request file and the status of garlic-hub-update.
 */
class SelfUpdateTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/garlic-update-' . bin2hex(random_bytes(4));
		mkdir($this->dir);
	}

	protected function tearDown(): void
	{
		foreach ((array) glob($this->dir . '/*') as $file)
			unlink((string) $file);
		chmod($this->dir, 0700);
		rmdir($this->dir);
		parent::tearDown();
	}

	#[Group('units')]
	public function testAvailableOnlyWhenEnabledAndTheDirectoryExists(): void
	{
		static::assertTrue(new SelfUpdate(true, $this->dir)->isAvailable());
		static::assertFalse(new SelfUpdate(false, $this->dir)->isAvailable());
		static::assertFalse(new SelfUpdate(true, $this->dir . '/missing')->isAvailable());
	}

	#[Group('units')]
	public function testTheRequestWritesTheFile(): void
	{
		$selfUpdate = new SelfUpdate(true, $this->dir);
		static::assertFalse($selfUpdate->isRequested());

		$selfUpdate->request();

		static::assertTrue($selfUpdate->isRequested());
		static::assertSame('', file_get_contents($this->dir . '/requested'));
	}

	#[Group('units')]
	public function testNoRequestWhenNotAvailable(): void
	{
		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('not available');

		new SelfUpdate(false, $this->dir)->request();
	}

	#[Group('units')]
	public function testNoRequestWhenTheDirectoryIsNotWritable(): void
	{
		chmod($this->dir, 0500);
		if (is_writable($this->dir))
			static::markTestSkipped('root writes into every directory');

		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('is not writable');

		new SelfUpdate(true, $this->dir)->request();
	}

	#[Group('units')]
	public function testNoStatusBeforeTheFirstRun(): void
	{
		static::assertNull(new SelfUpdate(true, $this->dir)->readStatus());
	}

	#[Group('units')]
	public function testTheStatusOfTheScript(): void
	{
		file_put_contents($this->dir . '/status.json', '{"state":"failed","step":"migrate","from":"1.2.0","to":"1.3.0","time":"2026-11-15T03:00:00Z"}');

		static::assertSame(
			['state' => 'failed', 'step' => 'migrate', 'from' => '1.2.0', 'to' => '1.3.0', 'time' => '2026-11-15T03:00:00Z'],
			new SelfUpdate(true, $this->dir)->readStatus()
		);
	}

	#[Group('units')]
	public function testMissingFieldsAreEmpty(): void
	{
		file_put_contents($this->dir . '/status.json', '{"state":"current","step":3}');

		static::assertSame(
			['state' => 'current', 'step' => '', 'from' => '', 'to' => '', 'time' => ''],
			new SelfUpdate(true, $this->dir)->readStatus()
		);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function brokenStatus(): array
	{
		return [
			'no json'         => ['running'],
			'no object'       => ['"running"'],
			'no state'        => ['{"step":"download"}'],
			'unknown state'   => ['{"state":"exploded"}'],
		];
	}

	#[Group('units')]
	#[DataProvider('brokenStatus')]
	public function testABrokenStatusIsNone(string $content): void
	{
		file_put_contents($this->dir . '/status.json', $content);

		static::assertNull(new SelfUpdate(true, $this->dir)->readStatus());
	}
}
