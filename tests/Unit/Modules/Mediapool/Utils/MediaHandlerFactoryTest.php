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
use App\Framework\Config\IniConfigLoader;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Media\Ffmpeg;
use App\Framework\Media\MediaProperties;
use App\Framework\System\ShellExecutor;
use App\Modules\Mediapool\Utils\AbstractMediaHandler;
use App\Modules\Mediapool\Utils\Audio;
use App\Modules\Mediapool\Utils\Image;
use App\Modules\Mediapool\Utils\ImagickFactory;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;
use App\Modules\Mediapool\Utils\Miscellaneous;
use App\Modules\Mediapool\Utils\Pdf;
use App\Modules\Mediapool\Utils\Video;
use App\Modules\Mediapool\Utils\Widget;
use App\Modules\Mediapool\Utils\ZipFilesystemFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The factory is the one place which decides what is done with a type of media. The handlers are the real ones
 * with the real configuration of the application, nothing is touched on the disk when they are created.
 */
class MediaHandlerFactoryTest extends TestCase
{
	private MediaHandlerFactory $factory;

	protected function setUp(): void
	{
		parent::setUp();
		$config     = new Config(new IniConfigLoader(__DIR__ . '/../../../../../config/settings'), ['systemDir' => '/tmp/garlic-hub-not-used'], []);
		$filesystem = new Filesystem(new LocalFilesystemAdapter(sys_get_temp_dir()));

		$this->factory = new MediaHandlerFactory(
			$config,
			$filesystem,
			new ZipFilesystemFactory(),
			new ImagickFactory(),
			new Ffmpeg($config, $filesystem, new MediaProperties(), new ShellExecutor())
		);
	}

	/**
	 * @return array<string,array{string,class-string<AbstractMediaHandler>}>
	 */
	public static function handlers(): array
	{
		return [
			'png'                => ['image/png', Image::class],
			'jpeg'               => ['image/jpeg', Image::class],
			'svg'                => ['image/svg+xml', Image::class],
			'gif'                => ['image/gif', Image::class],
			'webp'               => ['image/webp', Image::class],
			'mp3'                => ['audio/mpeg', Audio::class],
			'wav'                => ['audio/wav', Audio::class],
			'mp4'                => ['video/mp4', Video::class],
			'webm'               => ['video/webm', Video::class],
			'pdf'                => ['application/pdf', Pdf::class],
			'widget'             => ['application/widget', Widget::class],
			'octet-stream'       => ['application/octet-stream', Widget::class],
			'zip'                => ['application/zip', Miscellaneous::class],
			'json'               => ['application/json', Miscellaneous::class],
			'csv'                => ['text/csv', Miscellaneous::class],
			'xml'                => ['application/xml', Miscellaneous::class],
			'text xml'           => ['text/xml', Miscellaneous::class],
			'rss'                => ['application/rss+xml', Miscellaneous::class],
			'atom'               => ['application/atom+xml', Miscellaneous::class],
			'apk'                => ['application/vnd.android.package-archive', Miscellaneous::class],
			'smil'               => ['application/smil', Miscellaneous::class],
		];
	}

	/**
	 * @param class-string<AbstractMediaHandler> $class
	 */
	#[Group('units')]
	#[DataProvider('handlers')]
	public function testEveryTypeGetsItsHandler(string $mimeType, string $class): void
	{
		static::assertSame($class, $this->factory->createMediaHandler($mimeType)::class);
		static::assertSame($class, $this->factory->createTemplatesHandler($mimeType)::class);
		static::assertSame($class, $this->factory->createPlaylistsHandler($mimeType)::class);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function unsupportedTypes(): array
	{
		return [
			'php'        => ['application/x-httpd-php'],
			'html'       => ['text/html'],
			'javascript' => ['application/javascript'],
			'plain text' => ['text/plain'],
			'executable' => ['application/x-msdownload'],
			'empty'      => [''],
			'upper case' => ['IMAGE/PNG'],
			'prefix'     => ['imagex/png'],
			'no slash'   => ['image'],
		];
	}

	#[Group('units')]
	#[DataProvider('unsupportedTypes')]
	public function testAnUnsupportedTypeIsRefusedForEveryPlace(string $mimeType): void
	{
		foreach (['createMediaHandler', 'createTemplatesHandler', 'createPlaylistsHandler'] as $method)
		{
			try
			{
				$this->factory->$method($mimeType);
				static::fail($method . ' has to refuse ' . $mimeType);
			}
			catch (TranslatableModuleException $e)
			{
				static::assertSame('unsupported_mimetype', $e->getTranslationKey());
				static::assertSame(['mimetype' => $mimeType], $e->getReplacements());
			}
		}
	}

	/**
	 * @return array<string,array{string,string}> the method of the factory and the storage it uses
	 */
	public static function places(): array
	{
		return [
			'mediapool' => ['createMediaHandler', 'var/storage/mediapool/originals'],
			'templates' => ['createTemplatesHandler', 'var/storage/templates/originals'],
			'playlists' => ['createPlaylistsHandler', 'var/storage/playlists/items/originals'],
		];
	}

	#[Group('units')]
	#[DataProvider('places')]
	public function testTheHandlerWorksInTheStorageOfItsPlace(string $method, string $originals): void
	{
		static::assertSame($originals, $this->factory->$method('image/png')->getOriginalPath());
	}
}
