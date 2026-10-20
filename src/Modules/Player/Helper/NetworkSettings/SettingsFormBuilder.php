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

namespace App\Modules\Player\Helper\NetworkSettings;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;

class SettingsFormBuilder
{
	public function __construct(private readonly FormElementsCreator $formElementsCreator)
	{}

	/**
	 * @param array<string,mixed> $networkData
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildForm(array $networkData): array
	{
		$form = [];
		$form[Parameters::PARAMETER_IS_INTRANET] = $this->formElementsCreator->createIsIntranet(
			(bool) ($networkData[Parameters::PARAMETER_IS_INTRANET] ?? false),
		);

		$form[Parameters::PARAMETER_API_ENDPOINT] = $this->formElementsCreator->createApiEndpointField(
			$networkData[Parameters::PARAMETER_API_ENDPOINT] ?? ''
		);

		$form['player_id'] = $this->formElementsCreator->createHiddenPlayerIdField((int) $networkData['player_id']);
		$form[BaseEditParameters::PARAMETER_CSRF_TOKEN] = $this->formElementsCreator->createCSRFTokenField();

		return $this->formElementsCreator->prepareForm($form);
	}
}
