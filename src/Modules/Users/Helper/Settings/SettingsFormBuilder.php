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

namespace App\Modules\Users\Helper\Settings;

use App\Framework\Core\Session;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Modules\Profile\Entities\TokenPurposes;

readonly class SettingsFormBuilder
{
	public function __construct(private Parameters          $parameters,
								private Session             $session,
								private FormElementsCreator $formElementsCreator)
	{}

	/**
	 * @param array<string,mixed> $user
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildForm(array $user): array
	{
		$form = [];
		if ($this->parameters->hasParameter(Parameters::PARAMETER_USER_NAME))
		{
			$form[Parameters::PARAMETER_USER_NAME] = $this->formElementsCreator->createUserNameField(
				$user[Parameters::PARAMETER_USER_NAME] ?? ''
			);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_USER_EMAIL))
		{
			$form[Parameters::PARAMETER_USER_EMAIL] = $this->formElementsCreator->createEmailField(
				$user[Parameters::PARAMETER_USER_EMAIL] ?? ''
			);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_USER_STATUS))
			$form[Parameters::PARAMETER_USER_STATUS] = $this->formElementsCreator->createUserStatusField((string) ($user[Parameters::PARAMETER_USER_STATUS] ?? 2));

		if ($this->parameters->hasParameter(Parameters::PARAMETER_USER_LOCALE))
			$form[Parameters::PARAMETER_USER_LOCALE] = $this->formElementsCreator->createUserLocaleField($user[Parameters::PARAMETER_USER_LOCALE] ?? 'en_US');

		$tokens = $user['tokens'] ?? [];
		foreach ($tokens as $token)
		{
			if ($token['purpose'] === TokenPurposes::AUTOLOGIN->value)
				continue;

			$value = '';
			if ($this->session->exists($token['purpose'].'_token'))
			{
				// Todo: This is just a workaround for now, we should another token process via JS
				$value = $this->session->get($token['purpose'].'_token');
				$value = is_string($value) ? $value : '';
				$this->session->delete($token['purpose'].'_token');
			}

			$form['token_' . $token['purpose']] = $this->formElementsCreator->createClipboardTextField(
				$value,
				$token['purpose'],
				$token['expires_at']
			);
		}

		if (isset($user[Parameters::PARAMETER_USER_ID]))
			$form[Parameters::PARAMETER_USER_ID] = $this->formElementsCreator->createHiddenUIDField((int) $user[Parameters::PARAMETER_USER_ID]);

		$form[BaseEditParameters::PARAMETER_CSRF_TOKEN] = $this->formElementsCreator->createCSRFTokenField();

		return $this->formElementsCreator->prepareForm($form);
	}

	/**
	 * @return list<array<string,string>>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildButtons(): array
	{
		return [$this->formElementsCreator->addResetPasswordButton()];
	}
}
