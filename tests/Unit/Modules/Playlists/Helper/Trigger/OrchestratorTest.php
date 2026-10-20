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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class OrchestratorTest extends TestCase
{
	private ResponseBuilder&MockObject $responseBuilderMock;
	private UserSession&MockObject $userSessionMock;
	private BaseValidator&MockObject $validatorMock;
	private TemplatePreparer&MockObject $templatePreparerMock;
	private ResponseInterface&Stub $responseStub;
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

		$this->responseStub = $this->createStub(ResponseInterface::class);

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
		$this->expectUntouched($this->userSessionMock, $this->templatePreparerMock, $this->triggerServiceMock);
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('invalid_token')
			->willReturn(false);

		$this->responseBuilderMock->expects($this->once())->method('csrfTokenMismatch')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		$this->orchestrator->setInput([BaseEditParameters::PARAMETER_CSRF_TOKEN => 'invalid_token']);

		$this->orchestrator->validateWithToken($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithTokenFailsNoItemId(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->templatePreparerMock, $this->triggerServiceMock);
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('valid_token')
			->willReturn(true);

		$this->responseBuilderMock->expects($this->once())->method('invalidItemId')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		$this->orchestrator->setInput([BaseEditParameters::PARAMETER_CSRF_TOKEN => 'valid_token']);

		$this->orchestrator->validateWithToken($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithTokenCallsValidate(): void
	{
		$this->expectUntouched($this->responseBuilderMock, $this->userSessionMock, $this->templatePreparerMock, $this->triggerServiceMock);
		$this->validatorMock->expects($this->once())->method('validateCsrfToken')
			->with('valid_token')
			->willReturn(true);

		$this->orchestrator->setInput(['item_id' => '1', BaseEditParameters::PARAMETER_CSRF_TOKEN => 'valid_token']);

		$this->orchestrator->validateWithToken($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFetchSuccess(): void
	{
		$this->validatorMock->expects($this->never())->method($this->anything());
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
			->with($this->responseStub, $data)
			->willReturn($this->responseStub);

		$this->initMocks();

		$this->orchestrator->fetch($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFetchItemNotFound(): void
	{
		$this->validatorMock->expects($this->never())->method($this->anything());
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(1);
		$this->triggerServiceMock->expects($this->once())->method('setUID')->with(1);
		$this->triggerServiceMock->expects($this->once())->method('fetchBeginTriggerByItemId')->with(123)->willReturn($this->triggerServiceMock);
		$this->triggerServiceMock->expects($this->once())->method('getItemData')->willReturn([]);

		$this->responseBuilderMock->expects($this->once())
			->method('itemNotFound')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		$this->initMocks();
		$result = $this->orchestrator->fetch($this->responseStub);

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
		// saving works on behalf of the session user, without form handling
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(7);
		$this->triggerServiceMock->expects($this->once())->method('setUID')->with(7);
		$this->validatorMock->expects($this->never())->method($this->anything());
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->triggerServiceMock->expects($this->once())->method('fetchAccessibleItem')
			->with(123)
			->willReturn([]);

		$this->responseBuilderMock->expects($this->once())->method('itemNotFound')
			->with($this->responseStub)
			->willReturn($this->responseStub);

		$this->orchestrator->setInput([
			'item_id' => '123',
			'csrf_token' => 'valid_csrf_token'
		]);

		$this->orchestrator->validate($this->responseStub);
		$result = $this->orchestrator->save($this->responseStub);

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
		// saving works on behalf of the session user, without form handling
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(7);
		$this->triggerServiceMock->expects($this->once())->method('setUID')->with(7);
		$this->validatorMock->expects($this->never())->method($this->anything());
		$this->templatePreparerMock->expects($this->never())->method($this->anything());
		$this->triggerServiceMock->expects($this->once())->method('fetchAccessibleItem')
			->with(123)
			->willReturn(['id' => 123]);

		$this->triggerServiceMock->expects($this->once())->method('saveBeginTrigger')
			->with(123, ['param1' => 'value1', 'param2' => 'value2'])
			->willReturn(true);

		$this->responseBuilderMock->expects($this->once())->method('generalSuccess')
			->with($this->responseStub, [])
			->willReturn($this->responseStub);

		$this->orchestrator->setInput([
			'item_id' => '123',
			'csrf_token' => 'valid_csrf_token',
			'param1' => 'value1',
			'param2' => 'value2'
		]);

		$this->orchestrator->validate($this->responseStub);
		$result = $this->orchestrator->save($this->responseStub);

		static::assertInstanceOf(ResponseInterface::class, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function initMocks():void
	{
		$this->orchestrator->setInput(['item_id' => '123', BaseEditParameters::PARAMETER_CSRF_TOKEN => 'invalid_token']);
		$this->orchestrator->validate($this->responseStub);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
