<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Modules\Profile;

use App\Framework\Core\Locales\Locales;
use App\Framework\Core\Session;
use App\Modules\Profile\Controller\EditLocalesController;
use App\Modules\Profile\Services\ProfileService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EditLocalesControllerTest extends TestCase
{
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private Session&MockObject $sessionMock;
	private Locales&MockObject $localesMock;
	private ProfileService&MockObject $profileServiceMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		$this->requestMock        = $this->createMock(ServerRequestInterface::class);
		$this->responseMock       = $this->createMock(ResponseInterface::class);
		$this->sessionMock        = $this->createMock(Session::class);
		$this->localesMock        = $this->createMock(Locales::class);
		$this->profileServiceMock = $this->createMock(ProfileService::class);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testSetLocales(): void
	{
		$this->requestMock->method('getAttribute')
						  ->willReturnCallback(function ($attribute) {
							  return match ($attribute) {
								  'session' => $this->sessionMock,
								  'locales' => $this->localesMock,
								  default => null,
							  };
						  });

		$this->sessionMock->method('exists')->with('user')->willReturn(true);
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 1, 'locale' => 'en_US']);
		$this->sessionMock->expects($this->exactly(2))->method('set');

		$this->localesMock->expects($this->once())->method('determineCurrentLocale');

		$previousUrl = 'some/url/line';
		$this->requestMock->method('getHeaderLine')->with('Referer')->willReturn($previousUrl);

		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', $previousUrl)
						   ->willReturn($this->responseMock);

		$this->responseMock->expects($this->once())->method('withStatus')
						   ->with(302)->willReturn($this->responseMock);

		$this->profileServiceMock->expects($this->once())->method('updateLocale')
			 ->with(1, 'de_DE');


		$controller = new EditLocalesController($this->profileServiceMock);
		$result = $controller->setLocales($this->requestMock, $this->responseMock, ['locale' => 'de_DE']);
		$this->assertSame($this->responseMock, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testSetLocalesWithBrokenUserArray(): void
	{
		$this->requestMock->method('getAttribute')
						  ->willReturnCallback(function ($attribute) {
							  return match ($attribute) {
								  'session' => $this->sessionMock,
								  'locales' => $this->localesMock,
								  default => null,
							  };
						  });
		$this->sessionMock->method('get')->with('user')->willReturn('not_an_array');
		$this->sessionMock->expects($this->once())->method('set')->with('locale', 'de_DE');

		$this->localesMock->expects($this->once())->method('determineCurrentLocale');

		$previousUrl = 'some/url/line';
		$this->requestMock->method('getHeaderLine')->with('Referer')->willReturn($previousUrl);

		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', $previousUrl)
						   ->willReturn($this->responseMock);

		$this->profileServiceMock->expects($this->never())->method('updateLocale');
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturn($this->responseMock);

		$controller = new EditLocalesController($this->profileServiceMock);
		$result = $controller->setLocales($this->requestMock, $this->responseMock, ['locale' => 'de_DE']);
		$this->assertSame($this->responseMock, $result);
	}

}
