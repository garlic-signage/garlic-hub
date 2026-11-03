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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Auth\UserSession;
use App\Shared\Acl\AclGrantService;
use App\Shared\Acl\AclSections;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

/**
 * The share dialog of a unit (player, playlist, template ...). One controller for every module,
 * the AclGrantService decides who may share what.
 */
readonly class UnitSharesController
{
	public function __construct(
		private AclGrantService $aclGrantService,
		private UserSession $userSession,
		private Translator $translator,
		private JsonResponseHandler $responseHandler
	) {}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$shares = $this->aclGrantService->findUnitShares($this->userSession->getUID(), $args['module'] ?? '', $args['unit_id'] ?? '');

		$users = [];
		foreach ($shares['users'] as $user)
		{
			$users[] = [
				'UID'      => $user['UID'],
				'username' => $user['username'],
				'roles'    => array_map(fn(AclSections $role) => $role->value, $user['roles']),
				'role'     => $user['role']->value ?? ''
			];
		}

		return $this->responseHandler->jsonSuccess($response, [
			'roles'  => array_map(fn(AclSections $role) => $role->value, $shares['roles']),
			'users'  => $users,
			'labels' => [
				'title'    => $this->translator->translate('share_unit', 'users'),
				'filter'   => $this->translator->translate('share_filter', 'users'),
				'no_users' => $this->translator->translate('share_no_users', 'users'),
				'none'     => $this->translator->translate('role_none', 'users'),
				'viewer'   => $this->translator->translate('role_viewer', 'users'),
				'editor'   => $this->translator->translate('role_editor', 'users'),
				'close'    => $this->translator->translate('close', 'main')
			]
		]);
	}

	/**
	 * An empty role removes the role of the user on the unit.
	 *
	 * @param array<string,string> $args
	 * @throws Throwable
	 */
	public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$input = RequestInput::of($request->getParsedBody());
		$value = $input->text('role');
		$role  = $value === '' ? null : AclSections::tryFrom($value);
		if ($value !== '' && $role === null)
			throw new TranslatableModuleException('users', 'role_not_available', ['role' => $value]);

		$this->aclGrantService->setUnitRole($this->userSession->getUID(), $input->id('UID'), $args['module'] ?? '', $args['unit_id'] ?? '', $role);

		return $this->responseHandler->jsonSuccess($response);
	}
}
