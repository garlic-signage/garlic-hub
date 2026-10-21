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

namespace Tests\Unit\Modules\Templates\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Core\CsrfToken;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Templates\Controller\TemplatesController;
use App\Modules\Templates\Helper\Composer\Orchestrator;
use App\Modules\Templates\Helper\Datatable\Parameters;
use App\Modules\Templates\Services\TemplatesDatatableService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TemplatesControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private TemplatesDatatableService&MockObject $serviceMock;
	private Parameters&MockObject $parametersMock;
	private JsonResponseHandler&MockObject $responseHandlerMock;
	private CsrfToken&MockObject $csrfTokenMock;
	private MediaUrlSigner&MockObject $signerMock;
	private ResponseInterface $response;
	private TemplatesController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock    = $this->createMock(Orchestrator::class);
		$this->serviceMock         = $this->createMock(TemplatesDatatableService::class);
		$this->parametersMock      = $this->createMock(Parameters::class);
		$this->responseHandlerMock = $this->createMock(JsonResponseHandler::class);
		$this->csrfTokenMock       = $this->createMock(CsrfToken::class);
		$this->signerMock          = $this->createMock(MediaUrlSigner::class);
		$this->response            = $this->createStub(ResponseInterface::class);

		$this->controller = new TemplatesController(
			$this->orchestratorMock,
			$this->serviceMock,
			$this->parametersMock,
			$this->responseHandlerMock,
			$this->csrfTokenMock,
			$this->signerMock
		);
	}

	#[Group('units')]
	public function testDeleteWithInvalidCsrfToken(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->with('wrong')->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('delete');
		$this->expectError('CSRF token mismatch.');
		$this->expectUntouched($this->serviceMock, $this->parametersMock, $this->signerMock);

		$this->controller->delete($this->request(['template_id' => 12, 'csrf_token' => 'wrong']), $this->response);
	}

	#[Group('units')]
	public function testDeleteWithoutTemplateId(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->orchestratorMock->expects($this->never())->method('delete');
		$this->expectError('No template Id');
		$this->expectUntouched($this->serviceMock, $this->parametersMock, $this->signerMock);

		$this->controller->delete($this->request(['csrf_token' => 'token']), $this->response);
	}

	#[Group('units')]
	public function testDeletePassesErrorOn(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$exception = new TranslatableModuleException('templates', 'template_in_use', ['template_id' => 12]);
		$this->orchestratorMock->expects($this->once())->method('delete')->with(12)->willThrowException($exception);
		$this->expectUntouched($this->serviceMock, $this->parametersMock, $this->signerMock, $this->responseHandlerMock);

		$this->expectExceptionObject($exception);
		$this->controller->delete($this->request(['template_id' => 12, 'csrf_token' => 'token']), $this->response);
	}

	#[Group('units')]
	public function testDelete(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->with('token')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('delete')->with(12);
		$this->expectSuccess([]);
		$this->expectUntouched($this->serviceMock, $this->parametersMock, $this->signerMock);

		$this->controller->delete($this->request(['template_id' => 12, 'csrf_token' => 'token']), $this->response);
	}

    /**
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     * @throws FrameworkException
     */
    #[Group('units')]
	public function testDetermineRightsWithoutTemplateId(): void
	{
		$this->serviceMock->expects($this->never())->method('getTemplatesInUse');
		$this->expectSuccess(['can_edit' => false, 'can_delete' => false]);
		$this->expectUntouched($this->orchestratorMock, $this->parametersMock, $this->csrfTokenMock, $this->signerMock);

		$this->controller->determineRights($this->request(), $this->response, []);
	}

    /**
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     * @throws FrameworkException
     */
    #[Group('units')]
	public function testDetermineRightsWithoutEditRights(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(false);
		$this->serviceMock->expects($this->never())->method('getTemplatesInUse');
		$this->expectSuccess(['can_edit' => false, 'can_delete' => false]);
		$this->expectUntouched($this->parametersMock, $this->csrfTokenMock, $this->signerMock);

		$this->controller->determineRights($this->request(), $this->response, ['template_id' => '12']);
	}

    /**
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     * @throws FrameworkException
     */
    #[Group('units')]
	public function testDetermineRightsForTemplateInUse(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(true);
		$this->serviceMock->expects($this->once())->method('getTemplatesInUse')->with([12])->willReturn([12 => 3]);
		$this->expectSuccess(['can_edit' => true, 'can_delete' => false, 'template_id' => 12]);
		$this->expectUntouched($this->parametersMock, $this->csrfTokenMock, $this->signerMock);

		$this->controller->determineRights($this->request(), $this->response, ['template_id' => '12']);
	}

    /**
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     * @throws FrameworkException
     */
    #[Group('units')]
	public function testDetermineRightsForUnusedTemplate(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(true);
		$this->serviceMock->expects($this->once())->method('getTemplatesInUse')->with([12])->willReturn([]);
		$this->expectSuccess(['can_edit' => true, 'can_delete' => true, 'template_id' => 12]);
		$this->expectUntouched($this->parametersMock, $this->csrfTokenMock, $this->signerMock);

		$this->controller->determineRights($this->request(), $this->response, ['template_id' => '12']);
	}

    /**
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testLoadWithoutRights(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('getContent');
		$this->expectError('No rights');
		$this->expectUntouched($this->serviceMock, $this->parametersMock, $this->csrfTokenMock, $this->signerMock);

		$this->controller->load($this->request(), $this->response, ['template_id' => '12']);
	}

    /**
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testLoad(): void
	{
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('getContent')->willReturn('{"objects":[]}');
		$this->expectSuccess(['content' => '{"objects":[]}']);
		$this->expectUntouched($this->serviceMock, $this->parametersMock, $this->csrfTokenMock, $this->signerMock);

		$this->controller->load($this->request(), $this->response, ['template_id' => '12']);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testFindSkipsTemplatesWithoutVisibility(): void
	{
		$this->parametersMock->expects($this->once())->method('setUserInputs')->with(['elements_per_page' => 5]);
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');
		$this->serviceMock->expects($this->once())->method('loadDatatable');
		$this->serviceMock->expects($this->once())->method('getCurrentFilterResults')->willReturn([
			['template_id' => 1, 'name' => 'Public', 'visibility' => 'public'],
			['template_id' => 2, 'name' => 'Hidden', 'visibility' => ''],
			['template_id' => '3', 'name' => 'Private', 'visibility' => 'private'],
		]);
		$this->signerMock->expects($this->exactly(2))->method('templateThumbUrl')
			->willReturnCallback(fn(int $id, string $extension): string => '/thumb/' . $id . '.' . $extension);
		$this->expectSuccess(['templates' => [
			['id' => 1, 'src' => '/thumb/1.jpg', 'name' => 'Public'],
			['id' => '3', 'src' => '/thumb/3.jpg', 'name' => 'Private'],
		]]);
		$this->expectUntouched($this->orchestratorMock, $this->csrfTokenMock);

		$this->controller->find($this->request([], ['elements_per_page' => '5']), $this->response);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testFindWithoutPagination(): void
	{
		$this->parametersMock->expects($this->once())->method('setUserInputs')->with(['elements_per_page' => 0]);
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');
		$this->serviceMock->expects($this->once())->method('loadDatatable');
		$this->serviceMock->expects($this->once())->method('getCurrentFilterResults')->willReturn([]);
		$this->expectSuccess(['templates' => []]);
		$this->expectUntouched($this->orchestratorMock, $this->csrfTokenMock, $this->signerMock);

		$this->controller->find($this->request(), $this->response);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testSaveWithInvalidCsrfToken(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->with('')->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method($this->anything());
		$this->expectError('CSRF token mismatch.');
		$this->expectUntouched($this->serviceMock, $this->parametersMock, $this->signerMock);

		$this->controller->save($this->request(['template_id' => 12]), $this->response);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws \Doctrine\DBAL\Exception
     * @throws FrameworkException
     */
    #[Group('units')]
	public function testSaveWithoutRights(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(false);
		$this->orchestratorMock->expects($this->never())->method('saveTemplate');
		$this->expectError('No rights');
		$this->expectUntouched($this->serviceMock, $this->parametersMock, $this->signerMock);

		$this->controller->save($this->request(['template_id' => 12, 'csrf_token' => 't', 'content' => '{}']), $this->response);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testSaveFails(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('saveTemplate')->with(12, '', '')->willReturn(0);
		$this->expectError('Save failed');
		$this->expectUntouched($this->serviceMock, $this->parametersMock, $this->signerMock);

		$this->controller->save($this->request(['template_id' => 12, 'csrf_token' => 't']), $this->response);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testSave(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->with('t')->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('checkEditRights')->with(12)->willReturn(true);
		$this->orchestratorMock->expects($this->once())->method('saveTemplate')
			->with(12, '{"objects":[]}', 'data:image/jpeg;base64,abc')
			->willReturn(1);
		$this->expectSuccess([]);
		$this->expectUntouched($this->serviceMock, $this->parametersMock, $this->signerMock);

		$this->controller->save($this->request([
			'template_id' => 12, 'csrf_token' => 't', 'content' => '{"objects":[]}', 'image' => 'data:image/jpeg;base64,abc'
		]), $this->response);
	}

    /**
     * @throws ModuleException
     * @throws CoreException
     * @throws FrameworkException
     * @throws \Doctrine\DBAL\Exception
     */
    #[Group('units')]
	public function testSaveWithoutTemplateId(): void
	{
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->orchestratorMock->expects($this->never())->method($this->anything());
		$this->expectError('No template Id');
		$this->expectUntouched($this->serviceMock, $this->parametersMock, $this->signerMock);

		$this->controller->save($this->request(['csrf_token' => 't', 'content' => '{}']), $this->response);
	}

	/**
	 * @param array<string,mixed> $body
	 * @param array<string,mixed> $query
	 */
	private function request(array $body = [], array $query = []): ServerRequestInterface
	{
		$request = $this->createStub(ServerRequestInterface::class);
		$request->method('getParsedBody')->willReturn($body);
		$request->method('getQueryParams')->willReturn($query);

		return $request;
	}

	private function expectError(string $message): void
	{
		$this->responseHandlerMock->expects($this->once())->method('jsonError')->with($this->response, $message)->willReturn($this->response);
		$this->responseHandlerMock->expects($this->never())->method('jsonSuccess');
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function expectSuccess(array $data): void
	{
		$this->responseHandlerMock->expects($this->once())->method('jsonSuccess')->with($this->response, $data)->willReturn($this->response);
		$this->responseHandlerMock->expects($this->never())->method('jsonError');
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
