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

use App\Framework\Core\Config\Config;
use App\Framework\Core\ShellExecutor;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Media\Ffmpeg;
use App\Framework\Media\MediaProperties;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class FfmpegTest extends TestCase
{
	private Config&MockObject $configMock;
	private Filesystem&Stub $filesystemStub;
	private ShellExecutor&Stub $shellExecutorStub;
	private MediaProperties&Stub $mediaPropertiesStub;
	private Ffmpeg $ffmpeg;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configMock          = $this->createMock(Config::class);
		$this->filesystemStub      = $this->createStub(Filesystem::class);
		$this->mediaPropertiesStub = $this->createStub(MediaProperties::class);
		$this->shellExecutorStub   = $this->createStub(ShellExecutor::class);

		$this->ffmpeg = new Ffmpeg($this->configMock, $this->filesystemStub, $this->mediaPropertiesStub, $this->shellExecutorStub);
	}

	#[Group('units')]
	public function testSetMetaData(): void
	{
		$this->configMock->expects($this->never())->method($this->anything());
		$metadata = ['test' => 'test'];
		$this->ffmpeg->setMetadata($metadata);

		static::assertEquals($metadata, $this->ffmpeg->getMetadata());
	}

	/**
	 * @throws FilesystemException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testInitWithValidFilePath(): void
	{
		$this->filesystemStub->method('fileExists')->willReturn(true);
		$this->configMock->expects($this->exactly(3))->method('getConfigValue')->willReturn('');
		$this->configMock->expects($this->once())->method('getPaths');
		$this->shellExecutorStub->method('executeSimple')->willReturn('{"format": {"filename": "test.mp4", "size": 12345, "format_name": "mp4", "duration": 60, "start_time": 0}, "streams": [{"codec_type": "video", "codec_name": "h264", "width": 1920, "height": 1080}]}');

		$this->ffmpeg->init('/path/to/video.mp4');
		$this->mediaPropertiesStub->method('toArray')->willReturn(['filename' => 'test.mp4']);
		static::assertEquals('test.mp4', $this->ffmpeg->getMediaProperties()['filename']);
	}

	#[Group('units')]
	public function testGetDuration(): void
	{
		$this->configMock->expects($this->never())->method($this->anything());
		static::assertSame(0.0, $this->ffmpeg->getDuration());
		$this->mediaPropertiesStub->method('getDuration')->willReturn(10.0);
		static::assertSame(10.0, $this->ffmpeg->getDuration());
	}

	/**
	 * @throws FilesystemException
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testInitWithNonExistentFileThrowsException(): void
	{
		$this->filesystemStub->method('fileExists')->willReturn(false);
		$this->configMock->expects($this->exactly(3))->method('getConfigValue')->willReturn('');

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('File does not exist: /path/to/video.mp4');

		$this->ffmpeg->init('/path/to/video.mp4');
	}

	/**
	 * @throws FilesystemException
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testInitProbeFileNull(): void
	{
		$this->filesystemStub->method('fileExists')->willReturn(true);
		$this->configMock->expects($this->exactly(3))->method('getConfigValue')->willReturn('');
		$this->configMock->expects($this->once())->method('getPaths');
		$this->shellExecutorStub->method('executeSimple')->willReturn('');

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Probing media file failed. Unsupported file type for file /path/to/video.mp4. Using command: ');

		$this->ffmpeg->init('/path/to/video.mp4');
	}

	/**
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateVideoThumbnailWithValidVideo(): void
	{
		$this->configMock->expects($this->never())->method('getConfigValue');
		$this->configMock->expects($this->once())->method('getPaths')->with('systemDir')->willReturn('/var/www');
		$this->filesystemStub->method('fileExists')->willReturn(true);
		$this->mediaPropertiesStub->method('hasVideoStream')->willReturn(true);
		$this->shellExecutorStub->method('executeSimple')
			->willReturn('{"streams": [{"codec_type": "video", "codec_name": "h264", "width": 1920, "height": 1080}]}');

		$vidcapPath = $this->ffmpeg->createVideoThumbnail('/path/to/destination');

		static::assertEquals('/path/to/destination/vid_1.jpg', $vidcapPath);
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCreateVideoThumbnailFails(): void
	{
		$this->configMock->expects($this->never())->method('getConfigValue');
		$this->configMock->expects($this->once())->method('getPaths')->with('systemDir')->willReturn('/var/www');
		$this->mediaPropertiesStub->method('hasVideoStream')->willReturn(true);
		$this->filesystemStub->method('fileExists')
			->willReturn(false);

		$this->shellExecutorStub->method('executeSimple')
			->willReturn('{"streams": [
			{"codec_type": "video",
			 "codec_name": "h264",
			  "width": 1920, "height": 1080}
			]}');

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Thumbnail /path/to/destination/vid_1.jpg not found');
		$vidcapPath = $this->ffmpeg->createVideoThumbnail('/path/to/destination');

		static::assertEquals('/path/to/destination/vid_1.jpg', $vidcapPath);
	}


	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCreateVideoThumbnailWithNoVideoStreamThrowsException(): void
	{
		$this->configMock->expects($this->never())->method($this->anything());
		$this->mediaPropertiesStub->method('hasVideoStream')->willReturn(false);

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Cannot create video thumbnail for . File has no readable video stream');

		$this->ffmpeg->createVideoThumbnail('/path/to/destination');
	}
}
