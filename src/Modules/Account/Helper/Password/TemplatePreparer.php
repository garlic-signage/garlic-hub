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

namespace App\Modules\Account\Helper\Password;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Shared\User\TokenPurposes;

readonly class TemplatePreparer
{
	public function __construct(private Translator           $translator,
								private FormTemplatePreparer $formTemplatePreparer)
	{}

	/**
	 * The account of the user: a tab for the password and one for the second factor.
	 *
	 * @param array<string,mixed> $dataSections
	 * @param array<string,mixed> $twoFactorTab
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function preparePasswordForm(array $dataSections, array $twoFactorTab): array
	{
		$dataSections['title']       = $this->translator->translate('my_account', 'menu');
		$dataSections['form_action'] = '/account/password';

		$page = $this->prepare($dataSections, 'account/settings');
		$page['main_layout']['additional_css'][] = '/css/account/settings.css';
		$page['this_layout']['data']['tabs'] = [
			['MODULE' => 'password', 'LANG_MODULE' => $this->translator->translate('password', 'account')],
			['MODULE' => 'two_factor', 'LANG_MODULE' => $this->translator->translate('two_factor', 'account')]
		];
		$page['this_layout']['data']['two_factor'] = $twoFactorTab;

		return $page;
	}

	/**
	 * @param array<string,mixed> $dataSections
	 * @param array{username:string, purpose:string} $tokenUser
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function prepareForcedPasswordForm(array $dataSections, array $tokenUser): array
	{
		if ($tokenUser['purpose'] === TokenPurposes::INITIAL_PASSWORD->value)
			$title = $this->translator->translate('initial_password_for', 'account');
		else
			$title = $this->translator->translate('edit_password_for', 'account');

		$dataSections['title']       = sprintf($title, $tokenUser['username']);
		$dataSections['form_action'] = '/force-password';

		return $this->prepare($dataSections, 'account/edit');
	}

	/**
	 * @param array<string,mixed> $dataSections
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function prepare(array $dataSections, string $templateName): array
	{
		$dataSections['explanations']      = $this->translator->translate('password_explanation', 'account');
		$dataSections['additional_css']    = ['/css/account/password.css'];
		$dataSections['footer_modules']    = ['/js/account/password/init.js'];
		$dataSections['template_name']     = $templateName;
		$dataSections['save_button_label'] = $this->translator->translate('save', 'main');

		return $this->formTemplatePreparer->prepareUITemplate($dataSections);
	}
}
