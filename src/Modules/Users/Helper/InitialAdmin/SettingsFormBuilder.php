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

namespace App\Modules\Users\Helper\InitialAdmin;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;

readonly class SettingsFormBuilder
{
	public function __construct(private FormElementsCreator $formElementsCreator)
	{}

	/**
	 * @param array{username?:string, email?:string, locale?: string} $post
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildForm(array $post, string $passwordPattern): array
	{
		$form = [];
		$form[Parameters::PARAMETER_ADMIN_NAME] = $this->formElementsCreator->createUserNameField(
			$post[Parameters::PARAMETER_ADMIN_NAME] ?? ''
		);
		$form[Parameters::PARAMETER_ADMIN_EMAIL] = $this->formElementsCreator->createEmailField(
			$post[Parameters::PARAMETER_ADMIN_EMAIL] ?? ''
		);
		$form[Parameters::PARAMETER_ADMIN_PASSWORD]         = $this->formElementsCreator->createPasswordField('', $passwordPattern);
		$form[Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM] = $this->formElementsCreator->createPasswordConfirmField('');
		$form[Parameters::PARAMETER_ADMIN_LOCALE] = $this->formElementsCreator->createUserLocaleField(
			$post[Parameters::PARAMETER_ADMIN_LOCALE] ?? 'en_US'
		);
		$form[BaseEditParameters::PARAMETER_CSRF_TOKEN] = $this->formElementsCreator->createCSRFTokenField();

		return $this->formElementsCreator->prepareForm($form);
	}
}
