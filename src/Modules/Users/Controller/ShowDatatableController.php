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

namespace App\Modules\Users\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Core\BaseValidator;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Modules\Users\Helper\Datatable\DatatableFacade;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Slim\Flash\Messages;

readonly class ShowDatatableController
{
	public function __construct(
		private DatatableFacade $facade,
		private DatatableTemplatePreparer $templateFormatter,
		private BaseValidator $validator,
		private JsonResponseHandler $responseHandler
	) {}

	/**
	 * The frontend reloads the page after the request, so the result is also
	 * stored as flash message, which is shown after the reload.
	 *
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $post */
		$post       = $request->getParsedBody();
		/** @var Messages $flash */
		$flash      = $request->getAttribute('flash');
		/** @var Translator $translator */
		$translator = $request->getAttribute('translator');

		if (!$this->validator->validateCsrfToken($post['csrf_token'] ?? ''))
			return $this->error($response, $flash, $translator->translate('csrf_token_mismatch', 'security'));

		$UID = (int) ($post['UID'] ?? 0);
		if ($UID === 0)
			return $this->error($response, $flash, $translator->translate('user_not_found', 'users'));

		$this->facade->configure($translator, $request->getAttribute('session'));
		if (!$this->facade->deleteUser($UID))
			return $this->error($response, $flash, $translator->translate('user_delete_failed', 'users'));

		$flash->addMessage('success', $translator->translate('user_deleted', 'users'));
		return $this->responseHandler->jsonSuccess($response);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception|ModuleException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$this->facade->configure($request->getAttribute('translator'), $request->getAttribute('session'));
		$this->facade->processSubmittedUserInput($request->getQueryParams());

		$this->facade->prepareDataGrid();
		$dataGrid = $this->facade->prepareUITemplate();

		$templateData = $this->templateFormatter->preparerUITemplate($dataGrid);

		$response->getBody()->write(serialize($templateData));

		return $response->withHeader('Content-Type', 'text/html');
	}

	private function error(ResponseInterface $response, Messages $flash, string $message): ResponseInterface
	{
		$flash->addMessage('error', $message);
		return $this->responseHandler->jsonError($response, $message);
	}
}
