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
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Mediapool\Services\NodesService;
use PHPUnit\Framework\Attributes\Group;
use Throwable;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Who may see, create, rename, delete and move which directory. Fixtures: the public tree 1 to 6 of
 * the migration (owner admin, visibility public), anna 7 with 8 Fotos and 9 Archiv,
 * bernd 10 with 11 Privat and 12 Geteilt (public).
 */
#[Group('acl')]
class NodeAccessTest extends ContainerTestCase
{
	private NodesService $nodesService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->nodesService = $this->container->get(NodesService::class);
	}

	public function testTheTreeShowsTheRightsOfTheDirectoriesTheUserMayRead(): void
	{
		$this->actingAs(2);

		$rights = array_column($this->nodesService->getNodes(0), 'rights', 'key');

		// her own user folder: she may add directories, but not rename or delete it
		static::assertTrue($rights[7]['create']);
		static::assertFalse($rights[7]['edit']);
		static::assertFalse($rights[7]['delete']);
		// the public tree is for reading only
		static::assertFalse($rights[1]['create']);
		static::assertFalse($rights[1]['edit']);
		static::assertFalse($rights[1]['delete']);
	}

	/**
	 * The folder of another user is not shown: its name is the name of the user.
	 */
	public function testTheTreeDoesNotShowTheFolderOfAnotherUser(): void
	{
		$this->actingAs(2);
		static::assertSame([1, 7], array_column($this->nodesService->getNodes(0), 'key'));

		$this->actingAs(3);
		static::assertSame([1, 10], array_column($this->nodesService->getNodes(0), 'key'));
	}

	public function testAdminMayDoEverythingInTheTree(): void
	{
		$this->actingAs(1);

		$rights = array_column($this->nodesService->getNodes(0), 'rights', 'key');

		static::assertSame([1, 7, 10], array_column($this->nodesService->getNodes(0), 'key'));
		foreach ([1, 7, 10] as $nodeId)
		{
			static::assertTrue($rights[$nodeId]['create']);
			static::assertTrue($rights[$nodeId]['edit']);
			static::assertTrue($rights[$nodeId]['delete']);
		}
	}

	/**
	 * Of the directories of another user only the public one is shown, and it can not be changed.
	 */
	public function testOfAForeignDirectoryOnlyThePublicChildrenAreShown(): void
	{
		$this->actingAs(2);

		$nodes = $this->nodesService->getNodes(10);

		static::assertSame([12], array_column($nodes, 'key'));
		static::assertSame(['create' => false, 'edit' => false, 'delete' => false, 'share' => ''], $nodes[0]['rights']);
	}

	public function testThePrivateDirectoriesOfOthersAreNotShownAtAnyLevel(): void
	{
		$this->actingAs(3);

		static::assertSame([], $this->nodesService->getNodes(7));
	}

	/**
	 * @return array<string,array{int,bool}>
	 */
	public static function uploadTargets(): array
	{
		return [
			'own sub directory'           => [8, true],
			'own user folder'             => [7, true],
			'private directory of bernd'  => [11, false],
			'public directory of bernd'   => [12, false],
			'user folder of bernd'        => [10, false],
			'public images'               => [2, false],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('uploadTargets')]
	public function testUserMayOnlyUploadIntoDirectoriesWhereSheMayCreate(int $nodeId, bool $expected): void
	{
		$this->actingAs(2);

		static::assertSame($expected, $this->nodesService->isNodeUploadable($nodeId));
	}

	public function testAdminMayUploadEverywhere(): void
	{
		$this->actingAs(1);

		foreach ([2, 8, 11, 12] as $nodeId)
			static::assertTrue($this->nodesService->isNodeUploadable($nodeId));
	}

	public function testUploadIntoAnUnknownDirectoryIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->nodesService->isNodeUploadable(999);
	}

	public function testUserMayAddDirectoriesInHerOwnTree(): void
	{
		$this->actingAs(2);

		$inRoot = $this->nodesService->addNode(7, 'Neu in Root');
		$inSub  = $this->nodesService->addNode(8, 'Neu in Fotos');

		static::assertSame('Neu in Root', $this->nodeName($inRoot));
		static::assertSame(8, $this->parentOf($inSub));
		static::assertSame(2, $this->ownerOf($inSub));
	}

	/**
	 * @return array<string,array{int}>
	 */
	public static function foreignParents(): array
	{
		return [
			'private directory of bernd' => [11],
			'public directory of bernd'  => [12],
			'user folder of bernd'       => [10],
			'public tree'                => [1],
			'public images'              => [2],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('foreignParents')]
	public function testUserMayNotAddDirectoriesInForeignOrPublicTrees(int $parentId): void
	{
		$this->actingAs(2);
		$before = $this->countNodes();

		$this->expectDenied(fn() => $this->nodesService->addNode($parentId, 'gehackt'));

		static::assertSame($before, $this->countNodes());
	}

	public function testOnlyTheAdminMayAddRootDirectories(): void
	{
		$this->actingAs(2);
		$before = $this->countNodes();
		$this->expectDenied(fn() => $this->nodesService->addNode(0, 'Neue Wurzel'));
		static::assertSame($before, $this->countNodes());

		$this->actingAs(1);
		$id = $this->nodesService->addNode(0, 'Neue Wurzel');
		static::assertSame(0, $this->parentOf($id));
	}

	public function testUserMayRenameHerOwnSubDirectory(): void
	{
		$this->actingAs(2);

		$this->nodesService->editNode(8, 'Urlaubsfotos');

		static::assertSame('Urlaubsfotos', $this->nodeName(8));
	}

	/**
	 * @return array<string,array{int}>
	 */
	public static function notEditableNodes(): array
	{
		return [
			'private directory of bernd' => [11],
			'public directory of bernd'  => [12],
			'public images'              => [2],
			'the own user folder'        => [7],
			'public tree'                => [1],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('notEditableNodes')]
	public function testUserMayNotRenameOtherDirectories(int $nodeId): void
	{
		$this->actingAs(2);
		$before = $this->nodeName($nodeId);

		$this->expectDenied(fn() => $this->nodesService->editNode($nodeId, 'gehackt'));

		static::assertSame($before, $this->nodeName($nodeId));
	}

	public function testOnlyTheAdminMayChangeTheVisibility(): void
	{
		$this->actingAs(2);
		$this->nodesService->editNode(8, 'Fotos', 2);
		static::assertSame(0, $this->visibilityOf(8));

		$this->actingAs(1);
		$this->nodesService->editNode(8, 'Fotos', 2);
		static::assertSame(2, $this->visibilityOf(8));
	}

	public function testAdminMayRenameEveryDirectory(): void
	{
		$this->actingAs(1);

		$this->nodesService->editNode(11, 'Admin war da');
		$this->nodesService->editNode(10, 'bernd2');

		static::assertSame('Admin war da', $this->nodeName(11));
		static::assertSame('bernd2', $this->nodeName(10));
	}

	public function testUnknownDirectoryIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->nodesService->editNode(999, 'x');
	}

	public function testUserMayDeleteHerOwnSubDirectory(): void
	{
		$this->actingAs(2);

		static::assertSame(1, $this->nodesService->deleteNode(9));

		static::assertSame(0, $this->countWhere('node_id = 9'));
		static::assertSame(1, $this->countWhere('node_id = 8'));
	}

	/**
	 * @return array<string,array{int}>
	 */
	public static function notDeletableNodes(): array
	{
		return [
			'private directory of bernd' => [11],
			'public directory of bernd'  => [12],
			'user folder of bernd'       => [10],
			'the own user folder'        => [7],
			'public images'              => [2],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('notDeletableNodes')]
	public function testUserMayNotDeleteOtherDirectories(int $nodeId): void
	{
		$this->actingAs(2);
		$before = $this->countNodes();

		$this->expectDenied(fn() => $this->nodesService->deleteNode($nodeId));

		static::assertSame($before, $this->countNodes());
	}

	public function testAdminMayDeleteADirectoryTree(): void
	{
		$this->actingAs(1);

		static::assertSame(3, $this->nodesService->deleteNode(10));

		static::assertSame(0, $this->countWhere('root_id = 10'));
		static::assertSame(3, $this->countWhere('root_id = 7'));
	}

	public function testUserMayMoveHerOwnDirectoryIntoAnotherOwnDirectory(): void
	{
		$this->actingAs(2);

		$this->nodesService->moveNode(8, 9, 'appendChild');

		static::assertSame(9, $this->parentOf(8));
	}

	/**
	 * The move works on ids from the request. Neither the moved directory nor the target is checked
	 * against the rights of the user in the service.
	 */
	public function testUserMayNotMoveADirectoryOfAnotherUser(): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->nodesService->moveNode(11, 9, 'appendChild'));

		static::assertSame(10, $this->parentOf(11));
		static::assertSame(10, $this->rootOf(11));
	}

	public function testUserMayNotMoveHerDirectoryIntoTheTreeOfAnotherUser(): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->nodesService->moveNode(8, 11, 'appendChild'));

		static::assertSame(7, $this->parentOf(8));
		static::assertSame(7, $this->rootOf(8));
	}

	public function testUserMayNotMoveADirectoryIntoThePublicTree(): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->nodesService->moveNode(8, 2, 'appendChild'));

		static::assertSame(7, $this->parentOf(8));
	}

	public function testUserMayNotMoveAPublicDirectoryAway(): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->nodesService->moveNode(2, 9, 'appendChild'));

		static::assertSame(1, $this->parentOf(2));
	}

	public function testAdminMayMoveDirectories(): void
	{
		$this->actingAs(1);

		$this->nodesService->moveNode(11, 9, 'appendChild');

		static::assertSame(9, $this->parentOf(11));
	}

	/**
	 * Passes only if the call is rejected by the ACL, not by a technical error.
	 *
	 * @param callable():mixed $call
	 */
	private function expectDenied(callable $call): void
	{
		try
		{
			$call();
		}
		catch (AccessDeniedException|NotFoundException)
		{
			static::addToAssertionCount(1);

			return;
		}
		catch (Throwable $e)
		{
			static::fail('Expected a rejection by the ACL but got ' . $e::class . ': ' . $e->getMessage());
		}

		static::fail('The call was not rejected. A user changed directories of other users.');
	}

	private function countNodes(): int
	{
		return $this->countWhere('1 = 1');
	}

	private function countWhere(string $where): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_nodes WHERE ' . $where);
	}

	private function nodeName(int $nodeId): string
	{
		return (string) $this->connection->fetchOne('SELECT name FROM mediapool_nodes WHERE node_id = ?', [$nodeId]);
	}

	private function parentOf(int $nodeId): int
	{
		return (int) $this->connection->fetchOne('SELECT parent_id FROM mediapool_nodes WHERE node_id = ?', [$nodeId]);
	}

	private function rootOf(int $nodeId): int
	{
		return (int) $this->connection->fetchOne('SELECT root_id FROM mediapool_nodes WHERE node_id = ?', [$nodeId]);
	}

	private function ownerOf(int $nodeId): int
	{
		return (int) $this->connection->fetchOne('SELECT UID FROM mediapool_nodes WHERE node_id = ?', [$nodeId]);
	}

	private function visibilityOf(int $nodeId): int
	{
		return (int) $this->connection->fetchOne('SELECT visibility FROM mediapool_nodes WHERE node_id = ?', [$nodeId]);
	}
}
