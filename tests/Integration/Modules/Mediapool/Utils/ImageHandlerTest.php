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
use App\Modules\Mediapool\Utils\Image;
use Imagick;
use ImagickException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The image handler with real images, the real Imagick and a real storage.
 */
#[Group('io')]
class ImageHandlerTest extends TestCase
{
	private MediaWorkspace $workspace;
	private Image $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		$this->handler   = new Image($this->workspace->config, $this->workspace->filesystem, new Imagick());
		$this->handler->setStoragePaths('mediapool');
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	/**
	 * @return string the image as file content
	 */
	private function image(int $width, int $height, string $format): string
	{
		$imagick = new Imagick();
		$imagick->newImage($width, $height, 'red');
		$imagick->setImageFormat($format);

		return $imagick->getImagesBlob();
	}

	public function testTheMaximumIs20Megabyte(): void
	{
		static::assertSame(20 * 1024 * 1024, $this->handler->getMaxFileSize());
	}

	public function testAFileOfTheMaximumSizeIsAllowedAndOneByteMoreIsNot(): void
	{
		$this->handler->checkFileBeforeUpload(20 * 1024 * 1024);

		try
		{
			$this->handler->checkFileBeforeUpload(20 * 1024 * 1024 + 1);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('filesize_exceeded', $e->getTranslationKey());
			static::assertSame(['size' => '20', 'max_size' => '20'], $e->getReplacements());
		}
	}

	public function testTheDimensionsAndTheSizeOfAnImageAreKnownAfterTheCheck(): void
	{
		$path = $this->workspace->store('a.png', $this->image(40, 30, 'png'));

		$this->handler->validateStoredFile($path);

		static::assertSame(['width' => 40, 'height' => 30], $this->handler->getDimensions());
		static::assertSame((int) filesize($this->workspace->absolute($path)), $this->handler->getFileSize());
	}

	public function testAnImageWithTheMaximumResolutionIsAllowed(): void
	{
		$path = $this->workspace->store('a.png', $this->image(3840, 2, 'png'));

		$this->handler->validateStoredFile($path);

		static::assertSame(3840, $this->handler->getDimensions()['width']);
	}

	/**
	 * @return array<string,array{int,int,string,int}>
	 */
	public static function tooBigImages(): array
	{
		return [
			'too wide' => [3841, 2, 'width_exceeded', 3841],
			'too high' => [2, 3841, 'height_exceeded', 3841],
		];
	}

	#[DataProvider('tooBigImages')]
	public function testAnImageOverTheMaximumResolutionIsRefused(int $width, int $height, string $key, int $value): void
	{
		$path = $this->workspace->store('a.png', $this->image($width, $height, 'png'));

		try
		{
			$this->handler->validateStoredFile($path);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame($key, $e->getTranslationKey());
			static::assertSame(['value' => $value, 'max' => 3840], $e->getReplacements());
		}
	}

	public function testAFileOverTheMaximumSizeIsRefusedWithoutReadingIt(): void
	{
		$path = $this->workspace->sparse('big.png', 20 * 1024 * 1024 + 1);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('filesize_exceeded');

		$this->handler->validateStoredFile($path);
	}

	public function testAMissingFileIsAnError(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('not exists');

		$this->handler->validateStoredFile($this->workspace->original('nothing.png'));
	}

	public function testAFileWhichIsNoImageCanNotBeRead(): void
	{
		$path = $this->workspace->store('a.png', 'this is no image');

		$this->expectException(ImagickException::class);

		$this->handler->validateStoredFile($path);
	}

	/**
	 * @return array<string,array{string,int,int,int,int}> the format, the size of the image, the size of the thumbnail
	 */
	public static function thumbnailFormats(): array
	{
		return [
			'wide png'    => ['png', 1000, 500, 300, 150],
			'high jpg'    => ['jpg', 600, 900, 133, 200],
			'square webp' => ['webp', 400, 400, 200, 200],
		];
	}

	#[DataProvider('thumbnailFormats')]
	public function testTheThumbnailFitsIntoThePreviewAndKeepsTheFormat(string $format, int $width, int $height, int $thumbWidth, int $thumbHeight): void
	{
		$path = $this->workspace->store('abc.' . $format, $this->image($width, $height, $format));
		$this->handler->validateStoredFile($path);

		$this->handler->createThumbnail($path);

		static::assertSame(['abc.' . $format], $this->workspace->listing('thumbs'));
		$size = getimagesize($this->workspace->dir . '/var/storage/mediapool/thumbs/abc.' . $format);
		static::assertNotFalse($size);
		static::assertSame([$thumbWidth, $thumbHeight], [$size[0], $size[1]]);
		static::assertSame($format, $this->handler->getThumbExtension());
	}

	public function testAnAnimatedGifGetsAJpegThumbnail(): void
	{
		$gif = new Imagick();
		foreach ([0, 1] as $frame)
		{
			$gif->newImage(500, 400, $frame === 0 ? 'red' : 'blue');
			$gif->setImageFormat('gif');
		}
		$path = $this->workspace->store('anim.gif', $gif->getImagesBlob());
		$this->handler->validateStoredFile($path);

		$this->handler->createThumbnail($path);

		static::assertSame(['anim.jpg'], $this->workspace->listing('thumbs'));
		static::assertSame('jpg', $this->handler->getThumbExtension());
	}

	public function testAnSvgIsItsOwnThumbnail(): void
	{
		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';
		$path = $this->workspace->store('logo.svg', $svg);

		$this->handler->createThumbnail($path);

		static::assertSame(['logo.svg'], $this->workspace->listing('thumbs'));
		static::assertSame($svg, file_get_contents($this->workspace->dir . '/var/storage/mediapool/thumbs/logo.svg'));
		static::assertSame('svg', $this->handler->getThumbExtension());
	}
}
