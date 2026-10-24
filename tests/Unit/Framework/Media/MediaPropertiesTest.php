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

use App\Framework\Media\MediaProperties;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class MediaPropertiesTest extends TestCase
{
	private MediaProperties $mediaProperties;

	protected function setUp(): void
	{
		parent::setUp();
		$this->mediaProperties = new MediaProperties();
	}

	#[Group('units')]
	public function testMediaProperties(): void
	{
		$json = '{"format": {"filename": "test.mp4", "size": 12345, "format_name": "mp4", "duration": 60, "start_time": 0}, "streams": [{"codec_type": "video", "codec_name": "h264", "width": 1920, "height": 1080}]}';

		$stdClass = json_decode($json);
		$this->mediaProperties->fromStdClass($stdClass);

		static::assertSame('test.mp4', $this->mediaProperties->getFilename());
		static::assertSame(60.0, $this->mediaProperties->getDuration());
	}

	#[Group('units')]
	public function testMediaPropertiesWithUserValues(): void
	{
		$json = '{"format": {"filename": "test.mp4", "size": 12345, "format_name": "mp4", "start_time": 0}, "streams": [{"codec_type": "video", "codec_name": "h264", "width": 1920, "height": 1080}]}';

		$stdClass = json_decode($json);
		$this->mediaProperties->fromStdClass($stdClass, ['duration' => '160']);

		static::assertSame('test.mp4', $this->mediaProperties->getFilename());
		static::assertSame(160.0, $this->mediaProperties->getDuration());
	}

	#[Group('units')]
	public function testFromArrayWithVideoAndAudioStream(): void
	{
		$json = '{"format": {"filename": "media.mp4", "size": 10000, "format_name": "mp4", "duration": 120, "start_time": 0}, "streams": [{"codec_type": "video", "codec_name": "vp9", "width": 1280, "height": 720}, {"codec_type": "audio", "codec_name": "aac"}]}';

		$stdClass = json_decode($json);
		$this->mediaProperties->fromStdClass($stdClass);

		static::assertSame(1280, $this->mediaProperties->getWidth());
		static::assertSame(720, $this->mediaProperties->getHeight());
		static::assertSame('vp9', $this->mediaProperties->getVideoCodec());
		static::assertSame('aac', $this->mediaProperties->getAudioCodec());
		static::assertSame('media.mp4', $this->mediaProperties->getFilename());
		static::assertTrue($this->mediaProperties->hasVideoStream());

	}

	#[Group('units')]
	public function testToArray(): void
	{
		// Test default values in toArray output
		$expected = [
			'width' => 0,
			'height' => 0,
			'video_codec' => '',
			'audio_codec' => '',
			'aspect_ratio' => '',
			'start_time' => '',
			'duration' => 0.0,
			'filename' => '',
			'filesize' => 0,
			'container' => '',
		];
		static::assertFalse($this->mediaProperties->hasVideoStream());

		static::assertSame($expected, $this->mediaProperties->toArray());
	}

	#[Group('units')]
	public function testToArrayWithPopulatedValues(): void
	{
		// Set the object values using fromStdClass
		$json = '{"format": {"filename": "video.mp4", "size": 20000, "format_name": "mkv", "duration": 150, "start_time": 0.5}, "streams": [{"codec_type": "video", "codec_name": "h265", "width": 1280, "height": 720}, {"codec_type": "audio", "codec_name": "aac"}]}';
		$stdClass = json_decode($json);
		$this->mediaProperties->fromStdClass($stdClass);

		// Test populated values in toArray output
		$expected = [
			'width' => 1280,
			'height' => 720,
			'video_codec' => 'h265',
			'audio_codec' => 'aac',
			'aspect_ratio' => '1:1', // Default aspect ratio in the absence of data
			'start_time' => '0.5',
			'duration' => 150.0,
			'filename' => 'video.mp4',
			'filesize' => 20000,
			'container' => 'mkv',
		];

		static::assertSame($expected, $this->mediaProperties->toArray());
	}

	#[Group('units')]
	public function testReset(): void
	{
		// Populate the MediaProperties object with sample values
		$json = '{"format": {"filename": "sample.mp4", "size": 50000, "format_name": "avi", "duration": 300.5, "start_time": 0.1}, "streams": [{"codec_type": "video", "codec_name": "mpeg4", "width": 1024, "height": 768}, {"codec_type": "audio", "codec_name": "mp3"}]}';
		$stdClass = json_decode($json);
		$this->mediaProperties->fromStdClass($stdClass);

		// Reset the object
		$this->mediaProperties->reset();

		// Verify that all properties have been reset to defaults
		static::assertSame(0, $this->mediaProperties->getWidth());
		static::assertSame(0, $this->mediaProperties->getHeight());
		static::assertSame('', $this->mediaProperties->getVideoCodec());
		static::assertSame('', $this->mediaProperties->getAudioCodec());
		static::assertSame('', $this->mediaProperties->getAspectRatio());
		static::assertSame('', $this->mediaProperties->getStartTime());
		static::assertSame(0.0, $this->mediaProperties->getDuration());
		static::assertSame('', $this->mediaProperties->getFilename());
		static::assertSame(0, $this->mediaProperties->getFilesize());
		static::assertSame('', $this->mediaProperties->getContainer());
	}

	#[Group('units')]
	public function testStreamsWithoutACodecNameAreSkipped(): void
	{
		$json = '{"format": {"filename": "sample.mp4", "size": 50000, "format_name": "avi", "duration": 300.5, "start_time": 0.1}, "streams": [{"codec_type": "video", "width": 1024, "height": 768}, {"codec_type": "audio"}]}';

		$this->mediaProperties->fromStdClass(json_decode($json));

		static::assertFalse($this->mediaProperties->hasVideoStream());
		static::assertSame(0, $this->mediaProperties->getWidth());
		static::assertSame(0, $this->mediaProperties->getHeight());
		static::assertSame('', $this->mediaProperties->getVideoCodec());
		static::assertSame('', $this->mediaProperties->getAudioCodec());
		// the format is read in any case
		static::assertSame('sample.mp4', $this->mediaProperties->getFilename());
		static::assertSame(300.5, $this->mediaProperties->getDuration());
	}

	#[Group('units')]
	public function testStreamsWithoutACodecTypeAreSkipped(): void
	{
		$json = '{"format": {"filename": "f.mp4", "size": 1, "format_name": "mp4", "start_time": 0}, "streams": [{"codec_name": "h264", "width": 1024}]}';

		$this->mediaProperties->fromStdClass(json_decode($json));

		static::assertFalse($this->mediaProperties->hasVideoStream());
	}

	#[Group('units')]
	public function testTheAspectRatioOfTheVideoStreamIsTaken(): void
	{
		$json = '{"format": {"filename": "f.mp4", "size": 1, "format_name": "mp4", "start_time": 0}, "streams": [{"codec_type": "video", "codec_name": "h264", "width": 1920, "height": 1080, "display_aspect_ratio": "16:9"}]}';

		$this->mediaProperties->fromStdClass(json_decode($json));

		static::assertSame('16:9', $this->mediaProperties->getAspectRatio());
	}

	#[Group('units')]
	public function testTheDurationOfTheFormatWinsOverTheUserValue(): void
	{
		$json = '{"format": {"filename": "f.mp4", "size": 1, "format_name": "mp4", "duration": "12.5", "start_time": 0}, "streams": []}';

		$this->mediaProperties->fromStdClass(json_decode($json), ['duration' => '99']);

		static::assertSame(12.5, $this->mediaProperties->getDuration());
	}

	#[Group('units')]
	public function testWithoutAnyDurationItIsZero(): void
	{
		$json = '{"format": {"filename": "f.png", "size": 1, "format_name": "png_pipe", "start_time": 0}, "streams": []}';

		$this->mediaProperties->fromStdClass(json_decode($json));

		static::assertSame(0.0, $this->mediaProperties->getDuration());
	}

	#[Group('units')]
	public function testAWidthWhichIsNoNumberIsZero(): void
	{
		$json = '{"format": {"filename": "f.mp4", "size": 1, "format_name": "mp4", "start_time": 0}, "streams": [{"codec_type": "video", "codec_name": "h264", "width": "wide", "height": "high"}]}';

		$this->mediaProperties->fromStdClass(json_decode($json));

		static::assertSame(0, $this->mediaProperties->getWidth());
		static::assertSame(0, $this->mediaProperties->getHeight());
		static::assertTrue($this->mediaProperties->hasVideoStream());
	}

	#[Group('units')]
	public function testTheLastAudioStreamWins(): void
	{
		$json = '{"format": {"filename": "f.mp4", "size": 1, "format_name": "mp4", "start_time": 0}, "streams": [{"codec_type": "audio", "codec_name": "aac"}, {"codec_type": "audio", "codec_name": "mp3"}]}';

		$this->mediaProperties->fromStdClass(json_decode($json));

		static::assertSame('mp3', $this->mediaProperties->getAudioCodec());
		static::assertFalse($this->mediaProperties->hasVideoStream());
	}
}
