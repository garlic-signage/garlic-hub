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

use Doctrine\DBAL\Exception;

/**
 * Who changed what. The container gives the NullAuditLog while the audit log or the scheduler is off:
 * the callers record without asking whether it is enabled.
 */
interface AuditLog
{
	public function isEnabled(): bool;

	/**
	 * @param int $UID who did it, 0 for nobody known (a failed login)
	 * @param int|null $targetUID the user it was done to (rights, users)
	 * @param array<string,mixed> $details
	 * @throws Exception
	 */
	public function record(int $UID, string $module, AuditAction $action, int|string|null $unitId = null, ?int $targetUID = null, array $details = []): void;
}
