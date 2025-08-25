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

namespace App\Modules\Player\Helper\NetworkSettings;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Forms\AbstractBaseFormElementsCreator;
use App\Framework\Utils\Html\CheckboxField;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\UrlField;
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
	public function createApiEndpointField(string $value): UrlField
	{
		$title = $this->translator->translate(Parameters::PARAMETER_API_ENDPOINT, 'player');
		$field = $this->formBuilder->createField([
			'type'  => FieldType::URL,
			'id'    => Parameters::PARAMETER_API_ENDPOINT,
			'name'  => Parameters::PARAMETER_API_ENDPOINT,
			'title' => $title,
			'label' => $title,
			'value' => $value
		]);

		/** @var UrlField $field */
		$field->setPlaceholder('http://localhost:8080/v2');
		$field->setPattern('https?://.*');

		return $field;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function createIsIntranet(bool $checked): FieldInterface
	{
		$field =  $this->formBuilder->createField([
			'type' => FieldType::CHECKBOX,
			'id' => Parameters::PARAMETER_IS_INTRANET,
			'name' => Parameters::PARAMETER_IS_INTRANET,
			'title' => $this->translator->translate(Parameters::PARAMETER_IS_INTRANET, 'player')
		]);

		/** @var CheckboxField $field */
		$field->setChecked($checked);

		return $field;
	}

	/**
	 * @throws FrameworkException
	 */
	public function createHiddenPlayerIdField(int $value): FieldInterface
	{
		return $this->formBuilder->createField([
			'type' => FieldType::HIDDEN,
			'id' => 'player_id',
			'name' => 'player_id',
			'value' => $value,
		]);
	}


}