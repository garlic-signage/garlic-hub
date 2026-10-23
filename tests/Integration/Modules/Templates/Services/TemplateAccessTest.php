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

namespace Tests\Integration\Modules\Templates\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Templates\Helper\Composer\Orchestrator;
use App\Modules\Templates\Services\TemplatesService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Who may open, change and delete which template. In the edge edition only the module admin may,
 * even the owner of a template may not. Fixtures: template 1 belongs to the admin, 2 to anna, 3 to bernd.
 */
#[Group('acl')]
class TemplateAccessTest extends ContainerTestCase
{
	private TemplatesService $templatesService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
		$this->templatesService = $this->container->get(TemplatesService::class);
	}

	public function testAdminMayLoadEveryTemplate(): void
	{
		$this->actingAs(1);

		$bernd = $this->templatesService->loadWithUserById(3);
		$anna  = $this->templatesService->loadWithUserById(2);

		static::assertSame('Bernds Vorlage', $bernd['name']);
		static::assertSame('anna', $anna['username'] ?? null);
	}

	public function testUserMayNotLoadAForeignTemplate(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->templatesService->loadWithUserById(3);
	}

	public function testUserMayNotLoadAnOwnTemplateEither(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->templatesService->loadWithUserById(2);
	}

	public function testUnknownTemplateIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->templatesService->loadWithUserById(999);
	}

	public function testOnlyAdminsMayCreateTemplates(): void
	{
		$this->actingAs(1);
		static::assertTrue($this->templatesService->checkCreateRights());

		$this->actingAs(2);
		static::assertFalse($this->templatesService->checkCreateRights());
	}

	public function testAdminMayDeleteATemplate(): void
	{
		$this->actingAs(1);

		static::assertSame(1, $this->templatesService->delete(3));
		static::assertSame([1, 2], $this->remainingTemplateIds());
	}

	public function testUserMayNotDeleteATemplate(): void
	{
		$this->actingAs(2);

		foreach ([3, 2] as $templateId)
		{
			try
			{
				$this->templatesService->delete($templateId);
				static::fail('Expected AccessDeniedException');
			}
			catch (AccessDeniedException)
			{
				static::assertSame([1, 2, 3], $this->remainingTemplateIds());
			}
		}
	}

	public function testATemplateInUseCanNotBeDeleted(): void
	{
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/items.sql');
		$this->connection->executeStatement("UPDATE templates SET template_id = 7 WHERE template_id = 3");
		$this->actingAs(1);

		try
		{
			$this->container->get(Orchestrator::class)->delete(7);
			static::fail('Expected TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertStringContainsString('template_in_use', $e->getMessage());
			static::assertSame([1, 2, 7], $this->remainingTemplateIds());
		}
	}

	public function testAnUnusedTemplateIsDeletedByTheOrchestrator(): void
	{
		$this->actingAs(1);

		$this->container->get(Orchestrator::class)->delete(3);

		static::assertSame([1, 2], $this->remainingTemplateIds());
	}

	public function testUserMayNotDeleteThroughTheOrchestratorEither(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		try
		{
			$this->container->get(Orchestrator::class)->delete(3);
		}
		finally
		{
			static::assertSame([1, 2, 3], $this->remainingTemplateIds());
		}
	}

	public function testEditRightsAreOnlyGivenToAdmins(): void
	{
		$orchestrator = $this->container->get(Orchestrator::class);

		$this->actingAs(1);
		static::assertTrue($orchestrator->checkEditRights(3));

		$this->actingAs(2);
		static::assertFalse($orchestrator->checkEditRights(2));
		static::assertFalse($orchestrator->checkEditRights(3));
		static::assertFalse($orchestrator->checkEditRights(999));
	}

	/**
	 * @return list<int>
	 */
	private function remainingTemplateIds(): array
	{
		return array_map('intval', $this->connection->fetchFirstColumn('SELECT template_id FROM templates ORDER BY template_id'));
	}
}
