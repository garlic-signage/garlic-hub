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


namespace Tests\Unit\Modules\Player\Controller;

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Player\Controller\ShowConnectivityController;
use App\Modules\Player\Helper\NetworkSettings\Facade;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Flash\Messages;

class ShowConnectivityControllerTest extends TestCase
{
	private Facade&MockObject $facadeMock;
	private FormTemplatePreparer&MockObject $formElementPreparerMock;
	private Messages&MockObject $flashMock;
	private ResponseInterface&MockObject $responseMock;
	private ServerRequestInterface&MockObject $requestMock;
	private StreamInterface&MockObject $streamInterfaceMock;
	private ShowConnectivityController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->facadeMock              = $this->createMock(Facade::class);
		$this->formElementPreparerMock = $this->createMock(FormTemplatePreparer::class);
		$this->flashMock               = $this->createMock(Messages::class);

		$this->requestMock         = $this->createMock(ServerRequestInterface::class);
		$this->responseMock        = $this->createMock(ResponseInterface::class);
		$this->streamInterfaceMock = $this->createMock(StreamInterface::class);

		$this->controller = new ShowConnectivityController(
			$this->facadeMock,
			$this->formElementPreparerMock,
			$this->flashMock
		);


	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowSucceed(): void
	{
		$this->flashMock->expects($this->never())->method($this->anything());
		$this->initMock();

		$player = [
			'player_id' => 1,
			'player_name' => 'Test Player',
			'model' => 123,
			'is_intranet' => 1,
			'api_endpoint' => 'http://example.com'
		];
		$this->outputRenderMock($player);

		$this->facadeMock->method('loadPlayerForEdit')->willReturnSelf();
		$this->facadeMock->method('getPlayer')->willReturn($player);

		$this->outputRenderMock($player);

		$args = ['player_id' => '1'];
		$this->controller->show($this->requestMock, $this->responseMock, $args);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowFailsNoPlayerId(): void
	{
		$this->expectUntouched($this->requestMock, $this->facadeMock, $this->formElementPreparerMock, $this->streamInterfaceMock);

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/player')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302)
			->willReturnSelf();

		$this->flashMock->expects($this->once())->method('addMessage')
			->with('error', 'No player id given.');

		$args = ['player_id' => '0'];

		$this->controller->show($this->requestMock, $this->responseMock, $args);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testShowPlayerNotAccessible(): void
	{
		$this->formElementPreparerMock->expects($this->never())->method($this->anything());
		$this->streamInterfaceMock->expects($this->never())->method($this->anything());
		$this->initMock();
		$this->facadeMock->method('loadPlayerForEdit')->willReturnSelf();
		$this->facadeMock->method('getPlayer')->willReturn([]);

		$this->flashMock->expects($this->once())->method('addMessage')
			->with('error', 'Player ID not accessible.');
		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/player')
			->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302)
			->willReturnSelf();

		$args = ['player_id' => '1'];
		$this->controller->show($this->requestMock, $this->responseMock, $args);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[
		Group('units')]
	public function testStoreSucceed(): void
	{
		$this->formElementPreparerMock->expects($this->never())->method($this->anything());
		$this->streamInterfaceMock->expects($this->never())->method($this->anything());
		$this->initMock();

		$postData = [
			'player_id' => 1,
			'player_name' => 'Player Test',
			'is_intranet' => 1,
			'api_endpoint' => 'http://example.com'
		];

		$this->requestMock->method('getParsedBody')->willReturn($postData);
		$this->facadeMock->method('configureFormParameter')->with($postData)->willReturn([]);
		$this->facadeMock->method('storeNetworkData')->willReturn(1);

		$this->flashMock->expects($this->once())->method('addMessage')
			->with('success', 'Player connectivity data successfully stored.');

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/player')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302)
			->willReturnSelf();

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreFailsWithInvalidData(): void
	{
		$this->initMock();

		$postData = [
			'player_id' => 1,
			'player_name' => '',
			'is_intranet' => 1,
			'api_endpoint' => ''
		];

		$errors = ['Invalid player name.', 'Invalid API endpoint.'];

		$this->requestMock->method('getParsedBody')->willReturn($postData);
		$this->facadeMock->method('configureFormParameter')->with($postData)->willReturn($errors);

			$this->flashMock->expects($this->exactly(2))->method('addMessageNow')
				->willReturnMap([
					['error', $errors[0] ],
					['error', $errors[1]]
				]);

		$this->facadeMock->method('prepareUITemplate')->with($postData)->willReturn([]);
		$this->formElementPreparerMock->expects($this->atLeastOnce())->method('prepareUITemplate')->willReturn([]);

		$this->responseMock->expects($this->once())->method('getBody')
			->willReturn($this->streamInterfaceMock);
		$this->streamInterfaceMock->expects($this->once())->method('write')
			->with(serialize([]));

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(200)
			->willReturnSelf();

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreFailsWithSaveError(): void
	{
		$this->flashMock->expects($this->never())->method($this->anything());
		$this->initMock();

		$postData = [
			'player_id' => 1,
			'player_name' => 'Player Test',
			'is_intranet' => 1,
			'api_endpoint' => 'http://example.com'
		];

		$this->requestMock->method('getParsedBody')->willReturn($postData);
		$this->facadeMock->method('configureFormParameter')->with($postData)->willReturn([]);
		$this->facadeMock->method('storeNetworkData')->willReturn(0);

		$this->facadeMock->method('prepareUITemplate')->with($postData)->willReturn([]);
		$this->formElementPreparerMock->expects($this->atLeastOnce())->method('prepareUITemplate')->willReturn([]);

		$this->responseMock->expects($this->once())->method('getBody')
			->willReturn($this->streamInterfaceMock);
		$this->streamInterfaceMock->expects($this->once())->method('write')
			->with(serialize([]));

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(200)
			->willReturnSelf();

		$this->controller->store($this->requestMock, $this->responseMock);
	}

	/**
	 * @param array{player_id?:int, player_name?:string, model?:int, is_intranet?:int, api_endpoint?:string} $player
	 */
	private function outputRenderMock(array $player): void
	{
		$this->facadeMock->expects($this->once())->method('prepareUITemplate')
			->with($player)
			->willReturn([]);

		$this->formElementPreparerMock->expects($this->once())->method('prepareUITemplate')
			->willReturn([]);

		$this->responseMock->expects($this->once())->method('getBody')
			->willReturn($this->streamInterfaceMock);

		$this->streamInterfaceMock->expects($this->once())->method('write')
			->with(serialize([]));

		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(200)
			->willReturnSelf();
	}


	/**
	 * @throws Exception
	 */
	private function initMock(): void
	{
		$translatorStub = $this->createStub(Translator::class);
		$sessionStub = $this->createStub(Session::class);
		$this->requestMock->expects($this->exactly(2))->method('getAttribute')
			->willReturnMap([
				['translator', null, $translatorStub],
				['session', null, $sessionStub]
			]);

		$this->facadeMock->expects($this->once())->method('init')
			->with($translatorStub, $sessionStub);

	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
