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
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\Settings\FormInputHandler;
use App\Modules\Playlists\Helper\Settings\Orchestrator;
use App\Modules\Playlists\Helper\Settings\SettingsFormBuilder;
use App\Modules\Playlists\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Playlists\Services\PlaylistsService;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrchestratorTest extends TestCase
{
	private UserSession&MockObject $userSessionMock;
	private PlaylistsService&MockObject $serviceMock;
	private SettingsParametersPolicy&MockObject $policyMock;
	private FormInputHandler&MockObject $inputHandlerMock;
	private SettingsFormBuilder&MockObject $formBuilderMock;
	private Orchestrator $orchestrator;

	/** @var array{UID: int, company_id: int, playlist_id: int, playlist_mode: string, playlist_name: string, layout: array<string,mixed>} */
	private array $playlist = ['UID' => 3, 'company_id' => 1, 'playlist_id' => 5, 'playlist_mode' => 'multizone',
		'playlist_name' => 'Lobby', 'layout' => ['resolution' => '1920x1080', 'zoom' => 50, 'zones' => [['zone']]]];

	/** @var array{hidden: list<mixed>, visible: list<mixed>} */
	private array $form = ['hidden' => [], 'visible' => []];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->userSessionMock  = $this->createMock(UserSession::class);
		$this->serviceMock      = $this->createMock(PlaylistsService::class);
		$this->policyMock       = $this->createMock(SettingsParametersPolicy::class);
		$this->inputHandlerMock = $this->createMock(FormInputHandler::class);
		$this->formBuilderMock  = $this->createMock(SettingsFormBuilder::class);

		$this->orchestrator = new Orchestrator(
			$this->userSessionMock,
			$this->serviceMock,
			$this->policyMock,
			$this->inputHandlerMock,
			$this->formBuilderMock
		);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoadPlaylistForEdit(): void
	{
		$this->expectUntouched($this->policyMock, $this->inputHandlerMock, $this->formBuilderMock);
		$this->expectPlaylistLoaded();

		static::assertSame($this->playlist, $this->orchestrator->loadPlaylistForEdit(5));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testBuildCreateForm(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->serviceMock, $this->inputHandlerMock);
		$this->policyMock->expects($this->once())->method('addCreateFormElements')->with('master');
		$this->formBuilderMock->expects($this->once())->method('buildForm')
			->with(['playlist_mode' => 'master'])->willReturn($this->form);

		static::assertSame($this->form, $this->orchestrator->buildCreateForm('master'));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testBuildCreateFormKeepsUserInput(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->serviceMock, $this->inputHandlerMock);
		$this->policyMock->expects($this->once())->method('addCreateFormElements')->with('internal');
		$this->formBuilderMock->expects($this->once())->method('buildForm')
			->with(['playlist_name' => '', 'playlist_mode' => 'internal'])->willReturn($this->form);

		$this->orchestrator->buildCreateForm('internal', ['playlist_name' => '', 'playlist_mode' => 'internal']);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testBuildEditFormWithoutLoadedPlaylistThrows(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->serviceMock, $this->policyMock, $this->inputHandlerMock, $this->formBuilderMock);
		$this->expectException(ModuleException::class);

		$this->orchestrator->buildEditForm();
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testBuildEditFormWithLoadedPlaylist(): void
	{
		$this->expectUntouched($this->inputHandlerMock);
		$this->expectPlaylistLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements')->with($this->playlist);
		$this->formBuilderMock->expects($this->once())->method('buildForm')->with($this->playlist)->willReturn($this->form);

		$this->orchestrator->loadPlaylistForEdit(5);
		static::assertSame($this->form, $this->orchestrator->buildEditForm());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testBuildEditFormPrefersUserInput(): void
	{
		$this->expectUntouched($this->inputHandlerMock);
		$this->expectPlaylistLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->formBuilderMock->expects($this->once())->method('buildForm')
			->with(['playlist_id' => '5', 'playlist_name' => ''])->willReturn($this->form);

		$this->orchestrator->loadPlaylistForEdit(5);
		$this->orchestrator->buildEditForm(['playlist_id' => '5', 'playlist_name' => '']);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreCreateSettingsReturnsValidationErrors(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->serviceMock, $this->formBuilderMock);
		$this->policyMock->expects($this->once())->method('addCreateFormElements')->with('');
		$this->inputHandlerMock->expects($this->once())->method('validate')->with(['playlist_name' => ''])
			->willReturn(['No playlist name.', 'Parameters missing.']);
		$this->inputHandlerMock->expects($this->never())->method('getParsed');

		static::assertSame(
			['success' => false, 'errors' => ['No playlist name.', 'Parameters missing.']],
			$this->orchestrator->storeCreateSettings(['playlist_name' => ''])
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreCreateSettingsFails(): void
	{
		$this->expectUntouched($this->formBuilderMock);
		$post = ['playlist_name' => 'Lobby', 'playlist_mode' => 'internal'];
		$this->policyMock->expects($this->once())->method('addCreateFormElements')->with('internal');
		$this->expectValidInput($post, ['playlist_name' => 'Lobby', 'playlist_mode' => 'internal']);
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(7);
		$this->serviceMock->expects($this->once())->method('setUID')->with(7);
		$this->serviceMock->expects($this->once())->method('createNew')
			->with(['playlist_name' => 'Lobby', 'playlist_mode' => 'internal'])->willReturn(0);

		static::assertSame(
			['success' => false, 'errors' => ['Playlist “Lobby“ could not be stored.']],
			$this->orchestrator->storeCreateSettings($post)
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreCreateSettingsCreatesCompleteLayout(): void
	{
		$this->expectUntouched($this->formBuilderMock);
		$post = ['playlist_name' => 'Lobby', 'playlist_mode' => 'multizone', 'screen_width' => 1920, 'screen_height' => 1080];
		$this->policyMock->expects($this->once())->method('addCreateFormElements')->with('multizone');
		$this->expectValidInput($post, ['playlist_name' => 'Lobby']);
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(7);
		$this->serviceMock->expects($this->once())->method('setUID')->with(7);
		$expectedLayout = ['resolution' => '1920x1080', 'zoom' => 70, 'export_unit' => 'pixel', 'zones' => []];
		$this->serviceMock->expects($this->once())->method('createNew')
			->with(['playlist_name' => 'Lobby', 'layout' => serialize($expectedLayout)])->willReturn(12);

		static::assertSame(['success' => true], $this->orchestrator->storeCreateSettings($post));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreEditSettingsWithoutLoadedPlaylistThrows(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->serviceMock, $this->policyMock, $this->inputHandlerMock, $this->formBuilderMock);
		$this->expectException(ModuleException::class);

		$this->orchestrator->storeEditSettings([]);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreEditSettingsReturnsValidationErrors(): void
	{
		$this->expectUntouched($this->formBuilderMock);
		$this->expectPlaylistLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements')->with($this->playlist);
		$this->inputHandlerMock->expects($this->once())->method('validate')->willReturn(['No playlist name.']);
		$this->inputHandlerMock->expects($this->never())->method('getParsed');
		$this->serviceMock->expects($this->never())->method('updateSecure');

		$this->orchestrator->loadPlaylistForEdit(5);
		static::assertSame(
			['success' => false, 'errors' => ['No playlist name.']],
			$this->orchestrator->storeEditSettings(['playlist_id' => '5', 'playlist_name' => ''])
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreEditSettingsReturnsServiceException(): void
	{
		$this->expectUntouched($this->formBuilderMock);
		$this->expectPlaylistLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->expectValidInput(['playlist_id' => '5'], ['playlist_id' => 5]);
		$this->serviceMock->expects($this->once())->method('updateSecure')
			->willThrowException(new ModuleException('playlists', 'No rights.'));

		$this->orchestrator->loadPlaylistForEdit(5);
		static::assertSame(
			['success' => false, 'errors' => ['No rights.']],
			$this->orchestrator->storeEditSettings(['playlist_id' => '5'])
		);
	}

	/**
	 * A save without changes affects no rows and must count as success.
	 * An existing layout keeps its zones, only the resolution changes.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreEditSettingsKeepsExistingLayout(): void
	{
		$this->expectUntouched($this->formBuilderMock);
		$this->expectPlaylistLoaded();
		$post = ['playlist_id' => '5', 'screen_width' => 1080, 'screen_height' => 1920];
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->expectValidInput($post, ['playlist_id' => 5]);
		$expectedLayout = ['resolution' => '1080x1920', 'zoom' => 50, 'zones' => [['zone']], 'export_unit' => 'pixel'];
		$this->serviceMock->expects($this->once())->method('updateSecure')
			->with(['playlist_id' => 5, 'layout' => serialize($expectedLayout)])->willReturn(0);

		$this->orchestrator->loadPlaylistForEdit(5);
		static::assertSame(['success' => true], $this->orchestrator->storeEditSettings($post));
	}

	private function expectPlaylistLoaded(): void
	{
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(7);
		$this->serviceMock->expects($this->once())->method('setUID')->with(7);
		$this->serviceMock->expects($this->once())->method('loadPlaylistForEdit')->with(5)->willReturn($this->playlist);
	}

	/**
	 * @param array<string,mixed> $post
	 * @param array<string,mixed> $parsed
	 */
	private function expectValidInput(array $post, array $parsed): void
	{
		$this->inputHandlerMock->expects($this->once())->method('validate')->with($post)->willReturn([]);
		$this->inputHandlerMock->expects($this->once())->method('getParsed')->willReturn($parsed);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
