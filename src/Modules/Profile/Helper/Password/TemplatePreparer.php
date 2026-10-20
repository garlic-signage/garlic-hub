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

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Profile\Entities\TokenPurposes;

class TemplatePreparer
{
	public function __construct(private readonly Translator $translator,
								private readonly FormTemplatePreparer $formTemplatePreparer)
	{}

	/**
	 * @param array<string,mixed> $dataSections
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function preparePasswordForm(array $dataSections): array
	{
		$dataSections['title']       = $this->translator->translate('edit_password', 'profile');
		$dataSections['form_action'] = '/profile/password';

		return $this->prepare($dataSections);
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
			$title = $this->translator->translate('initial_password_for', 'profile');
		else
			$title = $this->translator->translate('edit_password_for', 'profile');

		$dataSections['title']       = sprintf($title, $tokenUser['username']);
		$dataSections['form_action'] = '/force-password';

		return $this->prepare($dataSections);
	}

	/**
	 * @param array<string,mixed> $dataSections
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function prepare(array $dataSections): array
	{
		$dataSections['explanations']      = $this->translator->translate('password_explanation', 'profile');
		$dataSections['additional_css']    = ['/css/profile/password.css'];
		$dataSections['footer_modules']    = ['/js/profile/password/init.js'];
		$dataSections['template_name']     = 'profile/edit';
		$dataSections['save_button_label'] = $this->translator->translate('save', 'main');

		return $this->formTemplatePreparer->prepareUITemplate($dataSections);
	}
}
