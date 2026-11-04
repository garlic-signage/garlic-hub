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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Playlists\Services\PlaylistVersionsService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Throwable;

/**
 * The versions dialog of the context menu: the exported states of a playlist and their restore.
 * Who may edit the playlist may restore a version.
 */
readonly class PlaylistVersionsController
{
	private const string MODULE_NAME = 'playlists';

	public function __construct(
		private PlaylistVersionsService $playlistVersionsService,
		private JsonResponseHandler $responseHandler,
		private Translator $translator,
		private Messages $flash
	) {}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		try
		{
			$versions = $this->playlistVersionsService->findVersions((int) ($args['playlist_id'] ?? 0));
		}
		catch (TranslatableModuleException $e)
		{
			return $this->responseHandler->jsonError($response, $this->translator->translateException($e));
		}

		return $this->responseHandler->jsonSuccess($response, [
			'versions' => $versions,
			'labels'   => [
				'title'           => $this->translator->translate('versions', 'main'),
				'empty'           => $this->translator->translate('versions_empty', 'main'),
				'restore'         => $this->translator->translate('version_restore', 'main'),
				'restore_confirm' => $this->translator->translate('version_restore_confirm', 'main'),
				'close'           => $this->translator->translate('close', 'main')
			]
		]);
	}

	/**
	 * After the restore the composer opens, the flash messages name the items which were left out.
	 *
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Throwable
	 */
	public function restore(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playlistId = (int) ($args['playlist_id'] ?? 0);
		$version    = RequestInput::of($request->getParsedBody())->id('version');
		if (!$this->playlistVersionsService->isEnabled() || $version === 0)
			return $this->responseHandler->jsonError($response, $this->translator->translate('version_not_found', self::MODULE_NAME));

		try
		{
			$omitted = $this->playlistVersionsService->restoreVersion($playlistId, $version);
		}
		catch (TranslatableModuleException $e)
		{
			return $this->responseHandler->jsonError($response, $this->translator->translateException($e));
		}

		$this->flash->addMessage('success', $this->translator->translate('version_restored', self::MODULE_NAME));
		if ($omitted !== [])
			$this->flash->addMessage('error', $this->translator->translate('version_items_omitted', self::MODULE_NAME, ['{items}' => implode(', ', $omitted)]));

		return $this->responseHandler->jsonSuccess($response, ['redirect' => '/playlists/compose/' . $playlistId, 'omitted' => $omitted]);
	}
}
