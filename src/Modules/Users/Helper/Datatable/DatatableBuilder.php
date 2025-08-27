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

namespace App\Modules\Users\Helper\Datatable;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\AbstractDatatableBuilder;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Framework\Utils\Html\FieldType;
use App\Modules\Users\Services\AclValidator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class DatatableBuilder
 *
 * This class is responsible for constructing and managing the structure of a data table,
 * including configuration of parameters, form elements, and table fields.
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
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function buildTitle(): void
	{
		$this->datatableStructure['title'] = $this->translator->translate('management', 'users');
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function collectFormElements(): void
	{
		$form       = [];
		$form[Parameters::PARAMETER_USERNAME] = $this->buildService->buildFormField([
			'type'  => FieldType::TEXT,
			'id'    => Parameters::PARAMETER_USERNAME,
			'name'  => Parameters::PARAMETER_USERNAME,
			'title' => $this->translator->translate(Parameters::PARAMETER_USERNAME, 'main'),
			'label' => $this->translator->translate(Parameters::PARAMETER_USERNAME, 'main'),
			'value' => $this->parameters->getValueOfParameter(Parameters::PARAMETER_USERNAME)
		]);

		$form[Parameters::PARAMETER_EMAIL] = $this->buildService->buildFormField([
			'type'  => FieldType::TEXT,
			'id'    => Parameters::PARAMETER_EMAIL,
			'name'  => Parameters::PARAMETER_EMAIL,
			'title' => $this->translator->translate(Parameters::PARAMETER_EMAIL, 'users'),
			'label' => $this->translator->translate(Parameters::PARAMETER_EMAIL, 'users'),
			'value' => $this->parameters->getValueOfParameter(Parameters::PARAMETER_EMAIL)
		]);

		if ($this->parameters->hasParameter(Parameters::PARAMETER_FIRSTNAME))
		{
			$form[Parameters::PARAMETER_FIRSTNAME] = $this->buildService->buildFormField([
				'type' => FieldType::TEXT,
				'id' => Parameters::PARAMETER_FIRSTNAME,
				'name' => Parameters::PARAMETER_FIRSTNAME,
				'title' => $this->translator->translate(Parameters::PARAMETER_FIRSTNAME, 'users'),
				'label' => $this->translator->translate(Parameters::PARAMETER_FIRSTNAME, 'users'),
				'value' => $this->parameters->getValueOfParameter(Parameters::PARAMETER_FIRSTNAME)
			]);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_SURNAME))
		{
			$form[Parameters::PARAMETER_SURNAME] = $this->buildService->buildFormField([
				'type' => FieldType::TEXT,
				'id' => Parameters::PARAMETER_SURNAME,
				'name' => Parameters::PARAMETER_SURNAME,
				'title' => $this->translator->translate(Parameters::PARAMETER_SURNAME, 'users'),
				'label' => $this->translator->translate(Parameters::PARAMETER_SURNAME, 'users'),
				'value' => $this->parameters->getValueOfParameter(Parameters::PARAMETER_SURNAME)
			]);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_COMPANY_NAME))
		{
			$form[Parameters::PARAMETER_COMPANY_NAME] = $this->buildService->buildFormField([
				'type' => FieldType::TEXT,
				'id' => Parameters::PARAMETER_COMPANY_NAME,
				'name' => Parameters::PARAMETER_COMPANY_NAME,
				'title' => $this->translator->translate(Parameters::PARAMETER_COMPANY_NAME, 'users'),
				'label' => $this->translator->translate(Parameters::PARAMETER_COMPANY_NAME, 'users'),
				'value' => $this->parameters->getValueOfParameter(Parameters::PARAMETER_COMPANY_NAME)
			]);
		}

		if ($this->parameters->hasParameter(BaseFilterParametersInterface::PARAMETER_COMPANY_ID))
		{
			$form[BaseFilterParametersInterface::PARAMETER_COMPANY_ID] = $this->buildService->buildFormField([
				'type'   => FieldType::DROPDOWN,
				'id'     => BaseFilterParametersInterface::PARAMETER_COMPANY_ID,
				'name'   => BaseFilterParametersInterface::PARAMETER_COMPANY_ID,
				'title'  => $this->translator->translate('belongs_company', 'main'),
				'label'  => $this->translator->translate('belongs_company', 'main'),
				'value'  => $this->parameters->getValueOfParameter(BaseFilterParametersInterface::PARAMETER_COMPANY_ID),
				'options' => []
			]);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_STATUS))
		{
			$form[Parameters::PARAMETER_STATUS] = $this->buildService->buildFormField([
				'type' => FieldType::DROPDOWN,
				'id' => Parameters::PARAMETER_STATUS,
				'name' => Parameters::PARAMETER_STATUS,
				'title' => $this->translator->translate(Parameters::PARAMETER_STATUS, 'users'),
				'label' => $this->translator->translate(Parameters::PARAMETER_STATUS, 'users'),
				'value' => $this->parameters->getValueOfParameter(Parameters::PARAMETER_STATUS),
				'options' => $this->translator->translateArrayForOptions(Parameters::PARAMETER_STATUS.'_selects', 'users')
			]);
		}

		$this->datatableStructure['form'] = $form;
	}

	public function createTableFields(): static
	{
		$this->buildService->getResultsBuilder()->createField('username',true);
		$this->buildService->getResultsBuilder()->createField('created_at', true);
		$this->buildService->getResultsBuilder()->createField('status', false);
		if ($this->aclValidator->getConfig()->getEdition() === Config::PLATFORM_EDITION_CORE || $this->aclValidator->getConfig()->getEdition()  === Config::PLATFORM_EDITION_ENTERPRISE)
		{
			$this->buildService->getResultsBuilder()->createField('firstname', false);
			$this->buildService->getResultsBuilder()->createField('surname', false);
			$this->buildService->getResultsBuilder()->createField('company_name', false);
		}

		$this->datatableStructure['header'] = $this->buildService->getResultsBuilder()->getHeaderFields();

		return $this;
	}
}