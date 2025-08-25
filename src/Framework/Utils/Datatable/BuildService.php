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

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FormBuilder;

/**
 * BuildService is responsible for constructing and managing form fields, results, and pagination settings
 * for various application components.
 */
class BuildService
{
	private FormBuilder $formBuilder;
	private Results\Builder $resultsBuilder;
	private Paginator\Builder $paginationBuilder;

	public function __construct(FormBuilder $formBuilder, Paginator\Builder $paginationBuilder, Results\Builder $resultsBuilder)
	{
		$this->formBuilder = $formBuilder;
		$this->resultsBuilder = $resultsBuilder;
		$this->paginationBuilder = $paginationBuilder;
	}

	/**
	 * @param array<string,mixed> $attributes
	 * @throws FrameworkException
	 */
	public function buildFormField(array $attributes = []): FieldInterface
	{
		return $this->formBuilder->createField($attributes);
	}

	public function getResultsBuilder(): Results\Builder
	{
		return $this->resultsBuilder;
	}

	public function createDatatableField(string $fieldName, bool $sortable): void
	{
		$this->resultsBuilder->createField($fieldName, $sortable);
	}

	/**
	 * @return list<HeaderField>
	 */
	public function getDatatableFields(): array
	{
		return $this->resultsBuilder->getHeaderFields();
	}

	/**
	 * @return array{min: int, max: int, steps: int}
	 */
	public function buildPaginationDropDown(int $min = 10, int $max = 100, int $steps = 10): array
	{
		return $this->paginationBuilder->createDropDown($min, $max, $steps)->getDropDownSettings();
	}

	/**
	 * @return list<array{name: string, page: int}>
	 */
	public function buildPaginationLinks(int $currentPage, int $itemsPerPage, int $totalItems, bool $usePager = false, bool $shortened = true): array
	{
		return $this->paginationBuilder->configure($currentPage, $itemsPerPage, $totalItems, $usePager, $shortened)
			->buildPagerLinks()
			->getPagerLinks();
	}
}