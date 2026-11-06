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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Templates\Services\TemplatePackService;
use Doctrine\DBAL\Exception;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * The button of the template overview which installs the bundled templates. When templates were installed, the
 * page is reloaded: the message comes as flash message with it.
 */
readonly class TemplatePackController
{
	public function __construct(
		private TemplatePackService $templatePackService,
		private JsonResponseHandler $responseHandler,
		private Translator $translator,
		private Messages $flash
	) {}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws JsonException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function install(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try
		{
			$installed = $this->templatePackService->install();
		}
		catch (TranslatableModuleException $e)
		{
			return $this->responseHandler->jsonError($response, $this->translator->translateException($e));
		}

		if ($installed === 0)
			return $this->responseHandler->jsonSuccess($response, [
				'installed' => 0,
				'message'   => $this->translator->translate('template_pack_up_to_date', 'templates')
			]);

		$message = $this->translator->translateWithPlural('template_pack_installed', 'templates', $installed);
		$this->flash->addMessage('success', $message);

		return $this->responseHandler->jsonSuccess($response, ['installed' => $installed, 'message' => $message]);
	}
}
