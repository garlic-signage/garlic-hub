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

namespace Tests\Unit\Modules\Playlists\Helper\Items;

use App\Framework\Validation\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Helper\Items\Orchestrator;
use App\Modules\Playlists\Helper\Items\ResponseBuilder;
use App\Modules\Playlists\Services\InsertItems\AbstractInsertItem;
use App\Modules\Playlists\Services\InsertItems\InsertItemFactory;
use App\Modules\Playlists\Services\ItemsService;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class OrchestratorTest extends TestCase
{
	private ResponseBuilder&MockObject $responseBuilderMock;
	private BaseValidator&Stub $validatorStub;
	private ItemsService&MockObject $itemsServiceMock;
	private InsertItemFactory&Stub $insertItemFactoryStub;
	private ResponseInterface&Stub $responseStub;
	private Orchestrator $orchestrator;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->responseBuilderMock   = $this->createMock(ResponseBuilder::class);
		$this->validatorStub         = $this->createStub(BaseValidator::class);
		$this->itemsServiceMock      = $this->createMock(ItemsService::class);
		$this->insertItemFactoryStub = $this->createStub(InsertItemFactory::class);
		$this->responseStub          = $this->createStub(ResponseInterface::class);

		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$this->validatorStub,
			$this->itemsServiceMock,
			$this->insertItemFactoryStub
		);
	}

	#[Group('units')]
	public function testValidateLoadItemsWithInvalidPlaylistId(): void
	{
		$this->expectValidationError('invalidPlaylistId');

		$this->orchestrator->setInput([])->validateLoadItems($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoadItems(): void
	{
		$this->prepareValid(['playlist_id' => 56], 'validateLoadItems');

		$list = ['some_stuff', 'some_other_stuff', 'and_more_stuff'];
		$this->itemsServiceMock->expects($this->once())->method('loadItemsByPlaylistIdForComposer')
			->with(56)
			->willReturn($list);
		$this->expectSuccess(['data' => $list]);

		$this->orchestrator->loadItems($this->responseStub);
	}

	#[Group('units')]
	public function testValidateInsertWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['playlist_id' => 1])->validateInsert($this->responseStub);
	}

	#[Group('units')]
	public function testValidateInsertWithInvalidPlaylistId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidPlaylistId');

		$this->orchestrator->setInput([])->validateInsert($this->responseStub);
	}

	#[Group('units')]
	public function testValidateInsertWithInvalidContentId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidContentId');

		$this->orchestrator->setInput(['playlist_id' => 1])->validateInsert($this->responseStub);
	}

	#[Group('units')]
	public function testValidateInsertWithInvalidSource(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidSource');

		$this->orchestrator->setInput(['playlist_id' => 1, 'id' => 'item1'])->validateInsert($this->responseStub);
	}

	#[Group('units')]
	public function testValidateInsertWithInvalidPosition(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidPosition');

		$this->orchestrator->setInput(['playlist_id' => 1, 'id' => 'item1', 'source' => 'video'])
			->validateInsert($this->responseStub);
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testInsert(): void
	{
		$this->prepareValid(['playlist_id' => 1, 'id' => 'item1', 'source' => 'video', 'position' => 5], 'validateInsert');
		$this->itemsServiceMock->expects($this->never())->method($this->anything());

		$insertItemMock = $this->createMock(AbstractInsertItem::class);
		$insertItemMock->expects($this->once())->method('insert')
			->with(1, 'item1', 5)
			->willReturn(['inserted_item_data']);
		$this->insertItemFactoryStub->method('create')->willReturnMap([['video', $insertItemMock]]);
		$this->expectSuccess(['data' => ['inserted_item_data']]);

		$this->orchestrator->insert($this->responseStub);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testInsertFactoryReturnsNull(): void
	{
		$this->prepareValid(['playlist_id' => 1, 'id' => 'item1', 'source' => 'video', 'position' => 5], 'validateInsert');
		$this->itemsServiceMock->expects($this->never())->method($this->anything());

		$this->insertItemFactoryStub->method('create')->willReturn(null);
		$this->expectError('insertFailed');

		$this->orchestrator->insert($this->responseStub);
	}

	#[Group('units')]
	public function testValidateEditWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['item_id' => 123])->validateEdit($this->responseStub);
	}

	#[Group('units')]
	public function testValidateEditWithInvalidItemId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidItemId');

		$this->orchestrator->setInput(['name' => 'item_name', 'value' => 'New Name'])->validateEdit($this->responseStub);
	}

	#[Group('units')]
	public function testValidateEditWithMissingParameterName(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingParameterName');

		$this->orchestrator->setInput(['item_id' => 123, 'value' => 'New Name'])->validateEdit($this->responseStub);
	}

	#[Group('units')]
	public function testValidateEditWithMissingParameterValue(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingParameterValue');

		$this->orchestrator->setInput(['item_id' => 123, 'name' => 'item_name'])->validateEdit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditItemName(): void
	{
		$this->prepareValid(['item_id' => 123, 'name' => 'item_name', 'value' => 'New Name'], 'validateEdit');

		$this->itemsServiceMock->expects($this->once())->method('updateField')
			->with(123, 'item_name', 'New Name')
			->willReturn(1);
		$this->expectSuccess(['data' => '']);

		$this->orchestrator->edit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditItemDuration(): void
	{
		$this->prepareValid(['item_id' => '123', 'name' => 'item_duration', 'value' => '300'], 'validateEdit');

		$this->itemsServiceMock->expects($this->once())->method('updateItemDuration')
			->with(123, 300)
			->willReturn(240); // shortened by a time limit
		$this->expectSuccess(['data' => 240]);

		$this->orchestrator->edit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditWithUnaffected(): void
	{
		$this->prepareValid(['item_id' => 123, 'name' => 'item_duration', 'value' => '300'], 'validateEdit');

		$this->itemsServiceMock->expects($this->once())->method('updateItemDuration')
			->with(123, 300)
			->willReturn(null);
		$this->responseBuilderMock->expects($this->never())->method('generalSuccess');
		$this->responseBuilderMock->expects($this->once())->method('updateFieldFailed')
			->with($this->responseStub, 'item_duration')
			->willReturn($this->responseStub);

		$this->orchestrator->edit($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditWithInvalidParameterName(): void
	{
		$this->prepareValid(['item_id' => 123, 'name' => 'invalid_field', 'value' => 'Some Value'], 'validateEdit');

		$this->itemsServiceMock->expects($this->never())->method($this->anything());
		$this->expectError('invalidParameterName');

		$this->orchestrator->edit($this->responseStub);
	}

	#[Group('units')]
	public function testValidateFetchWithInvalidItemId(): void
	{
		$this->expectValidationError('invalidItemId');

		$this->orchestrator->setInput([])->validateFetch($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFetch(): void
	{
		$this->prepareValid(['item_id' => 123], 'validateFetch');

		$item = ['id' => 123, 'name' => 'Test Item'];
		$this->itemsServiceMock->expects($this->once())->method('fetchItemById')
			->with(123)
			->willReturn($item);
		$this->expectSuccess(['item' => $item]);

		$this->orchestrator->fetch($this->responseStub);
	}

	#[Group('units')]
	public function testValidateUpdateItemOrdersWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['playlist_id' => 1])->validateUpdateItemOrders($this->responseStub);
	}

	#[Group('units')]
	public function testValidateUpdateItemOrdersWithInvalidPlaylistId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidPlaylistId');

		$this->orchestrator->setInput(['items_positions' => [['id' => 1, 'position' => 2]]])
			->validateUpdateItemOrders($this->responseStub);
	}

	#[Group('units')]
	public function testValidateUpdateItemOrdersWithMissingItemsPositions(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidItemsPositions');

		$this->orchestrator->setInput(['playlist_id' => 1])->validateUpdateItemOrders($this->responseStub);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testUpdateItemOrders(): void
	{
		$positions = [['id' => 1, 'position' => 2], ['id' => 2, 'position' => 1]];
		$this->prepareValid(['playlist_id' => 1, 'items_positions' => $positions], 'validateUpdateItemOrders');

		$this->itemsServiceMock->expects($this->once())->method('updateItemOrder')
			->with(1, $positions);
		$this->responseBuilderMock->expects($this->once())->method('itemsOrderUpdated')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		$this->orchestrator->updateItemOrders($this->responseStub);
	}

	#[Group('units')]
	public function testValidateDeleteWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['playlist_id' => 1, 'item_id' => 123])->validateDelete($this->responseStub);
	}

	#[Group('units')]
	public function testValidateDeleteWithInvalidPlaylistId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidPlaylistId');

		$this->orchestrator->setInput(['item_id' => 123])->validateDelete($this->responseStub);
	}

	#[Group('units')]
	public function testValidateDeleteWithInvalidItemId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidItemId');

		$this->orchestrator->setInput(['playlist_id' => 1])->validateDelete($this->responseStub);
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testDelete(): void
	{
		$this->prepareValid(['playlist_id' => 1, 'item_id' => 123], 'validateDelete');

		$item = ['id' => 123, 'name' => 'Test Item'];
		$this->itemsServiceMock->expects($this->once())->method('delete')
			->with(1, 123)
			->willReturn($item);
		$this->expectSuccess(['data' => $item]);

		$this->orchestrator->delete($this->responseStub);
	}

	/**
	 * Runs the validation, which has to pass, because it sets the ids the actions need.
	 *
	 * @param array<string,mixed> $input
	 */
	private function prepareValid(array $input, string $validateMethod): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);

		$answer = $this->orchestrator->setInput($input)->$validateMethod($this->responseStub);

		static::assertNull($answer);
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
	 * @param non-empty-string $method
	 */
	private function expectValidationError(string $method): void
	{
		$this->itemsServiceMock->expects($this->never())->method($this->anything());
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
