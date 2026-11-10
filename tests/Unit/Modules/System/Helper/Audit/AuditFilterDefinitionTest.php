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

namespace Tests\Unit\Modules\System\Helper\Audit;

use App\Framework\Database\Compare;
use App\Framework\Database\Where;
use App\Modules\System\Helper\Audit\AuditFilterDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The query with the database is tested in tests/Integration/Modules/System/AuditListTest.
 */
class AuditFilterDefinitionTest extends TestCase
{
	private AuditFilterDefinition $definition;

	protected function setUp(): void
	{
		parent::setUp();
		$this->definition = new AuditFilterDefinition();
	}

	/**
	 * @param array<string,mixed> $values
	 * @return array<string,Where>
	 */
	private function where(array $values): array
	{
		return $this->definition->whereForFiltering(array_map(static fn(mixed $value): array => ['value' => $value], $values));
	}

	#[Group('units')]
	public function testTableJoinsAndSelects(): void
	{
		$selects = ['audit_log.id', 'audit_log.created_at', 'audit_log.UID', 'audit_log.module', 'audit_log.action',
			'audit_log.unit_id', 'audit_log.target_UID', 'audit_log.details'];

		static::assertSame('audit_log', $this->definition->table());
		static::assertSame(['user_main' => 'user_main.UID = audit_log.UID'], $this->definition->joins());
		static::assertSame(['user_main' => 'user_main.UID = audit_log.UID'], $this->definition->userJoins());
		static::assertSame($selects, $this->definition->selects());
		static::assertSame([...$selects, 'user_main.username'], $this->definition->selectsWithUser());
	}

	#[Group('units')]
	public function testNoFieldsAreNoFilter(): void
	{
		static::assertSame([], $this->where([]));
		static::assertSame([], $this->where(['date_from' => '', 'date_to' => '', 'username' => '', 'module' => '', 'action' => '']));
	}

	#[Group('units')]
	public function testAllFiltersTogether(): void
	{
		static::assertEquals([
			'created_at_from'    => Where::compare('audit_log.created_at', Compare::GreaterOrEqual, '2024-02-01 00:00:00'),
			'created_at_to'      => Where::compare('audit_log.created_at', Compare::LessOrEqual, '2024-02-29 23:59:59'),
			'user_main.username' => Where::anyOf(
				Where::contains('user_main.username', 'anna'),
				Where::allOf(
					Where::equals('audit_log.action', 'login_failed'),
					Where::contains('audit_log.details', '"username":"*anna')
				)
			),
			'audit_log.module'   => Where::equals('audit_log.module', 'playlists'),
			'audit_log.action'   => Where::equals('audit_log.action', 'role_granted'),
		], $this->where(['date_from' => ' 2024-02-01 ', 'date_to' => '2024-02-29', 'username' => 'anna', 'module' => 'playlists', 'action' => 'role_granted']));
	}

	/**
	 * @return array<string,array{mixed}>
	 */
	public static function noDates(): array
	{
		return [
			'no day of the month'  => ['2024-02-30'],
			'no leap year'         => ['2023-02-29'],
			'no month'             => ['2024-13-01'],
			'text before'          => ['x2024-02-01'],
			'text after'           => ['2024-02-01x'],
			'german'               => ['01.02.2024'],
			'no string'            => [20240201],
		];
	}

	#[Group('units')]
	#[DataProvider('noDates')]
	public function testAnythingElseThanADayIsNoFilter(mixed $date): void
	{
		static::assertSame([], $this->where(['date_from' => $date, 'date_to' => $date]));
	}

	#[Group('units')]
	public function testOnlyStringsAndKnownActionsAreFilters(): void
	{
		static::assertSame([], $this->where(['username' => 5, 'module' => 5, 'action' => 'no_action']));
	}
}
