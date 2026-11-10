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

namespace Tests\Unit\Modules\Mediapool\Utils;

use App\Framework\Config\Config;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Media\Ffmpeg;
use App\Framework\Utils\Widget\ConfigXML;
use App\Modules\Mediapool\Utils\AbstractMediaHandler;
use App\Modules\Mediapool\Utils\Audio;
use App\Modules\Mediapool\Utils\Image;
use App\Modules\Mediapool\Utils\ImagickFactory;
use App\Modules\Mediapool\Utils\Miscellaneous;
use App\Modules\Mediapool\Utils\Pdf;
use App\Modules\Mediapool\Utils\TaggingImage;
use App\Modules\Mediapool\Utils\Video;
use App\Modules\Mediapool\Utils\Widget;
use App\Modules\Mediapool\Utils\ZipFilesystemFactory;
use Imagick;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * The media handlers with a recording Imagick, Ffmpeg and storage: what they ask the libraries for, with which
 * values and paths, and the limits exactly at the border. The real files are in tests/Integration/Modules/Mediapool/Utils.
 * The configuration has another maximum height than width, so a mixed up limit shows.
 */
class MediaHandlersTest extends TestCase
{
	private const int MAX = 1000;

	/** @var list<string> the calls to Imagick, Ffmpeg and the storage in their order */
	private array $calls = [];
	private int $storedSize = self::MAX;
	/** @var array{width:int,height:int} */
	private array $imageSize = ['width' => 100, 'height' => 100];
	private Config&Stub $config;

	protected function setUp(): void
	{
		parent::setUp();
		$this->calls      = [];
		$this->storedSize = self::MAX;
		$this->config     = $this->createStub(Config::class);
		$this->config->method('getPaths')->willReturn('/sys');
		$this->config->method('getConfigValue')->willReturnCallback(static fn(string $key, string $module, ?string $section = null): string => match ($key)
		{
			'width'       => '3840',
			'height'      => '2160',
			'thumb_width' => '300',
			'thumb_height'=> '200',
			'uploads'     => 'var/uploads',
			'icons'       => 'public/images/icons',
			'thumbnails'  => 'var/thumbs',
			'originals'   => 'var/originals',
			'previews'    => 'var/previews',
			default       => (string) self::MAX, // the sizes of the files
		});
	}

	private function record(string $call): mixed
	{
		$this->calls[] = $call;

		return null;
	}

	private function filesystem(): Filesystem
	{
		$filesystem = $this->createStub(Filesystem::class);
		$filesystem->method('fileExists')->willReturn(true);
		$filesystem->method('fileSize')->willReturnCallback(fn(): int => $this->storedSize);
		$filesystem->method('copy')->willReturnCallback(fn(string $from, string $to) => $this->record('copy ' . $from . ' > ' . $to));
		$filesystem->method('move')->willReturnCallback(fn(string $from, string $to) => $this->record('move ' . $from . ' > ' . $to));
		$filesystem->method('createDirectory')->willReturnCallback(fn(string $path) => $this->record('mkdir ' . $path));
		$filesystem->method('deleteDirectory')->willReturnCallback(fn(string $path) => $this->record('rmdir ' . $path));

		return $filesystem;
	}

	private function imagick(): Imagick
	{
		$imagick = $this->createStub(Imagick::class);
		$imagick->method('setResolution')->willReturnCallback(fn(float $x, float $y): bool => (bool) $this->record('setResolution ' . $x . ' ' . $y) || true);
		$imagick->method('readImage')->willReturnCallback(fn(string $path): bool => (bool) $this->record('readImage ' . $path) || true);
		$imagick->method('readImageBlob')->willReturnCallback(fn(string $blob): bool => (bool) $this->record('readImageBlob ' . $blob) || true);
		$imagick->method('setImageAlphaChannel')->willReturnCallback(fn(int $channel): bool => (bool) $this->record('setImageAlphaChannel ' . $channel) || true);
		$imagick->method('setImageBackgroundColor')->willReturnCallback(fn(mixed $color): bool => (bool) $this->record('setImageBackgroundColor ' . $color) || true);
		$imagick->method('setImageFormat')->willReturnCallback(fn(string $format): bool => (bool) $this->record('setImageFormat ' . $format) || true);
		$imagick->method('setImageCompressionQuality')->willReturnCallback(fn(int $quality): bool => (bool) $this->record('setImageCompressionQuality ' . $quality) || true);
		$imagick->method('thumbnailImage')->willReturnCallback(fn(int $width, int $height, bool $bestfit = false): bool => (bool) $this->record('thumbnailImage ' . $width . ' ' . $height . ' ' . ($bestfit ? 'bestfit' : 'exact')) || true);
		$imagick->method('writeImage')->willReturnCallback(fn(?string $path = null): bool => (bool) $this->record('writeImage ' . $path) || true);
		$imagick->method('mergeImageLayers')->willReturnCallback(fn(): Imagick => $imagick);
		$imagick->method('getImageWidth')->willReturnCallback(fn(): int => $this->imageSize['width']);
		$imagick->method('getImageHeight')->willReturnCallback(fn(): int => $this->imageSize['height']);
		$imagick->method('getImageBlob')->willReturn('jpeg');

		return $imagick;
	}

	/**
	 * @param array<string,mixed> $metadata what ffmpeg reads from the file
	 * @param array{width:int,height:int} $properties
	 */
	private function ffmpeg(array $metadata = [], array $properties = ['width' => 0, 'height' => 0]): Ffmpeg
	{
		$ffmpeg = $this->createStub(Ffmpeg::class);
		$ffmpeg->method('setMetadata')->willReturnCallback(function (array $given) use ($ffmpeg): Ffmpeg
		{
			$this->record('ffmpeg setMetadata ' . json_encode($given));
			return $ffmpeg;
		});
		$ffmpeg->method('init')->willReturnCallback(function (string $path) use ($ffmpeg): Ffmpeg
		{
			$this->record('ffmpeg init ' . $path);
			return $ffmpeg;
		});
		$ffmpeg->method('getMetadata')->willReturn($metadata);
		$ffmpeg->method('getMediaProperties')->willReturn($properties);
		$ffmpeg->method('getDuration')->willReturn(12.5);
		$ffmpeg->method('createVideoThumbnail')->willReturnCallback(function (string $destination): string
		{
			$this->record('ffmpeg thumbnail ' . $destination);
			return $destination . '/vid_1.jpg';
		});

		return $ffmpeg;
	}

	private function widget(): Widget
	{
		$zip = $this->createStub(Filesystem::class);
		$zip->method('fileExists')->willReturn(false);
		$zipFactory = $this->createStub(ZipFilesystemFactory::class);
		$zipFactory->method('create')->willReturn($zip);
		$configXML = $this->createStub(ConfigXML::class);
		$configXML->method('getIcon')->willReturn('');

		return new Widget($this->config, $this->filesystem(), $zipFactory, $this->imagick(), $configXML);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function handlers(): array
	{
		return ['image' => ['image'], 'pdf' => ['pdf'], 'audio' => ['audio'], 'video' => ['video'], 'widget' => ['widget'], 'miscellaneous' => ['miscellaneous']];
	}

	private function handler(string $type): AbstractMediaHandler
	{
		$handler = match ($type)
		{
			'image'  => new Image($this->config, $this->filesystem(), $this->imagick()),
			'pdf'    => new Pdf($this->config, $this->filesystem(), $this->imagick()),
			'audio'  => new Audio($this->config, $this->filesystem(), $this->ffmpeg()),
			'video'  => new Video($this->config, $this->filesystem(), $this->ffmpeg(), $this->imagick()),
			'widget' => $this->widget(),
			default  => new Miscellaneous($this->config, $this->filesystem()),
		};
		$handler->setStoragePaths('mediapool');

		return $handler;
	}

	/**
	 * A stored file of exactly the maximum size passes, one byte more is refused.
	 */
	#[Group('units')]
	#[DataProvider('handlers')]
	public function testAStoredFileOfTheMaximumSizeIsAllowedAndOneByteMoreIsNot(string $type): void
	{
		$handler = $this->handler($type);
		$handler->validateStoredFile('/var/originals/a.bin');
		static::assertSame(self::MAX, $handler->getFileSize());

		$this->storedSize = self::MAX + 1;
		try
		{
			$this->handler($type)->validateStoredFile('/var/originals/a.bin');
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('filesize_exceeded', $e->getTranslationKey());
		}
	}

	#[Group('units')]
	public function testAnImageOfTheMaximumResolutionIsAllowed(): void
	{
		$this->imageSize = ['width' => 3840, 'height' => 2160];
		$handler = $this->handler('image');

		$handler->validateStoredFile('/var/originals/a.png');

		static::assertSame(['width' => 3840, 'height' => 2160], $handler->getDimensions());
	}

	/**
	 * @return array<string,array{int,int,string,int,int}>
	 */
	public static function tooLargeResolutions(): array
	{
		return [
			'too wide' => [3841, 2160, 'width_exceeded', 3841, 3840],
			'too high' => [3840, 2161, 'height_exceeded', 2161, 2160],
		];
	}

	/**
	 * Each dimension is compared with its own maximum, and the message names that one.
	 */
	#[Group('units')]
	#[DataProvider('tooLargeResolutions')]
	public function testAnImageOverTheResolutionNamesTheMaximumOfItsDimension(int $width, int $height, string $key, int $value, int $max): void
	{
		$this->imageSize = ['width' => $width, 'height' => $height];

		try
		{
			$this->handler('image')->validateStoredFile('/var/originals/a.png');
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame($key, $e->getTranslationKey());
			static::assertSame(['value' => $value, 'max' => $max], $e->getReplacements());
		}
	}

	#[Group('units')]
	#[DataProvider('tooLargeResolutions')]
	public function testAVideoOverTheResolutionNamesTheMaximumOfItsDimension(int $width, int $height, string $key, int $value, int $max): void
	{
		$handler = new Video($this->config, $this->filesystem(), $this->ffmpeg([], ['width' => $width, 'height' => $height]), $this->imagick());
		$handler->setStoragePaths('mediapool');

		try
		{
			$handler->validateStoredFile('/var/originals/a.mp4');
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame($key, $e->getTranslationKey());
			static::assertSame(['value' => $value, 'max' => $max], $e->getReplacements());
		}
	}

	/**
	 * The metadata which came with the upload go to ffmpeg, what ffmpeg reads comes back with the duration.
	 */
	#[Group('units')]
	public function testAVideoOfTheMaximumResolutionGivesItsMetadataToFfmpegAndTakesTheReadOnes(): void
	{
		$handler = new Video($this->config, $this->filesystem(), $this->ffmpeg(['codec' => 'h264'], ['width' => 3840, 'height' => 2160]), $this->imagick());
		$handler->setStoragePaths('mediapool');
		$handler->setMetadata(['duration' => 3]);

		$handler->validateStoredFile('/var/originals/a.mp4');

		static::assertSame(['ffmpeg setMetadata {"duration":3}', 'ffmpeg init /var/originals/a.mp4'], $this->calls);
		static::assertSame(['codec' => 'h264'], $handler->getMetadata());
		static::assertSame(12.5, $handler->getDuration());
		static::assertSame(['width' => 3840, 'height' => 2160], $handler->getDimensions());
	}

	#[Group('units')]
	public function testAnAudioGivesItsMetadataToFfmpegAndTakesTheReadOnes(): void
	{
		$handler = new Audio($this->config, $this->filesystem(), $this->ffmpeg(['codec' => 'mp3']));
		$handler->setStoragePaths('mediapool');
		$handler->setMetadata(['duration' => 3]);

		$handler->validateStoredFile('/var/originals/a.mp3');

		static::assertSame(['ffmpeg setMetadata {"duration":3}', 'ffmpeg init /var/originals/a.mp3'], $this->calls);
		static::assertSame(['codec' => 'mp3'], $handler->getMetadata());
		static::assertSame(12.5, $handler->getDuration());
		static::assertSame([], $handler->getDimensions());
	}

	/**
	 * The first page at 150 dpi, without alpha channel (some pdf become black otherwise), as jpeg in the thumbnails.
	 */
	#[Group('units')]
	public function testThePdfThumbnailIsTheFirstPageAsJpeg(): void
	{
		$this->handler('pdf')->createThumbnail('/var/originals/doc.pdf');

		static::assertSame([
			'setResolution 150 150',
			'readImage /sys/var/originals/doc.pdf[0]',
			'setImageAlphaChannel ' . Imagick::ALPHACHANNEL_REMOVE,
			'setImageFormat jpg',
			'thumbnailImage 300 200 bestfit',
			'writeImage /sys/var/thumbs/doc.jpg',
		], $this->calls);
	}

	#[Group('units')]
	public function testTheThumbnailOfAGifIsAJpegWhichFitsIntoThePreview(): void
	{
		$handler = $this->handler('image');

		$handler->createThumbnail('/var/originals/anim.gif');

		static::assertSame(['thumbnailImage 300 200 bestfit', 'writeImage /sys/var/thumbs/anim.jpg'], $this->calls);
		static::assertSame('jpg', $handler->getThumbExtension());
	}

	#[Group('units')]
	public function testTheThumbnailOfAnImageKeepsItsFormat(): void
	{
		$handler = $this->handler('image');

		$handler->createThumbnail('/var/originals/a.png');

		static::assertSame(['thumbnailImage 300 200 bestfit', 'writeImage /sys/var/thumbs/a.png'], $this->calls);
		static::assertSame('png', $handler->getThumbExtension());
	}

	#[Group('units')]
	public function testAnSvgIsCopiedAsItsOwnThumbnail(): void
	{
		$handler = $this->handler('image');

		$handler->createThumbnail('/var/originals/logo.svg');

		static::assertSame(['copy /var/originals/logo.svg > /var/thumbs/logo.svg'], $this->calls);
		static::assertSame('svg', $handler->getThumbExtension());
	}

	/**
	 * The frame of ffmpeg is made in the originals, moved to the thumbnails and made small there.
	 */
	#[Group('units')]
	public function testTheVideoThumbnailIsAFrameInTheThumbnails(): void
	{
		$this->handler('video')->createThumbnail('/var/originals/a.mp4');

		static::assertSame([
			'ffmpeg thumbnail /var/originals',
			'move /var/originals/vid_1.jpg > /var/thumbs/a.jpg',
			'readImage /sys/var/thumbs/a.jpg',
			'thumbnailImage 300 200 bestfit',
			'writeImage /sys/var/thumbs/a.jpg',
		], $this->calls);
	}

	/**
	 * @return array<string,array{string,string,string}>
	 */
	public static function icons(): array
	{
		return [
			'audio'          => ['audio', '/var/originals/a.mp3', 'copy /public/images/icons/audio.svg > /var/thumbs/a.svg'],
			'widget'         => ['widget', '/var/originals/w.wgt', 'copy /public/images/icons/widget.svg > /var/thumbs/w.svg'],
			'csv'            => ['miscellaneous', '/var/originals/d.csv', 'copy /public/images/icons/database.svg > /var/thumbs/d.svg'],
			'json'           => ['miscellaneous', '/var/originals/d.json', 'copy /public/images/icons/database.svg > /var/thumbs/d.svg'],
			'xml'            => ['miscellaneous', '/var/originals/d.xml', 'copy /public/images/icons/database.svg > /var/thumbs/d.svg'],
			'other file'     => ['miscellaneous', '/var/originals/d.zip', 'copy /public/images/icons/file.svg > /var/thumbs/d.svg'],
			'without ending' => ['miscellaneous', '/var/originals/d', 'copy /public/images/icons/file.svg > /var/thumbs/d.svg'],
		];
	}

	#[Group('units')]
	#[DataProvider('icons')]
	public function testAFileWithoutPictureGetsTheIconOfItsKind(string $type, string $path, string $copy): void
	{
		$handler = $this->handler($type);

		$handler->createThumbnail($path);

		static::assertSame([$copy], $this->calls);
		static::assertSame('svg', $handler->getThumbExtension());
	}

	private function taggingImage(?Ffmpeg $ffmpeg = null): TaggingImage
	{
		$factory = $this->createStub(ImagickFactory::class);
		$factory->method('createImagick')->willReturn($this->imagick());

		return new TaggingImage($this->config, $this->filesystem(), $factory, $ffmpeg ?? $this->ffmpeg());
	}

	/**
	 * @return array<string,array{int,int,bool}>
	 */
	public static function taggingSizes(): array
	{
		return [
			'exactly the maximum' => [1024, 1024, false],
			'too wide'            => [1025, 10, true],
			'too high'            => [10, 1025, true],
		];
	}

	/**
	 * A picture larger than 1024 pixels in one direction is made smaller, both directions count on their own.
	 */
	#[Group('units')]
	#[DataProvider('taggingSizes')]
	public function testThePictureForTheAiIsAJpegOfAtMost1024Pixels(int $width, int $height, bool $smaller): void
	{
		$this->imageSize = ['width' => $width, 'height' => $height];

		$dataUrl = $this->taggingImage()->create('/var/originals/a.png', 'image/png');

		static::assertSame('data:image/jpeg;base64,' . base64_encode('jpeg'), $dataUrl);
		static::assertSame(array_merge(
			['readImage /sys/var/originals/a.png[0]', 'setImageBackgroundColor white'],
			$smaller ? ['thumbnailImage 1024 1024 bestfit'] : [],
			['setImageFormat jpeg', 'setImageCompressionQuality 85']
		), $this->calls);
	}

	/**
	 * The frame is made in a directory of its own under the uploads, which is removed again.
	 */
	#[Group('units')]
	public function testTheFrameOfAVideoIsMadeInADirectoryOfItsOwn(): void
	{
		$this->taggingImage()->create('var/originals/a.mp4', 'video/mp4');

		static::assertCount(8, $this->calls);
		static::assertMatchesRegularExpression('#^mkdir var/uploads/tagging-[0-9a-f]{16}$#', $this->calls[0]);
		$directory = substr($this->calls[0], strlen('mkdir '));
		static::assertSame([
			'ffmpeg init /var/originals/a.mp4',
			'ffmpeg thumbnail /' . $directory,
			'readImage /sys/' . $directory . '/vid_1.jpg[0]',
			'setImageBackgroundColor white',
			'setImageFormat jpeg',
			'setImageCompressionQuality 85',
			'rmdir ' . $directory,
		], array_slice($this->calls, 1));
	}
}
