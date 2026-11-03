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

namespace App\Modules\Users\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Auth\UserSession;
use App\Modules\Users\UserStatus;
use App\Shared\Acl\AclGrantService;
use App\Shared\Acl\AclSections;
use App\Shared\User\UsersService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Throwable;

/**
 * The rights page of a user: a tab per module in which the current user may grant rights,
 * with the roles and the units of the user. The units are changed with /async/acl (UnitSharesController).
 */
readonly class UserRightsController
{
	public function __construct(
		private AclGrantService $aclGrantService,
		private UsersService $usersService,
		private UserSession $userSession,
		private Translator $translator,
		private JsonResponseHandler $responseHandler,
		private Messages $flash
	) {}

	/**
	 * @param array<string,string> $args
	 * @throws Throwable
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$UID     = (int) ($args['UID'] ?? 0);
		$granter = $this->userSession->getUID();
		$main    = $UID > 0 ? $this->usersService->getUserById($UID)->getMain() : [];
		// nobody changes his own rights, a deleted user has none
		if ($main === [] || $UID === $granter || (int) $main['status'] === UserStatus::DELETED->value)
			return $this->redirectWithError($response);

		$modules = $this->aclGrantService->findGrantableModules($granter, (int) $main['company_id']);
		if ($modules === [])
			return $this->redirectWithError($response);

		$tabs = [];
		foreach ($modules as $module)
		{
			$tabs[] = $this->prepareTab($module, $this->aclGrantService->findUserRights($granter, $UID, $module));
		}

		$title = $this->translator->translate('rights', 'users') . ': ' . $main['username'];

		return new PageResponse([
			'main_layout' => [
				'LANG_PAGE_TITLE' => $title,
				'additional_css'  => ['/css/users/rights.css'],
				'footer_modules'  => ['/js/users/rights/init.js']
			],
			'this_layout' => [
				'template' => 'users/rights',
				'data'     => [
					'LANG_PAGE_HEADER'         => $title,
					'UID'                      => $UID,
					'LANG_ADD_UNIT'            => $this->translator->translate('add_unit', 'users'),
					'LANG_UNITS'               => $this->translator->translate('units', 'users'),
					'LANG_NO_UNITS'            => $this->translator->translate('no_units', 'users'),
					'LANG_CONFIRM_REMOVE_ROLE' => $this->translator->translate('confirm_remove_role', 'users'),
					'LANG_ROLE_NONE'           => $this->translator->translate('role_none', 'users'),
					'tabs'                     => $tabs
				]
			]
		]);
	}

	/**
	 * Sets or removes a role bit. Removing editor or viewer removes the units of the role.
	 *
	 * @param array<string,string> $args
	 * @throws Throwable
	 */
	public function updateRole(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$input = RequestInput::of($request->getParsedBody());
		$role  = AclSections::tryFrom($input->text('role'));
		if ($role === null)
			throw new TranslatableModuleException('users', 'role_not_available', ['role' => $input->text('role')]);

		$this->aclGrantService->setRole($this->userSession->getUID(), (int) ($args['UID'] ?? 0), $input->text('module'), $role, $input->text('granted') === '1');

		return $this->responseHandler->jsonSuccess($response);
	}

	/**
	 * @param array{roles: list<array{role:AclSections, granted:bool}>, units: list<array{id:string, name:string, role:AclSections}>} $rights
	 * @return array<string,mixed>
	 */
	private function prepareTab(string $module, array $rights): array
	{
		$roles     = [];
		$unitRoles = [];
		foreach ($rights['roles'] as $right)
		{
			$roles[] = [
				'ROLE'      => $right['role']->value,
				'LANG_ROLE' => $this->translator->translate('role_' . $right['role']->value, 'users'),
				'CHECKED'   => $right['granted'] ? 'checked' : ''
			];
			// a unit can get the roles the user has
			if ($right['granted'] && in_array($right['role'], [AclSections::VIEWER, AclSections::EDITOR], true))
				$unitRoles[] = $right['role'];
		}

		$units = [];
		foreach ($rights['units'] as $unit)
		{
			$units[] = ['UNIT_ID' => $unit['id'], 'UNIT_NAME' => $unit['name'], 'unit_roles' => $this->prepareUnitRoles($unitRoles, $unit['role'])];
		}

		return [
			'MODULE'      => $module,
			'LANG_MODULE' => $this->translator->translate($module, 'menu'),
			'roles'       => $roles,
			// units only for a user who is editor or viewer
			'has_units'   => $unitRoles !== [],
			'units'       => $units,
			'NO_UNITS'    => $units === [] ? '' : 'hidden',
			'unit_roles'  => $this->prepareUnitRoles($unitRoles, null)
		];
	}

	/**
	 * @param list<AclSections> $unitRoles
	 * @return list<array{VALUE:string, LANG_ROLE:string, SELECTED:string}>
	 */
	private function prepareUnitRoles(array $unitRoles, ?AclSections $current): array
	{
		$options = [];
		foreach ([AclSections::VIEWER, AclSections::EDITOR] as $role)
		{
			if (in_array($role, $unitRoles, true) || $role === $current)
				$options[] = ['VALUE' => $role->value, 'LANG_ROLE' => $this->translator->translate('role_' . $role->value, 'users'), 'SELECTED' => $role === $current ? 'selected' : ''];
		}

		return $options;
	}

	private function redirectWithError(ResponseInterface $response): ResponseInterface
	{
		$this->flash->addMessage('error', $this->translator->translate('rights_not_grantable', 'users'));
		return $response->withHeader('Location', '/users')->withStatus(302);
	}
}
