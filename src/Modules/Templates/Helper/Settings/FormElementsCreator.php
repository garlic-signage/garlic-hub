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

namespace App\Modules\Templates\Helper\Settings;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Forms\AbstractBaseFormElementsCreator;
use App\Framework\Utils\Html\CheckboxField;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Utils\Html\UrlField;
use App\Modules\Auth\UserSession;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class responsible for creating form elements within the application.
 */
class FormElementsCreator extends AbstractBaseFormElementsCreator
{

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function createNameField(string $value): FieldInterface
	{
		$title = $this->translator->translate(Parameters::PARAMETER_NAME, 'templates');
		$field = $this->formBuilder->createField([
			'type'  => FieldType::TEXT,
			'id'    => Parameters::PARAMETER_NAME,
			'name'  => Parameters::PARAMETER_NAME,
			'title' => $title,
			'label' => $title,
			'value' => $value
		]);

		return $field;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function createUIDField(string|int $value, string $username, int $UID): FieldInterface
	{
		return $this->formBuilder->createField([
			'type'          => FieldType::AUTOCOMPLETE,
			'id'            => 'UID',
			'name'          => 'UID',
			'title'         => $this->translator->translate('owner', 'main'),
			'label'         => $this->translator->translate('owner', 'main'),
			'value'         => $value,
			'data-label'    => $username,
			'default_value' => $UID
		]);
	}


	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function createVisibilityField(string|int $value, string $username, int $UID): FieldInterface
	{
		return $this->formBuilder->createField([
			'type'          => FieldType::DROPDOWN,
			'id'            => Parameters::PARAMETER_VISIBILITY,
			'name'          => Parameters::PARAMETER_VISIBILITY,
			'title'         => $this->translator->translate('visibility', 'main'),
			'label'         => $this->translator->translate('visibility', 'main'),
			'value'         => $value,
			'options' => $this->translator->translateArrayForOptions(Parameters::PARAMETER_VISIBILITY.'_selects', 'main'),
			'default_value' => 'public'
		]);
	}

	/**
	 * @throws FrameworkException
	 */
	public function createHiddenTemplateIdField(int $value): FieldInterface
	{
		return $this->formBuilder->createField([
			'type' => FieldType::HIDDEN,
			'id' => 'template_id',
			'name' => 'template_id',
			'value' => $value,
		]);
	}


}