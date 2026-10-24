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

namespace Tests\Unit\Framework\Media;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Media\Ffmpeg;
use App\Framework\Media\MediaProperties;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Framework\Config\RecordingConfigLoader;

/**
 * Ffmpeg with real MediaProperties, Config and a real directory. Only the shell is replaced: the test
 * shows the command lines which would be started and what Ffmpeg makes of the answer of ffprobe.
 */
class FfmpegTest extends TestCase
{
	private const string PROBE_VIDEO = '{"format": {"filename": "x", "size": "12345", "format_name": "mov,mp4", "duration": "60.5", "start_time": "0.000000"}, "streams": [{"codec_type": "video", "codec_name": "h264", "width": 1920, "height": 1080, "display_aspect_ratio": "16:9"}, {"codec_type": "audio", "codec_name": "aac"}]}';
	private const string PROBE_AUDIO = '{"format": {"filename": "x", "size": "999", "format_name": "mp3", "duration": "10", "start_time": "0"}, "streams": [{"codec_type": "audio", "codec_name": "mp3"}]}';

	private string $systemDir;
	private RecordingShellExecutor $shell;
	private Ffmpeg $ffmpeg;

	protected function setUp(): void
	{
		parent::setUp();

		$this->systemDir = sys_get_temp_dir() . '/ffmpeg_test_' . uniqid();
		mkdir($this->systemDir . '/media/thumbs', 0777, true);
		touch($this->systemDir . '/media/video.mp4');
		touch($this->systemDir . "/media/my video's.mp4");

		$loader = new RecordingConfigLoader(['video' => [
			'path_to_ffmpeg_bin'        => '/usr/bin/ffmpeg',
			'path_to_ffmpeg_probe'      => '/usr/bin/ffprobe',
			'audio_quality_ffmpeg_param' => '4',
		]]);

		$this->shell  = new RecordingShellExecutor();
		$this->ffmpeg = $this->buildFfmpeg($loader);
	}

	protected function tearDown(): void
	{
		array_map('unlink', glob($this->systemDir . '/media/thumbs/*') ?: []);
		array_map('unlink', glob($this->systemDir . '/media/*.mp4') ?: []);
		rmdir($this->systemDir . '/media/thumbs');
		rmdir($this->systemDir . '/media');
		rmdir($this->systemDir);
		parent::tearDown();
	}

	private function buildFfmpeg(RecordingConfigLoader $loader): Ffmpeg
	{
		return new Ffmpeg(
			new Config($loader, ['systemDir' => $this->systemDir]),
			new Filesystem(new LocalFilesystemAdapter($this->systemDir)),
			new MediaProperties(),
			$this->shell
		);
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testInitReadsThePropertiesOfTheProbe(): void
	{
		$this->shell->probeOutput = self::PROBE_VIDEO;

		$this->ffmpeg->init('/media/video.mp4');

		static::assertSame(
			[
				'width'        => 1920,
				'height'       => 1080,
				'video_codec'  => 'h264',
				'audio_codec'  => 'aac',
				'aspect_ratio' => '16:9',
				'start_time'   => '0.000000',
				'duration'     => 60.5,
				'filename'     => 'x',
				'filesize'     => 12345,
				'container'    => 'mov,mp4',
			],
			$this->ffmpeg->getMediaProperties()
		);
		static::assertSame(60.5, $this->ffmpeg->getDuration());
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testInitProbesTheAbsolutePathWithTheConfiguredBinary(): void
	{
		$this->shell->probeOutput = self::PROBE_VIDEO;

		$this->ffmpeg->init('/media/video.mp4');

		static::assertSame(
			["/usr/bin/ffprobe -v quiet -print_format json -show_format -show_streams '" . $this->systemDir . "/media/video.mp4'"],
			$this->shell->commands
		);
	}

	/**
	 * A file name with a space or a quote must stay one argument for the shell.
	 *
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testInitQuotesThePathForTheShell(): void
	{
		$this->shell->probeOutput = self::PROBE_VIDEO;

		$this->ffmpeg->init("/media/my video's.mp4");

		static::assertSame(
			["/usr/bin/ffprobe -v quiet -print_format json -show_format -show_streams '" . $this->systemDir . "/media/my video'\\''s.mp4'"],
			$this->shell->commands
		);
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheMetadataOfTheUserGivesTheDurationWhenTheProbeHasNone(): void
	{
		$this->shell->probeOutput = '{"format": {"filename": "x", "size": "1", "format_name": "png_pipe", "start_time": "0"}, "streams": []}';

		$this->ffmpeg->setMetadata(['duration' => '12'])->init('/media/video.mp4');

		static::assertSame(['duration' => '12'], $this->ffmpeg->getMetadata());
		static::assertSame(12.0, $this->ffmpeg->getDuration());
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testInitForgetsTheFileBefore(): void
	{
		$this->shell->probeOutput = self::PROBE_VIDEO;
		$this->ffmpeg->init('/media/video.mp4');
		$this->shell->probeOutput = self::PROBE_AUDIO;

		$this->ffmpeg->init('/media/video.mp4');

		$properties = $this->ffmpeg->getMediaProperties();
		static::assertSame('', $properties['video_codec']);
		static::assertSame(0, $properties['width']);
		static::assertSame('mp3', $properties['audio_codec']);
		static::assertSame(10.0, $properties['duration']);
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testInitWithNonExistentFileThrowsExceptionAndStartsNothing(): void
	{
		try
		{
			$this->ffmpeg->init('/media/missing.mp4');
		}
		catch (FrameworkException $e)
		{
			static::assertSame('File does not exist: /media/missing.mp4', $e->getMessage());
			static::assertSame([], $this->shell->commands);

			return;
		}

		static::fail('Expected a FrameworkException.');
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testInitThrowsWhenTheProbeAnswersNothingUsable(): void
	{
		foreach (['', 'no json', '[]', '{"streams": []}'] as $answer)
		{
			$this->shell->probeOutput = $answer;

			try
			{
				$this->ffmpeg->init('/media/video.mp4');
			}
			catch (FrameworkException $e)
			{
				static::assertStringStartsWith('Probing media file failed. Unsupported file type for file ' . $this->systemDir . '/media/video.mp4. Using command: /usr/bin/ffprobe', $e->getMessage());
				continue;
			}

			static::fail("Expected a FrameworkException for the answer '$answer'.");
		}
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateVideoThumbnailStartsFfmpegAndReturnsThePath(): void
	{
		$this->shell->probeOutput = self::PROBE_VIDEO;
		$this->ffmpeg->init('/media/video.mp4');
		$this->shell->commands    = [];
		$this->shell->createsFile = $this->systemDir . '/media/thumbs/vid_1.jpg';

		$path = $this->ffmpeg->createVideoThumbnail('/media/thumbs');

		static::assertSame('/media/thumbs/vid_1.jpg', $path);
		static::assertSame(
			["/usr/bin/ffmpeg -i 'x'  -ss 00:00:02 -an -r 1 -vframes 1 -y '" . $this->systemDir . "/media/thumbs/vid_%d.jpg' 2>&1"],
			$this->shell->commands
		);
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateVideoThumbnailFailsWhenFfmpegCreatedNoFile(): void
	{
		$this->shell->probeOutput = self::PROBE_VIDEO;
		$this->ffmpeg->init('/media/video.mp4');

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Thumbnail /media/thumbs/vid_1.jpg not found');

		$this->ffmpeg->createVideoThumbnail('/media/thumbs');
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCreateVideoThumbnailOfAFileWithoutVideoStreamThrows(): void
	{
		$this->shell->probeOutput = self::PROBE_AUDIO;
		$this->ffmpeg->init('/media/video.mp4');
		$this->shell->commands = [];

		try
		{
			$this->ffmpeg->createVideoThumbnail('/media/thumbs');
		}
		catch (FrameworkException $e)
		{
			static::assertSame('Cannot create video thumbnail for x. File has no readable video stream', $e->getMessage());
			static::assertSame([], $this->shell->commands);

			return;
		}

		static::fail('Expected a FrameworkException.');
	}

	#[Group('units')]
	public function testWithoutInitThereIsNoVideoToMakeAThumbnailOf(): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('File has no readable video stream');

		$this->ffmpeg->createVideoThumbnail('/media/thumbs');
	}

	#[Group('units')]
	public function testWithoutInitTheDurationIsZero(): void
	{
		static::assertSame(0.0, $this->ffmpeg->getDuration());
	}

	/**
	 * The configured binary may carry options (e.g. "nice /usr/bin/ffmpeg"), so only the files are quoted.
	 *
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheConfiguredBinaryIsNotQuoted(): void
	{
		$ffmpeg = $this->buildFfmpeg(new RecordingConfigLoader(['video' => [
			'path_to_ffmpeg_bin'         => 'nice /usr/bin/ffmpeg',
			'path_to_ffmpeg_probe'       => 'nice /usr/bin/ffprobe',
			'audio_quality_ffmpeg_param' => '4',
		]]));
		$this->shell->probeOutput = self::PROBE_VIDEO;
		$ffmpeg->init('/media/video.mp4');

		static::assertStringStartsWith('nice /usr/bin/ffprobe -v quiet', $this->shell->commands[0]);
	}
}
