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

namespace Tests\Integration\Modules\Mediapool\Utils;

use App\Framework\Media\Ffmpeg;
use App\Framework\Media\MediaProperties;
use App\Framework\System\ShellExecutor;
use App\Modules\Mediapool\Utils\ImagickFactory;
use App\Modules\Mediapool\Utils\TaggingImage;
use Imagick;
use ImagickException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The image which the AI sees for the tagging, made from real files: a jpeg of at most 1024 pixels, not stored.
 */
#[Group('io')]
class TaggingImageTest extends TestCase
{
	private MediaWorkspace $workspace;
	private TaggingImage $taggingImage;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace    = new MediaWorkspace();
		$this->taggingImage = new TaggingImage(
			$this->workspace->config,
			$this->workspace->filesystem,
			new ImagickFactory(),
			new Ffmpeg($this->workspace->config, $this->workspace->filesystem, new MediaProperties(), new ShellExecutor())
		);
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	/**
	 * @throws ImagickException
	 */
	private function image(string $name, int $width, int $height, string $format, string $color = 'red'): string
	{
		$imagick = new Imagick();
		$imagick->newImage($width, $height, $color);
		$imagick->setImageFormat($format);

		return ltrim($this->workspace->store($name, $imagick->getImagesBlob()), '/');
	}

	/**
	 * @return array{int,int,string} width, height and format of the image of the data url
	 * @throws ImagickException
	 */
	private function decode(string $dataUrl): array
	{
		static::assertStringStartsWith('data:image/jpeg;base64,', $dataUrl);
		$imagick = new Imagick();
		$imagick->readImageBlob((string) base64_decode(substr($dataUrl, strlen('data:image/jpeg;base64,')), true));

		return [$imagick->getImageWidth(), $imagick->getImageHeight(), $imagick->getImageFormat()];
	}

	/**
	 * @return array<string,array{string,bool}>
	 */
	public static function mimetypes(): array
	{
		return [
			'jpeg'     => ['image/jpeg', true],
			'png'      => ['image/png', true],
			'video'    => ['video/mp4', true],
			'pdf'      => ['application/pdf', true],
			'svg'      => ['image/svg+xml', false],
			'audio'    => ['audio/mpeg', false],
			'widget'   => ['application/widget', false],
		];
	}

	#[DataProvider('mimetypes')]
	public function testTheAiSeesPicturesVideosAndDocuments(string $mimetype, bool $supported): void
	{
		static::assertSame($supported, TaggingImage::supports($mimetype));
	}

	public function testALargePictureIsMadeSmallerAndAJpeg(): void
	{
		$path = $this->image('a.png', 3000, 1500, 'png');

		static::assertSame([1024, 512, 'JPEG'], $this->decode($this->taggingImage->create($path, 'image/png')));
	}

	public function testASmallPictureKeepsItsSize(): void
	{
		$path = $this->image('a.jpg', 300, 200, 'jpeg');

		static::assertSame([300, 200, 'JPEG'], $this->decode($this->taggingImage->create($path, 'image/jpeg')));
	}

	public function testATransparentPictureBecomesWhite(): void
	{
		$path = $this->image('a.png', 10, 10, 'png', 'transparent');

		$imagick = new Imagick();
		$dataUrl = $this->taggingImage->create($path, 'image/png');
		$imagick->readImageBlob((string) base64_decode(substr($dataUrl, strlen('data:image/jpeg;base64,')), true));

		static::assertGreaterThan(0.95, $imagick->getImagePixelColor(5, 5)->getColorValue(Imagick::COLOR_RED));
	}

	public function testTheFirstPageOfADocument(): void
	{
		$imagick = new Imagick();
		$imagick->newImage(595, 842, 'white');
		$imagick->setImageFormat('pdf');
		$path = ltrim($this->workspace->store('a.pdf', $imagick->getImagesBlob()), '/');

		try
		{
			[$width, $height] = $this->decode($this->taggingImage->create($path, 'application/pdf'));
		}
		catch (ImagickException $e)
		{
			static::markTestSkipped('Reading a pdf needs Ghostscript: ' . $e->getMessage());
		}

		// A4 at 120 dpi is higher than 1024 pixels
		static::assertSame(1024, $height);
		static::assertEqualsWithDelta(724, $width, 1);
	}

	public function testTheFrameOfAVideoAndNothingStaysInTheStorage(): void
	{
		if (!is_executable('/usr/bin/ffmpeg') || !is_executable('/usr/bin/ffprobe'))
			static::markTestSkipped('ffmpeg and ffprobe are needed.');

		$path    = 'var/storage/mediapool/originals/a.mp4';
		$command = sprintf('/usr/bin/ffmpeg -hide_banner -loglevel error -f lavfi -i %s -t 3 -pix_fmt yuv420p -y %s 2>&1', escapeshellarg('testsrc=size=1920x1080:rate=10'), escapeshellarg($this->workspace->absolute('/' . $path)));
		exec($command, $output, $code);
		static::assertSame(0, $code, 'ffmpeg: ' . implode("\n", $output));

		static::assertSame([1024, 576, 'JPEG'], $this->decode($this->taggingImage->create($path, 'video/mp4')));
		static::assertSame([], $this->workspace->listing('uploads'));
		static::assertSame(['a.mp4'], $this->workspace->listing('originals'));
	}

	public function testAMissingFileIsAnError(): void
	{
		$this->expectException(ImagickException::class);

		$this->taggingImage->create('var/storage/mediapool/originals/missing.jpg', 'image/jpeg');
	}
}
