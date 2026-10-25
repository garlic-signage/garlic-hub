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

namespace App\Modules\Playlists\Helper\Widgets;

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Utils\Widget\ConfigXML;

/**
 * A class responsible for preparing and processing content data based on specific configurations
 * and request data. It validates mandatory fields, processes input types, and interacts with
 * configuration data to determine editable preferences.
 */
readonly class ContentDataPreparer
{
	private ConfigXML $configXml;

	public function __construct(ConfigXML $configXml)
	{
		$this->configXml = $configXml;
	}

	/**
	 * @param array<string, mixed> $requestData
	 * @return array<string, mixed>
	 * @throws ModuleException
	 * @throws FrameworkException
	 */
	public function prepareContentData(string $configData, array $requestData, bool $init = false): array
	{
		$preferencesData = $this->determinePreferences($configData);

		// only the preferences of the widget are stored, never other fields of the request (csrf_token, item_id, ...)
		$contentData = [];
		foreach ($preferencesData as $key => $value)
		{
			$mandatory = (array_key_exists('mandatory', $value) && $value['mandatory'] === 'true');
			$has_key = (array_key_exists($key, $requestData) && !empty($requestData[$key]));
			if (!$init && !$has_key && $mandatory)
				throw new TranslatableModuleException('playlists', 'widget_field_mandatory', ['field' => $key]);

			// an empty value (0, an emptied text) is a value of the user, only a missing one is left out
			if (!array_key_exists($key, $requestData))
				continue;

			// a preference of a plain widget has no type: it is a text, like in the editor
			switch ($value['types'] ?? 'text')
			{
				case 'colorOpacity':
				case 'integer':
					$contentData[$key] = (int)$requestData[$key];
					break;
				default:
				case 'text':
				case 'radio':
				case 'color':
				case 'list':
				case 'combo':
					$contentData[$key] = is_scalar($requestData[$key]) ? (string) $requestData[$key] : '';
					break;
			}
		}

		return $contentData;
	}

	/**
	 * @return array<string, mixed>
	 * @throws ModuleException
	 * @throws FrameworkException
	 */
	public function determinePreferences(string $configData): array
	{
		if (!$this->configXml->load($configData)->hasEditablePreferences())
			throw new TranslatableModuleException('playlists', 'widget_not_editable');

		$this->configXml->parseBasic()->parsePreferences();

		return $this->configXml->getPreferences();
	}

}