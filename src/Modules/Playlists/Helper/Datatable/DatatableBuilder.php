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

namespace App\Modules\Playlists\Helper\Datatable;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\AbstractDatatableBuilder;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Framework\Utils\Html\FieldType;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\AclValidator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class DatatableBuilder
 *
 * Extends the AbstractDatatableBuilder class to provide functionality for constructing
 * and managing parameters, titles, form elements, and table fields for a datatable.
 */
class DatatableBuilder extends AbstractDatatableBuilder
{
	private AclValidator $aclValidator;

	public function __construct(BuildService $buildService, Parameters $parameters, AclValidator $aclValidator)
	{
		$this->aclValidator = $aclValidator;
		parent::__construct($buildService, $parameters);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function configureParameters(int $UID): void
	{
		if ($this->aclValidator->getConfig()->getEdition() === Config::PLATFORM_EDITION_EDGE)
			return;

		if ($this->aclValidator->isModuleAdmin($UID) || $this->aclValidator->isSubAdmin($UID))
		{
			$this->parameters->addOwner();
			$this->parameters->addCompany();
		}
	}

	/**
	 * @throws ModuleException
	 */
	public function determineParameters(): void
	{
		$this->parameters->setUserInputs($_GET);
		$this->parameters->parseInputFilterAllUsers();
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function buildTitle(): void
	{
		$this->datatableStructure['title'] = $this->translator->translate('overview', 'playlists');
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function collectFormElements(): void
	{
		$form       = [];
		$form[Parameters::PARAMETER_PLAYLIST_NAME] = $this->buildService->buildFormField([
			'type' => FieldType::TEXT,
			'id' => Parameters::PARAMETER_PLAYLIST_NAME,
			'name' => Parameters::PARAMETER_PLAYLIST_NAME,
			'title' => $this->translator->translate(Parameters::PARAMETER_PLAYLIST_NAME, 'playlists'),
			'label' => $this->translator->translate(Parameters::PARAMETER_PLAYLIST_NAME, 'playlists'),
			'value' => $this->parameters->getValueOfParameter(Parameters::PARAMETER_PLAYLIST_NAME)
		]);

		if ($this->parameters->hasParameter(BaseParameters::PARAMETER_UID))
		{
			$form[BaseParameters::PARAMETER_UID] = $this->buildService->buildFormField([
				'type' => FieldType::AUTOCOMPLETE,
				'id' => 'UID',
				'name' => 'UID',
				'title' => $this->translator->translate('owner', 'main'),
				'label' => $this->translator->translate('owner', 'main'),
				'value' => $this->parameters->getValueOfParameter(BaseParameters::PARAMETER_UID),
				'data-label' => ''
			]);
		}
		$allowedPlaylistModes = $this->determineAllowedPlaylistModes();
		$form[Parameters::PARAMETER_PLAYLIST_MODE] = $this->buildService->buildFormField([
			'type' => FieldType::DROPDOWN,
			'id' => Parameters::PARAMETER_PLAYLIST_MODE,
			'name' => Parameters::PARAMETER_PLAYLIST_MODE,
			'title' => $this->translator->translate(Parameters::PARAMETER_PLAYLIST_MODE, 'playlists'),
			'label' => $this->translator->translate(Parameters::PARAMETER_PLAYLIST_MODE, 'playlists'),
			'value' => $this->parameters->getValueOfParameter(Parameters::PARAMETER_PLAYLIST_MODE),
			'options' => $allowedPlaylistModes
		]);

		$this->datatableStructure['form'] = $form;
	}

	public function createTableFields(): static
	{
		$this->buildService->createDatatableField('playlist_name', true);

		if ($this->parameters->hasParameter(BaseParameters::PARAMETER_UID))
			$this->buildService->createDatatableField('UID', true);

		$this->buildService->createDatatableField('playlist_mode', true);
		$this->buildService->createDatatableField('duration', false);

		$this->datatableStructure['header'] = $this->buildService->getDatatableFields();

		return $this;
	}

	/**
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function determineAllowedPlaylistModes(): array
	{
		$list = $this->translator->translateArrayForOptions('playlist_mode_selects', 'playlists');
		$allowedPlaylistModes = [];
		$edition = $this->aclValidator->getConfig()->getEdition();
		foreach ($list as $key => $value)
		{
			if ($edition === Config::PLATFORM_EDITION_EDGE && $key === PlaylistMode::CHANNEL->value)
				continue;

			if ($edition === Config::PLATFORM_EDITION_EDGE && $key === PlaylistMode::EXTERNAL->value)
				continue;

			$allowedPlaylistModes[$key] = $value;
		}

		return $allowedPlaylistModes;
	}

}