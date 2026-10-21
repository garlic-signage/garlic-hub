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

namespace App\Framework\Middleware;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use DomainException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;

/**
 * Turns every exception of the JSON routes (/api, /async) into a response.
 *
 * This is the one place which decides status, format, language and logging of an error,
 * so services just throw and controllers do not need to catch. The format is the one of
 * JsonResponseHandler::jsonError(), which the frontend shows as error message.
 */
readonly class JsonErrorHandler
{
	private const string FALLBACK_UNEXPECTED = 'An unexpected error occurred.';

	public function __construct(
		private ResponseFactoryInterface $responseFactory,
		private JsonResponseHandler $jsonResponseHandler,
		private Translator $translator,
		private LoggerInterface $logger
	) {}

	public function __invoke(ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails): ResponseInterface
	{
		[$status, $message] = $this->resolve($exception, $displayErrorDetails);
		$this->log($request, $exception, $status);

		return $this->jsonResponseHandler->jsonError($this->responseFactory->createResponse(), $message, $status);
	}

	/**
	 * @return array{0:int, 1:string} status and message for the user
	 */
	private function resolve(Throwable $exception, bool $displayErrorDetails): array
	{
		return match (true)
		{
			$exception instanceof NotFoundException             => [404, $this->translateException($exception)],
			$exception instanceof AccessDeniedException         => [403, $this->translateException($exception)],
			$exception instanceof TranslatableModuleException   => [400, $this->translateException($exception)],
			// not converted to a translation key yet, the text is shown as the orchestrators did before
			$exception instanceof ModuleException               => [400, $exception->getMessage()],
			$exception instanceof HttpNotFoundException         => [404, 'Route not found'],
			$exception instanceof HttpMethodNotAllowedException => [405, 'Method not allowed'],
			$exception instanceof DomainException               => [400, 'Domain-specific error'],
			// never show internals of unexpected errors to the user, they are in the log
			default => [500, $displayErrorDetails ? $exception->getMessage() : $this->translate('unexpected_error', 'main', [], self::FALLBACK_UNEXPECTED)],
		};
	}

	/**
	 * Replacements are written as {name} in the translation, e.g. "Playlist {playlist_id} not found."
	 */
	private function translateException(TranslatableModuleException $exception): string
	{
		$replacements = [];
		foreach ($exception->getReplacements() as $name => $value)
		{
			$replacements['{' . $name . '}'] = (string) $value;
		}

		return $this->translate($exception->getTranslationKey(), $exception->getModuleName(), $replacements, $exception->getTranslationKey());
	}

	/**
	 * An error while handling an error must not hide the original one. When the translation
	 * fails, e.g. the locale is not determined yet because the error happened before the
	 * EnvironmentMiddleware, or the key is missing, the fallback is used.
	 *
	 * @param array<string,string> $replacements
	 */
	private function translate(string $key, string $module, array $replacements, string $fallback): string
	{
		try
		{
			$translation = $this->translator->translate($key, $module, $replacements);
		}
		catch (Throwable)
		{
			$translation = '';
		}

		return $translation !== '' ? $translation : $fallback;
	}

	private function log(ServerRequestInterface $request, Throwable $exception, int $status): void
	{
		$context = ['method' => $request->getMethod(), 'path' => $request->getUri()->getPath(), 'status' => $status];

		if ($status >= 500)
		{
			$context['exception'] = $exception;
			$this->logger->error($exception->getMessage(), $context);
		}
		else
			$this->logger->notice($exception->getMessage(), $context);
	}
}
