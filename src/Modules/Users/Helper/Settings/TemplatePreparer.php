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

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Forms\FormTemplatePreparer;

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
	public function prepareCreateSettings(array $dataSections): array
	{
		return $this->prepareSettings($dataSections, $this->translator->translate('add', 'users'));
	}

	/**
	 * @param array<string,mixed> $dataSections
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function prepareEditSettings(array $dataSections, string $username): array
	{
		return $this->prepareSettings($dataSections, $username);
	}

	/**
	 * @param array<string,mixed> $dataSections
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function prepareSettings(array $dataSections, string $name): array
	{
		$dataSections['title']             = $this->translator->translate('core_data', 'users') . ': ' . $name;
		$dataSections['additional_css']    = ['/css/users/edit.css'];
		$dataSections['footer_modules']    = ['/js/users/edit/init.js'];
		$dataSections['template_name']     = 'users/edit';
		$dataSections['form_action']       = '/users/edit';
		$dataSections['save_button_label'] = $this->translator->translate('save', 'main');

		return $this->formTemplatePreparer->prepareUITemplate($dataSections);
	}
}
