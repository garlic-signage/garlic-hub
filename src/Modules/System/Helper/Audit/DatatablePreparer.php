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

namespace App\Modules\System\Helper\Audit;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Datatable\AbstractDatatablePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Shared\Audit\AuditAction;

/**
 * The rows of the audit log as text. A failed login shows the user it was tried for,
 * or what was typed in when there is no such user.
 */
class DatatablePreparer extends AbstractDatatablePreparer
{
	private const string MODULE_NAME = 'system';

	public function __construct(PrepareService $prepareService, Parameters $parameters, Translator $translator)
	{
		// the links of the pages are relative to /system/audit
		parent::__construct('audit', $prepareService, $parameters, $translator);
	}

	/**
	 * @param list<array<string,mixed>> $currentFilterResults with target_username and unit_name of the service
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function prepareTableBody(array $currentFilterResults, array $fields, int $currentUID): array
	{
		$body = [];
		foreach ($currentFilterResults as $entry)
		{
			$details = $this->decodeDetails($entry['details'] ?? null);
			$list    = ['UNIT_ID' => $entry['id']];
			foreach ($fields as $headerField)
			{
				$name = $headerField->getName();
				$text = match ($name)
				{
					'created_at' => (string) $entry['created_at'],
					'UID'        => $this->formatWho($entry, $details),
					'action'     => $this->translator->translate('action_' . $entry['action'], self::MODULE_NAME),
					'module'     => (string) $entry['module'],
					'target_UID' => $this->formatTarget($entry),
					'details'    => $this->formatDetails($entry, $details),
					default      => ''
				};
				$list['elements_result_element'][] = ['CONTROL_NAME_BODY' => $name, 'is_text' => $this->prepareService->getBodyPreparer()->formatText($text)];
			}

			$body[] = $list;
		}

		return $body;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeDetails(mixed $json): array
	{
		$details = is_string($json) ? json_decode($json, true) : null;

		return is_array($details) ? $details : [];
	}

	/**
	 * @param array<string,mixed> $entry
	 * @param array<string,mixed> $details
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function formatWho(array $entry, array $details): string
	{
		if ($entry['action'] === AuditAction::LOGIN_FAILED->value)
		{
			if (is_string($entry['target_username'] ?? null))
				return $entry['target_username'];

			return '„' . (is_string($details['username'] ?? null) ? $details['username'] : '') . '" (' . $this->translator->translate('unknown', self::MODULE_NAME) . ')';
		}

		return is_string($entry['username'] ?? null) ? $entry['username'] : $this->translator->translate('unknown', self::MODULE_NAME);
	}

	/**
	 * @param array<string,mixed> $entry
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function formatTarget(array $entry): string
	{
		$parts = [];
		if ($entry['target_UID'] !== null)
			$parts[] = is_string($entry['target_username'] ?? null) ? $entry['target_username'] : '#' . $entry['target_UID'];

		if ($entry['unit_id'] !== null)
		{
			// a deleted unit has no name anymore
			$name    = is_string($entry['unit_name'] ?? null) ? $entry['unit_name'] : '#' . $entry['unit_id'];
			$parts[] = $this->translator->translate('unit', self::MODULE_NAME) . ': ' . $name;
		}

		return implode(', ', $parts);
	}

	/**
	 * @param array<string,mixed> $entry
	 * @param array<string,mixed> $details
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function formatDetails(array $entry, array $details): string
	{
		// what was typed in is in the column of who
		if ($entry['action'] === AuditAction::LOGIN_FAILED->value)
			unset($details['username']);

		$parts = [];
		foreach ($details as $key => $value)
			$parts[] = $this->translator->translate('detail_' . $key, self::MODULE_NAME) . ': ' . $this->formatValue($key, $value);

		return implode('; ', $parts);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function formatValue(string $key, mixed $value): string
	{
		if ($key === 'role' && is_string($value))
			return $this->translator->translate('role_' . $value, 'users');

		if (!is_array($value))
			return is_scalar($value) ? (string) $value : '';

		$texts = [];
		foreach ($value as $name => $item)
		{
			$item    = is_scalar($item) ? (string) $item : '';
			$texts[] = is_string($name) ? $name . ' = ' . $item : $item;
		}

		return implode(', ', $texts);
	}
}
