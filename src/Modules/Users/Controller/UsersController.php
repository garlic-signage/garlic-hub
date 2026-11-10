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
use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\ScalarType;
use App\Modules\Users\Helper\Datatable\Parameters;
use App\Modules\Users\Services\UsersAdminService;
use App\Modules\Users\Services\UsersDatatableService;
use App\Shared\User\UserStatus;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use App\Modules\Auth\UserSession;
use App\Modules\Users\Services\UserActions;
use App\Shared\User\UsersService;

/**
 * UsersController handles user-related operations and interacts with services
 * for processing datatables and parameters.
 */
readonly class UsersController
{
	public function __construct(
		private UsersDatatableService $usersDatatableService,
		private UsersAdminService $usersAdminService,
		private Parameters $parameters,
		private JsonResponseHandler $responseHandler,
		private UserActions $userActions,
		private UsersService $usersService,
		private UserSession $userSession
	) {}

	/**
	 * The context menu of a user asks what the current user may do. Asking is no error:
	 * without rights the answer is "nothing".
	 *
	 * @param array<string,string> $args
	 * @throws Throwable
	 */
	public function determineRights(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		// @infection-ignore-all the route always has the UID
		$UID  = (int) ($args['UID'] ?? 0);
		// @infection-ignore-all there is no user 0: >= 0 finds no user either
		$main = $UID > 0 ? $this->usersService->getUserById($UID)->getMain() : [];
		if ($main === [])
			return $this->responseHandler->jsonSuccess($response, ['can_edit' => false, 'can_rights' => false, 'can_delete' => false]);

		$actions = $this->userActions->determine($this->userSession->getUID(), $UID, (int) $main['company_id'], (int) $main['status']);

		return $this->responseHandler->jsonSuccess($response, $actions);
	}

	/**
	 * @throws Exception
	 * @throws Throwable
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$input = RequestInput::of($request->getParsedBody());

		$UID = $input->id('UID');
		if ($UID === 0)
			return $this->responseHandler->jsonError($response, 'No UID');

		$this->usersAdminService->deleteUser($UID);

		return $this->responseHandler->jsonSuccess($response);
	}

	/**
	 * Returns the plain list without a success envelope, because the autocomplete expects it.
	 *
	 * @param array<string,string> $args
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function findByName(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		// we want only registered user and higher
		$this->parameters->addParameter(Parameters::PARAMETER_FROM_STATUS, ScalarType::INT, UserStatus::REGISTERED->value);

		$this->parameters->setUserInputs($args);
		$this->parameters->parseInputAllParameters();

		$this->usersDatatableService->loadDatatable();
		$results = [];
		foreach ($this->usersDatatableService->getCurrentFilterResults() as $value)
		{
			$results[] = ['id' => $value['UID'], 'name' => $value['username']];
		}

		return $this->responseHandler->jsonResponse($response, $results);
	}

}
