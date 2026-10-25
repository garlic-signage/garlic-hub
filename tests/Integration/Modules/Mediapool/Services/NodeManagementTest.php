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

namespace Tests\Integration\Modules\Mediapool\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Mediapool\Services\NodesService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * What the directory tree does besides the rights (see NodeAccessTest): user folders, the number of deleted
 * directories, the media of deleted directories and the refused moves. Fixtures: the public tree 1 to 6, anna 7
 * with 8 Fotos and 9 Archiv, bernd 10 with 11 Privat and 12 Geteilt. The media of mediapool/files.sql.
 */
#[Group('acl')]
class NodeManagementTest extends ContainerTestCase
{
	private NodesService $nodesService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->loadFixture('mediapool/files.sql');
		$this->nodesService = $this->container->get(NodesService::class);
	}

	public function testAUserFolderIsARootDirectoryOfTheUser(): void
	{
		$this->actingAs(1);

		$id = $this->nodesService->addUserDirectory(4, 'neuer');

		$row = $this->connection->fetchAssociative('SELECT parent_id, UID, is_user_folder, name FROM mediapool_nodes WHERE node_id = ?', [$id]);
		static::assertSame(['parent_id' => 0, 'UID' => 4, 'is_user_folder' => 1, 'name' => 'neuer'], array_map(static fn(mixed $v): mixed => is_numeric($v) ? (int) $v : $v, $row ?: []));
	}

	public function testOnlyTheAdminDeletesAUserFolderWithEverythingInIt(): void
	{
		$this->actingAs(2);
		try
		{
			$this->nodesService->deleteUserDirectory(3);
			static::fail('Expected an AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame(3, $this->countNodes('root_id = 10'));
		}
	}

	public function testDeletingAUserFolderRemovesTheTreeAndMarksTheMediaAsDeleted(): void
	{
		$this->actingAs(1);

		$this->nodesService->deleteUserDirectory(2);

		static::assertSame(0, $this->countNodes('root_id = 7'));
		static::assertSame(3, $this->countNodes('root_id = 10'), 'the folder of bernd is not touched');
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files WHERE node_id = 8 AND deleted = 0'));
		static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files WHERE deleted = 0 AND node_id IN (11, 12)'));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files WHERE deleted = 0 AND node_id = 2'));
	}

	/**
	 * A user without a folder (e.g. deleted before it was created) is nothing to fail about.
	 */
	public function testDeletingTheFolderOfAUserWithoutOneDoesNothing(): void
	{
		$this->actingAs(2);
		$before = $this->countNodes('1 = 1');

		$this->nodesService->deleteUserDirectory(999);

		static::assertSame($before, $this->countNodes('1 = 1'));
	}

	public function testTheNumberOfDeletedDirectoriesIsGiven(): void
	{
		$this->actingAs(1);

		static::assertSame(1, $this->nodesService->deleteNode(8));
		static::assertSame(2, $this->nodesService->deleteNode(7), 'the folder with the one directory which is left');
		static::assertSame(3, $this->nodesService->deleteNode(10));
	}

	/**
	 * Without the directory nobody can read its media again (the rights come from the directory):
	 * they are marked as deleted with it, and so are the media of the directories below.
	 */
	public function testTheMediaOfADeletedDirectoryAreMarkedAsDeleted(): void
	{
		$this->actingAs(1);

		$this->nodesService->deleteNode(8);

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files WHERE node_id = 8 AND deleted = 0'));
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files WHERE deleted = 0'), 'the media of other directories stay');
	}

	public function testTheMediaOfAllDirectoriesOfADeletedTreeAreMarkedAsDeleted(): void
	{
		$this->actingAs(1);

		$this->nodesService->deleteNode(10);

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files WHERE node_id IN (11, 12) AND deleted = 0'));
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files WHERE deleted = 0'));
		static::assertSame(0, $this->countNodes('root_id = 10'));
	}

	public function testADeletedDirectoryWithoutMediaTouchesNoMedia(): void
	{
		$this->actingAs(1);
		$before = $this->connection->fetchAllAssociative('SELECT media_id, deleted FROM mediapool_files ORDER BY media_id');

		$this->nodesService->deleteNode(9);

		static::assertSame($before, $this->connection->fetchAllAssociative('SELECT media_id, deleted FROM mediapool_files ORDER BY media_id'));
	}

	public function testTheListMarksDirectoriesWithChildrenAsLazy(): void
	{
		$this->actingAs(1);

		$lazy = array_column($this->nodesService->getNodes(0), 'lazy', 'key');
		static::assertTrue($lazy[7]);
		static::assertTrue($lazy[1]);

		$children = $this->nodesService->getNodes(7);
		static::assertSame([8, 9], array_column($children, 'key'));
		static::assertSame([false, false], array_column($children, 'lazy'));
		static::assertSame(['Fotos', 'Archiv'], array_column($children, 'title'));
		static::assertSame([true, true], array_column($children, 'folder'));
	}

	public function testTheVisibilityIsPartOfTheList(): void
	{
		$this->actingAs(3);

		$children = array_column($this->nodesService->getNodes(10), 'visibility', 'key');

		static::assertSame([11 => 0, 12 => 2], $children);
	}

	/**
	 * @return array<string,array{int,int,string,string}>
	 */
	public static function refusedMoves(): array
	{
		return [
			'unknown region'            => [8, 9, 'inside', ModuleException::class],
			'empty region'              => [8, 9, '', ModuleException::class],
			'a root directory'          => [7, 9, 'appendChild', TranslatableModuleException::class],
			'unknown moved directory'   => [999, 9, 'appendChild', NotFoundException::class],
			'unknown target'            => [8, 999, 'appendChild', NotFoundException::class],
			'as new root by appending'  => [8, 0, 'appendChild', TranslatableModuleException::class],
			'before a root directory'   => [8, 7, 'before', TranslatableModuleException::class],
			'after a root directory'    => [8, 7, 'after', TranslatableModuleException::class],
		];
	}

	/**
	 * @param class-string<\Throwable> $exception
	 */
	#[DataProvider('refusedMoves')]
	public function testAnImpossibleMoveChangesNothing(int $moved, int $target, string $region, string $exception): void
	{
		$this->actingAs(1);
		$before = $this->connection->fetchAllAssociative('SELECT node_id, parent_id, lft, rgt FROM mediapool_nodes ORDER BY node_id');

		try
		{
			$this->nodesService->moveNode($moved, $target, $region);
			static::fail('Expected ' . $exception);
		}
		catch (\Throwable $e)
		{
			static::assertInstanceOf($exception, $e);
		}

		static::assertSame($before, $this->connection->fetchAllAssociative('SELECT node_id, parent_id, lft, rgt FROM mediapool_nodes ORDER BY node_id'));
	}

	public function testADirectoryCanBeMovedBeforeAndAfterAnother(): void
	{
		$this->actingAs(1);

		$this->nodesService->moveNode(9, 8, 'before');

		static::assertSame([9, 8], array_column($this->nodesService->getNodes(7), 'key'));

		$this->nodesService->moveNode(9, 8, 'after');

		static::assertSame([8, 9], array_column($this->nodesService->getNodes(7), 'key'));
	}

	public function testRenamingAnUnknownDirectoryIsNotFound(): void
	{
		$this->actingAs(1);

		$this->expectException(NotFoundException::class);
		$this->nodesService->editNode(999, 'x');
	}

	public function testOnlyTheModuleAdminIsAdmin(): void
	{
		static::assertTrue($this->nodesService->isModuleAdmin(1));
		static::assertFalse($this->nodesService->isModuleAdmin(2));
	}

	public function testDeletingAnUnknownDirectoryIsNotFound(): void
	{
		$this->actingAs(1);

		$this->expectException(NotFoundException::class);
		$this->nodesService->deleteNode(999);
	}

	public function testAddingBelowAnUnknownDirectoryIsNotFound(): void
	{
		$this->actingAs(1);
		$before = $this->countNodes('1 = 1');

		try
		{
			$this->nodesService->addNode(999, 'x');
			static::fail('Expected a NotFoundException');
		}
		catch (NotFoundException)
		{
			static::assertSame($before, $this->countNodes('1 = 1'));
		}
	}

	private function countNodes(string $where): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_nodes WHERE ' . $where);
	}
}
