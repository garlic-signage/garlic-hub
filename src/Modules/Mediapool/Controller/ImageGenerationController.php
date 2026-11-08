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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Llm\LlmException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Auth\UserSession;
use App\Shared\Llm\ImageGeneration;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * An image of the AI for the uploader /async/mediapool/generateImage. Nothing is stored: the browser shows it and
 * uploads it like a photo of the camera.
 */
readonly class ImageGenerationController
{
	public function __construct(
		private ImageGeneration $imageGeneration,
		private UserSession $userSession,
		private JsonResponseHandler $responseHandler,
		private Translator $translator
	) {}

	/**
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function generate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$input = RequestInput::of($request->getParsedBody());
		$UID   = $this->userSession->getUID();

		try
		{
			// the time of the AI only with one for the task
			if (!$this->imageGeneration->isAvailable($UID))
				throw new LlmException('llm_not_configured');

			$image = $this->imageGeneration->generate($UID, $input->text('prompt'), $input->text('format'), $input->text('negative_prompt'));
		}
		catch (LlmException $e)
		{
			return $this->responseHandler->jsonError($response, $this->translator->translateException($e));
		}

		return $this->responseHandler->jsonSuccess($response, ['image' => $image]);
	}
}
