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

namespace App\Framework\Media;

use stdClass;

/**
 * Represents media properties, including video and audio stream details, file metadata,
 * and container information. This class provides methods to retrieve, reset, and populate
 * media-related data.
 */
class MediaProperties
{
	private int $width = 0;
	private int $height = 0;
	private string $videoCodec = '';
	private string $audioCodec = '';
	private string $aspectRatio = '';
	private string $startTime = '';
	private float $duration = 0.0;
	private string $filename = '';
	private int $filesize = 0;
	private string $container = '';

	public function __construct()
	{
	}

	public function getWidth(): int
	{
		return $this->width;
	}

	public function getHeight(): int
	{
		return $this->height;
	}

	public function getVideoCodec(): string
	{
		return $this->videoCodec;
	}

	public function getAudioCodec(): string
	{
		return $this->audioCodec;
	}

	public function getAspectRatio(): string
	{
		return $this->aspectRatio;
	}

	public function getStartTime(): string
	{
		return $this->startTime;
	}

	public function getDuration(): float
	{
		return $this->duration;
	}

	public function getFilename(): string
	{
		return $this->filename;
	}

	public function getFilesize(): int
	{
		return $this->filesize;
	}

	public function getContainer(): string
	{
		return $this->container;
	}

	public function hasVideoStream(): bool
	{
		return $this->videoCodec !== '';
	}

	public function reset(): void
	{
		$this->width = 0;
		$this->height = 0;
		$this->videoCodec = '';
		$this->audioCodec = '';
		$this->aspectRatio = '';
		$this->startTime = '';
		$this->duration = 0.0;
		$this->filename = '';
		$this->filesize = 0;
		$this->container = '';
	}
	/**
	 * @return array{width: int, height: int,
	 * video_codec: string, audio_codec: string,
	 * aspect_ratio: string,
	 * start_time: string,
	 * duration: float,
	 * filename: string,
	 * filesize: int,
	 * container: string
	 * }
	 */
	public function toArray(): array
	{
		return [
			'width' => $this->getWidth(),
			'height' => $this->getHeight(),
			'video_codec' => $this->getVideoCodec(),
			'audio_codec' => $this->getAudioCodec(),
			'aspect_ratio' => $this->getAspectRatio(),
			'start_time' => $this->getStartTime(),
			'duration' => $this->getDuration(),
			'filename' => $this->getFilename(),
			'filesize' => $this->getFilesize(),
			'container' => $this->getContainer(),
		];
	}

	/**
	 * @param array<string,mixed> $userMetadata
	 */
	public function fromStdClass(stdClass $metadata, array $userMetadata = []): void
	{
		foreach ($metadata->streams as $stream)
		{
			if (!isset($stream->codec_type) || !is_string($stream->codec_type) || !isset($stream->codec_name) || !is_string($stream->codec_name))
				continue;

			if ($stream->codec_type === 'video')
			{
				$this->videoCodec  = $stream->codec_name;
				$this->aspectRatio = isset($stream->display_aspect_ratio) ? (string)$stream->display_aspect_ratio : '1:1';
				$this->width       = isset($stream->width) && is_numeric($stream->width) ? (int)$stream->width : 0;
				$this->height      = isset($stream->height) && is_numeric($stream->height) ? (int)$stream->height : 0;
			}
			elseif ($stream->codec_type === 'audio')
			{
				$this->audioCodec = $stream->codec_name;
			}
		}

		// Handle duration from various sources
		$duration = 0;
		if (isset($metadata->format->duration))
			$duration = (float) $metadata->format->duration;
		 elseif (array_key_exists('duration', $userMetadata))
			$duration = (float) $userMetadata['duration'];

		$this->startTime = (string) $metadata->format->start_time;
		$this->duration  = $duration;
		$this->filename  = (string) $metadata->format->filename;
		$this->filesize  = (int) $metadata->format->size;
		$this->container = (string) $metadata->format->format_name;
	}

}