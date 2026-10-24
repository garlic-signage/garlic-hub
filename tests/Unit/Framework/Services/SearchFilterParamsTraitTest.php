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

namespace Tests\Unit\Framework\Services;

use App\Framework\Services\AbstractBaseService;
use App\Framework\Services\SearchFilterParamsTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConcreteTraitService extends AbstractBaseService
{
	use SearchFilterParamsTrait;

	/**
	 * @return array<string,mixed>
	 */
	public function getCurrentFilterParameter(): array
	{
		return $this->currentFilterParams;
	}

	/**
	 * @param list<array<string,mixed>> $results
	 */
	public function setPublicAllResultData(int $total, array $results): static
	{
		return $this->setAllResultData($total, $results);
	}
}
class SearchFilterParamsTraitTest extends TestCase
{
	private ConcreteTraitService $searchFilterService;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$loggerStub = $this->createStub(LoggerInterface::class);
		$this->searchFilterService = new ConcreteTraitService($loggerStub);
	}

	#[Group('units')]
	public function testSetCurrentFilterParams(): void
	{
		$filterParams = ['keyword' => 'test', 'category' => 'example'];
		$this->searchFilterService->setCurrentFilterParams($filterParams);

		static::assertSame($filterParams, $this->searchFilterService->getCurrentFilterParameter());
	}

	#[Group('units')]
	public function testSetAndGetCurrentTotalResult(): void
	{
		$this->searchFilterService->setCurrentTotalResult(100);

		static::assertSame(100, $this->searchFilterService->getCurrentTotalResult());
	}

	#[Group('units')]
	public function testSetAndGetCompanyArray(): void
	{
		$companies = [1 => 'Company A', 2 => 'Company B', 3 => []];
		$this->searchFilterService->setCompanyArray($companies);

		static::assertSame($companies, $this->searchFilterService->getCompanyArray());
	}

	#[Group('units')]
	public function testSetAndGetAllowedCompanyIds(): void
	{
		$this->searchFilterService->setAllowedCompanyIds([3, 1]);

		static::assertSame([3, 1], $this->searchFilterService->getAllowedCompanyIds());
	}

	#[Group('units')]
	public function testSetAndGetCurrentFilterResults(): void
	{
		$filterResults = [['id' => 1, 'name' => 'Result 1'], ['id' => 2, 'name' => 'Result 2']];
		$this->searchFilterService->setCurrentFilterResults($filterResults);

		static::assertSame($filterResults, $this->searchFilterService->getCurrentFilterResults());
	}

	#[Group('units')]
	public function testTheSettersCanBeChained(): void
	{
		$result = $this->searchFilterService
			->setCurrentFilterParams([])
			->setCurrentTotalResult(1)
			->setCompanyArray([])
			->setAllowedCompanyIds([])
			->setCurrentFilterResults([]);

		static::assertSame($this->searchFilterService, $result);
	}

	#[Group('units')]
	public function testReturnFilteredDomainsArrayForCheckBoxes(): void
	{
		static::assertSame([], $this->searchFilterService->returnFilteredDomainsArrayForCheckBoxes());
	}

	#[Group('units')]
	public function testReturnFilteredCompaniesForDropdowns(): void
	{
		$this->searchFilterService->setAllowedCompanyIds([1, 2]);
		$this->searchFilterService->setCompanyArray([1 => 'Company A', 2 => 'Company B', 3 => 'Company C']);

		static::assertSame([0 => '-', 1 => 'Company A', 2 => 'Company B'], $this->searchFilterService->returnFilteredCompaniesForDropdowns());
	}

	#[Group('units')]
	public function testReturnFilteredCompaniesForDropdownsSkipsUnknownIdsAndKeepsTheKeys(): void
	{
		$this->searchFilterService->setAllowedCompanyIds([3, 9]);
		$this->searchFilterService->setCompanyArray([1 => 'Company A', 2 => 'Company B', 3 => 'Company C']);

		static::assertSame([0 => '-', 3 => 'Company C'], $this->searchFilterService->returnFilteredCompaniesForDropdowns());
	}

	#[Group('units')]
	public function testWithoutAllowedCompaniesOnlyTheDashRemains(): void
	{
		$this->searchFilterService->setCompanyArray([1 => 'Company A']);

		static::assertSame([0 => '-'], $this->searchFilterService->returnFilteredCompaniesForDropdowns());
		static::assertSame([0 => '-'], $this->searchFilterService->returnFilteredDomainsForDropdowns());
	}

	#[Group('units')]
	public function testReturnFilteredDomainsForDropdowns(): void
	{
		$this->searchFilterService->setAllowedCompanyIds([1, 2, 3]);
		$this->searchFilterService->setCompanyArray([1 => 'Company A', 2 => 'Company B', 3 => 'Company C']);

		static::assertSame([0 => '-', 2 => 'Company A', 4 => 'Company B', 8 => 'Company C'], $this->searchFilterService->returnFilteredDomainsForDropdowns());
	}

	/**
	 * The bit value belongs to the company, whatever the order of the allowed ids is.
	 */
	#[Group('units')]
	public function testTheBitValueFollowsTheCompanyAndNotTheOrderOfTheAllowedIds(): void
	{
		$this->searchFilterService->setAllowedCompanyIds([3, 1]);
		$this->searchFilterService->setCompanyArray([1 => 'Company A', 2 => 'Company B', 3 => 'Company C']);

		static::assertSame([0 => '-', 2 => 'Company A', 8 => 'Company C'], $this->searchFilterService->returnFilteredDomainsForDropdowns());
	}

	/**
	 * An allowed id without a company is skipped, it must not break the list.
	 */
	#[Group('units')]
	public function testReturnFilteredDomainsForDropdownsSkipsUnknownIds(): void
	{
		$this->searchFilterService->setAllowedCompanyIds([1, 9]);
		$this->searchFilterService->setCompanyArray([1 => 'Company A', 2 => 'Company B']);

		static::assertSame([0 => '-', 2 => 'Company A'], $this->searchFilterService->returnFilteredDomainsForDropdowns());
	}

	#[Group('units')]
	public function testSetAllResultData(): void
	{
		$results = [['id' => 1, 'name' => 'Result 1'], ['id' => 2, 'name' => 'Result 2']];

		$result = $this->searchFilterService->setPublicAllResultData(50, $results);

		static::assertSame($this->searchFilterService, $result);
		static::assertSame(50, $this->searchFilterService->getCurrentTotalResult());
		static::assertSame($results, $this->searchFilterService->getCurrentFilterResults());
	}
}
