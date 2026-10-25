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

namespace Tests\Unit\Modules\Mediapool\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Media\MimeTypeService;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Mediapool\Services\UploadService;
use App\Modules\Mediapool\Utils\AbstractMediaHandler;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;
use App\Modules\Mediapool\Utils\SsrfValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\UploadedFile;

/**
 * The checks before a file is touched and the address handling of the external upload. The client is a real
 * Guzzle client with a prepared answer, so the requests can be looked at. The whole upload with real files and
 * handlers is tested in tests/Integration/Modules/Mediapool/Services/UploadTest.
 */
class UploadServiceTest extends TestCase
{
	/** @var array<string,string> */
	private const array DNS = [
		'media.example.com' => '93.184.216.34',
		'cdn.example.com'   => '93.184.216.35',
		'internal.example'  => '10.0.0.5',
		'metadata.example'  => '169.254.169.254',
		'loopback.example'  => '127.0.0.1',
	];

	/** @var list<array{request:RequestInterface,options:array<string,mixed>}> every request which was sent */
	private array $sent = [];
	private bool $uploadable = true;
	/** @var list<string> the types for which a handler was asked */
	private array $askedTypes = [];
	private bool $factoryGivesAHandler = false;

	protected function setUp(): void
	{
		parent::setUp();
		$this->sent       = [];
		$this->uploadable = true;
		$this->askedTypes = [];
		$this->factoryGivesAHandler = false;
	}

	/**
	 * @param list<Response|\Throwable> $answers
	 */
	private function service(array $answers = []): UploadService
	{
		// the stack hands over the request with its options: the tests look at both
		$capture = new MockHandler($answers);
		$stack   = HandlerStack::create(function ($request, array $options) use ($capture)
		{
			$this->sent[] = ['request' => $request, 'options' => $options];

			return $capture($request, $options);
		});

		$nodes = $this->createStub(NodesService::class);
		$nodes->method('isNodeUploadable')->willReturnCallback(fn(): bool => $this->uploadable);

		// the factory notes the type it is asked for and stops the upload there
		$factory = $this->createStub(MediaHandlerFactory::class);
		$factory->method('createMediaHandler')->willReturnCallback(function (string $mimeType): AbstractMediaHandler
		{
			$this->askedTypes[] = $mimeType;
			if ($this->factoryGivesAHandler)
				return $this->createStub(AbstractMediaHandler::class);

			throw new TranslatableModuleException('mediapool', 'handler_asked');
		});

		return new UploadService(
			$factory,
			$nodes,
			new Client(['handler' => $stack]),
			$this->createStub(FilesRepository::class),
			$this->createStub(MimeTypeService::class),
			new SsrfValidator(static fn(string $host): string => self::DNS[$host] ?? $host)
		);
	}

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	#[Group('units')]
	public function testTheApiAnswerIsDecoded(): void
	{
		$service = $this->service([new Response(200, [], '{"items":[{"id":1}],"total":1}')]);

		static::assertSame(['items' => [['id' => 1]], 'total' => 1], $service->requestApi('https://api.example.com/search?q=cat'));
		static::assertSame('GET', $this->sent[0]['request']->getMethod());
		static::assertSame('https://api.example.com/search?q=cat', (string) $this->sent[0]['request']->getUri());
		static::assertFalse($this->sent[0]['request']->hasHeader('Authorization'));
	}

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	#[Group('units')]
	public function testTheHeadersForTheApiAreSent(): void
	{
		$service = $this->service([new Response(200, [], '[]')]);

		$service->requestApi('https://api.example.com/search', ['Authorization' => 'Bearer abc', 'Accept' => 'application/json']);

		static::assertSame('Bearer abc', $this->sent[0]['request']->getHeaderLine('Authorization'));
		static::assertSame('application/json', $this->sent[0]['request']->getHeaderLine('Accept'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function noJsonProvider(): array
	{
		return ['empty' => [''], 'text' => ['Service unavailable'], 'a number' => ['5'], 'null' => ['null']];
	}

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	#[Group('units')]
	#[DataProvider('noJsonProvider')]
	public function testAnAnswerWhichIsNoListIsAnEmptyResult(string $body): void
	{
		static::assertSame([], $this->service([new Response(200, [], $body)])->requestApi('https://api.example.com/search'));
	}

	#[Group('units')]
	public function testWithoutTheRightToUploadNothingHappens(): void
	{
		$this->uploadable = false;
		$service = $this->service();

		foreach ([
			fn() => $service->uploadMediaFile(7, 1, new UploadedFile('/tmp/x', 'x.png', 'image/png', 10), []),
			fn() => $service->uploadExternalMedia(7, 1, 'https://media.example.com/a.png', []),
		] as $call)
		{
			try
			{
				$call();
				static::fail('Expected an AccessDeniedException');
			}
			catch (AccessDeniedException $e)
			{
				static::assertSame('node_not_uploadable', $e->getTranslationKey());
			}
		}
		static::assertSame([], $this->sent, 'No request is sent for a directory where the user may not upload.');
	}

	/**
	 * @return array<string,array{int,string,string}> the PHP upload error, the class of the error, the key or text
	 */
	public static function uploadErrors(): array
	{
		return [
			'ini size'      => [UPLOAD_ERR_INI_SIZE, TranslatableModuleException::class, 'upload_file_too_big'],
			'form size'     => [UPLOAD_ERR_FORM_SIZE, TranslatableModuleException::class, 'upload_file_too_big'],
			'partial'       => [UPLOAD_ERR_PARTIAL, TranslatableModuleException::class, 'upload_partial'],
			'no file'       => [UPLOAD_ERR_NO_FILE, TranslatableModuleException::class, 'upload_no_file'],
			'no tmp dir'    => [UPLOAD_ERR_NO_TMP_DIR, ModuleException::class, 'Missing a temporary folder'],
			'cant write'    => [UPLOAD_ERR_CANT_WRITE, ModuleException::class, 'Failed to write file to disk'],
			'by extension'  => [UPLOAD_ERR_EXTENSION, ModuleException::class, 'File upload stopped by extension'],
			'unknown code'  => [99, ModuleException::class, 'Unknown upload error'],
		];
	}

	/**
	 * What the user can fix gets a text for the user, the problems of the server stay technical.
	 *
	 * @param class-string<ModuleException> $class
	 */
	#[Group('units')]
	#[DataProvider('uploadErrors')]
	public function testAnUploadErrorOfPhpIsReported(int $code, string $class, string $messageOrKey): void
	{
		$file = new UploadedFile('/tmp/x', 'x.png', 'image/png', 10, $code);

		try
		{
			$this->service()->uploadMediaFile(7, 1, $file, []);
			static::fail('Expected ' . $class);
		}
		catch (ModuleException $e)
		{
			static::assertSame($class, $e::class);
			static::assertStringContainsString($messageOrKey, $e->getMessage());
		}
	}

	#[Group('units')]
	public function testAFileWithoutATypeIsRefused(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Not able to detect mime type.');

		$this->service()->uploadMediaFile(7, 1, new UploadedFile('/tmp/x', 'x.png', null, 10), []);
	}

	#[Group('units')]
	public function testAFileWithoutASizeIsRefused(): void
	{
		$this->factoryGivesAHandler = true;
		$service = $this->service();

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Not able to detect size.');

		$service->uploadMediaFile(7, 1, new UploadedFile('/tmp/x', 'x.png', 'image/png', null), []);
	}

	/**
	 * @return array<string,array{string,string,string}> the address, the Content-Type of the answer, the type of the handler
	 */
	public static function typesOfExternalFiles(): array
	{
		return [
			'the type of the server'               => ['https://media.example.com/photo.png', 'image/png', 'image/png'],
			'the type wins over the name'          => ['https://media.example.com/video/photo.png', 'image/png', 'image/png'],
			'no type, mp4 in the address'          => ['https://media.example.com/clip.mp4', '', 'video/mp4'],
			'no type, webm in the address'         => ['https://media.example.com/clip.webm', '', 'video/mp4'],
			'no type, video in the address'        => ['https://media.example.com/video/123', '', 'video/mp4'],
			'no type, nothing which tells'         => ['https://media.example.com/photo.png', '', ''],
		];
	}

	/**
	 * Servers of streams do not always send a type. A video is recognized by its address then.
	 */
	#[Group('units')]
	#[DataProvider('typesOfExternalFiles')]
	public function testTheTypeOfAnExternalFileComesFromTheServerOrTheAddress(string $url, string $contentType, string $expected): void
	{
		$headers = $contentType === '' ? [] : ['Content-Type' => $contentType];

		try
		{
			$this->service([new Response(200, $headers)])->uploadExternalMedia(7, 1, $url, []);
			static::fail('The handler factory has to stop the upload');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('handler_asked', $e->getTranslationKey());
		}

		static::assertSame([$expected], $this->askedTypes);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function notAllowedAddresses(): array
	{
		return [
			'no url'          => ['not a url'],
			'file'            => ['file:///etc/passwd'],
			'private network' => ['http://internal.example/a.png'],
			'metadata'        => ['http://metadata.example/latest/meta-data/'],
			'loopback'        => ['http://loopback.example:8080/a.png'],
			'ip address'      => ['http://127.0.0.1/a.png'],
		];
	}

	/**
	 * The address is checked before any request: the server does not even ask.
	 */
	#[Group('units')]
	#[DataProvider('notAllowedAddresses')]
	public function testAnAddressOfTheOwnNetworkIsNeverRequested(string $url): void
	{
		try
		{
			$this->service([new Response(200)])->uploadExternalMedia(7, 1, $url, []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertStringStartsWith('url_', $e->getTranslationKey());
		}

		static::assertSame([], $this->sent);
	}

	/**
	 * The host is pinned to the IP which was checked, so the name can not be answered differently a second time.
	 */
	#[Group('units')]
	public function testTheRequestIsPinnedToTheCheckedIp(): void
	{
		try
		{
			$this->service([new Response(404)])->uploadExternalMedia(7, 1, 'https://media.example.com:8443/a.png', []);
		}
		catch (TranslatableModuleException)
		{
		}

		static::assertSame(['media.example.com:8443:93.184.216.34'], $this->sent[0]['options']['curl'][CURLOPT_RESOLVE] ?? null);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function redirectsToTheOwnNetwork(): array
	{
		return [
			'private network' => ['http://internal.example/secret'],
			'metadata'        => ['http://metadata.example/latest/meta-data/'],
			'loopback'        => ['http://loopback.example/admin'],
			'ip address'      => ['http://127.0.0.1:9000/'],
			'unknown host'    => ['http://nowhere.example/a.png'],
		];
	}

	/**
	 * A page which is fine itself can send the server somewhere else. The target is checked like the first address,
	 * and the request to it is not made.
	 */
	#[Group('units')]
	#[DataProvider('redirectsToTheOwnNetwork')]
	public function testARedirectToTheOwnNetworkIsNotFollowed(string $target): void
	{
		$service = $this->service([new Response(302, ['Location' => $target]), new Response(200, ['Content-Type' => 'image/png'])]);

		try
		{
			$service->uploadExternalMedia(7, 1, 'https://media.example.com/a.png', []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertStringStartsWith('url_', $e->getTranslationKey());
		}

		static::assertCount(1, $this->sent, 'only the first address was asked');
	}

	/**
	 * Guzzle itself follows only http and https.
	 */
	#[Group('units')]
	public function testARedirectToAnotherProtocolIsAMessageForTheUser(): void
	{
		$service = $this->service([new Response(302, ['Location' => 'ftp://media.example.com/a.png'])]);

		try
		{
			$service->uploadExternalMedia(7, 1, 'https://media.example.com/a.png', []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('external_media_not_loadable', $e->getTranslationKey());
		}

		static::assertCount(1, $this->sent);
	}

	/**
	 * @return array<string,array{Response|\Throwable}>
	 */
	public static function brokenLinks(): array
	{
		return [
			'not found'      => [new Response(404)],
			'server error'   => [new Response(500)],
			'forbidden'      => [new Response(403)],
			'no connection'  => [new ConnectException('Connection refused', new Request('HEAD', 'https://media.example.com/a.png'))],
		];
	}

	/**
	 * A broken link is the mistake of the user and not a failure of the server.
	 */
	#[Group('units')]
	#[DataProvider('brokenLinks')]
	public function testABrokenLinkIsAMessageForTheUser(Response|\Throwable $answer): void
	{
		try
		{
			$this->service([$answer])->uploadExternalMedia(7, 1, 'https://media.example.com/a.png', []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('external_media_not_loadable', $e->getTranslationKey());
			static::assertNotNull($e->getPrevious());
		}
	}

	#[Group('units')]
	public function testTooManyRedirectsAreAMessageForTheUser(): void
	{
		$answers = array_fill(0, 6, new Response(302, ['Location' => 'https://cdn.example.com/loop']));

		try
		{
			$this->service($answers)->uploadExternalMedia(7, 1, 'https://media.example.com/a.png', []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('external_media_not_loadable', $e->getTranslationKey());
		}

		static::assertLessThanOrEqual(4, count($this->sent), 'at most three redirects are followed');
	}
}
