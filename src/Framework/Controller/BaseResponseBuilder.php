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


namespace App\Framework\Controller;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Abstract class responsible for building standardized response structures.
 * Provides utility methods for generating JSON responses for various scenarios.
 */
abstract class BaseResponseBuilder
{
	public function __construct(
		protected readonly JsonResponseHandler $jsonResponseHandler,
		protected readonly Translator $translator
	) {}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function csrfTokenMismatch(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError(
			$response, $this->translator->translate('csrf_token_mismatch', 'security')
		);
	}

	public function generalError(ResponseInterface $response, string $message): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, $message);
	}

	/**
	 * @param array<string,mixed> $message
	 */
	public function generalSuccess(ResponseInterface $response, array $message): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonSuccess($response, $message);
	}

}
