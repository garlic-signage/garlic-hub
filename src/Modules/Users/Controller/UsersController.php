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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\ScalarType;
use App\Modules\Auth\UserSession;
use App\Modules\Users\Helper\Datatable\Parameters;
use App\Modules\Users\Services\UsersDatatableService;
use App\Modules\Users\UserStatus;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * UsersController handles user-related operations and interacts with services
 * for processing datatables and parameters.
 */
readonly class UsersController
{
	public function __construct(
		private UsersDatatableService $usersDatatableService,
		private Parameters $parameters,
		private UserSession $userSession,
		private JsonResponseHandler $responseHandler
	) {}

	/**
	 * Returns the plain list without success envelope, because the autocomplete expects it.
	 *
	 * @param array<string,string> $args
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function findByName(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		// we want only registered user and higher
		$this->parameters->addParameter(Parameters::PARAMETER_FROM_STATUS, ScalarType::INT, UserStatus::REGISTERED->value);

		$this->parameters->setUserInputs($args);
		$this->parameters->parseInputAllParameters();

		$this->usersDatatableService->setUID($this->userSession->getUID());
		$this->usersDatatableService->loadDatatable();
		$results = [];
		foreach ($this->usersDatatableService->getCurrentFilterResults() as $value)
		{
			$results[] = ['id' => $value['UID'], 'name' => $value['username']];
		}

		return $this->responseHandler->jsonResponse($response, $results);
	}

}
