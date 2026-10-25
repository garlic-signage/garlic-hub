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

use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Utils\Widget\ConfigXML;
use App\Modules\Mediapool\Utils\Widget;
use App\Modules\Mediapool\Utils\ZipFilesystemFactory;
use Imagick;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\MediaWorkspace;
use ZipArchive;

/**
 * The widget handler with real widget packages (zip files with a config.xml and an icon).
 */
#[Group('io')]
class WidgetHandlerTest extends TestCase
{
	private const string CONFIG = '<?xml version="1.0" encoding="UTF-8"?><widget xmlns="http://www.w3.org/ns/widgets" id="http://example.com/clock" version="1.2" defaultlocale="en"><name>Clock</name><icon src="%s"/></widget>';

	private MediaWorkspace $workspace;
	private Widget $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		$this->handler   = new Widget($this->workspace->config, $this->workspace->filesystem, new ZipFilesystemFactory(), new Imagick(), new ConfigXML());
		$this->handler->setStoragePaths('mediapool');
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	/**
	 * @param array<string,string> $files the content of the package by name
	 */
	private function widget(string $name, array $files): string
	{
		$path = $this->workspace->original($name);
		$zip  = new ZipArchive();
		$zip->open($this->workspace->absolute($path), ZipArchive::CREATE);
		foreach ($files as $entry => $content)
			$zip->addFromString($entry, $content);
		$zip->close();

		return $path;
	}

	private function png(int $width, int $height): string
	{
		$imagick = new Imagick();
		$imagick->newImage($width, $height, 'blue');
		$imagick->setImageFormat('png');

		return $imagick->getImagesBlob();
	}

	public function testTheMaximumIsOneGigabyte(): void
	{
		static::assertSame(1024 * 1024 * 1024, $this->handler->getMaxFileSize());
	}

	public function testAFileOfTheMaximumSizeIsAllowedBeforeTheUploadAndOneByteMoreIsNot(): void
	{
		$this->handler->checkFileBeforeUpload(1024 * 1024 * 1024);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('filesize_exceeded');

		$this->handler->checkFileBeforeUpload(1024 * 1024 * 1024 + 1);
	}

	public function testAMissingFileIsAnError(): void
	{
		$this->expectException(\App\Framework\Exceptions\ModuleException::class);
		$this->expectExceptionMessage('not exists');

		$this->handler->validateStoredFile($this->workspace->original('nothing.wgt'));
	}

	public function testTheSizeIsKnownAfterTheCheck(): void
	{
		$path = $this->widget('w.wgt', ['config.xml' => sprintf(self::CONFIG, 'icon.png')]);

		$this->handler->validateStoredFile($path);

		static::assertSame((int) filesize($this->workspace->absolute($path)), $this->handler->getFileSize());
	}

	public function testAWidgetOverTheMaximumSizeIsRefused(): void
	{
		$path = $this->workspace->sparse('big.wgt', 1024 * 1024 * 1024 + 1);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('filesize_exceeded');

		$this->handler->validateStoredFile($path);
	}

	public function testTheThumbnailIsTheIconOfThePackageAndTheConfigIsKept(): void
	{
		$config = sprintf(self::CONFIG, 'icon.png');
		$path   = $this->widget('clock.wgt', ['config.xml' => $config, 'icon.png' => $this->png(600, 300), 'index.html' => '<html></html>']);

		$this->handler->createThumbnail($path);

		static::assertSame(['clock.png'], $this->workspace->listing('thumbs'));
		$size = getimagesize($this->workspace->dir . '/var/storage/mediapool/thumbs/clock.png');
		static::assertNotFalse($size);
		static::assertSame([300, 150], [$size[0], $size[1]]);
		static::assertSame('png', $this->handler->getThumbExtension());
		static::assertSame($config, $this->handler->getConfigData());
	}

	public function testAPackageWithoutAnIconGetsTheIconOfAWidget(): void
	{
		$path = $this->widget('clock.wgt', ['config.xml' => sprintf(self::CONFIG, 'missing.png'), 'index.html' => '<html></html>']);

		$this->handler->createThumbnail($path);

		static::assertSame(['clock.svg'], $this->workspace->listing('thumbs'));
		static::assertFileEquals($this->workspace->dir . '/public/images/icons/widget.svg', $this->workspace->dir . '/var/storage/mediapool/thumbs/clock.svg');
		static::assertSame('svg', $this->handler->getThumbExtension());
	}

	public function testAPackageWithoutAConfigGetsTheIconOfAWidget(): void
	{
		$path = $this->widget('plain.wgt', ['index.html' => '<html></html>']);

		$this->handler->createThumbnail($path);

		static::assertSame(['plain.svg'], $this->workspace->listing('thumbs'));
		static::assertSame('', $this->handler->getConfigData());
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function endingsWhichAreNoImages(): array
	{
		return ['script' => ['icon.php'], 'html' => ['icon.html'], 'no ending' => ['icon'], 'svg' => ['icon.svg'], 'double ending' => ['icon.php.txt']];
	}

	/**
	 * The ending of the icon is part of the name and the url of the thumbnail. The package comes from the
	 * outside, so only the ending of an image is taken, otherwise the icon of a widget.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('endingsWhichAreNoImages')]
	public function testAnIconWithAnEndingWhichIsNoImageIsNotUsed(string $icon): void
	{
		$path = $this->widget('evil.wgt', ['config.xml' => sprintf(self::CONFIG, $icon), $icon => $this->png(10, 10)]);

		$this->handler->createThumbnail($path);

		static::assertSame(['evil.svg'], $this->workspace->listing('thumbs'));
		static::assertSame('svg', $this->handler->getThumbExtension());
	}

	public function testTheEndingOfAnIconIsTakenInLowerCase(): void
	{
		$path = $this->widget('clock.wgt', ['config.xml' => sprintf(self::CONFIG, 'ICON.PNG'), 'ICON.PNG' => $this->png(10, 10)]);

		$this->handler->createThumbnail($path);

		static::assertSame(['clock.png'], $this->workspace->listing('thumbs'));
		static::assertSame('png', $this->handler->getThumbExtension());
	}

	public function testAFileWhichIsNoPackageCanNotBeRead(): void
	{
		$path = $this->workspace->store('broken.wgt', 'this is no zip');

		$this->expectException(\Throwable::class);

		$this->handler->createThumbnail($path);
	}
}
