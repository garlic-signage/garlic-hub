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

namespace App\Framework\Utils\Datatable\Paginator;

/**
 * The Builder class is responsible for configuring and generating pagination-related data, such as pager links and dropdown settings.
 */
class Builder
{
	private int $currentPage;
	private int $itemsPerPage;
	private int $totalItems;
	private bool $usePager;
	private bool $shortened;
	/** @var list<array{name: string, page: int}>  */
	private array $pagerLinks;
	/** @var array{min: int, max: int, steps: int} */
	private array $dropDownSettings;

	public function configure(int $currentPage, int $itemsPerPage, int $totalItems, bool $usePager, bool $shortened): static
	{
		$this->currentPage  = max(1, $currentPage);
		$this->itemsPerPage = max(1, $itemsPerPage);
		$this->totalItems   = max(0, $totalItems);
		$this->usePager     = $usePager;
		$this->shortened    = $shortened;

		return $this;
	}

	/**
	 * @return list<array{name: string, page: int}>
	 */
	public function getPagerLinks(): array
	{
		return $this->pagerLinks;
	}

	/**
	 * @return array{min: int, max: int, steps: int}
	 */
	public function getDropDownSettings(): array
	{
		return $this->dropDownSettings;
	}

	public function createDropDown(int $min, int $max, int $steps): static
	{
		$this->dropDownSettings = ['min' => $min, 'max' => $max, 'steps' => $steps];

		return $this;
	}

	public function buildPagerLinks(): static
	{
		$this->pagerLinks = [];
		$maxPages = max(1, (int) ceil($this->totalItems / $this->itemsPerPage));

		if ($this->usePager && $this->currentPage > 1)
		{
			$this->pagerLinks[] = ['name' => '«', 'page' => 1];
			$this->pagerLinks[] = ['name' => '‹', 'page' => $this->currentPage - 1];
		}

		for ($i = 1; $i <= $maxPages; $i++)
		{
			if (!$this->shortened || $this->isPageInRange($i, $maxPages))
				$this->pagerLinks[] = ['name' => (string) $i, 'page' => $i, 'active' => ($i === $this->currentPage)];
		}

		if ($this->usePager && $this->currentPage < $maxPages)
		{
			$this->pagerLinks[] = ['name' => '›', 'page' => $this->currentPage + 1];
			$this->pagerLinks[] = ['name' => '»', 'page' => $maxPages];
		}

		return $this;
	}

	private function isPageInRange(int $page, int $maxPages): bool
	{
		return $page <= 3 || $page >= ($maxPages - 2) || abs($this->currentPage - $page) <= 2;
	}
}
