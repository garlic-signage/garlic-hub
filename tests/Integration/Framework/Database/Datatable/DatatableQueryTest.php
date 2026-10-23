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

namespace Tests\Integration\Framework\Database\Datatable;

use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Database\Datatable\FilterConditions;
use App\Framework\Database\Datatable\FilterDefinition;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Modules\Templates\Helper\Datatable\TemplatesFilterDefinition;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

/**
 * The list query of all datatables, with the filter definition of the templates. Fixtures: users 1 admin,
 * 2 anna, 3 bernd (all company 1) and 4 carla (company 2). Templates: 1 Admin Vorlage (UID 1),
 * 2 Annas Vorlage (UID 2), 3 Bernds Vorlage (UID 3), 4 Annas zweite (UID 2), 5 Carlas Vorlage (UID 4).
 */
#[Group('db')]
class DatatableQueryTest extends RepositoryTestCase
{
	private DatatableQuery $query;
	private FilterConditions $conditions;
	private TemplatesFilterDefinition $definition;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
		$this->connection->executeStatement("INSERT INTO user_main (UID, company_id, status, locale, username, email) VALUES (4, 2, 3, 'en_US', 'carla', 'carla@example.com')");
		$this->connection->executeStatement("INSERT INTO templates (template_id, UID, visibility, name) VALUES (4, 2, 'private', 'Annas zweite'), (5, 4, 'private', 'Carlas Vorlage')");

		$this->conditions = new FilterConditions();
		$this->definition = new TemplatesFilterDefinition($this->conditions);
		$this->query      = new DatatableQuery($this->connection, $this->conditions);
	}

	/**
	 * @param array<string,mixed> $fields
	 * @return array<string,array<string,mixed>>
	 */
	private function fields(array $fields = []): array
	{
		$result = [];
		foreach ($fields as $key => $value)
			$result[$key] = ['value' => $value];

		return $result;
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return list<int>
	 */
	private function ids(array $rows): array
	{
		return array_map('intval', array_column($rows, 'template_id'));
	}

	/**
	 * @throws Exception
	 */
	public function testModuleAdminSeesEveryRowWithTheOwner(): void
	{
		$rows = $this->query->findForModuleAdmin($this->definition, $this->fields());

		static::assertSame(5, $this->query->countForModuleAdmin($this->definition, $this->fields()));
		static::assertEqualsCanonicalizing([1, 2, 3, 4, 5], $this->ids($rows));
		$byId = array_column($rows, null, 'template_id');
		static::assertSame('anna', $byId[2]['username']);
		static::assertSame(2, (int) $byId[5]['company_id']);
		static::assertSame('moduleadmin', end($rows[0]));
	}

	/**
	 * @throws Exception
	 */
	public function testUserSeesOnlyHisRowsAndNoOwnerColumns(): void
	{
		$rows = $this->query->findForUser($this->definition, $this->fields(), 2);

		static::assertSame(2, $this->query->countForUser($this->definition, $this->fields(), 2));
		static::assertEqualsCanonicalizing([2, 4], $this->ids($rows));
		static::assertArrayNotHasKey('username', $rows[0]);
		static::assertSame('user', end($rows[0]));
		static::assertSame([], $this->query->findForUser($this->definition, $this->fields(), 99));
	}

	/**
	 * @throws Exception
	 */
	public function testFiltersNarrowTheResultAndUseWildcards(): void
	{
		$byName = $this->query->findForModuleAdmin($this->definition, $this->fields(['name' => 'annas*']));

		static::assertEqualsCanonicalizing([2, 4], $this->ids($byName));
		static::assertSame(1, $this->query->countForModuleAdmin($this->definition, $this->fields(['name' => 'Bernd'])));
		static::assertSame(0, $this->query->countForModuleAdmin($this->definition, $this->fields(['name' => 'gibt es nicht'])));
	}

	/**
	 * @throws Exception
	 */
	public function testFiltersByOwnerAndCompanyForAdmins(): void
	{
		static::assertEqualsCanonicalizing([2, 4], $this->ids($this->query->findForModuleAdmin($this->definition, $this->fields(['username' => 'ann']))));
		static::assertSame([5], $this->ids($this->query->findForModuleAdmin($this->definition, $this->fields(['company_id' => 2]))));
	}

	/**
	 * A filter never widens what a user sees: his own rows stay the limit.
	 *
	 * @throws Exception
	 */
	public function testAUserFilterStaysInsideHisOwnRows(): void
	{
		$rows = $this->query->findForUser($this->definition, $this->fields(['name' => 'Vorlage']), 2);

		static::assertSame([2], $this->ids($rows));
		static::assertSame(1, $this->query->countForUser($this->definition, $this->fields(['name' => 'Vorlage']), 2));
	}

	/**
	 * @throws Exception
	 */
	public function testSortingAscendingAndDescending(): void
	{
		$asc  = $this->query->findForModuleAdmin($this->definition, $this->fields(['sort_column' => 'name', 'sort_order' => 'ASC']));
		$desc = $this->query->findForModuleAdmin($this->definition, $this->fields(['sort_column' => 'name', 'sort_order' => 'desc']));

		static::assertSame([1, 2, 4, 3, 5], $this->ids($asc));
		static::assertSame([5, 3, 4, 2, 1], $this->ids($desc));
	}

	/**
	 * @throws Exception
	 */
	public function testSortingByTheOwner(): void
	{
		$rows = $this->query->findForModuleAdmin($this->definition, $this->fields(['sort_column' => 'username', 'sort_order' => 'DESC']));

		static::assertSame(['carla', 'bernd', 'anna', 'anna', 'admin'], array_column($rows, 'username'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function badSortColumns(): array
	{
		return [
			'injection'   => ['name; DROP TABLE templates'],
			'subquery'    => ['(SELECT password FROM user_main)'],
			'with a dot'  => ['templates.name'],
			'with quotes' => ['"name"'],
			'a space'     => ['name DESC, UID'],
		];
	}

	#[DataProvider('badSortColumns')]
	public function testAnInvalidSortColumnIsIgnoredAndNothingIsExecuted(string $column): void
	{
		$rows = $this->query->findForModuleAdmin($this->definition, $this->fields(['sort_column' => $column]));

		static::assertEqualsCanonicalizing([1, 2, 3, 4, 5], $this->ids($rows));
		static::assertSame(5, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM templates'));
	}

	/**
	 * @throws Exception
	 */
	public function testAnInvalidSortOrderFallsBackToAscending(): void
	{
		$rows = $this->query->findForModuleAdmin($this->definition, $this->fields(['sort_column' => 'name', 'sort_order' => 'sideways; DROP TABLE templates']));

		static::assertSame([1, 2, 4, 3, 5], $this->ids($rows));
	}

	/**
	 * @throws Exception
	 */
	public function testPaging(): void
	{
		$sort = ['sort_column' => 'template_id', 'sort_order' => 'ASC'];

		static::assertSame([1, 2], $this->ids($this->query->findForModuleAdmin($this->definition, $this->fields($sort + [BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE => 2, BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE => 1]))));
		static::assertSame([3, 4], $this->ids($this->query->findForModuleAdmin($this->definition, $this->fields($sort + [BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE => 2, BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE => 2]))));
		static::assertSame([5], $this->ids($this->query->findForModuleAdmin($this->definition, $this->fields($sort + [BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE => 2, BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE => 3]))));
		// without a page size everything comes, the count does not depend on the page
		static::assertCount(5, $this->query->findForModuleAdmin($this->definition, $this->fields($sort)));
		static::assertSame(5, $this->query->countForModuleAdmin($this->definition, $this->fields([BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE => 2])));
	}

	/**
	 * A reseller sees what belongs to him and to the companies he is responsible for.
	 *
	 * @throws Exception
	 */
	public function testResellerSeesOwnRowsAndTheRowsOfHisCompanies(): void
	{
		// anna (company 1) is the reseller of company 2: her rows and the rows of carla, not the ones of bernd
		$rows = $this->query->findForReseller($this->definition, [2], $this->fields(), 2);

		static::assertEqualsCanonicalizing([2, 4, 5], $this->ids($rows));
		static::assertSame(3, $this->query->countForReseller($this->definition, [2], $this->fields(), 2));
		static::assertSame('subadmin', end($rows[0]));
	}

	/**
	 * @throws Exception
	 */
	public function testResellerWithoutCompaniesSeesOnlyHisOwnRows(): void
	{
		static::assertEqualsCanonicalizing([2, 4], $this->ids($this->query->findForReseller($this->definition, [], $this->fields(), 2)));
		static::assertSame(2, $this->query->countForReseller($this->definition, [], $this->fields(), 2));
	}

	/**
	 * @throws Exception
	 */
	public function testResellerFilterStaysInsideTheScope(): void
	{
		$rows = $this->query->findForReseller($this->definition, [2], $this->fields(['name' => 'Vorlage']), 2);

		// Admin Vorlage and Bernds Vorlage match the filter, but are not in the scope
		static::assertEqualsCanonicalizing([2, 5], $this->ids($rows));
	}

	/**
	 * A definition which filters with OR must not let the rows of others through the scope.
	 *
	 * @throws Exception
	 */
	public function testAnOrFilterDoesNotBypassTheScope(): void
	{
		$orDefinition = new class($this->conditions) implements FilterDefinition {
			public function __construct(private readonly FilterConditions $conditions) {}

			public function table(): string { return 'templates'; }
			public function joins(): array { return ['user_main' => 'user_main.UID = templates.UID']; }
			public function userJoins(): array { return []; }
			public function selects(): array { return ['templates.*']; }
			public function selectsWithUser(): array { return $this->selects(); }
			public function whereForFiltering(array $fields): array
			{
				return [
					'templates.name'        => $this->conditions->whereClause('%Admin%', 'LIKE', 'OR'),
					'templates.description' => $this->conditions->whereClause('%Admin%', 'LIKE', 'OR'),
				];
			}
		};

		// without the scope the filter finds template 1 (name) and nothing else: "von Admin" is in the description of 1
		static::assertSame([1], $this->ids($this->query->findForModuleAdmin($orDefinition, [])));
		// anna has no part in it: the scope keeps her out of the rows of the admin
		static::assertSame([], $this->query->findForReseller($orDefinition, [], [], 2));
		static::assertSame(0, $this->query->countForReseller($orDefinition, [], [], 2));
		// the admin as a reseller sees his own row
		static::assertSame([1], $this->ids($this->query->findForReseller($orDefinition, [], [], 1)));
		static::assertSame([], $this->query->findForUser($orDefinition, [], 2));
	}

	/**
	 * @throws Exception
	 */
	public function testExpressionsAreAppliedAsTheyAre(): void
	{
		$definition = new class($this->conditions) implements FilterDefinition {
			public function __construct(private readonly FilterConditions $conditions) {}

			public function table(): string { return 'templates'; }
			public function joins(): array { return []; }
			public function userJoins(): array { return []; }
			public function selects(): array { return ['templates.*']; }
			public function selectsWithUser(): array { return $this->selects(); }
			public function whereForFiltering(array $fields): array
			{
				return ['e' => $this->conditions->expression("templates.visibility = 'public'")];
			}
		};

		static::assertSame([1], $this->ids($this->query->findForModuleAdmin($definition, [])));
	}
}
