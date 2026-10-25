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

use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Services\AbstractDatatableService;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use Psr\Log\NullLogger;

/**
 * A datatable service whose loading is only noted, the result is set by the test. It notes the values of the
 * parameters at the time of loading, they have to be parsed by then.
 */
class DatatableTestService extends AbstractDatatableService
{
	public int $loaded = 0;
	/** @var array<int|string, mixed> */
	public array $parametersAtLoad = [];

	public function __construct(DatatableQuery $datatableQuery, private readonly BaseFilterParameters $parameters)
	{
		parent::__construct(new NullLogger(), $datatableQuery);
	}

	public function loadDatatable(): void
	{
		$this->loaded++;
		$this->parametersAtLoad = $this->parameters->getInputValuesArray();
	}
}
