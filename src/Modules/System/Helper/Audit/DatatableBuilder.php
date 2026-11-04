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

namespace App\Modules\System\Helper\Audit;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Datatable\AbstractDatatableBuilder;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Html\FieldType;
use App\Modules\System\Services\AclValidator;
use App\Shared\Acl\EditionAclModules;
use App\Shared\Audit\AuditAction;

/**
 * The filter and the columns of the audit log. Nothing is sortable: the newest entries come first.
 */
class DatatableBuilder extends AbstractDatatableBuilder
{
	private const string MODULE_NAME = 'system';
	// the modules which write without having rights of their own
	private const array MODULES_WITHOUT_RIGHTS = ['auth', 'account'];

	public function __construct(BuildService $buildService, Parameters $parameters, AclValidator $aclValidator, Translator $translator)
	{
		parent::__construct($buildService, $parameters, $aclValidator, $translator);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildTitle(): void
	{
		$this->datatableStructure['title'] = $this->translator->translate('audit_log', self::MODULE_NAME);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function collectFormElements(): void
	{
		$form = [];
		foreach ([Parameters::PARAMETER_DATE_FROM, Parameters::PARAMETER_DATE_TO, Parameters::PARAMETER_USERNAME] as $name)
		{
			$form[$name] = $this->buildService->buildFormField([
				'type'        => FieldType::TEXT,
				'id'          => $name,
				'name'        => $name,
				'title'       => $this->translator->translate($name, self::MODULE_NAME),
				'label'       => $this->translator->translate($name, self::MODULE_NAME),
				'value'       => $this->parameters->getValueOfParameter($name),
				'placeholder' => $name === Parameters::PARAMETER_USERNAME ? '' : 'YYYY-MM-DD'
			]);
		}

		$form[Parameters::PARAMETER_MODULE] = $this->buildService->buildFormField([
			'type'    => FieldType::DROPDOWN,
			'id'      => Parameters::PARAMETER_MODULE,
			'name'    => Parameters::PARAMETER_MODULE,
			'title'   => $this->translator->translate(Parameters::PARAMETER_MODULE, self::MODULE_NAME),
			'label'   => $this->translator->translate(Parameters::PARAMETER_MODULE, self::MODULE_NAME),
			'value'   => $this->parameters->getValueOfParameter(Parameters::PARAMETER_MODULE),
			'options' => $this->moduleOptions()
		]);

		$form[Parameters::PARAMETER_ACTION] = $this->buildService->buildFormField([
			'type'    => FieldType::DROPDOWN,
			'id'      => Parameters::PARAMETER_ACTION,
			'name'    => Parameters::PARAMETER_ACTION,
			'title'   => $this->translator->translate(Parameters::PARAMETER_ACTION, self::MODULE_NAME),
			'label'   => $this->translator->translate(Parameters::PARAMETER_ACTION, self::MODULE_NAME),
			'value'   => $this->parameters->getValueOfParameter(Parameters::PARAMETER_ACTION),
			'options' => $this->actionOptions()
		]);

		$this->datatableStructure['form'] = $form;
	}

	public function createTableFields(): static
	{
		foreach (['created_at', 'UID', 'action', 'module', 'target_UID', 'details'] as $field)
			$this->buildService->createDatatableField($field, false);

		$this->datatableStructure['header'] = $this->buildService->getDatatableFields();

		return $this;
	}

	/**
	 * @return array<string,string>
	 */
	private function moduleOptions(): array
	{
		$modules = array_merge(EditionAclModules::getModules($this->aclValidator->getConfig()->getEdition()), self::MODULES_WITHOUT_RIGHTS);
		sort($modules);

		return array_combine($modules, $modules);
	}

	/**
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function actionOptions(): array
	{
		$options = [];
		foreach (AuditAction::cases() as $action)
			$options[$action->value] = $this->translator->translate('action_' . $action->value, self::MODULE_NAME);

		return $options;
	}
}
