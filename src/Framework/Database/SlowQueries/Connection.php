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

namespace App\Framework\Database\SlowQueries;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;

final class Connection extends AbstractConnectionMiddleware
{
	public function __construct(ConnectionInterface $connection, private readonly SlowQueryLog $log)
	{
		parent::__construct($connection);
	}

	public function prepare(string $sql): StatementInterface
	{
		return new Statement(parent::prepare($sql), $sql, $this->log);
	}

	public function query(string $sql): Result
	{
		return $this->log->measure($sql, fn(): Result => parent::query($sql));
	}

	public function exec(string $sql): int|string
	{
		return $this->log->measure($sql, fn(): int|string => parent::exec($sql));
	}
}
