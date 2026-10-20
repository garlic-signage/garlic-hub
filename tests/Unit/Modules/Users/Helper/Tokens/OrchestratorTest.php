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

namespace Tests\Unit\Modules\Users\Helper\Tokens;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Services\UserTokenService;
use App\Modules\Users\Helper\Tokens\Orchestrator;
use App\Modules\Users\Helper\Tokens\ResponseBuilder;
use App\Modules\Users\Services\AclValidator;
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
	private AclValidator&Stub $aclValidatorStub;
	private UserTokenService&MockObject $userTokenServiceMock;
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
		$this->aclValidatorStub     = $this->createStub(AclValidator::class);
		$this->userTokenServiceMock = $this->createMock(UserTokenService::class);
		$this->responseStub         = $this->createStub(ResponseInterface::class);

		$userSessionStub = $this->createStub(UserSession::class);
		$userSessionStub->method('getUID')->willReturn(1);

		$this->orchestrator = new Orchestrator(
			$this->responseBuilderMock,
			$userSessionStub,
			$this->validatorStub,
			$this->aclValidatorStub,
			$this->userTokenServiceMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testValidateWithInvalidCsrfToken(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(false);
		$this->expectValidationError('csrfTokenMismatch');

		$this->orchestrator->setInput($this->validInput())->validate($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testValidateWithoutRights(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->aclValidatorStub->method('isModuleAdmin')->willReturn(false);
		$this->expectValidationError('noRights');

		$this->orchestrator->setInput($this->validInput())->validate($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testValidateWithMissingUID(): void
	{
		$this->allowAccess();
		$this->expectValidationError('missingUID');

		$this->orchestrator->setInput(['purpose' => 'password_reset'])->validate($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testValidateWithMissingPurpose(): void
	{
		$this->allowAccess();
		$this->expectValidationError('missingPurpose');

		$this->orchestrator->setInput(['UID' => 1])->validate($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testRefresh(): void
	{
		$this->prepareValid();
		$token = random_bytes(32);
		$this->userTokenServiceMock->expects($this->once())->method('refreshToken')
			->with(1, TokenPurposes::INITIAL_PASSWORD->value)
			->willReturn($token);
		$this->expectSuccess(['token' => bin2hex($token)]);

		$this->orchestrator->refresh($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testRefreshFails(): void
	{
		$this->prepareValid();
		$this->userTokenServiceMock->expects($this->once())->method('refreshToken')->willReturn('');
		$this->expectError('tokenNotEditable');

		$this->orchestrator->refresh($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testDelete(): void
	{
		$this->prepareValid();
		$this->userTokenServiceMock->expects($this->once())->method('deleteByUserPurpose')
			->with(1, TokenPurposes::INITIAL_PASSWORD->value)
			->willReturn(1);
		$this->expectSuccess([]);

		$this->orchestrator->delete($this->responseStub);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testDeleteFails(): void
	{
		$this->prepareValid();
		$this->userTokenServiceMock->expects($this->once())->method('deleteByUserPurpose')->willReturn(0);
		$this->expectError('tokenNotEditable');

		$this->orchestrator->delete($this->responseStub);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function validInput(): array
	{
		return ['csrf_token' => 'token', 'UID' => '1', 'purpose' => TokenPurposes::INITIAL_PASSWORD->value];
	}

	private function allowAccess(): void
	{
		$this->validatorStub->method('validateCsrfToken')->willReturn(true);
		$this->aclValidatorStub->method('isModuleAdmin')->willReturn(true);
	}

	/**
	 * Runs the validation, which has to pass, because it sets UID and purpose the actions need.
	 *
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \Exception
	 */
	private function prepareValid(): void
	{
		$this->allowAccess();

		static::assertNull($this->orchestrator->setInput($this->validInput())->validate($this->responseStub));
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
		$this->userTokenServiceMock->expects($this->never())->method('refreshToken');
		$this->userTokenServiceMock->expects($this->never())->method('deleteByUserPurpose');
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
