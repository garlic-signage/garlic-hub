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

namespace App\Modules\Playlists\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Playlists\Services\PlaylistFillingService;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The suggestion of the AI for the media of a playlist on a theme /async/playlists/ai/suggest.
 */
readonly class PlaylistFillingController
{
	public function __construct(
		private PlaylistFillingService $playlistFillingService,
		private JsonResponseHandler $responseHandler,
		private Translator $translator
	) {}

	/**
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws JsonException
	 * @throws ModuleException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function suggest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$input      = RequestInput::of($request->getParsedBody());
		$playlistId = $input->id('playlist_id');
		$theme      = trim($input->text('theme'));
		if ($playlistId === 0 || $theme === '')
			return $this->responseHandler->jsonError($response, $this->translator->translate('ai_theme_missing', 'playlists'));

		try
		{
			$items = $this->playlistFillingService->suggest($playlistId, $theme, $input->optionalInt('minutes') ?? 5);
		}
		catch (AccessDeniedException|NotFoundException $e)
		{
			throw $e;
		}
		catch (TranslatableModuleException $e)
		{
			return $this->responseHandler->jsonError($response, $this->translator->translateException($e));
		}

		return $this->responseHandler->jsonSuccess($response, ['items' => $items]);
	}
}
