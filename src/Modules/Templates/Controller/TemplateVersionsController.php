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
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Templates\Services\TemplatesService;
use Doctrine\DBAL\Exception;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The versions dialog of the context menu: the earlier contents of a template and their restore.
 * Who may edit the template may restore a version.
 */
readonly class TemplateVersionsController
{
	public function __construct(
		private TemplatesService $templatesService,
		private JsonResponseHandler $responseHandler,
		private Translator $translator
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
		$templateId = (int) ($args['template_id'] ?? 0);
		try
		{
			$versions = $this->templatesService->findVersions($templateId);
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
	 * After the restore the composer opens: its next save renews the thumbnail.
	 *
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws JsonException
	 * @throws UserException
	 */
	public function restore(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$templateId = (int) ($args['template_id'] ?? 0);
		$version    = RequestInput::of($request->getParsedBody())->id('version');
		if (!$this->templatesService->isVersioningEnabled() || $version === 0)
			return $this->responseHandler->jsonError($response, $this->translator->translate('version_not_found', 'templates'));

		try
		{
			$this->templatesService->restoreVersion($templateId, $version);
		}
		catch (TranslatableModuleException $e)
		{
			return $this->responseHandler->jsonError($response, $this->translator->translateException($e));
		}

		return $this->responseHandler->jsonSuccess($response, ['redirect' => '/templates/composer/' . $templateId]);
	}
}
