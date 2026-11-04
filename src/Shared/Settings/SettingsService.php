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

namespace App\Shared\Settings;

use App\Framework\Config\Config;
use App\Framework\Config\ConfigLoaderInterface;
use App\Framework\Config\ConfigOverrides;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Shared\Acl\AclHelper;
use Doctrine\DBAL\Exception;

/**
 * The settings page: the defaults are in config/settings, the changes of the admin in var/settings.
 * Only the module admin of a module changes its settings, and only the ones of its schema.
 */
class SettingsService
{
	private const string MODULE_NAME = 'system';

	/**
	 * @param array<string,SettingsSchema> $schemas module name => schema
	 */
	public function __construct(
		private readonly AclHelper $aclHelper,
		private readonly ConfigLoaderInterface $defaults,
		private readonly ConfigOverrides $overrides,
		private readonly array $schemas
	) {}

	/**
	 * The modules whose settings the user may change: the tabs of the settings page.
	 *
	 * @return list<string>
	 * @throws CoreException
	 * @throws Exception
	 */
	public function findEditableModules(int $UID): array
	{
		return array_values(array_filter(array_keys($this->schemas), fn(string $module) => $this->aclHelper->isModuleAdmin($UID, $module)));
	}

	/**
	 * @return list<array{field:SettingField, value:string, default:string, changed:bool}>
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function findSettings(int $UID, string $module): array
	{
		$this->assertMayChange($UID, $module);

		$defaults  = $this->defaults->load($module);
		$overrides = $this->overrides->load($module);
		$settings  = [];
		foreach ($this->schemas[$module]->fields() as $field)
		{
			$default  = $this->valueOf($defaults, $field);
			$override = $this->valueOf($overrides, $field);
			$settings[] = ['field' => $field, 'value' => $override ?? $default ?? '', 'default' => $default ?? '', 'changed' => $override !== null];
		}

		return $settings;
	}

	/**
	 * Stores the values of the schema. A value like the default is no change, so its key is removed:
	 * a later default of an update takes effect. The keys to reset go back to the default.
	 *
	 * @param array<string,mixed> $values "section.key" => value, a switch which is not sent is off
	 * @param list<string> $reset "section.key"
	 * @throws AccessDeniedException
	 * @throws TranslatableModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function save(int $UID, string $module, array $values, array $reset = []): void
	{
		$this->assertMayChange($UID, $module);

		$defaults  = $this->defaults->load($module);
		$overrides = $this->overrides->load($module);
		$sections  = [];
		// changes which are not in the schema (written by hand) stay
		foreach ($overrides as $section => $keys)
		{
			if (is_array($keys))
				$sections[$section] = $keys;
		}

		foreach ($this->schemas[$module]->fields() as $field)
		{
			$name = $field->section . '.' . $field->key;
			unset($sections[$field->section][$field->key]);
			if (in_array($name, $reset, true))
				continue;

			$value = $this->validate($field, $values[$name] ?? null);
			if ($value !== ($this->valueOf($defaults, $field) ?? ''))
				$sections[$field->section][$field->key] = $value;
		}

		$this->overrides->save($module, $sections);
	}

	/**
	 * @throws TranslatableModuleException
	 */
	private function validate(SettingField $field, mixed $value): string
	{
		$invalid = new TranslatableModuleException(self::MODULE_NAME, 'setting_invalid', ['setting' => $field->section . '.' . $field->key]);

		return match ($field->type)
		{
			SettingType::SWITCH => in_array($value, ['1', 1, true, 'on'], true) ? '1' : '0',
			SettingType::NUMBER => $this->validateNumber($field, $value) ?? throw $invalid,
			SettingType::TEXT   => is_string($value) && !str_contains($value, '"') && preg_match('/[\r\n]/', $value) !== 1 ? trim($value) : throw $invalid,
		};
	}

	private function validateNumber(SettingField $field, mixed $value): ?string
	{
		if (!is_string($value) && !is_int($value))
			return null;

		$number = filter_var($value, FILTER_VALIDATE_INT);
		if ($number === false || ($field->min !== null && $number < $field->min) || ($field->max !== null && $number > $field->max))
			return null;

		return (string) $number;
	}

	/**
	 * @param array<string,string|array<string,string>> $config
	 */
	private function valueOf(array $config, SettingField $field): ?string
	{
		$section = $config[$field->section] ?? null;
		if (!is_array($section) || !array_key_exists($field->key, $section))
			return null;

		// the defaults have comments behind the value
		return trim(explode(';', $section[$field->key])[0]);
	}

	/**
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 */
	private function assertMayChange(int $UID, string $module): void
	{
		if (!isset($this->schemas[$module]) || !$this->aclHelper->isModuleAdmin($UID, $module))
			throw new AccessDeniedException(self::MODULE_NAME, 'settings_not_changeable', ['module' => $module]);
	}
}
