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

use App\Framework\Utils\Datatable\AbstractDatatableBuilder;
use App\Framework\Utils\Html\FieldType;

/**
 * A datatable builder of a module: title, filter form and table fields.
 */
class DatatableTestBuilder extends AbstractDatatableBuilder
{
	public bool $moduleParametersAdded = false;

	public function buildTitle(): void
	{
		$this->datatableStructure['title'] = 'Test overview';
	}

	public function collectFormElements(): void
	{
		$this->datatableStructure['form'] = [
			'name' => $this->buildService->buildFormField([
				'type'  => FieldType::TEXT,
				'id'    => 'name',
				'name'  => 'name',
				'label' => 'Name',
				'value' => $this->parameters->getValueOfParameter('name'),
			]),
		];
	}

	public function createTableFields(): static
	{
		$this->buildService->createDatatableField('name', true);
		$this->buildService->createDatatableField('type', false);
		$this->datatableStructure['header'] = $this->buildService->getDatatableFields();

		return $this;
	}

	protected function addModuleParameters(): void
	{
		$this->moduleParametersAdded = true;
	}
}
