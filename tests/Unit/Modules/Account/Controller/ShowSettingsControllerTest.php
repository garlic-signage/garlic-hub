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


namespace Tests\Unit\Modules\Account\Controller;

use App\Modules\Account\Controller\ShowSettingsController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ShowSettingsControllerTest extends TestCase
{
	private ShowSettingsController $showSettingsController;
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&MockObject $responseMock;

	protected function setUp(): void
	{
		parent::setUp();

		$this->requestStub = $this->createStub(ServerRequestInterface::class);
		$this->responseMock = $this->createMock(ResponseInterface::class);
		$this->showSettingsController = new ShowSettingsController();
	}

	#[Group('units')]
	public function testShowMethodRedirectsToPasswordPage(): void
	{
		$this->responseMock->expects($this->once())->method('withHeader')
			->with('Location', '/account/password')
			->willReturnSelf();

		$this->responseMock->expects($this->once())->method('withStatus')
			->with(302)
			->willReturnSelf();

		$result = $this->showSettingsController->show($this->requestStub, $this->responseMock);

		self::assertSame($this->responseMock, $result);
	}
}
