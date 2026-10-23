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

namespace App\Modules\Users\Helper\Tokens;

use App\Framework\Validation\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Shared\User\UserTokenService;
use App\Modules\Users\Services\AclValidator;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Validates the input for the user token endpoints and executes the
 * requested action. validate() returns a response on error and null
 * when the input is valid.
 */
class Orchestrator
{
	/** @var array<string,mixed> */
	private array $input = [];
	private int $UID = 0;
	private string $purpose = '';

	public function __construct(
		private readonly ResponseBuilder $responseBuilder,
		private readonly UserSession $userSession,
		private readonly BaseValidator $validator,
		private readonly AclValidator $aclValidator,
		private readonly UserTokenService $userTokenService
	) {}

	/**
	 * @param array<string,mixed> $input
	 */
	public function setInput(array $input): static
	{
		$this->input = $input;
		return $this;
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function validate(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->validator->validateCsrfToken($this->input['csrf_token'] ?? ''))
			return $this->responseBuilder->csrfTokenMismatch($response);

		if (!$this->aclValidator->isModuleAdmin($this->userSession->getUID()))
			return $this->responseBuilder->noRights($response);

		$this->UID = (int) ($this->input['UID'] ?? 0);
		if ($this->UID === 0)
			return $this->responseBuilder->missingUID($response);

		// validated against TokenPurposes in UserTokenService
		$this->purpose = (string) ($this->input['purpose'] ?? '');
		if ($this->purpose === '')
			return $this->responseBuilder->missingPurpose($response);

		return null;
	}

	/**
	 * @throws Exception
	 * @throws \Exception
	 */
	public function refresh(ResponseInterface $response): ResponseInterface
	{
		$token = $this->userTokenService->refreshToken($this->UID, $this->purpose);
		if ($token === '')
			return $this->responseBuilder->tokenNotEditable($response);

		return $this->responseBuilder->generalSuccess($response, ['token' => bin2hex($token)]);
	}

	/**
	 * @throws Exception
	 * @throws \Exception
	 */
	public function delete(ResponseInterface $response): ResponseInterface
	{
		if ($this->userTokenService->deleteByUserPurpose($this->UID, $this->purpose) === 0)
			return $this->responseBuilder->tokenNotEditable($response);

		return $this->responseBuilder->generalSuccess($response, []);
	}
}
