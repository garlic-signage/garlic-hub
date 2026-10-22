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

namespace Tests\Unit\Framework\Middleware;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Middleware\JsonErrorHandler;
use DomainException;
use Error;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;

class JsonErrorHandlerTest extends TestCase
{
	private Translator&MockObject $translatorMock;
	private LoggerInterface&MockObject $loggerMock;
	private ServerRequestInterface $request;
	private JsonErrorHandler $handler;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorMock = $this->createMock(Translator::class);
		$this->loggerMock     = $this->createMock(LoggerInterface::class);
		$this->request        = (new ServerRequestFactory())->createServerRequest('POST', '/async/playlists/delete');

		$this->handler = new JsonErrorHandler(new ResponseFactory(), new JsonResponseHandler(), $this->translatorMock, $this->loggerMock);
	}

	#[Group('units')]
	public function testNotFoundIsTranslated404(): void
	{
		$exception = new NotFoundException('playlists', 'playlist_not_found', ['playlist_id' => 5]);
		$this->translatorMock->expects($this->once())->method('translateException')
			->with($exception)
			->willReturn('Playlist 5 nicht gefunden.');
		$this->loggerMock->expects($this->once())->method('notice');

		$response = ($this->handler)($this->request, $exception, false);

		$this->assertJsonError($response, 404, 'Playlist 5 nicht gefunden.');
	}

	#[Group('units')]
	public function testAccessDeniedIsTranslated403(): void
	{
		$this->translatorMock->expects($this->once())->method('translateException')->willReturn('Keine Rechte.');
		$this->loggerMock->expects($this->once())->method('notice');

		$response = ($this->handler)($this->request, new AccessDeniedException('playlists', 'no_rights'), false);

		$this->assertJsonError($response, 403, 'Keine Rechte.');
	}

	#[Group('units')]
	public function testOtherTranslatableExceptionIs400(): void
	{
		$this->translatorMock->expects($this->once())->method('translateException')->willReturn('Zeitlimit überschritten.');
		$this->loggerMock->expects($this->once())->method('notice');

		$response = ($this->handler)($this->request, new TranslatableModuleException('playlists', 'time_limit_exceeded'), false);

		$this->assertJsonError($response, 400, 'Zeitlimit überschritten.');
	}

	#[Group('units')]
	public function testMissingTranslationFallsBackToKey(): void
	{
		$this->translatorMock->expects($this->once())->method('translateException')->willReturn('');
		$this->loggerMock->expects($this->once())->method('notice');

		$response = ($this->handler)($this->request, new NotFoundException('playlists', 'playlist_not_found'), false);

		$this->assertJsonError($response, 404, 'playlist_not_found');
	}

	/**
	 * e.g. the locale is not determined yet, because the error happened before the LocaleMiddleware
	 */
	#[Group('units')]
	public function testFailingTranslatorFallsBackToKey(): void
	{
		$this->translatorMock->expects($this->once())->method('translateException')->willThrowException(new Error('locale not initialized'));
		$this->loggerMock->expects($this->once())->method('notice');

		$response = ($this->handler)($this->request, new NotFoundException('playlists', 'playlist_not_found'), false);

		$this->assertJsonError($response, 404, 'playlist_not_found');
	}

	#[Group('units')]
	public function testLegacyModuleExceptionShowsItsText(): void
	{
		$this->translatorMock->expects($this->never())->method('translate');
		$this->loggerMock->expects($this->once())->method('notice')
			->with('Playlist is not accessible', ['method' => 'POST', 'path' => '/async/playlists/delete', 'status' => 400]);

		$response = ($this->handler)($this->request, new ModuleException('playlists', 'Playlist is not accessible'), false);

		$this->assertJsonError($response, 400, 'Playlist is not accessible');
	}

	#[Group('units')]
	public function testHttpErrors(): void
	{
		$this->translatorMock->expects($this->never())->method('translate');
		$this->loggerMock->expects($this->exactly(3))->method('notice');
		$response = ($this->handler)($this->request, new HttpNotFoundException($this->request), false);
		$this->assertJsonError($response, 404, 'Route not found');

		$response = ($this->handler)($this->request, new HttpMethodNotAllowedException($this->request), false);
		$this->assertJsonError($response, 405, 'Method not allowed');

		$response = ($this->handler)($this->request, new DomainException('anything'), false);
		$this->assertJsonError($response, 400, 'Domain-specific error');
	}

	#[Group('units')]
	public function testUnexpectedErrorHidesDetails(): void
	{
		$this->translatorMock->expects($this->once())->method('translate')
			->with('unexpected_error', 'main', [])
			->willReturn('Ein unerwarteter Fehler ist aufgetreten.');
		$this->loggerMock->expects($this->once())->method('error')
			->with('SQLSTATE[HY000] connection refused', static::callback(fn(array $context) => $context['status'] === 500 && isset($context['exception'])));

		$response = ($this->handler)($this->request, new RuntimeException('SQLSTATE[HY000] connection refused'), false);

		$this->assertJsonError($response, 500, 'Ein unerwarteter Fehler ist aufgetreten.');
	}

	#[Group('units')]
	public function testUnexpectedErrorWithoutTranslationUsesEnglishText(): void
	{
		$this->translatorMock->expects($this->once())->method('translate')->willThrowException(new CoreException('no config'));
		$this->loggerMock->expects($this->once())->method('error');

		$response = ($this->handler)($this->request, new RuntimeException('internal'), false);

		$this->assertJsonError($response, 500, 'An unexpected error occurred.');
	}

	#[Group('units')]
	public function testUnexpectedErrorShowsDetailsInDebugMode(): void
	{
		$this->translatorMock->expects($this->never())->method('translate');
		$this->loggerMock->expects($this->once())->method('error');

		$response = ($this->handler)($this->request, new RuntimeException('internal'), true);

		$this->assertJsonError($response, 500, 'internal');
	}

	private function assertJsonError(ResponseInterface $response, int $status, string $message): void
	{
		static::assertSame($status, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => false, 'error_message' => $message], json_decode((string) $response->getBody(), true));
	}
}
