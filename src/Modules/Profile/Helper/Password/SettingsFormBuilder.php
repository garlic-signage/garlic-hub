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

namespace App\Modules\Profile\Helper\Password;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;

class SettingsFormBuilder
{
	public function __construct(private readonly FormElementsCreator $formElementsCreator)
	{}

	/**
	 * Builds the form for a forced password change when a token is given.
	 *
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildForm(string $passwordPattern, string $passwordToken = ''): array
	{
		$form = [];
		$form[Parameters::PARAMETER_PASSWORD]         = $this->formElementsCreator->createPasswordField('', $passwordPattern);
		$form[Parameters::PARAMETER_PASSWORD_CONFIRM] = $this->formElementsCreator->createPasswordConfirmField('');
		$form[BaseEditParameters::PARAMETER_CSRF_TOKEN] = $this->formElementsCreator->createCSRFTokenField();
		if ($passwordToken !== '')
			$form[Parameters::PARAMETER_PASSWORD_TOKEN] = $this->formElementsCreator->createPasswordTokenField($passwordToken);

		return $this->formElementsCreator->prepareForm($form);
	}
}
