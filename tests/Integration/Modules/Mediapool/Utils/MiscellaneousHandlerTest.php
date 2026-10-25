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

namespace Tests\Integration\Modules\Mediapool\Utils;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Mediapool\Utils\Miscellaneous;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\MediaWorkspace;

#[Group('io')]
class MiscellaneousHandlerTest extends TestCase
{
	private MediaWorkspace $workspace;
	private Miscellaneous $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		$this->handler   = new Miscellaneous($this->workspace->config, $this->workspace->filesystem);
		$this->handler->setStoragePaths('mediapool');
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	public function testTheMaximumIsOneGigabyte(): void
	{
		static::assertSame(1024 * 1024 * 1024, $this->handler->getMaxFileSize());
	}

	public function testAFileOfTheMaximumSizeIsAllowedAndOneByteMoreIsNot(): void
	{
		$this->handler->checkFileBeforeUpload(1024 * 1024 * 1024);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('filesize_exceeded');

		$this->handler->checkFileBeforeUpload(1024 * 1024 * 1024 + 1);
	}

	public function testTheSizeIsKnownAfterTheCheck(): void
	{
		$path = $this->workspace->store('data.csv', "a;b\n1;2\n");

		$this->handler->validateStoredFile($path);

		static::assertSame(8, $this->handler->getFileSize());
	}

	public function testAFileOverTheMaximumSizeIsRefusedAfterTheUpload(): void
	{
		$path = $this->workspace->sparse('big.zip', 1024 * 1024 * 1024 + 1);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('filesize_exceeded');

		$this->handler->validateStoredFile($path);
	}

	public function testAMissingFileIsAnError(): void
	{
		$this->expectException(ModuleException::class);

		$this->handler->validateStoredFile($this->workspace->original('nothing.csv'));
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function icons(): array
	{
		return [
			'csv'      => ['table.csv', 'database.svg'],
			'json'     => ['table.json', 'database.svg'],
			'xml'      => ['table.xml', 'database.svg'],
			'zip'      => ['table.zip', 'file.svg'],
			'apk'      => ['app.apk', 'file.svg'],
			'smil'     => ['plan.smil', 'file.svg'],
			'upper'    => ['TABLE.CSV', 'file.svg'],
		];
	}

	/**
	 * Data files show the icon of a database, everything else the icon of a file.
	 */
	#[DataProvider('icons')]
	public function testTheThumbnailIsAnIconOfTheKindOfFile(string $name, string $icon): void
	{
		$path = $this->workspace->store($name, 'x');

		$this->handler->createThumbnail($path);

		$thumb = pathinfo($name, PATHINFO_FILENAME) . '.svg';
		static::assertSame([$thumb], $this->workspace->listing('thumbs'));
		static::assertFileEquals($this->workspace->dir . '/public/images/icons/' . $icon, $this->workspace->dir . '/var/storage/mediapool/thumbs/' . $thumb);
		static::assertSame('svg', $this->handler->getThumbExtension());
	}
}
