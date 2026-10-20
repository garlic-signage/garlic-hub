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

namespace App\Framework\Services;

/**
 * trait to store the filter/search parameters
 */
trait SearchFilterParamsTrait
{
	/**
	 * @var array<string,mixed>
	 */
	protected array $currentFilterParams = [];
	protected int $currentTotalResult = 0;

	/**
	 * @var int[]
	 */
	protected array $allowedCompanyIds = [];
	/**
	 * @var list<array<string,mixed>>
	 */
	protected array $currentFilterResults = [];
	/**
	 * @var array<int,mixed>
	 */
	protected array $companies = [];

	/**
	 * @param array<string,mixed> $ar_search
	 */
	public function setCurrentFilterParams(array $ar_search): static
	{
		$this->currentFilterParams = $ar_search;
		return $this;
	}

	public function setCurrentTotalResult(int $total_result): static
	{
		$this->currentTotalResult = $total_result;
		return $this;
	}

	public function getCurrentTotalResult(): int
	{
		return $this->currentTotalResult;
	}

	/**
	 * @param array<int,mixed> $companies
	 */
	public function setCompanyArray(array $companies): static
	{
		$this->companies = $companies;
		return $this;
	}

	/**
	 * @return array<int,mixed>
	 */
	public function getCompanyArray(): array
	{
		return $this->companies;
	}

	/**
	 * @param int[] $ar_company_ids
	 */
	public function setAllowedCompanyIds(array $ar_company_ids): static
	{
		$this->allowedCompanyIds = $ar_company_ids;
		return $this;
	}

	/**
	 * @return int[]
	 */
	public function getAllowedCompanyIds(): array
	{
		return $this->allowedCompanyIds;
	}

	/**
	 * @param list<array<string,mixed>> $results
	 * @return $this
	 */
	public function setCurrentFilterResults(array $results): static
	{
		$this->currentFilterResults = $results;
		return $this;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function getCurrentFilterResults(): array
	{
		return $this->currentFilterResults;
	}

	/**
	 * @return array<int,string>|array<empty,empty>
	 */
	public function returnFilteredDomainsArrayForCheckBoxes(): array
	{
		$domains = [];
// Todo: Find a solution for getUser which is here userEntity;
/*		foreach($this->getUser()->getDomainsArray() as $key => $value)
		{
			if (in_array($value['company_id'], $this->getAllowedCompanyIds()))
			{
				$domains[$key] = $value;
			}
		}
*/
		return $domains;
	}

	/**
	 * this method adds an all (-) for dropdowns at the first position
	 * If you don't need this, call returnFilteredDomainsArray()
	 *
	 * the return array is
	 * - key: domain_id
	 * - value: domain_name
	 *
	 * NOT usable for createBinaryDomainCheckboxGroup()
	 *
	 * @return array<int,string>
	 */
	public function returnFilteredCompaniesForDropdowns(): array
	{
		// need domain_id as a key, not value
		$allowed_ids       = array_flip($this->getAllowedCompanyIds());
		$allowed_companies = array_intersect_key($this->getCompanyArray(), $allowed_ids);

		// array_merge re-numerate keys+
		// look at https://www.php.net/manual/de/function.array-merge.php
		// we need
		return ['-'] + $allowed_companies;
	}

	/**
	 * this is basically the same as above (including of unshifted '-' value), but here we are using
	 * the key as pow(2, domain_id). Bit values for comparing AND with our stored value in DB
	 *
	 * example:
	 * array (2 => 'first company', 4 => 'second company', 8 => 'third company', ...)
	 *
	 * @return array<int,string>
	 */
	public function returnFilteredDomainsForDropdowns(): array
	{
		// need domain_id as a key, not value
		$allowed_ids       = array_flip($this->getAllowedCompanyIds());
		$allowed_companies = array_intersect_key($this->getCompanyArray(), $allowed_ids);

		$domain_ids = array_map( function($value) { return pow(2, $value); }, $this->getAllowedCompanyIds());

		// notice: we can not use array_merge() because this will renumber any numeric key
		return ['-'] + array_combine($domain_ids, $allowed_companies);
	}

	/**
	 * @param int $total
	 * @param list<array<string,mixed>> $results
	 */
	protected function setAllResultData(int $total, array $results): static
	{
		return $this->setCurrentTotalResult($total)
					->setCurrentFilterResults($results);
	}
}
