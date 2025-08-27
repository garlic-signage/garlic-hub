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


namespace App\Modules\Playlists\Helper\ConditionalPlay;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Services\ConditionalPlayService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * The Orchestrator class provides operations for handling and orchestrating user input,
 * validation, data fetching, preparation of templates, and saving conditional play configurations.
 * It integrates multiple services like response building, user session handling, validation,
 * and template preparation to ensure a cohesive workflow for processing requests.
 */
class Orchestrator
{
	/** @var array<string,string>  */
	private array $input;
	private int $itemId;

	public function __construct(
		private readonly ResponseBuilder        $responseBuilder,
		private readonly UserSession            $userSession,
		private readonly BaseValidator          $validator,
		private readonly TemplatePreparer       $templatePreparer,
		private readonly ConditionalPlayService $conditionalPlayService,
	) {}

	/**
	 * @param array<string,string> $input
	 */
	public function setInput(array $input): static
	{
		$this->input = $input;
		return $this;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function validateSave(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->validator->validateCsrfToken($this->input[BaseEditParameters::PARAMETER_CSRF_TOKEN]))
			return $this->responseBuilder->csrfTokenMismatch($response);

		return $this->validate($response);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function validate(ResponseInterface $response): ?ResponseInterface
	{
		$this->itemId = (int) ($this->input['item_id'] ?? 0);
		if ($this->itemId === 0)
			return $this->responseBuilder->invalidItemId($response);

		return null;
	}

	/**
	 * @throws UserException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function fetch(ResponseInterface $response): ?ResponseInterface
	{
		$this->conditionalPlayService->setUID($this->userSession->getUID());

		$itemData = $this->conditionalPlayService->fetchConditionalByItemId($this->itemId);
		if ($itemData === [])
			return $this->responseBuilder->itemNotFound($response);

		$this->templatePreparer->prepare($this->itemId, $itemData['conditional']);

		$html = $this->templatePreparer->render();
		return $this->responseBuilder->generalSuccess($response, ['data' => $itemData, 'html' => $html]);

	}

	/**
	 * @param ResponseInterface $response
	 * @return ResponseInterface|null
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function save(ResponseInterface $response): ?ResponseInterface
	{
		$this->conditionalPlayService->setUID($this->userSession->getUID());

		unset($this->input['csrf_token']);
		unset($this->input['item_id']);

		$item = $this->conditionalPlayService->fetchAccessibleItem($this->itemId);
		if ($item === [])
			return $this->responseBuilder->itemNotFound($response);

		$this->conditionalPlayService->saveConditionalPlay($this->itemId, $this->input);

		return $this->responseBuilder->generalSuccess($response, []);
	}


}