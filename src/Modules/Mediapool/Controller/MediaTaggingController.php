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

namespace App\Modules\Mediapool\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Mediapool\Services\MediaTaggingService;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The suggestion of the AI for the description and the tags of a medium /async/mediapool/media/tags.
 */
readonly class MediaTaggingController
{
	public function __construct(
		private MediaTaggingService $mediaTaggingService,
		private JsonResponseHandler $responseHandler,
		private Translator $translator
	) {}

	/**
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function suggest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$mediaId = RequestInput::of($request->getParsedBody())->text('media_id');
		if ($mediaId === '')
			return $this->responseHandler->jsonError($response, 'Media id is missing');

		try
		{
			$suggestion = $this->mediaTaggingService->suggest($mediaId);
		}
		catch (AccessDeniedException|NotFoundException $e)
		{
			throw $e;
		}
		catch (TranslatableModuleException $e)
		{
			return $this->responseHandler->jsonError($response, $this->translator->translateException($e));
		}

		return $this->responseHandler->jsonSuccess($response, $suggestion);
	}
}
