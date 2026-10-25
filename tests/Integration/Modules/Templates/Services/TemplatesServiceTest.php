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

use App\Modules\Templates\Services\TemplatesService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * What TemplatesService stores of the data it gets. Who may do it is in TemplateAccessTest.
 * Fixtures: template 1 belongs to the admin, 2 to anna, 3 to bernd.
 */
#[Group('acl')]
class TemplatesServiceTest extends ContainerTestCase
{
	private TemplatesService $templatesService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
		$this->actingAs(1);
		$this->templatesService = $this->container->get(TemplatesService::class);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function template(int $id): array
	{
		$row = $this->connection->fetchAssociative('SELECT * FROM templates WHERE template_id = ?', [$id]);

		return $row !== false ? $row : static::fail('No template ' . $id);
	}

	public function testANewTemplateBelongsToTheLoggedInUser(): void
	{
		$id = $this->templatesService->insert(['type' => 'canvas', 'name' => 'Neu', 'visibility' => 'public']);

		$template = $this->template($id);
		static::assertSame([1, 'canvas', 'Neu', 'public'], [(int) $template['UID'], $template['type'], $template['name'], $template['visibility']]);
	}

	public function testANewTemplateGetsTheGivenOwner(): void
	{
		$id = $this->templatesService->insert(['UID' => 3, 'type' => 'canvas', 'name' => 'Für Bernd']);

		static::assertSame(3, (int) $this->template($id)['UID']);
	}

	public function testANewTemplateStoresOnlyTheFieldsOfTheSettings(): void
	{
		$id = $this->templatesService->insert(['type' => 'canvas', 'name' => 'Neu', 'content' => '{"objects":[]}', 'csrf_token' => 'x', 'template_id' => 99]);

		$template = $this->template($id);
		static::assertNotSame(99, $id);
		static::assertNotSame('{"objects":[]}', $template['content']);
	}

	public function testAnUpdateTakesTheSettingsAndTheContentButNotTheType(): void
	{
		static::assertSame(1, $this->templatesService->update(3, ['UID' => 2, 'content' => '{"objects":[]}', 'name' => 'Neu', 'visibility' => 'public', 'type' => 'other', 'csrf_token' => 'x']));

		$template = $this->template(3);
		static::assertSame([2, '{"objects":[]}', 'Neu', 'public', 'fabric.js'], [(int) $template['UID'], $template['content'], $template['name'], $template['visibility'], $template['type']]);
	}

	public function testAnUpdateWithTheNameOnlyKeepsTheRest(): void
	{
		$before = $this->template(1);

		$this->templatesService->update(1, ['name' => 'Umbenannt']);

		$after = $this->template(1);
		static::assertSame('Umbenannt', $after['name']);
		static::assertSame([$before['UID'], $before['visibility'], $before['content']], [$after['UID'], $after['visibility'], $after['content']]);
	}

	public function testOnlyTheGivenTemplateIsUpdated(): void
	{
		$this->templatesService->update(2, ['name' => 'Neu']);

		static::assertSame('Admin Vorlage', $this->template(1)['name']);
		static::assertSame('Bernds Vorlage', $this->template(3)['name']);
	}
}
