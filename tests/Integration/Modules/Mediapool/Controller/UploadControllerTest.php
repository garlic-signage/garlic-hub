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

namespace Tests\Integration\Modules\Mediapool\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Media\MimeTypeService;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Controller\UploadController;
use App\Modules\Mediapool\Helper\Uploads\Orchestrator;
use App\Modules\Mediapool\Helper\Uploads\ResponseBuilder;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Mediapool\Services\UploadService;
use App\Modules\Mediapool\Utils\SsrfValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Imagick;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\RequestInterface;
use Slim\Psr7\UploadedFile;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The upload endpoints /async/mediapool/uploadLocalFile, uploadFromUrl and searchStockImages. The storage is a
 * temporary directory, the answers of the web are prepared, so nothing leaves the machine.
 */
#[Group('acl')]
#[Group('io')]
class UploadControllerTest extends ControllerTestCase
{
	private MediaWorkspace $workspace;
	/** @var list<RequestInterface> the requests which went to the web */
	private array $sent = [];
	private int $files = 0;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		$this->sent      = [];
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	/**
	 * @param list<Response|\Throwable> $answers
	 */
	private function controller(array $answers = []): UploadController
	{
		$stack = HandlerStack::create(new MockHandler($answers));
		$stack->push(function (callable $next)
		{
			return function (RequestInterface $request, array $options) use ($next)
			{
				$this->sent[] = $request;

				return $next($request, $options);
			};
		});
		$service = new UploadService(
			$this->workspace->handlerFactory(),
			$this->container->get(NodesService::class),
			new Client(['handler' => $stack]),
			new FilesRepository($this->connection),
			$this->container->get(MimeTypeService::class),
			new SsrfValidator(static fn(string $host): string => ['media.example.com' => '93.184.216.34', 'intranet.example' => '10.0.0.5'][$host] ?? $host)
		);

		return new UploadController(new Orchestrator(
			new ResponseBuilder($this->container->get(JsonResponseHandler::class), $this->container->get(Translator::class)),
			$this->container->get(UserSession::class),
			$service
		));
	}

	private function pngBytes(int $red = 200): string
	{
		$imagick = new Imagick();
		$imagick->newImage(8, 6, sprintf('rgb(%d,20,30)', $red));
		$imagick->setImageFormat('png');

		return $imagick->getImagesBlob();
	}

	private function pngUpload(string $name = 'bild.png', int $red = 200): UploadedFile
	{
		$path = $this->workspace->dir . '/incoming/' . (++$this->files);
		file_put_contents($path, $this->pngBytes($red));

		return new UploadedFile($path, $name, 'image/png', (int) filesize($path));
	}

	/**
	 * @param array<string,mixed> $body
	 * @param array<string,mixed> $files
	 */
	private function uploadLocal(UploadController $controller, array $body, array $files): \Psr\Http\Message\ResponseInterface
	{
		return $controller->uploadLocalFile($this->request('POST', $body)->withUploadedFiles($files), $this->newResponse());
	}

	private function mediaCount(): int
	{
		return (int) $this->databaseValue('SELECT COUNT(*) FROM mediapool_files');
	}

	public function testALocalFileIsUploaded(): void
	{
		$this->actingAs(2);
		$before = $this->mediaCount();

		$response = $this->uploadLocal($this->controller(), ['node_id' => '8', 'metadata' => '{"description":"Am Meer"}'], ['file' => $this->pngUpload()]);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame(['success' => true, 'message' => 'bild.png successful uploaded'], $this->json($response));
		static::assertSame($before + 1, $this->mediaCount());
		static::assertSame('Am Meer', $this->databaseValue('SELECT media_description FROM mediapool_files WHERE filename = ?', 'bild.png'));
		static::assertSame(2, (int) $this->databaseValue('SELECT UID FROM mediapool_files WHERE filename = ?', 'bild.png'));
		static::assertCount(1, $this->workspace->listing('originals'));
	}

	/**
	 * @return array<string,array{string,mixed}> the metadata which is sent and the description which is stored
	 */
	public static function metadata(): array
	{
		return [
			'a text'            => ['{"description":"Strand"}', 'Strand'],
			'a number'          => ['{"description":5}', '5'],
			'a list'            => ['{"description":["a","b"]}', ''],
			'an object'         => ['{"description":{"a":"b"}}', ''],
			'null'              => ['{"description":null}', ''],
			'no description'    => ['{"source":"stock"}', ''],
			'not JSON'          => ['{broken', ''],
			'not an object'     => ['"text"', ''],
			'empty'             => ['', ''],
		];
	}

	/**
	 * What the browser sends as metadata is text, numbers and yes or no. Lists and objects are not taken.
	 */
	#[DataProvider('metadata')]
	public function testTheMetadataOfTheBrowserAreReducedToSimpleValues(string $sent, string $description): void
	{
		$this->actingAs(2);

		$this->uploadLocal($this->controller(), ['node_id' => '8', 'metadata' => $sent], ['file' => $this->pngUpload()]);

		static::assertSame($description, $this->databaseValue('SELECT media_description FROM mediapool_files WHERE filename = ?', 'bild.png'));
		$stored = json_decode((string) $this->databaseValue('SELECT metadata FROM mediapool_files WHERE filename = ?', 'bild.png'), true);
		foreach ($stored as $value)
			static::assertTrue(is_scalar($value) || is_array($value));
		static::assertArrayNotHasKey('a', $stored);
	}

	public function testListsAndObjectsAsMetadataAreNotStored(): void
	{
		$this->actingAs(2);

		$this->uploadLocal($this->controller(), ['node_id' => '8', 'metadata' => '{"tags":["a"],"owner":{"x":1},"source":"stock","rating":4.5,"public":true}'], ['file' => $this->pngUpload()]);

		$stored = json_decode((string) $this->databaseValue('SELECT metadata FROM mediapool_files WHERE filename = ?', 'bild.png'), true);
		static::assertArrayNotHasKey('tags', $stored);
		static::assertArrayNotHasKey('owner', $stored);
		static::assertSame('stock', $stored['source']);
		static::assertSame(4.5, $stored['rating']);
		static::assertTrue($stored['public']);
	}

	public function testALocalUploadWithoutFilesIsRefused(): void
	{
		$this->actingAs(2);

		$this->assertError($this->uploadLocal($this->controller(), ['node_id' => '8'], []), 'No files to upload.');
		$this->assertError($this->uploadLocal($this->controller(), ['node_id' => '8'], ['other' => $this->pngUpload()]), 'no files');
	}

	/**
	 * A field with several files (file[]) is a list: the upload takes one file.
	 */
	public function testSeveralFilesInOneFieldAreNoFile(): void
	{
		$this->actingAs(2);
		$before = $this->mediaCount();

		$response = $this->uploadLocal($this->controller(), ['node_id' => '8'], ['file' => [$this->pngUpload('a.png'), $this->pngUpload('b.png', 100)]]);

		$this->assertError($response, 'no files');
		static::assertSame($before, $this->mediaCount());
	}

	/**
	 * @return array<string,array{mixed}>
	 */
	public static function noNodes(): array
	{
		return ['missing' => [null], 'zero' => ['0'], 'text' => ['abc'], 'a list' => [['8']], 'negative' => ['-8']];
	}

	#[DataProvider('noNodes')]
	public function testAnUploadNeedsADirectory(mixed $node): void
	{
		$this->actingAs(2);
		$body = $node === null ? [] : ['node_id' => $node, 'external_link' => 'https://media.example.com/a.png'];

		$this->assertError($this->uploadLocal($this->controller(), $body, ['file' => $this->pngUpload()]), 'node is missing');
		$this->assertError($this->controller()->uploadFromUrl($this->request('POST', $body), $this->newResponse()), 'node is missing');
		static::assertSame([], $this->sent);
	}

	public function testAnExternalFileIsUploaded(): void
	{
		$this->actingAs(2);
		$body     = $this->pngBytes(77);
		$controller = $this->controller([
			new Response(200, ['Content-Type' => 'image/png', 'Content-Length' => (string) strlen($body)]),
			new Response(200, ['Content-Type' => 'image/png'], $body),
		]);

		$response = $controller->uploadFromUrl($this->request('POST', ['node_id' => '8', 'external_link' => 'https://media.example.com/img/katze.png', 'metadata' => '{"description":"Katze"}']), $this->newResponse());

		static::assertSame(['success' => true, 'message' => 'katze.png successful uploaded'], $this->json($response));
		static::assertSame('Katze', $this->databaseValue('SELECT media_description FROM mediapool_files WHERE filename = ?', 'katze.png'));
		static::assertCount(2, $this->sent);
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function noLinks(): array
	{
		return ['missing' => [[]], 'empty' => [['external_link' => '']], 'a list' => [['external_link' => ['https://media.example.com/a.png']]], 'a number' => [['external_link' => 5]]];
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('noLinks')]
	public function testAnUploadFromTheWebNeedsAnAddress(array $body): void
	{
		$this->actingAs(2);

		$response = $this->controller()->uploadFromUrl($this->request('POST', ['node_id' => '8'] + $body), $this->newResponse());

		$this->assertError($response, 'No external link submitted.');
		static::assertSame([], $this->sent);
	}

	public function testAnAddressInTheOwnNetworkIsRefusedBeforeAnyRequest(): void
	{
		$this->actingAs(2);
		$before = $this->mediaCount();

		try
		{
			$this->controller([new Response(200)])->uploadFromUrl($this->request('POST', ['node_id' => '8', 'external_link' => 'http://intranet.example/a.png']), $this->newResponse());
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('url_not_allowed', $e->getTranslationKey());
		}

		static::assertSame([], $this->sent);
		static::assertSame($before, $this->mediaCount());
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function allowedStockAddresses(): array
	{
		return [
			'unsplash' => ['https://api.unsplash.com/search/photos?query=cat'],
			'pexels'   => ['https://api.pexels.com/v1/search?query=cat'],
			'pixabay'  => ['https://pixabay.com/api/?key=abc&q=cat'],
			'upper case' => ['HTTPS://API.PEXELS.COM/v1/search'],
		];
	}

	#[DataProvider('allowedStockAddresses')]
	public function testTheStockServicesAreAsked(string $url): void
	{
		$this->actingAs(2);
		$controller = $this->controller([new Response(200, [], '{"results":[{"id":1}]}')]);

		$response = $controller->searchStockImages($this->request('POST', ['api_url' => $url, 'headers' => ['Authorization' => 'abc']]), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame(['results' => [['id' => 1]]], $this->json($response)['data']);
		static::assertSame('abc', $this->sent[0]->getHeaderLine('Authorization'));
	}

	/**
	 * @return array<string,array{mixed}>
	 */
	public static function wrongStockAddresses(): array
	{
		return [
			'other host'           => ['https://evil.example/search'],
			'no tls'               => ['http://pixabay.com/api/'],
			'ftp'                  => ['ftp://pixabay.com/x'],
			'file'                 => ['file:///etc/passwd'],
			'host as prefix'       => ['https://pixabay.com.evil.example/api/'],
			'host as path'         => ['https://evil.example/pixabay.com'],
			'user info'            => ['https://pixabay.com@evil.example/api/'],
			'user info the other way' => ['https://evil.example@pixabay.com.evil.example/'],
			'sub host'             => ['https://evil.pixabay.com/api/'],
			'no host'              => ['/api/'],
			'empty'                => [''],
			'a list'               => [['https://pixabay.com/api/']],
			'nothing'              => [null],
		];
	}

	#[DataProvider('wrongStockAddresses')]
	public function testOtherAddressesAreRefusedBeforeAnyRequest(mixed $url): void
	{
		$this->actingAs(2);

		$response = $this->controller([new Response(200, [], '{}')])->searchStockImages($this->request('POST', $url === null ? [] : ['api_url' => $url]), $this->newResponse());

		$this->assertError($response, 'Host not allowed.');
		static::assertSame([], $this->sent);
	}

	/**
	 * @return array<string,array{mixed}>
	 */
	public static function wrongStockHeaders(): array
	{
		return [
			'cookie'          => [['Cookie' => 'GhSid=1']],
			'host'            => [['Host' => 'evil.example']],
			'forwarded'       => [['X-Forwarded-For' => '10.0.0.1']],
			'one of two'      => [['Authorization' => 'abc', 'Cookie' => 'x']],
			'a list'          => [['Authorization: abc']],
			'a number'        => [['Authorization' => 5]],
			'a list as value' => [['Authorization' => ['abc']]],
			'text'            => ['Authorization: abc'],
		];
	}

	#[DataProvider('wrongStockHeaders')]
	public function testOnlyTheTokenOfTheServiceIsSentAlong(mixed $headers): void
	{
		$this->actingAs(2);

		$response = $this->controller([new Response(200, [], '{}')])->searchStockImages(
			$this->request('POST', ['api_url' => 'https://pixabay.com/api/', 'headers' => $headers]),
			$this->newResponse()
		);

		$this->assertError($response, 'Header not allowed.');
		static::assertSame([], $this->sent);
	}

	public function testTheHeadersAreNotCaseSensitive(): void
	{
		$this->actingAs(2);

		$response = $this->controller([new Response(200, [], '{}')])->searchStockImages(
			$this->request('POST', ['api_url' => 'https://api.pexels.com/v1/', 'headers' => ['AUTHORIZATION' => 'abc', 'x-api-key' => 'k', 'Accept' => 'application/json']]),
			$this->newResponse()
		);

		$this->assertSuccess($response);
		static::assertSame('k', $this->sent[0]->getHeaderLine('X-Api-Key'));
	}

	/**
	 * The stock services do not redirect. A redirect would take the token of the user to another host.
	 */
	public function testARedirectOfAStockServiceIsNotFollowed(): void
	{
		$this->actingAs(2);
		$controller = $this->controller([new Response(302, ['Location' => 'https://evil.example/steal']), new Response(200, [], '{"leak":true}')]);

		$response = $controller->searchStockImages($this->request('POST', ['api_url' => 'https://pixabay.com/api/', 'headers' => ['Authorization' => 'secret']]), $this->newResponse());

		static::assertCount(1, $this->sent);
		static::assertSame(['success' => true, 'data' => []], $this->json($response));
	}

	public function testARequestWithoutABodyIsAMissingValue(): void
	{
		$this->actingAs(2);
		$request = new \Slim\Psr7\Factory\ServerRequestFactory()->createServerRequest('POST', '/async/mediapool/searchStockImages');

		$this->assertError($this->controller()->searchStockImages($request, $this->newResponse()), 'Host not allowed.');
		$this->assertError($this->controller()->uploadFromUrl($request, $this->newResponse()), 'node is missing');
	}
}
