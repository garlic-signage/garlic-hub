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


namespace Tests\Unit\Modules\Playlists\Helper\Trigger;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\ConditionalPlay\ResponseBuilder;
use App\Modules\Playlists\Helper\Trigger\TemplatePreparer;
use App\Modules\Playlists\Helper\Trigger\Orchestrator;
use App\Modules\Playlists\Services\TriggerService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class OrchestratorTest extends TestCase
{
	private ResponseBuilder&MockObject $responseBuilderMock;
	private UserSession&MockObject $userSessionMock;
	private BaseValidator&MockObject $validatorMock;
	private TemplatePreparer&MockObject $templatePreparerMock;
	private ResponseInterface&MockObject $responseMock;
	private TriggerService&MockObject $triggerServiceMock;
	private Orchestrator $orchestrator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->responseBuilderMock = $this->createMock(ResponseBuilder::class);
		$this->userSessionMock = $this->createMock(UserSession::class);
		$this->validatorMock = $this->createMock(BaseValidator::class);
		$this->templatePreparerMock = $this->createMock(TemplatePreparer::class);
		$this->triggerServiceMock = $this->createMock(TriggerService::class);

		$this->responseMock = $this->createMock(ResponseInterface::class);

		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$this->userSessionMock,
			$this->validatorMock,
			$this->templatePreparerMock,
			$this->triggerServiceMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithTokenCsrfMismatch(): void
	{
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('invalid_token')
			->willReturn(false);

		$this->responseBuilderMock->expects($this->once())->method('csrfTokenMismatch')
			->with($this->responseMock)
			->willReturn($this->responseMock);

		$this->orchestrator->setInput([BaseEditParameters::PARAMETER_CSRF_TOKEN => 'invalid_token']);

		$this->orchestrator->validateWithToken($this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithTokenFailsNoItemId(): void
	{
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('valid_token')
			->willReturn(true);

		$this->responseBuilderMock->expects($this->once())->method('invalidItemId')
			->with($this->responseMock)
			->willReturn($this->responseMock);

		$this->orchestrator->setInput([BaseEditParameters::PARAMETER_CSRF_TOKEN => 'valid_token']);

		$this->orchestrator->validateWithToken($this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithTokenCallsValidate(): void
	{
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('valid_token')
			->willReturn(true);

		$this->orchestrator->setInput(['item_id' => '1', BaseEditParameters::PARAMETER_CSRF_TOKEN => 'valid_token']);

		$this->orchestrator->validateWithToken($this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFetchSuccess(): void
	{
		$itemData = ['item_key' => 'value'];
		$touchableMedia = ['media_item_1', 'media_item_2'];
		$preparedHtml = '<html lang="en">Prepared Content</html>';

		$this->userSessionMock->expects($this->once())->method('getUID')
			->willReturn(1);
		$this->triggerServiceMock->expects($this->once())->method('setUID')
			->with(1);

		$this->triggerServiceMock->expects($this->once())->method('fetchBeginTriggerByItemId')
			->with(123)
			->willReturn($this->triggerServiceMock);
		$this->triggerServiceMock->expects($this->once())->method('getItemData')
			->willReturn($itemData);
		$this->templatePreparerMock->expects($this->once())->method('prepare')
			->with(123);

		$this->triggerServiceMock->expects($this->once())->method('getTouchableMedia')->willReturn($touchableMedia);

		$this->templatePreparerMock->expects($this->once())->method('render')->willReturn($preparedHtml);

		$data = [
			'item_data' => $itemData,
			'touchable_medialist' => $touchableMedia,
			'html' => $preparedHtml
		];

		$this->responseBuilderMock->expects($this->once())
			->method('generalSuccess')
			->with($this->responseMock, $data)
			->willReturn($this->responseMock);

		$this->initMocks();

		$this->orchestrator->fetch($this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFetchItemNotFound(): void
	{
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(1);
		$this->triggerServiceMock->expects($this->once())->method('setUID')->with(1);
		$this->triggerServiceMock->expects($this->once())->method('fetchBeginTriggerByItemId')->with(123)->willReturn($this->triggerServiceMock);
		$this->triggerServiceMock->expects($this->once())->method('getItemData')->willReturn([]);

		$this->responseBuilderMock->expects($this->once())
			->method('itemNotFound')
			->with($this->responseMock)
			->willReturn($this->responseMock);

		$this->initMocks();
		$result = $this->orchestrator->fetch($this->responseMock);

		static::assertInstanceOf(ResponseInterface::class, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws UserException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testSaveItemNotFound(): void
	{
		$this->triggerServiceMock->expects($this->once())->method('fetchAccessibleItem')
			->with(123)
			->willReturn([]);

		$this->responseBuilderMock->expects($this->once())->method('itemNotFound')
			->with($this->responseMock)
			->willReturn($this->responseMock);

		$this->orchestrator->setInput([
			'item_id' => '123',
			'csrf_token' => 'valid_csrf_token'
		]);

		$this->orchestrator->validate($this->responseMock);
		$result = $this->orchestrator->save($this->responseMock);

		static::assertInstanceOf(ResponseInterface::class, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws UserException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testSaveSuccess(): void
	{
		$this->triggerServiceMock->expects($this->once())->method('fetchAccessibleItem')
			->with(123)
			->willReturn(['id' => 123]);

		$this->triggerServiceMock->expects($this->once())->method('saveBeginTrigger')
			->with(123, ['param1' => 'value1', 'param2' => 'value2'])
			->willReturn(true);

		$this->responseBuilderMock->expects($this->once())->method('generalSuccess')
			->with($this->responseMock, [])
			->willReturn($this->responseMock);

		$this->orchestrator->setInput([
			'item_id' => '123',
			'csrf_token' => 'valid_csrf_token',
			'param1' => 'value1',
			'param2' => 'value2'
		]);

		$this->orchestrator->validate($this->responseMock);
		$result = $this->orchestrator->save($this->responseMock);

		static::assertInstanceOf(ResponseInterface::class, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function initMocks():void
	{
		$this->orchestrator->setInput(['item_id' => '123', BaseEditParameters::PARAMETER_CSRF_TOKEN => 'invalid_token']);
		$this->orchestrator->validate($this->responseMock);
	}
}
