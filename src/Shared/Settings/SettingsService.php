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
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
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
		private readonly array $schemas,
		private readonly AuditLog $auditLog
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
			$settings[] = ['field' => $field, 'value' => $this->toUnit($field, $override ?? $default ?? ''), 'default' => $this->toUnit($field, $default ?? ''), 'changed' => $override !== null];
		}

		return $settings;
	}

	/**
	 * Stores the values of the schema. A value like the default is no change, so its key is removed:
	 * a later default of an update takes effect.
	 *
	 * @param array<string,mixed> $values "section.key" => value, a switch which is not sent is off
	 * @throws AccessDeniedException
	 * @throws TranslatableModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function save(int $UID, string $module, array $values): void
	{
		$this->assertMayChange($UID, $module);

		$defaults  = $this->defaults->load($module);
		$overrides = $this->overrides->load($module);
		$sections  = $this->sectionsOf($overrides);
		foreach ($this->schemas[$module]->fields() as $field)
		{
			$name = $field->section . '.' . $field->key;
			// the form does not send a setting whose switch is off: it keeps its value
			if ($field->dependsOn !== null && !$this->isOn($values[$field->dependsOn] ?? null))
				continue;

			unset($sections[$field->section][$field->key]);
			$value = $this->validate($field, $values[$name] ?? null);
			if ($value !== ($this->valueOf($defaults, $field) ?? ''))
				$sections[$field->section][$field->key] = $value;
		}

		$this->overrides->save($module, $sections);
		$this->recordChanges($UID, $module, $defaults, $overrides, $sections);
	}

	/**
	 * One setting goes back to its default, the others stay as they are stored.
	 *
	 * @param string $name "section.key" of the schema
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function reset(int $UID, string $module, string $name): void
	{
		$this->assertMayChange($UID, $module);

		$overrides = $this->overrides->load($module);
		$sections  = $this->sectionsOf($overrides);
		foreach ($this->schemas[$module]->fields() as $field)
		{
			if ($field->section . '.' . $field->key === $name)
				unset($sections[$field->section][$field->key]);
		}

		$this->overrides->save($module, $sections);
		$this->recordChanges($UID, $module, $this->defaults->load($module), $overrides, $sections);
	}

	/**
	 * The changes which are not in the schema (written by hand) stay.
	 *
	 * @param array<string,string|array<string,string>> $overrides
	 * @return array<string,array<string,string>>
	 */
	private function sectionsOf(array $overrides): array
	{
		$sections = [];
		foreach ($overrides as $section => $keys)
		{
			if (is_array($keys))
				$sections[$section] = $keys;
		}

		return $sections;
	}

	/**
	 * The settings whose value changed, with the new value.
	 *
	 * @param array<string,string|array<string,string>> $defaults
	 * @param array<string,string|array<string,string>> $before
	 * @param array<string,array<string,string>> $after
	 * @throws Exception
	 */
	private function recordChanges(int $UID, string $module, array $defaults, array $before, array $after): void
	{
		$changed = [];
		foreach ($this->schemas[$module]->fields() as $field)
		{
			$default  = $this->valueOf($defaults, $field) ?? '';
			$newValue = $this->valueOf($after, $field) ?? $default;
			if (($this->valueOf($before, $field) ?? $default) !== $newValue)
				$changed[$field->section . '.' . $field->key] = $newValue;
		}

		if ($changed !== [])
			$this->auditLog->record($UID, $module, AuditAction::SETTINGS_CHANGED, null, null, ['changed' => $changed]);
	}

	/**
	 * @throws TranslatableModuleException
	 */
	private function validate(SettingField $field, mixed $value): string
	{
		$invalid = new TranslatableModuleException(self::MODULE_NAME, 'setting_invalid', ['setting' => $field->section . '.' . $field->key]);

		return match ($field->type)
		{
			SettingType::SWITCH => $this->isOn($value) ? '1' : '0',
			SettingType::NUMBER => $this->validateNumber($field, $value) ?? throw $invalid,
			SettingType::TEXT   => is_string($value) && !str_contains($value, '"') && preg_match('/[\r\n]/', $value) !== 1 ? trim($value) : throw $invalid,
			SettingType::SELECT => is_string($value) && in_array($value, $field->options, true) ? $value : throw $invalid,
		};
	}

	private function isOn(mixed $value): bool
	{
		return in_array($value, ['1', 1, true, 'on'], true);
	}

	private function validateNumber(SettingField $field, mixed $value): ?string
	{
		if (!is_string($value) && !is_int($value))
			return null;

		$number = filter_var($value, FILTER_VALIDATE_INT);
		if ($number === false || ($field->min !== null && $number < $field->min) || ($field->max !== null && $number > $field->max))
			return null;

		return (string) ($number * $field->factor);
	}

	/**
	 * A stored number in the unit of the form, a value which is no whole unit is rounded.
	 */
	private function toUnit(SettingField $field, string $value): string
	{
		if ($field->factor === 1 || !is_numeric($value))
			return $value;

		return (string) (int) round((float) $value / $field->factor);
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
