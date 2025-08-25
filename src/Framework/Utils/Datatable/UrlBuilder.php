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

namespace App\Framework\Utils\Datatable;


/**
 * A class to build and manage URLs with customizable parameters.
 */
class UrlBuilder
{
	private string $site = '';

	private int $page;
	private int $elementsPerPage;
	private string $sortColumn;
	private string $sortOrder;
	/** @var array<string,mixed>  */
	private array $additionalUrlParameters;

	public function setSite(string $site): static
	{
		$this->site = $site;
		return $this;
	}

	public function setPage(int $page): static
	{
		$this->page = $page;
		return $this;
	}

	public function setElementsPerPage(int $elementsPerPage): static
	{
		$this->elementsPerPage = $elementsPerPage;
		return $this;
	}

	public function setSortColumn(string $sortColumn): static
	{
		$this->sortColumn = $sortColumn;
		return $this;
	}

	public function setSortOrder(string $sortOrder): static
	{
		$this->sortOrder = $sortOrder;
		return $this;
	}

	public function buildFilterUrl(): string
	{
		$params = [
			'elements_page' => $this->page,
			'sort_column' => $this->sortColumn,
			'sort_order' => $this->sortOrder,
			'elements_per_page' =>$this->elementsPerPage
		];

		if ($this->hasAdditionalUrlParameters())
		{
			$params = array_merge($params, $this->additionalUrlParameters);
		}

		return $this->site . '?' . http_build_query($params);
	}

	public function addAdditionalUrlParameter(string $key, string $value): static
	{
		$this->additionalUrlParameters[$key] = $value;
		return $this;
	}

	public function clearAdditionalUrlParameters(): static
	{
		$this->additionalUrlParameters = [];
		return $this;
	}

	public function hasAdditionalUrlParameters(): bool
	{
		return (!empty($this->additionalUrlParameters));
	}
}