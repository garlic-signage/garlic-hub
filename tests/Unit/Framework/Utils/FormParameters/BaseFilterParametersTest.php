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

namespace Tests\Unit\Framework\Utils\FormParameters;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Http\Session;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Framework\Utils\FormParameters\ScalarType;
use App\Framework\Validation\Sanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

class ConcreteFilterBaseParameters extends BaseFilterParameters
{
	public function __construct(Session $session, string $keyStore)
	{
		parent::__construct('testModule', new Sanitizer(), $session, $keyStore);
		$this->currentParameters = $this->defaultParameters;
		$this->userInputs        = [];
		$this->addParameter('name', ScalarType::STRING, '');
	}
}

/**
 * The filter, sort and page parameters of a datatable with the real Sanitizer and a session in memory.
 * The sort column ends in ORDER BY, so what is allowed there is a matter of security.
 */
class BaseFilterParametersTest extends TestCase
{
	private const string KEY = 'testKeyStore';

	private MemorySession $session;
	private ConcreteFilterBaseParameters $parameters;

	protected function setUp(): void
	{
		parent::setUp();
		$this->session    = new MemorySession();
		$this->parameters = new ConcreteFilterBaseParameters($this->session, self::KEY);
	}

	/**
	 * @param array<string,mixed> $inputs
	 * @param list<string> $sortable
	 * @return array<string,mixed> value of every parameter
	 * @throws ModuleException
	 */
	private function parse(array $inputs = [], array $sortable = ['name', 'created'], ?ConcreteFilterBaseParameters $parameters = null): array
	{
		$parameters ??= $this->parameters;
		$parameters->setParameterDefaultValues('name')->setUserInputs($inputs)->parseInputFilterAllUsers($sortable);

		$values = [];
		foreach ($parameters->getInputParametersKeys() as $key)
			$values[$key] = $parameters->getValueOfParameter($key);

		return $values;
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheFirstVisitGetsTheDefaults(): void
	{
		static::assertSame(
			['elements_per_page' => 10, 'elements_page' => 1, 'sort_column' => 'name', 'sort_order' => 'ASC', 'name' => ''],
			$this->parse()
		);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheInputOfTheVisitorIsTaken(): void
	{
		$values = $this->parse(['elements_per_page' => '25', 'elements_page' => '3', 'sort_column' => 'created', 'sort_order' => 'DESC', 'name' => ' Anna ']);

		static::assertSame(['elements_per_page' => 25, 'elements_page' => 3, 'sort_column' => 'created', 'sort_order' => 'DESC', 'name' => 'Anna'], $values);
	}

	/**
	 * A sort column which is not allowed falls back to the default: it would be part of the ORDER BY.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('hostileSortColumnProvider')]
	public function testASortColumnWhichIsNotAllowedFallsBackToTheDefault(string $column): void
	{
		static::assertSame('name', $this->parse(['sort_column' => $column])['sort_column']);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function hostileSortColumnProvider(): array
	{
		return [
			'unknown column'   => ['password'],
			'injection'        => ['name; DROP TABLE user_main'],
			'sub select'       => ['(SELECT password FROM user_main LIMIT 1)'],
			'other case'       => ['CREATED'],
			'empty'            => [''],
		];
	}

	/**
	 * The input is trimmed before it is compared, spaces around an allowed column do not matter.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testSpacesAroundAnAllowedSortColumnAreRemoved(): void
	{
		static::assertSame('created', $this->parse(['sort_column' => ' created '])['sort_column']);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheDefaultSortColumnIsAllowedEvenIfItIsNotListed(): void
	{
		static::assertSame('name', $this->parse(['sort_column' => 'name'], ['created'])['sort_column']);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('sortOrderProvider')]
	public function testTheSortOrderIsAscOrDesc(string $input, string $expected): void
	{
		static::assertSame($expected, $this->parse(['sort_order' => $input])['sort_order']);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function sortOrderProvider(): array
	{
		return [
			'DESC'        => ['DESC', 'DESC'],
			'desc'        => ['desc', 'DESC'],
			'ASC'         => ['ASC', 'ASC'],
			'asc'         => ['asc', 'ASC'],
			'unknown'     => ['sideways', 'ASC'],
			'injection'   => ['DESC; DROP TABLE x', 'ASC'],
			'empty'       => ['', 'ASC'],
		];
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('paginationProvider')]
	public function testThePaginationIsLimited(string $perPage, string $page, int $expectedPerPage, int $expectedPage): void
	{
		$values = $this->parse(['elements_per_page' => $perPage, 'elements_page' => $page]);

		static::assertSame($expectedPerPage, $values['elements_per_page']);
		static::assertSame($expectedPage, $values['elements_page']);
	}

	/**
	 * @return array<string, array{string, string, int, int}>
	 */
	public static function paginationProvider(): array
	{
		return [
			'normal'              => ['50', '4', 50, 4],
			'maximum'             => ['100', '1', 100, 1],
			'more than the maximum' => ['5000', '1', 100, 1],
			'zero per page'       => ['0', '1', 10, 1],
			'negative per page'   => ['-5', '1', 10, 1],
			'no number per page'  => ['many', '1', 10, 1],
			'page zero'           => ['10', '0', 10, 1],
			'negative page'       => ['10', '-3', 10, 1],
			'no number as page'   => ['10', 'last', 10, 1],
		];
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheValuesOfTheLastVisitAreRestoredFromTheSession(): void
	{
		$this->parse(['name' => 'Anna', 'elements_page' => '4', 'sort_column' => 'created', 'sort_order' => 'DESC']);

		// the next visit sends nothing, the session has the filter of the last one
		$next   = new ConcreteFilterBaseParameters($this->session, self::KEY);
		$values = $this->parse([], ['name', 'created'], $next);

		static::assertSame(['elements_per_page' => 10, 'elements_page' => 4, 'sort_column' => 'created', 'sort_order' => 'DESC', 'name' => 'Anna'], $values);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAChangedFilterStartsAtPageOne(): void
	{
		$this->parse(['name' => 'Anna', 'elements_page' => '4']);

		$values = $this->parse(['name' => 'Berta', 'elements_page' => '4'], ['name', 'created'], new ConcreteFilterBaseParameters($this->session, self::KEY));

		static::assertSame('Berta', $values['name']);
		static::assertSame(1, $values['elements_page']);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheSameFilterKeepsThePage(): void
	{
		$this->parse(['name' => 'Anna', 'elements_page' => '4']);

		$values = $this->parse(['name' => 'Anna', 'elements_page' => '5'], ['name', 'created'], new ConcreteFilterBaseParameters($this->session, self::KEY));

		static::assertSame(5, $values['elements_page']);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheFirstVisitWithAFilterKeepsThePageOfTheRequest(): void
	{
		static::assertSame(3, $this->parse(['name' => 'Anna', 'elements_page' => '3'])['elements_page']);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheParametersAreStoredInTheSession(): void
	{
		$this->parse(['name' => 'Anna']);

		$stored = $this->session->get(self::KEY);
		static::assertIsArray($stored);
		static::assertSame(['elements_per_page', 'elements_page', 'sort_column', 'sort_order', 'name'], array_keys($stored));
		static::assertSame('Anna', $stored['name']['value']);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testWithoutAKeyNothingIsStoredOrRestored(): void
	{
		$noStore = new ConcreteFilterBaseParameters($this->session, '');
		$this->session->set('', ['name' => ['value' => 'from the session', 'parsed' => true]]);

		$values = $this->parse(['elements_page' => '2'], ['name'], $noStore);

		static::assertFalse($noStore->hasSessionKeyStore());
		static::assertSame('', $values['name']);
		static::assertSame(['' => ['name' => ['value' => 'from the session', 'parsed' => true]]], $this->session->data);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testHasSessionKeyStore(): void
	{
		static::assertTrue($this->parameters->hasSessionKeyStore());
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAnEntryInTheSessionWhichIsNoArrayIsIgnored(): void
	{
		$this->session->data[self::KEY] = 'garbage';

		static::assertSame('', $this->parse()['name']);
	}

	/**
	 * The parameters come from the current definition, a stored one which is not defined any more is dropped.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoredParametersWhichAreNotDefinedAnyMoreAreIgnored(): void
	{
		$this->session->data[self::KEY] = ['old_param' => ['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => true, 'value' => 'old']];

		$values = $this->parse();

		static::assertArrayNotHasKey('old_param', $values);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testSetParameterDefaultValues(): void
	{
		$result = $this->parameters->setParameterDefaultValues('created');

		static::assertSame($this->parameters, $result);
		static::assertSame('created', $this->parameters->getDefaultValueOfParameter(BaseFilterParametersInterface::PARAMETER_SORT_COLUMN));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testSetElementsParametersToNull(): void
	{
		$this->parameters->setValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE, 3)
			->setValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE, 50);

		$result = $this->parameters->setElementsParametersToNull();

		static::assertSame($this->parameters, $result);
		static::assertSame(0, $this->parameters->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE));
		static::assertSame(0, $this->parameters->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testSetElementsParametersToNullWithoutThoseParametersChangesNothing(): void
	{
		$this->parameters->removeParameters([BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE, BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE]);

		$this->parameters->setElementsParametersToNull();

		static::assertFalse($this->parameters->hasParameter(BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE));
	}

	#[Group('units')]
	public function testAddCompany(): void
	{
		$this->parameters->addCompany();

		static::assertSame(
			['scalar_type' => ScalarType::STRING, 'default_value' => '', 'parsed' => false],
			$this->parameters->getInputParametersArray()[BaseFilterParametersInterface::PARAMETER_COMPANY_ID]
		);
	}
}
