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

namespace Tests\Integration\Modules\Mediapool\Controller;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Mediapool\Controller\NodesController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The directory endpoints /async/mediapool/node as the tree of the browser uses them.
 */
#[Group('acl')]
class NodesControllerTest extends ControllerTestCase
{
	private NodesController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(NodesController::class);
	}

	private function nameOf(int $nodeId): string
	{
		return (string) $this->databaseValue('SELECT name FROM mediapool_nodes WHERE node_id = ?', $nodeId);
	}

	private function countNodes(): int
	{
		return (int) $this->databaseValue('SELECT COUNT(*) FROM mediapool_nodes');
	}

	public function testTheListOfTheRootDirectoriesHasOnlyTheDirectoriesTheUserMayRead(): void
	{
		$this->actingAs(2);

		$response = $this->controller->list($this->request('GET'), $this->newResponse(), ['parent_id' => '0']);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		$nodes = $this->json($response);
		static::assertSame([1, 7], array_column($nodes, 'key'));
		static::assertSame('anna', $nodes[1]['title']);
		static::assertTrue($nodes[1]['rights']['create']);
	}

	public function testTheListOfTheChildren(): void
	{
		$this->actingAs(2);

		$nodes = $this->json($this->controller->list($this->request('GET'), $this->newResponse(), ['parent_id' => '7']));

		static::assertSame(['Fotos', 'Archiv'], array_column($nodes, 'title'));
	}

	public function testADirectoryIsAdded(): void
	{
		$this->actingAs(2);

		$response = $this->controller->add($this->request('POST', ['node_id' => '8', 'name' => 'Neu']), $this->newResponse());

		$this->assertSuccess($response);
		$data = $this->json($response)['data'];
		static::assertSame('Neu', $data['new_name']);
		static::assertSame('Neu', $this->nameOf($data['id']));
		static::assertSame(8, (int) $this->databaseValue('SELECT parent_id FROM mediapool_nodes WHERE node_id = ?', $data['id']));
	}

	public function testADirectoryIsAddedWithNumbersFromJson(): void
	{
		$this->actingAs(2);

		$response = $this->controller->add($this->request('POST', ['node_id' => 8, 'name' => 'Aus JSON']), $this->newResponse());

		$this->assertSuccess($response);
	}

	public function testTheAdminAddsARootDirectory(): void
	{
		$this->actingAs(1);

		$response = $this->controller->add($this->request('POST', ['name' => 'Wurzel']), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame(0, (int) $this->databaseValue('SELECT parent_id FROM mediapool_nodes WHERE node_id = ?', $this->json($response)['data']['id']));
	}

	/**
	 * @return array<string,array{array<string,mixed>,string}> the input and the message of the answer
	 */
	public static function wrongAdds(): array
	{
		return [
			'no name'         => [['node_id' => '8'], 'node name is missing'],
			'empty name'      => [['node_id' => '8', 'name' => ''], 'node name is missing'],
			'name is a list'  => [['node_id' => '8', 'name' => ['a']], 'node name is missing'],
			'name is a number' => [['node_id' => '8', 'name' => 5], 'node name is missing'],
			'parent is a list' => [['node_id' => ['8'], 'name' => 'x'], 'NodeId is missing'],
			'parent is text'  => [['node_id' => 'abc', 'name' => 'x'], 'NodeId is missing'],
			'parent is negative' => [['node_id' => '-1', 'name' => 'x'], 'NodeId is missing'],
		];
	}

	/**
	 * Input which is no input of a form gets the answer for a missing value, never an error of the server.
	 *
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('wrongAdds')]
	public function testAnAddWithWrongInputChangesNothing(array $body, string $message): void
	{
		$this->actingAs(1);
		$before = $this->countNodes();

		$response = $this->controller->add($this->request('POST', $body), $this->newResponse());

		$this->assertError($response, $message);
		static::assertSame($before, $this->countNodes());
	}

	public function testADirectoryIsRenamed(): void
	{
		$this->actingAs(2);

		$response = $this->controller->edit($this->request('PATCH', ['node_id' => '8', 'name' => 'Urlaub']), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame(['id' => 8, 'new_name' => 'Urlaub', 'visibility' => null], $this->json($response)['data']);
		static::assertSame('Urlaub', $this->nameOf(8));
	}

	/**
	 * @return array<string,array{mixed,int}> the visibility which is sent and the one which is stored
	 */
	public static function visibilities(): array
	{
		return [
			'a number'          => [2, 2],
			'a number as text'  => ['2', 2],
			'zero as text'      => ['0', 0],
			'a list'            => [[2], 0],
			'text'              => ['public', 0],
			'nothing'           => [null, 0],
		];
	}

	/**
	 * The admin changes the visibility. A value which is no number leaves it as it is.
	 */
	#[DataProvider('visibilities')]
	public function testTheVisibilityIsChangedByTheAdminWithANumberOnly(mixed $sent, int $stored): void
	{
		$this->actingAs(1);

		$response = $this->controller->edit($this->request('PATCH', ['node_id' => '8', 'name' => 'Fotos', 'visibility' => $sent]), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame($stored, (int) $this->databaseValue('SELECT visibility FROM mediapool_nodes WHERE node_id = ?', 8));
	}

	/**
	 * @return array<string,array{array<string,mixed>,string}>
	 */
	public static function wrongEdits(): array
	{
		return [
			'no name'        => [['node_id' => '8'], 'node name or id is missing'],
			'no node'        => [['name' => 'x'], 'node name or id is missing'],
			'name is a list' => [['node_id' => '8', 'name' => ['x']], 'node name or id is missing'],
			'node is a list' => [['node_id' => ['8'], 'name' => 'x'], 'node name or id is missing'],
			'node is text'   => [['node_id' => 'eight', 'name' => 'x'], 'node name or id is missing'],
		];
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('wrongEdits')]
	public function testAnEditWithWrongInputChangesNothing(array $body, string $message): void
	{
		$this->actingAs(1);

		$this->assertError($this->controller->edit($this->request('PATCH', $body), $this->newResponse()), $message);

		static::assertSame('Fotos', $this->nameOf(8));
	}

	public function testADirectoryOfAnotherUserIsNotRenamed(): void
	{
		$this->actingAs(2);

		try
		{
			$this->controller->edit($this->request('PATCH', ['node_id' => '11', 'name' => 'gehackt']), $this->newResponse());
			static::fail('Expected an AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame('Privat', $this->nameOf(11));
		}
	}

	public function testADirectoryIsMoved(): void
	{
		$this->actingAs(2);

		$response = $this->controller->move($this->request('POST', ['src_node_id' => '9', 'target_node_id' => '8', 'target_region' => 'before']), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame([9, 8], array_column($this->json($this->controller->list($this->request('GET'), $this->newResponse(), ['parent_id' => '7'])), 'key'));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongMoves(): array
	{
		return [
			'nothing'            => [[]],
			'no region'          => [['src_node_id' => '9', 'target_node_id' => '8']],
			'region is a list'   => [['src_node_id' => '9', 'target_node_id' => '8', 'target_region' => ['before']]],
			'empty region'       => [['src_node_id' => '9', 'target_node_id' => '8', 'target_region' => '']],
			'source is a list'   => [['src_node_id' => ['9'], 'target_node_id' => '8', 'target_region' => 'before']],
			'target is text'     => [['src_node_id' => '9', 'target_node_id' => 'x', 'target_region' => 'before']],
		];
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('wrongMoves')]
	public function testAMoveWithWrongInputChangesNothing(array $body): void
	{
		$this->actingAs(1);

		$this->assertError($this->controller->move($this->request('POST', $body), $this->newResponse()), 'Source node, target node, or target region is missing');

		static::assertSame(7, (int) $this->databaseValue('SELECT parent_id FROM mediapool_nodes WHERE node_id = ?', 9));
	}

	public function testAMoveToARegionWhichDoesNotExistIsAnError(): void
	{
		$this->actingAs(1);

		$this->expectException(ModuleException::class);
		$this->controller->move($this->request('POST', ['src_node_id' => '9', 'target_node_id' => '8', 'target_region' => 'inside']), $this->newResponse());
	}

	public function testADirectoryIsDeletedAndTheNumberIsGiven(): void
	{
		$this->actingAs(2);

		$response = $this->controller->delete($this->request('DELETE', ['node_id' => '8']), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame(['count_deleted_nodes' => 1], $this->json($response)['data']);
		static::assertSame(0, (int) $this->databaseValue('SELECT COUNT(*) FROM mediapool_nodes WHERE node_id = 8'));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongDeletes(): array
	{
		return [
			'nothing'      => [[]],
			'a list'       => [['node_id' => ['8']]],
			'text'         => [['node_id' => 'all']],
			'an injection' => [['node_id' => '8 OR 1=1']],
		];
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('wrongDeletes')]
	public function testADeleteWithWrongInputChangesNothing(array $body): void
	{
		$this->actingAs(1);
		$before = $this->countNodes();

		$this->assertError($this->controller->delete($this->request('DELETE', $body), $this->newResponse()), 'NodeId is missing');

		static::assertSame($before, $this->countNodes());
	}

	public function testADirectoryOfAnotherUserIsNotDeleted(): void
	{
		$this->actingAs(2);
		$before = $this->countNodes();

		try
		{
			$this->controller->delete($this->request('DELETE', ['node_id' => '10']), $this->newResponse());
			static::fail('Expected a rejection');
		}
		catch (AccessDeniedException|NotFoundException)
		{
			static::assertSame($before, $this->countNodes());
		}
	}

	/**
	 * A request without a body (or one which can not be read) is a request without values.
	 */
	public function testARequestWithoutABodyIsAMissingValue(): void
	{
		$this->actingAs(1);
		$request = new \Slim\Psr7\Factory\ServerRequestFactory()->createServerRequest('POST', '/async/mediapool/node');

		$response = $this->controller->add($request, $this->newResponse());

		static::assertSame(400, $response->getStatusCode());
		static::assertFalse($this->json($response)['success']);
	}
}
