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

namespace Tests\Unit\Modules\Templates\Helper\Composer;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Templates\Helper\Composer\ExportImage;
use App\Modules\Templates\Helper\Composer\Orchestrator;
use App\Modules\Templates\Helper\Composer\TemplatePreparer;
use App\Modules\Templates\Services\TemplatesService;
use App\Modules\Templates\Services\TemplatesUsageService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class OrchestratorTest extends TestCase
{
	private TemplatePreparer&MockObject $preparerMock;
	private TemplatesUsageService&MockObject $usageServiceMock;
	private ExportImage&MockObject $exportImageMock;
	private TemplatesService&MockObject $serviceMock;
	private MediaUrlSigner&MockObject $signerMock;
	private Orchestrator $orchestrator;

	private string $validContent = '{"objects":[{"type":"image","fileName":"abc.png"}]}';

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->preparerMock     = $this->createMock(TemplatePreparer::class);
		$this->usageServiceMock = $this->createMock(TemplatesUsageService::class);
		$this->exportImageMock  = $this->createMock(ExportImage::class);
		$this->serviceMock      = $this->createMock(TemplatesService::class);
		$this->signerMock       = $this->createMock(MediaUrlSigner::class);
		$configStub             = $this->createStub(Config::class);
		$configStub->method('getConfigValue')->willReturn('https://cdn');

		$this->orchestrator = new Orchestrator(
			$this->preparerMock,
			$this->usageServiceMock,
			$this->exportImageMock,
			$this->serviceMock,
			$configStub,
			$this->signerMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCheckEditRightsAndBuild(): void
	{
		$prepared = ['main_layout' => ['title' => 'x'], 'this_layout' => ['template' => 'templates/canvas', 'data' => []]];
		$this->loadTemplate(['name' => 'Tpl', 'content' => null]);
		$this->preparerMock->expects($this->once())->method('replace')->with(12)->willReturn(['TEMPLATE_ID' => 12]);
		$this->preparerMock->expects($this->once())->method('prepare')->with('Tpl', ['TEMPLATE_ID' => 12])->willReturn($prepared);
		$this->expectUntouched($this->usageServiceMock, $this->exportImageMock, $this->signerMock);

		static::assertSame($prepared, $this->orchestrator->build(12));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCheckEditRightsWithoutRights(): void
	{
		$this->serviceMock->expects($this->once())->method('loadWithUserById')
			->willThrowException(new ModuleException('templates', 'No template with Id: 12 found.'));
		$this->expectUntouched($this->preparerMock, $this->usageServiceMock, $this->exportImageMock, $this->signerMock);

		static::assertFalse($this->orchestrator->checkEditRights(12));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testSaveTemplate(): void
	{
		$this->exportImageMock->expects($this->once())->method('decode')->with('data:image/png;base64,abc')->willReturnSelf();
		$this->exportImageMock->expects($this->once())->method('exportBase64')->with(12)->willReturn(true);
		$this->serviceMock->expects($this->once())->method('update')
			->with(12, ['content' => '{"objects":[{"type":"image","fileName":"abc.png"}]}'])
			->willReturn(1);
		$this->expectUntouched($this->preparerMock, $this->usageServiceMock, $this->signerMock);

		$content = '{"objects":[{"type":"image","fileName":"abc.png","src":"https://old"}]}';
		static::assertSame(1, $this->orchestrator->saveTemplate(12, $content, 'data:image/png;base64,abc'));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testSaveTemplateWithInvalidContent(): void
	{
		$this->expectUntouched($this->exportImageMock, $this->serviceMock, $this->preparerMock, $this->usageServiceMock, $this->signerMock);

		static::assertSame(0, $this->orchestrator->saveTemplate(12, '{"no":"objects"}', 'data:image/png;base64,abc'));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testSaveTemplateExportFails(): void
	{
		$this->exportImageMock->expects($this->once())->method('decode')->willReturnSelf();
		$this->exportImageMock->expects($this->once())->method('exportBase64')->willReturn(false);
		$this->serviceMock->expects($this->never())->method('update');
		$this->expectUntouched($this->preparerMock, $this->usageServiceMock, $this->signerMock);

		static::assertSame(0, $this->orchestrator->saveTemplate(12, $this->validContent, 'data:image/png;base64,abc'));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDeleteTemplateInUse(): void
	{
		$this->usageServiceMock->expects($this->once())->method('determineTemplatesInUse')->with([12])->willReturn([12 => 2]);
		$this->serviceMock->expects($this->never())->method('delete');
		$this->expectUntouched($this->preparerMock, $this->exportImageMock, $this->signerMock);

		static::assertSame('Template is in use.', $this->orchestrator->delete(12));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDeleteFails(): void
	{
		$this->usageServiceMock->expects($this->once())->method('determineTemplatesInUse')->willReturn([]);
		$this->serviceMock->expects($this->once())->method('delete')->with(12)->willReturn(0);
		$this->expectUntouched($this->preparerMock, $this->exportImageMock, $this->signerMock);

		static::assertSame('Template not deleted.', $this->orchestrator->delete(12));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDelete(): void
	{
		$this->usageServiceMock->expects($this->once())->method('determineTemplatesInUse')->willReturn([]);
		$this->serviceMock->expects($this->once())->method('delete')->with(12)->willReturn(1);
		$this->expectUntouched($this->preparerMock, $this->exportImageMock, $this->signerMock);

		static::assertSame('', $this->orchestrator->delete(12));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testGetContentRestoresSignedUrls(): void
	{
		$this->loadTemplate(['name' => 'Tpl', 'content' => $this->validContent]);
		$this->signerMock->expects($this->once())->method('originalUrl')->with('abc', 'png')->willReturn('/media/abc.png?sig=1');
		$this->expectUntouched($this->preparerMock, $this->usageServiceMock, $this->exportImageMock);

		static::assertSame(
			'{"objects":[{"type":"image","fileName":"abc.png","src":"https:\/\/cdn\/media\/abc.png?sig=1"}]}',
			$this->orchestrator->getContent()
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testGetContentOfEmptyOrBrokenTemplate(): void
	{
		$this->expectUntouched($this->preparerMock, $this->usageServiceMock, $this->exportImageMock, $this->signerMock);
		static::assertSame('', $this->orchestrator->getContent());

		$this->serviceMock->expects($this->exactly(2))->method('loadWithUserById')
			->willReturnOnConsecutiveCalls(
				['UID' => 5, 'company_id' => 1, 'template_id' => 12, 'content' => null],
				['UID' => 5, 'company_id' => 1, 'template_id' => 12, 'content' => '{broken']
			);

		$this->orchestrator->checkEditRights(12);
		static::assertSame('', $this->orchestrator->getContent());

		$this->orchestrator->checkEditRights(12);
		static::assertSame('', $this->orchestrator->getContent());
	}

	/**
	 * @param array<string,mixed> $data
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	private function loadTemplate(array $data): void
	{
		$template = array_merge(['UID' => 5, 'company_id' => 1, 'template_id' => 12], $data);
		$this->serviceMock->expects($this->once())->method('loadWithUserById')->with(12)->willReturn($template);

		static::assertTrue($this->orchestrator->checkEditRights(12));
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
