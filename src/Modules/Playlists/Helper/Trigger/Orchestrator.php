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


namespace App\Modules\Playlists\Helper\Trigger;

use App\Framework\Validation\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Controller\ReadsInput;
use App\Modules\Playlists\Helper\ConditionalPlay\ResponseBuilder;
use App\Modules\Playlists\Services\TriggerService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Handles orchestration of validation, fetching, and saving processes.
 */
class Orchestrator
{
	use ReadsInput;

	private int $itemId;
	/** @var array<string,list<array<string,int|string>>> */
	private array $trigger = [];

	public function __construct(
		private readonly ResponseBuilder  $responseBuilder,
		private readonly BaseValidator    $validator,
		private readonly TemplatePreparer $templatePreparer,
		private readonly TriggerService   $triggerService,
		private readonly Schema           $schema,
	) {}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateWithToken(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->validator->validateCsrfToken($this->text('csrf_token')))
			return $this->responseBuilder->csrfTokenMismatch($response);

		$answer = $this->validate($response);
		if ($answer !== null)
			return $answer;

		$trigger = $this->schema->parse($this->input);
		if ($trigger === null)
			return $this->responseBuilder->invalidTriggerData($response);

		$this->trigger = $trigger;

		return null;
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validate(ResponseInterface $response): ?ResponseInterface
	{
		$this->itemId = $this->id('item_id');
		if ($this->itemId === 0)
			return $this->responseBuilder->invalidItemId($response);

		return null;
	}

	/**
	 * @throws UserException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function fetch(ResponseInterface $response): ?ResponseInterface
	{
		$trigger = $this->triggerService->fetchBeginTriggerByItemId($this->itemId);

		$this->templatePreparer->prepare($this->itemId);

		$data = [
			'item_data' => $trigger['item_data'],
			'touchable_medialist' => $trigger['touchable_media'],
			'html' => $this->templatePreparer->render()
		];

		return $this->responseBuilder->generalSuccess($response, $data);
	}


	/**
	 * @param ResponseInterface $response
	 * @return ResponseInterface|null
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function save(ResponseInterface $response): ?ResponseInterface
	{
		$this->triggerService->saveBeginTrigger($this->itemId, $this->trigger);

		return $this->responseBuilder->generalSuccess($response, []);
	}
}