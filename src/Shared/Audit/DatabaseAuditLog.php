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

namespace App\Shared\Audit;

use JsonException;

/**
 * The audit log while it is on: one row per action.
 */
final readonly class DatabaseAuditLog implements AuditLog
{
	public function __construct(private AuditLogRepository $repository) {}

	public function isEnabled(): bool
	{
		return true;
	}

	/**
	 * @throws JsonException
	 */
	public function record(int $UID, string $module, AuditAction $action, int|string|null $unitId = null, ?int $targetUID = null, array $details = []): void
	{
		$this->repository->insert([
			'created_at' => date('Y-m-d H:i:s'),
			'UID'        => $UID,
			'module'     => $module,
			'action'     => $action->value,
			'unit_id'    => $unitId === null ? null : (string) $unitId,
			'target_UID' => $targetUID,
			'details'    => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
		]);
	}
}
