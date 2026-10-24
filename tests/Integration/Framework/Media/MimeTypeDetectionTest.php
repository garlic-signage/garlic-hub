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

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\DataUrlDecoder;
use App\Framework\Media\FileInfoWrapper;
use App\Framework\Media\MimeTypeDetector;
use App\Framework\Media\MimeTypeExtensionMapper;
use App\Framework\Media\MimeTypeService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Detector, service and data url decoder with the real finfo on real bytes.
 */
#[Group('io')]
class MimeTypeDetectionTest extends TestCase
{
	private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
	private const string GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

	private string $directory;
	private MimeTypeService $service;

	protected function setUp(): void
	{
		parent::setUp();

		$this->directory = sys_get_temp_dir() . '/mime_test_' . uniqid();
		mkdir($this->directory);

		$detector      = new MimeTypeDetector(new FileInfoWrapper());
		$this->service = new MimeTypeService($detector, new MimeTypeExtensionMapper());
	}

	protected function tearDown(): void
	{
		array_map('unlink', glob($this->directory . '/*') ?: []);
		rmdir($this->directory);
		parent::tearDown();
	}

	/**
	 * @return array<string, array{string, string, string}> name of the file, content, expected MIME type
	 */
	public static function filesProvider(): array
	{
		return [
			'png'        => ['image.png', (string) base64_decode(self::PNG), 'image/png'],
			'gif'        => ['image.gif', (string) base64_decode(self::GIF), 'image/gif'],
			'pdf'        => ['doc.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n", 'application/pdf'],
			'text'       => ['note.txt', 'just some text', 'text/plain'],
			'json'       => ['data.json', '{"a": 1}', 'application/json'],
		];
	}

	/**
	 * The content decides, not the name: a file with the wrong extension is detected by what it is.
	 *
	 * @throws ModuleException
	 */
	#[DataProvider('filesProvider')]
	public function testDetectFromFile(string $name, string $content, string $expected): void
	{
		file_put_contents($this->directory . '/' . $name, $content);
		file_put_contents($this->directory . '/disguised.mp4', $content);

		static::assertSame($expected, $this->service->detectFromFile($this->directory . '/' . $name));
		static::assertSame($expected, $this->service->detectFromFile($this->directory . '/disguised.mp4'));
	}

	/**
	 * @throws ModuleException
	 */
	#[DataProvider('filesProvider')]
	public function testDetectFromStringContent(string $name, string $content, string $expected): void
	{
		static::assertSame($expected, $this->service->detectFromStringContent($content));
	}

	/**
	 * @throws ModuleException
	 */
	#[DataProvider('filesProvider')]
	public function testDetectFromStream(string $name, string $content, string $expected): void
	{
		$stream = fopen('php://memory', 'r+');
		static::assertIsResource($stream);
		fwrite($stream, $content);

		static::assertSame($expected, $this->service->detectFromStream($stream));

		fclose($stream);
	}

	/**
	 * @throws ModuleException
	 */
	public function testAWidgetIsRecognisedByItsExtension(): void
	{
		$widget = __DIR__ . '/../../../resources/widgets/widget.wgt';

		static::assertSame('application/widget', $this->service->detectFromFile($widget));
	}

	/**
	 * @throws ModuleException
	 */
	public function testAMissingFileThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->service->detectFromFile($this->directory . '/missing.png');
	}

	/**
	 * Anything but a stream resource is no stream: a string must not end in a TypeError.
	 *
	 * @throws ModuleException
	 */
	#[DataProvider('noStreamProvider')]
	public function testSomethingWhichIsNoStreamThrowsInvalidStream(mixed $notAStream): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid stream.');

		$this->service->detectFromStream($notAStream);
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function noStreamProvider(): array
	{
		return ['string' => ['some not stream'], 'null' => [null], 'integer' => [5], 'array' => [[]]];
	}

	public function testAnEmptyStringContentThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Content is empty.');

		$this->service->detectFromStringContent('');
	}

	public function testTheExtensionFollowsTheMimeType(): void
	{
		static::assertSame('png', $this->service->determineExtensionByMimeType('image/png'));
		static::assertSame('bin', $this->service->determineExtensionByMimeType('chemical/x-unknown'));
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function testADataUrlIsDecodedToTheFile(): void
	{
		$decoder = new DataUrlDecoder($this->service);

		$file = $decoder->decode('data:image/png;base64,' . self::PNG);

		static::assertSame('image/png', $file->mimeType);
		static::assertSame('png', $file->extension);
		static::assertSame(base64_decode(self::PNG), $file->binaryContent);
		static::assertSame(strlen((string) base64_decode(self::PNG)), $file->size);
	}

	/**
	 * The type in the data url is only a claim of the sender, the bytes decide.
	 *
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function testADataUrlWithAWrongTypeIsDetectedByItsContent(): void
	{
		$decoder = new DataUrlDecoder($this->service);

		$file = $decoder->decode('data:image/jpeg;base64,' . self::GIF);

		static::assertSame('image/gif', $file->mimeType);
		static::assertSame('gif', $file->extension);
	}

	/**
	 * @throws ModuleException
	 */
	#[DataProvider('invalidDataUrlProvider')]
	public function testAnInvalidDataUrlThrows(string $dataUrl, string $message): void
	{
		$decoder = new DataUrlDecoder($this->service);

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage($message);

		$decoder->decode($dataUrl);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function invalidDataUrlProvider(): array
	{
		return [
			'no data url'         => ['https://example.com/a.png', 'Data-URL not valid.'],
			'no base64'           => ['data:image/png,abc', 'Data-URL not valid.'],
			'no type'             => ['data:;base64,' . self::PNG, 'Data-URL not valid.'],
			'empty data'          => ['data:image/png;base64,', 'Base64-Data are empty.'],
			'invalid characters'  => ['data:image/png;base64,@@@@', 'Base64-Data are not valid.'],
		];
	}
}
