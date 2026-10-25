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

namespace Tests\Unit\Modules\Playlists\Helper\Playlists;

use App\Framework\Validation\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Helper\Datatable\Parameters;
use App\Modules\Playlists\Helper\Playlists\Orchestrator;
use App\Modules\Playlists\Helper\Playlists\ZonesSchema;
use App\Modules\Playlists\Helper\Playlists\ResponseBuilder;
use App\Modules\Playlists\Services\PlaylistsDatatableService;
use App\Modules\Playlists\Services\PlaylistsService;
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
	private PlaylistsService&MockObject $playlistsServiceMock;
	private PlaylistsDatatableService&MockObject $datatableServiceMock;
	private Parameters&MockObject $parametersMock;
	private ResponseInterface&Stub $responseStub;
	private Orchestrator $orchestrator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->responseBuilderMock  = $this->createMock(ResponseBuilder::class);
		$this->validatorStub        = $this->createStub(BaseValidator::class);
		$this->playlistsServiceMock = $this->createMock(PlaylistsService::class);
		$this->responseStub         = $this->createStub(ResponseInterface::class);


		// the datatable dependencies are only needed by the find tests, see useDatatableMocks()
		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$this->validatorStub,
			$this->playlistsServiceMock,
			$this->createStub(PlaylistsDatatableService::class),
			$this->createStub(Parameters::class),
			new ZonesSchema()
		);
	}

	#[Group('units')]
	public function testValidateWithInvalidPlaylistId(): void
	{
		$this->expectValidationError('invalidPlaylistId');

		$this->orchestrator->setInput([])->validate($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithTokenWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['playlist_id' => 1, 'csrf_token' => 'wrong'])->validateWithToken($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateWithTokenWithInvalidPlaylistId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidPlaylistId');

		$this->orchestrator->setInput(['csrf_token' => 'token'])->validateWithToken($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateShufflePickingWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput(['playlist_id' => 1, 'shuffle_picking' => 2])->validateShufflePicking($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateShufflePickingWithInvalidPlaylistId(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('invalidPlaylistId');

		$this->orchestrator->setInput(['shuffle_picking' => 2])->validateShufflePicking($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateShufflePickingWithMissingPickingValue(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->expectValidationError('missingPickingValue');

		$this->orchestrator->setInput(['playlist_id' => 1])->validateShufflePicking($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateShufflePickingValid(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->responseBuilderMock->expects($this->never())->method('missingPickingValue');
		$this->playlistsServiceMock->expects($this->never())->method($this->anything());

		static::assertNull($this->orchestrator->setInput(['playlist_id' => 1, 'shuffle_picking' => 2])
			->validateShufflePicking($this->responseStub));
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testDelete(): void
	{
		$this->prepareValid(['playlist_id' => 1]);
		$this->playlistsServiceMock->expects($this->once())->method('delete')->with(1)->willReturn(1);
		$this->expectSuccess([]);

		$this->orchestrator->delete($this->responseStub);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testDeleteNotFound(): void
	{
		$this->prepareValid(['playlist_id' => 1]);
		$this->playlistsServiceMock->expects($this->once())->method('delete')->with(1)->willReturn(0);
		$this->expectError('playlistNotFound');

		$this->orchestrator->delete($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testToggleShuffle(): void
	{
		$this->prepareValid(['playlist_id' => 1]);
		$this->playlistsServiceMock->expects($this->once())->method('toggleShuffle')->with(1)
			->willReturn(['affected' => 1, 'playlist_metrics' => ['duration' => 60]]);
		$this->expectSuccess(['playlist_metrics' => ['duration' => 60]]);

		$this->orchestrator->toggleShuffle($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testToggleShuffleNotAffected(): void
	{
		$this->prepareValid(['playlist_id' => 1]);
		$this->playlistsServiceMock->expects($this->once())->method('toggleShuffle')->with(1)
			->willReturn(['affected' => 0, 'playlist_metrics' => []]);
		$this->expectError('playlistNotFound');

		$this->orchestrator->toggleShuffle($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testShufflePicking(): void
	{
		$this->prepareValid(['playlist_id' => 1, 'shuffle_picking' => '5']);
		$this->playlistsServiceMock->expects($this->once())->method('shufflePicking')->with(1, 5)
			->willReturn(['affected' => 1, 'playlist_metrics' => ['duration' => 60]]);
		$this->expectSuccess(['playlist_metrics' => ['duration' => 60]]);

		$this->orchestrator->shufflePicking($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testShufflePickingNotFound(): void
	{
		$this->prepareValid(['playlist_id' => 1, 'shuffle_picking' => 5]);
		$this->playlistsServiceMock->expects($this->once())->method('shufflePicking')->with(1, 5)
			->willReturn(['affected' => 0, 'playlist_metrics' => []]);
		$this->expectError('playlistNotFound');

		$this->orchestrator->shufflePicking($this->responseStub);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoadZone(): void
	{
		$this->prepareValid(['playlist_id' => 1]);
		$this->playlistsServiceMock->expects($this->once())->method('loadPlaylistForMultizone')->with(1)
			->willReturn(['zone1']);
		$this->expectSuccess(['zones' => ['zone1']]);

		$this->orchestrator->loadZone($this->responseStub);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testSaveZoneSavesTheCheckedLayoutWithoutTheTokenAndThePlaylistId(): void
	{
		$this->prepareValid(['playlist_id' => 1, 'csrf_token' => 'token', 'resolution' => '1920x1080', 'unknown' => 'x', 'zones' => []]);
		$this->playlistsServiceMock->expects($this->once())->method('saveZones')
			->with(1, ['resolution' => '1920x1080', 'zoom' => 70, 'export_unit' => 'pixel', 'zones' => []])
			->willReturn(1);
		$this->expectSuccess([]);

		$this->orchestrator->saveZone($this->responseStub);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testSaveZoneFails(): void
	{
		$this->prepareValid(['playlist_id' => 1, 'csrf_token' => 'token', 'resolution' => '1920x1080']);
		$this->playlistsServiceMock->expects($this->once())->method('saveZones')->willReturn(0);
		$this->expectError('zoneSaveFailed');

		$this->orchestrator->saveZone($this->responseStub);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testSaveZoneRejectsAnInvalidLayout(): void
	{
		$this->prepareValid(['playlist_id' => 1, 'csrf_token' => 'token', 'zones' => ['zone1']]);
		$this->playlistsServiceMock->expects($this->never())->method('saveZones');
		$this->expectError('invalidZoneData');

		$this->orchestrator->saveZone($this->responseStub);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFindById(): void
	{
		$this->prepareValid(['playlist_id' => 14]);
		$this->playlistsServiceMock->expects($this->once())->method('loadNameById')->with(14)
			->willReturn(['playlist_id' => 14, 'name' => 'playlist']);
		$this->responseBuilderMock->expects($this->once())->method('plainData')
			->with($this->responseStub, ['playlist_id' => 14, 'name' => 'playlist'])
			->willReturn($this->responseStub);

		$this->orchestrator->findById($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindByName(): void
	{
		$this->useDatatableMocks();
		$this->parametersMock->expects($this->once())->method('setUserInputs')
			->with(['playlist_name' => 'play', 'elements_per_page' => 0, 'sort_column' => 'playlist_id', 'sort_order' => 'DESC']);
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');
		$this->expectDatatableResults();

		$this->orchestrator->setInput(['playlist_name' => 'play'])->findByName($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindForPlayerAssignment(): void
	{
		$this->useDatatableMocks();
		$this->parametersMock->expects($this->once())->method('setUserInputs')
			->with([
				'playlist_name' => 'play',
				'playlist_mode' => 'master,multizone',
				'elements_per_page' => 0,
				'sort_column' => 'playlist_id',
				'sort_order' => 'DESC'
			]);
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');
		$this->expectDatatableResults();

		$this->orchestrator->setInput(['playlist_name' => 'play'])->findForPlayerAssignment($this->responseStub);
	}

	/**
	 * @throws Exception
	 */
	private function useDatatableMocks(): void
	{
		$this->datatableServiceMock = $this->createMock(PlaylistsDatatableService::class);
		$this->parametersMock       = $this->createMock(Parameters::class);

		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$this->validatorStub,
			$this->playlistsServiceMock,
			$this->datatableServiceMock,
			$this->parametersMock,
			new ZonesSchema()
		);
	}

	private function expectDatatableResults(): void
	{
		$this->datatableServiceMock->expects($this->once())->method('loadDatatable');
		$this->datatableServiceMock->expects($this->once())->method('getCurrentFilterResults')->willReturn([
			['playlist_id' => 1, 'playlist_name' => 'playlist1', 'description' => 'description1'],
			['playlist_id' => 2, 'playlist_name' => 'playlist2', 'description' => 'description2'],
		]);
		$this->playlistsServiceMock->expects($this->never())->method($this->anything());
		$this->responseBuilderMock->expects($this->once())->method('plainData')
			->with($this->responseStub, [['id' => 1, 'name' => 'playlist1'], ['id' => 2, 'name' => 'playlist2']])
			->willReturn($this->responseStub);
	}

	/**
	 * Runs the validation, which has to pass, because it sets the playlist id the actions need.
	 *
	 * @param array<string,mixed> $input
	 */
	private function prepareValid(array $input): void
	{
		static::assertNull($this->orchestrator->setInput($input)->validate($this->responseStub));
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
		$this->playlistsServiceMock->expects($this->never())->method($this->anything());
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
