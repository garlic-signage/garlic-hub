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

namespace App\Modules\System\Helper\Audit;

use App\Framework\Database\Compare;
use App\Framework\Database\Datatable\FilterDefinition;
use App\Framework\Database\Where;
use App\Shared\Audit\AuditAction;

/**
 * The query of the audit log. Only the module admin of system sees it, so there is no user scope.
 */
readonly class AuditFilterDefinition implements FilterDefinition
{
	private const string TABLE = 'audit_log';

	public function table(): string
	{
		return self::TABLE;
	}

	public function joins(): array
	{
		return ['user_main' => 'user_main.UID = ' . self::TABLE . '.UID'];
	}

	public function userJoins(): array
	{
		return $this->joins();
	}

	public function selects(): array
	{
		return [self::TABLE . '.id', self::TABLE . '.created_at', self::TABLE . '.UID', self::TABLE . '.module', self::TABLE . '.action',
			self::TABLE . '.unit_id', self::TABLE . '.target_UID', self::TABLE . '.details'];
	}

	public function selectsWithUser(): array
	{
		return array_merge($this->selects(), ['user_main.username']);
	}

	public function whereForFiltering(array $fields): array
	{
		$where = [];
		$from  = $this->dateOf($fields[Parameters::PARAMETER_DATE_FROM]['value'] ?? '');
		if ($from !== null)
			$where['created_at_from'] = Where::compare(self::TABLE . '.created_at', Compare::GreaterOrEqual, $from . ' 00:00:00');

		$to = $this->dateOf($fields[Parameters::PARAMETER_DATE_TO]['value'] ?? '');
		if ($to !== null)
			$where['created_at_to'] = Where::compare(self::TABLE . '.created_at', Compare::LessOrEqual, $to . ' 23:59:59');

		// who: the user who did it, or what was typed in at a failed login
		$username = $fields[Parameters::PARAMETER_USERNAME]['value'] ?? '';
		if (is_string($username) && $username !== '')
			$where['user_main.username'] = Where::anyOf(
				Where::contains('user_main.username', $username),
				Where::allOf(
					Where::equals(self::TABLE . '.action', AuditAction::LOGIN_FAILED->value),
					Where::contains(self::TABLE . '.details', '"username":"*' . $username)
				)
			);

		$module = $fields[Parameters::PARAMETER_MODULE]['value'] ?? '';
		if (is_string($module) && $module !== '')
			$where[self::TABLE . '.module'] = Where::equals(self::TABLE . '.module', $module);

		$action = $fields[Parameters::PARAMETER_ACTION]['value'] ?? '';
		if (is_string($action) && AuditAction::tryFrom($action) !== null)
			$where[self::TABLE . '.action'] = Where::equals(self::TABLE . '.action', $action);

		return $where;
	}

	/**
	 * A day as YYYY-MM-DD, anything else is no filter.
	 */
	private function dateOf(mixed $value): ?string
	{
		if (!is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', trim($value), $date) !== 1)
			return null;

		return checkdate((int) $date[2], (int) $date[3], (int) $date[1]) ? trim($value) : null;
	}
}
