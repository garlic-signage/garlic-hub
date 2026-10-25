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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Auth\UserSession;

readonly class SettingsFormBuilder
{
	public function __construct(private Parameters          $parameters,
								private UserSession         $userSession,
								private FormElementsCreator $formElementsCreator)
	{}

    /**
     * Builds the edit form when settings data of a template is given, otherwise build the creation form.
     *
     * @param array<string,mixed> $settingsData
     * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
     * @throws CoreException
     * @throws FrameworkException
     * @throws UserException
     */
	public function buildForm(array $settingsData): array
	{
		$form = [];
		$form[Parameters::PARAMETER_NAME] = $this->formElementsCreator->createNameField(
			($settingsData[Parameters::PARAMETER_NAME] ?? ''),
		);

		if ($this->parameters->hasParameter(BaseParameters::PARAMETER_UID))
		{
			$form[BaseParameters::PARAMETER_UID] = $this->formElementsCreator->createUIDField(
				$settingsData[BaseParameters::PARAMETER_UID] ?? $this->userSession->getUID(),
				$settingsData['username'] ?? $this->userSession->getUsername(),
				$this->userSession->getUID()
			);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_VISIBILITY))
		{
			$form[Parameters::PARAMETER_VISIBILITY] = $this->formElementsCreator->createVisibilityField(
				$settingsData[Parameters::PARAMETER_VISIBILITY] ?? '');
		}

		// a template which exists has its id; the input of a new template after an error has none
		if (array_key_exists('template_id', $settingsData))
			$form['template_id'] = $this->formElementsCreator->createHiddenTemplateIdField((int) $settingsData['template_id']);
		else
			$form['type'] = $this->formElementsCreator->createHiddenTypeField('canvas');

		$form[BaseEditParameters::PARAMETER_CSRF_TOKEN] = $this->formElementsCreator->createCSRFTokenField();

		return $this->formElementsCreator->prepareForm($form);
	}
}