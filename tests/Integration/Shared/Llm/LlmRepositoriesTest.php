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

namespace Tests\Integration\Shared\Llm;

use App\Shared\Llm\UserLlmConnectionRepository;
use App\Shared\Llm\UserLlmTaskRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Every read and change of the connections and tasks is one of the owner: an id of another owner changes nothing.
 * Users: 2 anna, 3 bernd. The repositories of the company work the same way with the company id.
 */
#[Group('auth')]
class LlmRepositoriesTest extends ContainerTestCase
{
	private UserLlmConnectionRepository $connections;
	private UserLlmTaskRepository $tasks;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->connections = new UserLlmConnectionRepository($this->connection);
		$this->tasks       = new UserLlmTaskRepository($this->connection);
		foreach ([[1, 2, 'Zeta'], [2, 2, 'Alpha'], [3, 3, 'Bernds']] as [$id, $UID, $name])
			$this->connection->insert('user_llm_connections', ['connection_id' => $id, 'UID' => $UID, 'name' => $name, 'base_url' => 'https://llm.example.test', 'api_key' => 'key-' . $id]);
		$this->tasks->storeTask(2, 'text', ['connection_id' => 1, 'model' => 'small']);
	}

	public function testTheConnectionsOfAnOwnerAreOnlyHisSortedByName(): void
	{
		static::assertSame(['Alpha', 'Zeta'], array_column($this->connections->findAllOfOwner(2), 'name'));
		static::assertSame([], $this->connections->findOfOwner(3, 1));
	}

	public function testAConnectionOfAnotherOwnerIsNotChangedOrDeleted(): void
	{
		$this->connections->store(3, 1, ['name' => 'taken', 'base_url' => 'https://evil.example.test']);
		static::assertSame(0, $this->connections->deleteOfOwner(3, 1));

		static::assertSame(['name' => 'Zeta', 'base_url' => 'https://llm.example.test'], $this->connection->fetchAssociative('SELECT name, base_url FROM user_llm_connections WHERE connection_id = 1'));
	}

	public function testATaskOfAnotherOwnerIsNotReadChangedOrDeleted(): void
	{
		static::assertSame([], $this->tasks->findTask(3, 'text'));

		$this->tasks->updateWithConnection(3, 1, ['model' => 'other']);
		$this->tasks->deleteWithConnection(3, 1);
		$this->tasks->deleteTask(3, 'text');

		static::assertSame('small', $this->tasks->findTask(2, 'text')['model'] ?? null);
		static::assertSame(['text'], array_keys($this->tasks->findAllOfOwner(2)));
	}
}
