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

namespace Tests\Unit\Framework\Utils\Datatable;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Validation\Sanitizer;
use Tests\Support\MemorySession;

/**
 * Real filter parameters (page, per page, sort) for the tests of the datatable, parsed from the given input.
 */
class FilterParametersFake extends BaseFilterParameters
{
	/**
	 * @param array<string,mixed> $input what the visitor sent
	 * @param list<string> $sortable sort columns which are allowed
	 * @throws ModuleException
	 */
	public function __construct(array $input = [], array $sortable = ['name', 'created'], string $defaultSortColumn = 'name')
	{
		parent::__construct('testModule', new Sanitizer(), new MemorySession(), '');
		$this->currentParameters = $this->defaultParameters;
		$this->userInputs        = $input;

		$this->setParameterDefaultValues($defaultSortColumn)->parseInputFilterAllUsers($sortable);
	}
}
