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

namespace Tests\Unit\Modules\Mediapool\Helper\Nodes;

use App\Framework\Validation\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Mediapool\Helper\Nodes\Orchestrator;
use App\Modules\Mediapool\Helper\Nodes\ResponseBuilder;
use App\Modules\Mediapool\Services\NodesService;
use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class OrchestratorTest extends TestCase
{
	private ResponseBuilder&MockObject $responseBuilderMock;
	private BaseValidator&Stub $validatorStub;
	private NodesService&MockObject $nodesServiceMock;
	private ResponseInterface&Stub $responseStub;
	private Orchestrator $orchestrator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->responseBuilderMock = $this->createMock(ResponseBuilder::class);
		$this->validatorStub       = $this->createStub(BaseValidator::class);
		$this->nodesServiceMock    = $this->createMock(NodesService::class);
		$this->responseStub        = $this->createStub(ResponseInterface::class);

		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$this->validatorStub,
			$this->nodesServiceMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testList(): void
	{
		$nodes = [['title' => 'folder', 'key' => 2]];
		$this->nodesServiceMock->expects($this->once())->method('getNodes')->with(5)->willReturn($nodes);
		$this->responseBuilderMock->expects($this->once())->method('nodeList')
			->with($this->responseStub, $nodes)
			->willReturn($this->responseStub);

		$this->orchestrator->setInput(['parent_id' => '5'])->list($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testListRootNodesWithoutParentId(): void
	{
		$this->nodesServiceMock->expects($this->once())->method('getNodes')->with(0)->willReturn([]);
		$this->responseBuilderMock->expects($this->once())->method('nodeList')
			->with($this->responseStub, [])
			->willReturn($this->responseStub);

		$this->orchestrator->setInput([])->list($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateAddWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['name' => 'folder'])->validateAdd($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateAddWithMissingName(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingName');

		$this->orchestrator->setInput(['node_id' => 1])->validateAdd($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAdd(): void
	{
		$this->prepareValid(['node_id' => 1, 'name' => 'New Node'], 'validateAdd');
		$this->nodesServiceMock->expects($this->once())->method('addNode')->with(1, 'New Node')->willReturn(2);
		$this->expectSuccess(['data' => ['id' => 2, 'new_name' => 'New Node']]);

		$this->orchestrator->add($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAddRootNodeWithoutNodeId(): void
	{
		$this->prepareValid(['name' => 'Root'], 'validateAdd');
		$this->nodesServiceMock->expects($this->once())->method('addNode')->with(0, 'Root')->willReturn(3);
		$this->expectSuccess(['data' => ['id' => 3, 'new_name' => 'Root']]);

		$this->orchestrator->add($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAddPassesServiceErrorOn(): void
	{
		$this->prepareValid(['node_id' => 1, 'name' => 'New Node'], 'validateAdd');
		$this->nodesServiceMock->expects($this->once())->method('addNode')->willThrowException(new ModuleException('mediapool', 'No rights'));
		$this->expectServiceError('No rights');

		$this->orchestrator->add($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateEditWithMissingParameters(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingNameOrId');

		$this->orchestrator->setInput(['name' => 'folder'])->validateEdit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testEdit(): void
	{
		$this->prepareValid(['node_id' => 1, 'name' => 'Edited Node', 'visibility' => 1], 'validateEdit');
		$this->nodesServiceMock->expects($this->once())->method('editNode')->with(1, 'Edited Node', 1)->willReturn(1);
		$this->expectSuccess(['data' => ['id' => 1, 'new_name' => 'Edited Node', 'visibility' => 1]]);

		$this->orchestrator->edit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testEditSucceedsWhenNothingChanged(): void
	{
		$this->prepareValid(['node_id' => 1, 'name' => 'Edited Node'], 'validateEdit');
		$this->nodesServiceMock->expects($this->once())->method('editNode')->with(1, 'Edited Node', null)->willReturn(0);
		$this->expectSuccess(['data' => ['id' => 1, 'new_name' => 'Edited Node', 'visibility' => null]]);

		$this->orchestrator->edit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateMoveWithMissingParameters(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingMoveParameters');

		$this->orchestrator->setInput(['src_node_id' => 1, 'target_node_id' => 2])->validateMove($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testMove(): void
	{
		$this->prepareValid(['src_node_id' => 1, 'target_node_id' => 2, 'target_region' => 'before'], 'validateMove');
		$this->nodesServiceMock->expects($this->once())->method('moveNode')->with(1, 2, 'before')->willReturn(1);
		$this->expectSuccess(['data' => ['count_deleted_nodes' => 1]]);

		$this->orchestrator->move($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testMovePassesServiceErrorOn(): void
	{
		$this->prepareValid(['src_node_id' => 1, 'target_node_id' => 2, 'target_region' => 'before'], 'validateMove');
		$this->nodesServiceMock->expects($this->once())->method('moveNode')->willThrowException(new ModuleException('mediapool', 'Move failed'));
		$this->expectServiceError('Move failed');

		$this->orchestrator->move($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateDeleteWithMissingNodeId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingNodeId');

		$this->orchestrator->setInput([])->validateDelete($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDelete(): void
	{
		$this->prepareValid(['node_id' => 1], 'validateDelete');
		$this->nodesServiceMock->expects($this->once())->method('deleteNode')->with(1)->willReturn(3);
		$this->expectSuccess(['data' => ['count_deleted_nodes' => 3]]);

		$this->orchestrator->delete($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDeletePassesServiceErrorOn(): void
	{
		$this->prepareValid(['node_id' => 1], 'validateDelete');
		$this->nodesServiceMock->expects($this->once())->method('deleteNode')->willThrowException(new ModuleException('mediapool', 'Delete failed'));
		$this->expectServiceError('Delete failed');

		$this->orchestrator->delete($this->responseStub);
	}

	/**
	 * Runs the validation, which has to pass before an action is executed.
	 *
	 * @param array<string,mixed> $input
	 * @param non-empty-string $validateMethod
	 */
	private function prepareValid(array $input, string $validateMethod): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);

		static::assertNull($this->orchestrator->setInput($input)->$validateMethod($this->responseStub));
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function expectSuccess(array $data): void
	{
		$this->responseBuilderMock->expects($this->once())->method('generalSuccess')
			->with($this->responseStub, $data)
			->willReturn($this->responseStub);
	}

	/**
	 * Errors of the service are not caught, the central error handler answers.
	 */
	private function expectServiceError(string $message): void
	{
		$this->responseBuilderMock->expects($this->never())->method($this->anything());
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage($message);
	}

	/**
	 * @param non-empty-string $method
	 */
	private function expectValidationError(string $method): void
	{
		$this->nodesServiceMock->expects($this->never())->method($this->anything());
		$this->expectError($method);
	}

	/**
	 * @param non-empty-string $method
	 */
	private function expectError(string $method): void
	{
		$this->responseBuilderMock->expects($this->never())->method('generalSuccess');
		$this->responseBuilderMock->expects($this->once())->method($method)
			->with($this->responseStub)
			->willReturn($this->responseStub);
	}
}
