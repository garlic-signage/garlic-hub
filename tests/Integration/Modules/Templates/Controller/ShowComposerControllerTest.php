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

namespace Tests\Integration\Modules\Templates\Controller;

use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Templates\Controller\ShowComposerController;
use PHPUnit\Framework\Attributes\Group;

/**
 * The editor of a template /templates/composer/{id}: only for who may edit the template.
 */
#[Group('acl')]
class ShowComposerControllerTest extends TemplatesControllerTestCase
{
	private function show(string $templateId = ''): \Psr\Http\Message\ResponseInterface
	{
		return $this->container->get(ShowComposerController::class)->show($this->request('GET'), $this->newResponse(), $templateId === '' ? [] : ['template_id' => $templateId]);
	}

	public function testTheAdminGetsTheEditorOfTheTemplate(): void
	{
		$this->actingAs(1);

		$page = $this->show('1');

		static::assertInstanceOf(PageResponse::class, $page);
		static::assertSame('templates/canvas', $page->getTemplate());
		static::assertSame(1, $page->getTemplateData()['TEMPLATE_ID']);
		static::assertSame('Template Editor: Admin Vorlage', $page->getTemplateData()['LANG_PAGE_HEADER']);
		// the editor of a template has no export formats, they belong to the items of the playlists
		static::assertArrayNotHasKey('is_playlist_item', $page->getTemplateData());
	}

	public function testAUserIsSentBackToTheList(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->show('2'), '/templates', 'No rights');
	}

	public function testAnUnknownTemplateOrNoTemplateSendsBackToTheList(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->show('99'), '/templates', 'No rights');
	}

	public function testWithoutATemplateIdTheAdminIsSentBackToo(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->show(), '/templates', 'No rights');
	}
}
