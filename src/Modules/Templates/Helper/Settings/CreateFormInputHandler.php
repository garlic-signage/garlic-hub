<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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


namespace App\Modules\Templates\Helper\Settings;

class CreateFormInputHandler
{
	public function __construct(private readonly Parameters $parameters,
								private readonly Validator $validator)
	{
	}

	public function validate(array $post)
	{
		$this->parameters->setUserInputs($post);
		$this->parameters->parseInputAllParameters();
		$errors = $this->validator->validateCreateInput();

		if ($errors !== [])
			return $errors;

		return [];
	}

	public function getParsed(): array
	{
		return array_combine(
			$this->parameters->getInputParametersKeys(),
			$this->parameters->getInputValuesArray()
		);
	}
}