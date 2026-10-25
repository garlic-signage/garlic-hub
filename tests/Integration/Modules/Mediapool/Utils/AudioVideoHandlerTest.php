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

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Media\Ffmpeg;
use App\Framework\Media\MediaProperties;
use App\Framework\System\ShellExecutor;
use App\Modules\Mediapool\Utils\Audio;
use App\Modules\Mediapool\Utils\Video;
use Imagick;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The audio and video handlers with files made by ffmpeg, the real ffprobe and a real storage.
 * Without ffmpeg the tests are skipped.
 */
#[Group('io')]
class AudioVideoHandlerTest extends TestCase
{
	private MediaWorkspace $workspace;
	private Ffmpeg $ffmpeg;

	protected function setUp(): void
	{
		parent::setUp();
		if (!is_executable('/usr/bin/ffmpeg') || !is_executable('/usr/bin/ffprobe'))
			static::markTestSkipped('ffmpeg and ffprobe are needed.');

		$this->workspace = new MediaWorkspace();
		$this->ffmpeg    = new Ffmpeg($this->workspace->config, $this->workspace->filesystem, new MediaProperties(), new ShellExecutor());
	}

	protected function tearDown(): void
	{
		if (isset($this->workspace))
			$this->workspace->remove();
		parent::tearDown();
	}

	private function audio(): Audio
	{
		$handler = new Audio($this->workspace->config, $this->workspace->filesystem, $this->ffmpeg);
		$handler->setStoragePaths('mediapool');

		return $handler;
	}

	private function video(): Video
	{
		$handler = new Video($this->workspace->config, $this->workspace->filesystem, $this->ffmpeg, new Imagick());
		$handler->setStoragePaths('mediapool');

		return $handler;
	}

	/**
	 * Makes a file with ffmpeg in the storage of the originals and gives its path.
	 */
	private function make(string $name, string $source, string $options = ''): string
	{
		$path    = $this->workspace->original($name);
		$command = sprintf('/usr/bin/ffmpeg -hide_banner -loglevel error -f lavfi -i %s %s -y %s 2>&1', escapeshellarg($source), $options, escapeshellarg($this->workspace->absolute($path)));
		exec($command, $output, $code);
		static::assertSame(0, $code, 'ffmpeg: ' . implode("\n", $output));

		return $path;
	}

	public function testTheMaximaAre100MegabyteForAudioAndOneGigabyteForVideo(): void
	{
		static::assertSame(100 * 1024 * 1024, $this->audio()->getMaxFileSize());
		static::assertSame(1024 * 1024 * 1024, $this->video()->getMaxFileSize());
	}

	/**
	 * @return array<string,array{bool,int}>
	 */
	public static function limits(): array
	{
		return ['audio' => [true, 100 * 1024 * 1024], 'video' => [false, 1024 * 1024 * 1024]];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('limits')]
	public function testAFileOfTheMaximumSizeIsAllowedBeforeTheUploadAndOneByteMoreIsNot(bool $audio, int $max): void
	{
		$handler = $audio ? $this->audio() : $this->video();
		$handler->checkFileBeforeUpload($max);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('filesize_exceeded');

		$handler->checkFileBeforeUpload($max + 1);
	}

	public function testTheDurationOfAnAudioFileIsKnownAfterTheCheck(): void
	{
		$handler = $this->audio();
		$path    = $this->make('tone.mp3', 'sine=frequency=440:duration=2');

		$handler->validateStoredFile($path);

		static::assertEqualsWithDelta(2.0, $handler->getDuration(), 0.2);
		static::assertSame([], $handler->getDimensions());
		static::assertSame((int) filesize($this->workspace->absolute($path)), $handler->getFileSize());
	}

	public function testAnAudioFileGetsTheIconAsThumbnail(): void
	{
		$handler = $this->audio();
		$path    = $this->make('tone.mp3', 'sine=frequency=440:duration=1');

		$handler->createThumbnail($path);

		static::assertSame(['tone.svg'], $this->workspace->listing('thumbs'));
		static::assertSame('svg', $handler->getThumbExtension());
		static::assertFileEquals($this->workspace->dir . '/public/images/icons/audio.svg', $this->workspace->dir . '/var/storage/mediapool/thumbs/tone.svg');
	}

	public function testTheDimensionsAndTheDurationOfAVideoAreKnownAfterTheCheck(): void
	{
		$handler = $this->video();
		$path    = $this->make('clip.mp4', 'testsrc=duration=2:size=320x240:rate=10', '-pix_fmt yuv420p');

		$handler->validateStoredFile($path);

		static::assertSame(['width' => 320, 'height' => 240], $handler->getDimensions());
		static::assertEqualsWithDelta(2.0, $handler->getDuration(), 0.3);
	}

	public function testAVideoOverTheMaximumResolutionIsRefused(): void
	{
		$path = $this->make('wide.mp4', 'testsrc=duration=1:size=3842x16:rate=1', '-pix_fmt yuv420p');

		try
		{
			$this->video()->validateStoredFile($path);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('width_exceeded', $e->getTranslationKey());
			static::assertSame(['value' => 3842, 'max' => 3840], $e->getReplacements());
		}
	}

	public function testAVideoOverTheMaximumHeightIsRefused(): void
	{
		$path = $this->make('high.mp4', 'testsrc=duration=1:size=16x3842:rate=1', '-pix_fmt yuv420p');

		try
		{
			$this->video()->validateStoredFile($path);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('height_exceeded', $e->getTranslationKey());
			static::assertSame(['value' => 3842, 'max' => 3840], $e->getReplacements());
		}
	}

	public function testTheThumbnailOfAVideoIsAJpegWhichFitsIntoThePreview(): void
	{
		$handler = $this->video();
		$path    = $this->make('clip.mp4', 'testsrc=duration=3:size=640x480:rate=10', '-pix_fmt yuv420p');
		$handler->validateStoredFile($path);

		$handler->createThumbnail($path);

		static::assertSame(['clip.jpg'], $this->workspace->listing('thumbs'));
		$size = getimagesize($this->workspace->dir . '/var/storage/mediapool/thumbs/clip.jpg');
		static::assertNotFalse($size);
		static::assertSame([267, 200], [$size[0], $size[1]]);
		static::assertSame('jpg', $handler->getThumbExtension());
	}

	/**
	 * @return array<string,array{bool}>
	 */
	public static function kinds(): array
	{
		return ['audio' => [true], 'video' => [false]];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('kinds')]
	public function testAFileWhichIsNoMediaCanNotBeProbed(bool $audio): void
	{
		$path = $this->workspace->store('x.bin', 'this is no media file');

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Probing media file failed');

		($audio ? $this->audio() : $this->video())->validateStoredFile($path);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('kinds')]
	public function testAMissingFileIsAnError(bool $audio): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('not exists');

		($audio ? $this->audio() : $this->video())->validateStoredFile($this->workspace->original('nothing.mp4'));
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('kinds')]
	public function testAFileOverTheMaximumSizeIsRefusedWithoutProbingIt(bool $audio): void
	{
		$max  = $audio ? 100 * 1024 * 1024 : 1024 * 1024 * 1024;
		$path = $this->workspace->sparse('big.bin', $max + 1);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('filesize_exceeded');

		($audio ? $this->audio() : $this->video())->validateStoredFile($path);
	}
}
