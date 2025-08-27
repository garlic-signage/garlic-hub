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

		foreach ($preferencesData as $key => $value)
		{
			$mandatory = (array_key_exists('mandatory', $value) && $value['mandatory'] === 'true');
			$has_key = (array_key_exists($key, $requestData) && !empty($requestData[$key]));
			if (!$init && !$has_key && $mandatory)
				throw new ModuleException('items', $key . ' is mandatory field.');

			if (!$has_key)
				continue;

			switch ($value['types'])
			{
				case 'colorOpacity':
				case 'integer':
					$requestData[$key] = (int)$requestData[$key];
					break;
				default:
				case 'text':
				case 'radio':
				case 'color':
				case 'list':
				case 'combo':
					$requestData[$key] = htmlspecialchars($requestData[$key], ENT_QUOTES);
					break;
			}
		}

		return $requestData;
	}

	/**
	 * @return array<string, mixed>
	 * @throws ModuleException
	 * @throws FrameworkException
	 */
	public function determinePreferences(string $configData): array
	{
		if (!$this->configXml->load($configData)->hasEditablePreferences())
			throw new ModuleException('items', 'Widget has no editable preferences.');

		$this->configXml->parseBasic()->parsePreferences();

		return $this->configXml->getPreferences();
	}

}