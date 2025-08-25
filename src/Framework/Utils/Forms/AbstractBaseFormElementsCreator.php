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


namespace App\Framework\Utils\Forms;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;

/**
 * Abstract class responsible for creating form elements with the help of a form builder and translator.
 */
abstract class AbstractBaseFormElementsCreator
{
	protected readonly FormBuilder $formBuilder;
	protected readonly Translator $translator;

	public function __construct(FormBuilder $formBuilder, Translator $translator)
	{
		$this->formBuilder = $formBuilder;
		$this->translator = $translator;
	}

	/**
	 * @param  array<string,FieldInterface> $form
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 */
	public function prepareForm(array $form): array
	{
		return $this->formBuilder->prepareForm($form);
	}

	/**
	 * @throws FrameworkException
	 */
	public function createCSRFTokenField(): FieldInterface
	{
		return $this->formBuilder->createField([
			'type' => FieldType::CSRF,
			'id'   => BaseEditParameters::PARAMETER_CSRF_TOKEN,
			'name' => BaseEditParameters::PARAMETER_CSRF_TOKEN,
		]);
	}

}