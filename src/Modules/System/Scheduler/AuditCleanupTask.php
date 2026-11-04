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

namespace App\Modules\System\Scheduler;

use App\Framework\Config\Config;
use App\Shared\Audit\AuditLogRepository;
use App\Shared\Scheduler\ScheduledTask;
use App\Shared\Scheduler\TaskInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;

/**
 * Deletes the entries of the audit log which are older than the retention of the settings.
 * It runs while the audit log is off, too: the old entries go anyway.
 */
final readonly class AuditCleanupTask implements ScheduledTask
{
	public function __construct(private AuditLogRepository $repository, private Config $config) {}

	public function name(): string
	{
		return 'audit.cleanup';
	}

	public function interval(): TaskInterval
	{
		return TaskInterval::DAILY;
	}

	/**
	 * @throws Exception
	 */
	public function run(): void
	{
		$days = max(1, (int) $this->config->getConfigValue('retention_days', 'system', 'Audit'));

		$this->repository->deleteOlderThan(new DateTimeImmutable('-' . $days . ' days'));
	}
}
