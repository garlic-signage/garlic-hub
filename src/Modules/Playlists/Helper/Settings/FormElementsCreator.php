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

namespace App\Modules\Playlists\Helper\Settings;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\Forms\AbstractBaseFormElementsCreator;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class FormElementsCreator
 *
 * Responsible for creating various form elements used in the application.
 */
class FormElementsCreator extends AbstractBaseFormElementsCreator
{

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function createPlaylistNameField(string $value): FieldInterface
	{
		return  $this->formBuilder->createField([
			'type' => FieldType::TEXT,
			'id' => 'playlist_name',
			'name' => 'playlist_name',
			'title' => $this->translator->translate('playlist_name', 'playlists'),
			'label' => $this->translator->translate('playlist_name', 'playlists'),
			'value' => $value,
			'rules' => ['required' => true, 'minlength' => 2],
			'default_value' => ''
		]);
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
	public function createTimeLimitField(int $value, int $defaultValue): FieldInterface
	{
		return $this->formBuilder->createField([
			'type' => FieldType::NUMBER,
			'id' => 'time_limit',
			'name' => 'time_limit',
			'title' => $this->translator->translate('time_limit_explanation', 'playlists'),
			'label' => $this->translator->translate('time_limit', 'playlists'),
			'value' => $value,
			'min'   => 0,
			'default_value' => $defaultValue
		]);
	}

	/**
	 * @throws FrameworkException
	 */
	public function createHiddenPlaylistIdField(int $value): FieldInterface
	{
		return $this->formBuilder->createField([
			'type' => FieldType::HIDDEN,
			'id' => 'playlist_id',
			'name' => 'playlist_id',
			'value' => $value,
		]);
	}

	/**
	 * @throws FrameworkException
	 */
	public function createPlaylistModeField(string|int $value): FieldInterface
	{
		return $this->formBuilder->createField([
			'type' => FieldType::HIDDEN,
			'id' => 'playlist_mode',
			'name' => 'playlist_mode',
			'value' => $value,
		]);
	}

}