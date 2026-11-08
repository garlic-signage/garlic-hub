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

namespace App\Modules\Templates\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Templates\Services\ImageSuggestionService;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Pictures of the mediapool which fit the texts of a template, suggested by the AI /async/templates/suggest-images.
 */
readonly class ImageSuggestionController
{
	public function __construct(
		private ImageSuggestionService $imageSuggestionService,
		private JsonResponseHandler $responseHandler,
		private Translator $translator
	) {}

	/**
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws JsonException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function suggest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$texts = trim(RequestInput::of($request->getParsedBody())->text('texts'));
		if ($texts === '')
			return $this->responseHandler->jsonError($response, $this->translator->translate('ai_no_texts', 'templates'));

		try
		{
			$images = $this->imageSuggestionService->suggest($texts);
		}
		catch (TranslatableModuleException $e)
		{
			return $this->responseHandler->jsonError($response, $this->translator->translateException($e));
		}

		return $this->responseHandler->jsonSuccess($response, ['images' => $images]);
	}
}
