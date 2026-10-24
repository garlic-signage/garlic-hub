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

namespace Tests\Integration\Framework\Media;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Media\Ffmpeg;
use App\Framework\Media\MediaProperties;
use App\Framework\System\ShellExecutor;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Framework\Config\RecordingConfigLoader;

/**
 * Ffmpeg with the real ffmpeg and ffprobe on a video which is made for the test. Skipped where they are not installed.
 */
#[Group('io')]
class FfmpegTest extends TestCase
{
	private string $systemDir;
	private Ffmpeg $ffmpeg;

	protected function setUp(): void
	{
		parent::setUp();

		$ffmpeg  = $this->findBinary('ffmpeg');
		$ffprobe = $this->findBinary('ffprobe');
		if ($ffmpeg === '' || $ffprobe === '')
			self::markTestSkipped('ffmpeg and ffprobe are needed.');

		$this->systemDir = sys_get_temp_dir() . '/ffmpeg_io_' . uniqid();
		mkdir($this->systemDir . '/thumbs', 0777, true);
		// a path with a space and a quote proves that the file names are quoted for the shell
		mkdir($this->systemDir . "/my media's");

		$config = new Config(
			new RecordingConfigLoader(['video' => [
				'path_to_ffmpeg_bin'         => $ffmpeg,
				'path_to_ffmpeg_probe'       => $ffprobe,
				'audio_quality_ffmpeg_param' => '4',
			]]),
			['systemDir' => $this->systemDir]
		);

		$this->ffmpeg = new Ffmpeg(
			$config,
			new Filesystem(new LocalFilesystemAdapter($this->systemDir)),
			new MediaProperties(),
			new ShellExecutor()
		);

		$video = $this->systemDir . "/my media's/test video.mp4";
		exec(escapeshellarg($ffmpeg) . ' -v quiet -f lavfi -i testsrc=duration=3:size=320x240:rate=5 -pix_fmt yuv420p ' . escapeshellarg($video) . ' 2>&1', $output, $code);
		if ($code !== 0 || !is_file($video))
			self::markTestSkipped('ffmpeg could not make the test video.');
	}

	protected function tearDown(): void
	{
		if (isset($this->systemDir))
		{
			array_map('unlink', glob($this->systemDir . '/thumbs/*') ?: []);
			array_map('unlink', glob($this->systemDir . "/my media's/*") ?: []);
			rmdir($this->systemDir . '/thumbs');
			rmdir($this->systemDir . "/my media's");
			rmdir($this->systemDir);
		}
		parent::tearDown();
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	public function testTheProbeReadsTheRealVideo(): void
	{
		$this->ffmpeg->init("/my media's/test video.mp4");

		$properties = $this->ffmpeg->getMediaProperties();
		static::assertSame(320, $properties['width']);
		static::assertSame(240, $properties['height']);
		static::assertNotSame('', $properties['video_codec']);
		static::assertSame('', $properties['audio_codec']);
		static::assertEqualsWithDelta(3.0, $this->ffmpeg->getDuration(), 0.5);
		static::assertGreaterThan(0, $properties['filesize']);
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	public function testAThumbnailIsMadeOfTheRealVideo(): void
	{
		$this->ffmpeg->init("/my media's/test video.mp4");

		$path = $this->ffmpeg->createVideoThumbnail('/thumbs');

		static::assertSame('/thumbs/vid_1.jpg', $path);
		static::assertSame("\xFF\xD8\xFF", substr((string) file_get_contents($this->systemDir . $path), 0, 3), 'The thumbnail is no JPEG.');
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 */
	public function testAFileWhichIsNoMediaThrows(): void
	{
		file_put_contents($this->systemDir . "/my media's/no media.mp4", 'this is not a video');

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Probing media file failed');

		$this->ffmpeg->init("/my media's/no media.mp4");
	}

	private function findBinary(string $name): string
	{
		$path = trim((string) shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null'));

		return $path;
	}
}
