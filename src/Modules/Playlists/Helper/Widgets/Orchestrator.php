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

namespace App\Modules\Playlists\Helper\Widgets;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Services\WidgetsService;
use Psr\Http\Message\ResponseInterface;

/**
 * Validates the input for the playlist widget endpoints and executes the
 * requested action. Every validate method returns a response on error
 * and null when the input is valid.
 */
class Orchestrator
{
	/** @var array<string,mixed> */
	private array $input = [];
	private int $itemId = 0;

	public function __construct(
		private readonly ResponseBuilder $responseBuilder,
		private readonly BaseValidator $validator,
		private readonly WidgetsService $widgetsService
	) {}

	/**
	 * @param array<string,mixed> $input
	 */
	public function setInput(array $input): static
	{
		$this->input = $input;
		return $this;
	}

	public function validate(ResponseInterface $response): ?ResponseInterface
	{
		$this->itemId = (int) ($this->input['item_id'] ?? 0);
		if ($this->itemId === 0)
			return $this->responseBuilder->invalidItemId($response);

		return null;
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateSave(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->validator->validateCsrfToken($this->input['csrf_token'] ?? ''))
			return $this->responseBuilder->csrfTokenMismatch($response);

		return $this->validate($response);
	}

	/**
	 * @throws UserException
	 */
	public function fetch(ResponseInterface $response): ResponseInterface
	{
		$data = $this->widgetsService->fetchWidgetByItemId($this->itemId);
		if (empty($data))
			return $this->responseBuilder->widgetLoadFailed($response);

		return $this->responseBuilder->generalSuccess($response, ['data' => $data]);
	}

	/**
	 * @throws UserException
	 */
	public function save(ResponseInterface $response): ResponseInterface
	{
		if (!$this->widgetsService->saveWidget($this->itemId, $this->input))
			return $this->responseBuilder->saveFailed($response, $this->widgetsService->getErrorText());

		return $this->responseBuilder->generalSuccess($response, []);
	}
}
