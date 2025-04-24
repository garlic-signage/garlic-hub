<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace App\Modules\Player\Helper\Datatable;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\AbstractDatatableBuilder;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Framework\Utils\Html\FieldType;
use App\Modules\Player\Services\AclValidator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

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
		$this->datatableStructure['title'] = $this->translator->translate('overview', 'player');
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

		$form[Parameters::PARAMETER_ACTIVITY] = $this->buildService->buildFormField([
			'type' => FieldType::DROPDOWN,
			'id' => Parameters::PARAMETER_ACTIVITY,
			'name' => Parameters::PARAMETER_ACTIVITY,
			'title' => $this->translator->translate(Parameters::PARAMETER_ACTIVITY, 'player'),
			'label' => $this->translator->translate(Parameters::PARAMETER_ACTIVITY, 'player'),
			'value' => $this->parameters->getValueOfParameter(Parameters::PARAMETER_ACTIVITY),
			'options' => $this->translator->translateArrayForOptions(Parameters::PARAMETER_ACTIVITY.'_selects', 'player')
		]);

		$form[Parameters::PARAMETER_PLAYER_NAME] = $this->buildService->buildFormField([
			'type' => FieldType::TEXT,
			'id' => Parameters::PARAMETER_PLAYER_NAME,
			'name' => Parameters::PARAMETER_PLAYER_NAME,
			'title' => $this->translator->translate(Parameters::PARAMETER_PLAYER_NAME, 'player'),
			'label' => $this->translator->translate(Parameters::PARAMETER_PLAYER_NAME, 'player'),
			'value' => $this->parameters->getValueOfParameter(Parameters::PARAMETER_PLAYER_NAME)
		]);

		$form[Parameters::PARAMETER_MODEL] = $this->buildService->buildFormField([
			'type' => FieldType::DROPDOWN,
			'id' => Parameters::PARAMETER_MODEL,
			'name' => Parameters::PARAMETER_MODEL,
			'title' => $this->translator->translate(Parameters::PARAMETER_MODEL, 'player'),
			'label' => $this->translator->translate(Parameters::PARAMETER_MODEL, 'player'),
			'value' => $this->parameters->getValueOfParameter(Parameters::PARAMETER_MODEL),
			'options' => $this->translator->translateArrayForOptions(Parameters::PARAMETER_MODEL.'_selects', 'player')
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

		$this->datatableStructure['form'] = $form;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function createTableFields(): static
	{
		$this->buildService->createDatatableField('player_name', true);

		if ($this->parameters->hasParameter(BaseParameters::PARAMETER_UID))
			$this->buildService->createDatatableField('UID', true);

		$this->buildService->createDatatableField('status', false);
		$this->buildService->createDatatableField('model', true);
		$this->buildService->createDatatableField('playlist_id', false);

		$this->datatableStructure['header'] = $this->buildService->getDatatableFields();

		return $this;
	}

}