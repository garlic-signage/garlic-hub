<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\Middleware;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\AuthException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Middleware\JsonErrorHandler;
use DomainException;
use Error;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Throwable;

class JsonErrorHandlerTest extends TestCase
{
	private Translator&Stub $translatorStub;
	private TestHandler $logs;
	private ServerRequestInterface $request;
	private JsonErrorHandler $handler;

	protected function setUp(): void
	{
		parent::setUp();

		$this->translatorStub = $this->createStub(Translator::class);
		$this->translatorStub->method('translateException')->willReturnCallback(
			static fn(TranslatableModuleException $e): string => [
				'playlist_not_found' => 'Playlist ' . ($e->getReplacements()['playlist_id'] ?? '') . ' nicht gefunden.',
				'no_rights'          => 'Keine Rechte.',
				'time_limit_exceeded' => 'Zeitlimit überschritten.',
			][$e->getTranslationKey()] ?? ''
		);
		$this->translatorStub->method('translate')->willReturnMap([['unexpected_error', 'main', [], 'Ein unerwarteter Fehler ist aufgetreten.']]);

		$this->logs    = new TestHandler();
		$this->request = (new ServerRequestFactory())->createServerRequest('POST', '/async/playlists/delete');
		$this->handler = new JsonErrorHandler(new ResponseFactory(), new JsonResponseHandler(), $this->translatorStub, new Logger('test', [$this->logs]));
	}

	/**
	 * @param class-string<TranslatableModuleException> $class
	 */
	#[Group('units')]
	#[DataProvider('translatableProvider')]
	public function testExpectedErrorsAreTranslatedAndKeepTheirStatus(string $class, string $key, int $status, string $message): void
	{
		$response = ($this->handler)($this->request, new $class('playlists', $key, ['playlist_id' => 5]), false);

		$this->assertJsonError($response, $status, $message);
	}

	/**
	 * @return array<string, array{class-string<TranslatableModuleException>, string, int, string}>
	 */
	public static function translatableProvider(): array
	{
		return [
			'not found'     => [NotFoundException::class, 'playlist_not_found', 404, 'Playlist 5 nicht gefunden.'],
			'access denied' => [AccessDeniedException::class, 'no_rights', 403, 'Keine Rechte.'],
			'other'         => [TranslatableModuleException::class, 'time_limit_exceeded', 400, 'Zeitlimit überschritten.'],
		];
	}

	#[Group('units')]
	public function testDebugModeDoesNotChangeTranslatedErrors(): void
	{
		$response = ($this->handler)($this->request, new NotFoundException('playlists', 'playlist_not_found', ['playlist_id' => 5]), true);

		$this->assertJsonError($response, 404, 'Playlist 5 nicht gefunden.');
	}

	#[Group('units')]
	public function testMissingTranslationFallsBackToKey(): void
	{
		$response = ($this->handler)($this->request, new NotFoundException('playlists', 'no_such_key'), false);

		$this->assertJsonError($response, 404, 'no_such_key');
	}

	/**
	 * e.g. the locale is not determined yet, because the error happened before the LocaleMiddleware
	 */
	#[Group('units')]
	public function testFailingTranslatorFallsBackToKey(): void
	{
		$translator = $this->createStub(Translator::class);
		$translator->method('translateException')->willThrowException(new Error('locale not initialized'));
		$handler = new JsonErrorHandler(new ResponseFactory(), new JsonResponseHandler(), $translator, new Logger('test', [$this->logs]));

		$response = $handler($this->request, new NotFoundException('playlists', 'playlist_not_found'), false);

		$this->assertJsonError($response, 404, 'playlist_not_found');
	}

	#[Group('units')]
	public function testLegacyModuleExceptionShowsItsText(): void
	{
		$response = ($this->handler)($this->request, new ModuleException('playlists', 'Playlist is not accessible'), false);

		$this->assertJsonError($response, 400, 'Playlist is not accessible');
	}

	#[Group('units')]
	#[DataProvider('httpErrorProvider')]
	public function testHttpErrors(Throwable $exception, int $status, string $message): void
	{
		$this->assertJsonError(($this->handler)($this->request, $exception, false), $status, $message);
	}

	/**
	 * @return array<string, array{Throwable, int, string}>
	 */
	public static function httpErrorProvider(): array
	{
		$request = (new ServerRequestFactory())->createServerRequest('GET', '/async/x');

		return [
			'route not found'    => [new HttpNotFoundException($request), 404, 'Route not found'],
			'method not allowed' => [new HttpMethodNotAllowedException($request), 405, 'Method not allowed'],
			'domain exception'   => [new DomainException('anything'), 400, 'Domain-specific error'],
		];
	}

	#[Group('units')]
	public function testUnexpectedErrorHidesDetails(): void
	{
		$response = ($this->handler)($this->request, new RuntimeException('SQLSTATE[HY000] connection refused'), false);

		$this->assertJsonError($response, 500, 'Ein unerwarteter Fehler ist aufgetreten.');
		static::assertStringNotContainsString('SQLSTATE', (string) $response->getBody());
	}

	/**
	 * A login error is no module error, it is treated like every unexpected one.
	 */
	#[Group('units')]
	public function testAnAuthExceptionIsAnUnexpectedError(): void
	{
		$response = ($this->handler)($this->request, new AuthException('Login failed'), false);

		$this->assertJsonError($response, 500, 'Ein unerwarteter Fehler ist aufgetreten.');
	}

	#[Group('units')]
	public function testUnexpectedErrorWithoutTranslationUsesEnglishText(): void
	{
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willThrowException(new CoreException('no config'));
		$handler = new JsonErrorHandler(new ResponseFactory(), new JsonResponseHandler(), $translator, new Logger('test', [$this->logs]));

		$this->assertJsonError($handler($this->request, new RuntimeException('internal'), false), 500, 'An unexpected error occurred.');
	}

	#[Group('units')]
	public function testUnexpectedErrorWithAnEmptyTranslationUsesEnglishText(): void
	{
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturn('');
		$handler = new JsonErrorHandler(new ResponseFactory(), new JsonResponseHandler(), $translator, new Logger('test', [$this->logs]));

		$this->assertJsonError($handler($this->request, new RuntimeException('internal'), false), 500, 'An unexpected error occurred.');
	}

	#[Group('units')]
	public function testUnexpectedErrorShowsDetailsInDebugMode(): void
	{
		$this->assertJsonError(($this->handler)($this->request, new RuntimeException('internal'), true), 500, 'internal');
	}

	/**
	 * The message of an exception may contain bytes which are no UTF-8, the answer must stay valid JSON.
	 */
	#[Group('units')]
	public function testDebugMessageWithInvalidUtf8StaysValidJson(): void
	{
		$response = ($this->handler)($this->request, new RuntimeException("broken\xB1text"), true);

		$this->assertJsonError($response, 500, "broken\u{FFFD}text");
	}

	#[Group('units')]
	#[DataProvider('logProvider')]
	public function testTheErrorIsLoggedWithMethodPathAndStatus(Throwable $exception, Level $level, int $status, bool $withException): void
	{
		($this->handler)($this->request, $exception, false);

		$records = $this->logs->getRecords();
		static::assertCount(1, $records);
		static::assertSame($level, $records[0]->level);
		static::assertSame($exception->getMessage(), $records[0]->message);
		static::assertSame($status, $records[0]->context['status']);
		static::assertSame('POST', $records[0]->context['method']);
		static::assertSame('/async/playlists/delete', $records[0]->context['path']);
		// only the unexpected errors carry the exception with its trace
		static::assertSame($withException ? $exception : null, $records[0]->context['exception'] ?? null);
	}

	/**
	 * @return array<string, array{Throwable, Level, int, bool}>
	 */
	public static function logProvider(): array
	{
		return [
			'not found'     => [new NotFoundException('playlists', 'playlist_not_found'), Level::Notice, 404, false],
			'access denied' => [new AccessDeniedException('playlists', 'no_rights'), Level::Notice, 403, false],
			'legacy module' => [new ModuleException('playlists', 'Not accessible'), Level::Notice, 400, false],
			'domain'        => [new DomainException('anything'), Level::Notice, 400, false],
			'unexpected'    => [new RuntimeException('SQLSTATE[HY000] connection refused'), Level::Error, 500, true],
		];
	}

	private function assertJsonError(ResponseInterface $response, int $status, string $message): void
	{
		static::assertSame($status, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => false, 'error_message' => $message], json_decode((string) $response->getBody(), true));
	}
}
