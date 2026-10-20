<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Modules\Playlists\Helper\Settings;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\Html\FieldInterface;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\Settings\FormElementsCreator;
use App\Modules\Playlists\Helper\Settings\Parameters;
use App\Modules\Playlists\Helper\Settings\SettingsFormBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsFormBuilderTest extends TestCase
{
	private Parameters&MockObject $parametersMock;
	private UserSession&MockObject $userSessionMock;
	private FormElementsCreator&MockObject $formElementsCreatorMock;
	private SettingsFormBuilder $builder;
	private FieldInterface $field;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->parametersMock          = $this->createMock(Parameters::class);
		$this->userSessionMock         = $this->createMock(UserSession::class);
		$this->formElementsCreatorMock = $this->createMock(FormElementsCreator::class);
		$this->field                   = $this->createStub(FieldInterface::class);

		$this->builder = new SettingsFormBuilder($this->parametersMock, $this->userSessionMock, $this->formElementsCreatorMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testBuildFormForSimpleUser(): void
	{
		$this->parametersMock->expects($this->exactly(4))->method('hasParameter')->willReturnMap([
			['UID', false],
			[Parameters::PARAMETER_TIME_LIMIT, false],
			[Parameters::PARAMETER_PLAYLIST_ID, false],
			[Parameters::PARAMETER_PLAYLIST_MODE, true]
		]);
		$this->userSessionMock->expects($this->never())->method($this->anything());
		$this->formElementsCreatorMock->expects($this->once())->method('createPlaylistNameField')->with('')->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->never())->method('createUIDField');
		$this->formElementsCreatorMock->expects($this->never())->method('createScreenWidthField');
		$this->formElementsCreatorMock->expects($this->once())->method('createPlaylistModeField')->with('internal')->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('createCSRFTokenField')->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('prepareForm')
			->with(['playlist_name' => $this->field, 'playlist_mode' => $this->field, 'csrf_token' => $this->field])
			->willReturn(['hidden' => [], 'visible' => []]);

		static::assertSame(['hidden' => [], 'visible' => []], $this->builder->buildForm(['playlist_mode' => 'internal']));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testBuildFormForAdminWithNewPlaylistUsesCurrentUserAsOwner(): void
	{
		$this->parametersMock->expects($this->exactly(4))->method('hasParameter')->willReturnMap([
			['UID', true],
			[Parameters::PARAMETER_TIME_LIMIT, false],
			[Parameters::PARAMETER_PLAYLIST_ID, false],
			[Parameters::PARAMETER_PLAYLIST_MODE, true]
		]);
		$this->userSessionMock->method('getUID')->willReturn(7);
		$this->userSessionMock->expects($this->once())->method('getUsername')->willReturn('heidi');
		$this->formElementsCreatorMock->method('createPlaylistNameField')->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('createUIDField')->with(7, 'heidi', 7)->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('createScreenWidthField')->with(1920)->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('createScreenHeightField')->with(1080)->willReturn($this->field);
		$this->formElementsCreatorMock->method('createPlaylistModeField')->willReturn($this->field);
		$this->formElementsCreatorMock->method('createCSRFTokenField')->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('prepareForm')
			->with([
				'playlist_name' => $this->field,
				'UID'           => $this->field,
				'screen_width'  => $this->field,
				'screen_height' => $this->field,
				'playlist_mode' => $this->field,
				'csrf_token'    => $this->field
			])
			->willReturn([]);

		$this->builder->buildForm(['playlist_mode' => 'master']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testBuildFormForAdminWithExistingPlaylist(): void
	{
		$playlist = [
			'UID'           => 3,
			'username'      => 'owner',
			'playlist_id'   => 456,
			'playlist_name' => 'Lobby',
			'playlist_mode' => 'multizone',
			'screen_width'  => 1080,
			'screen_height' => 1920,
			'time_limit'    => 600
		];
		$this->parametersMock->method('hasParameter')->willReturnMap([
			['UID', true],
			[Parameters::PARAMETER_TIME_LIMIT, true],
			[Parameters::PARAMETER_PLAYLIST_ID, true],
			[Parameters::PARAMETER_PLAYLIST_MODE, false]
		]);
		$this->parametersMock->method('getDefaultValueOfParameter')->with(Parameters::PARAMETER_TIME_LIMIT)->willReturn(500);
		$this->userSessionMock->method('getUID')->willReturn(7);
		$this->userSessionMock->expects($this->never())->method('getUsername');
		$this->formElementsCreatorMock->expects($this->once())->method('createPlaylistNameField')->with('Lobby')->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('createUIDField')->with(3, 'owner', 7)->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('createScreenWidthField')->with(1080)->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('createScreenHeightField')->with(1920)->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('createTimeLimitField')->with(600, 500)->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('createHiddenPlaylistIdField')->with(456)->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->never())->method('createPlaylistModeField');
		$this->formElementsCreatorMock->method('createCSRFTokenField')->willReturn($this->field);
		$this->formElementsCreatorMock->expects($this->once())->method('prepareForm')
			->with([
				'playlist_name' => $this->field,
				'UID'           => $this->field,
				'screen_width'  => $this->field,
				'screen_height' => $this->field,
				'time_limit'    => $this->field,
				'playlist_id'   => $this->field,
				'csrf_token'    => $this->field
			])
			->willReturn([]);

		$this->builder->buildForm($playlist);
	}
}
