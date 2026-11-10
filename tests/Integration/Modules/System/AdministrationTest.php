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

namespace Tests\Integration\Modules\System;

use App\Framework\TemplateEngine\PageResponse;
use App\Modules\System\Controller\ShowAdministrationController;
use PHPUnit\Framework\Attributes\Group;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The administration page. Users: 1 admin of every module, 2 anna without rights.
 */
#[Group('acl')]
class AdministrationTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	public function testTheAdminSeesHisAreas(): void
	{
		$this->actingAs(1);

		$page = $this->container->get(ShowAdministrationController::class)->show(new ServerRequestFactory()->createServerRequest('GET', '/administration'), new Response());

		static::assertInstanceOf(PageResponse::class, $page);
		static::assertSame('system/administration', $page->getTemplate());
		static::assertSame(['LANG_PAGE_TITLE' => 'Administration', 'additional_css' => ['/css/system/administration.css'], 'footer_modules' => []], $page->getMainLayoutData());
		static::assertSame('Administration', $page->getTemplateData()['LANG_PAGE_HEADER']);
		static::assertSame(['/users', '/users/sso', '/users/llm', '/templates', '/system/audit'], array_column($page->getTemplateData()['areas'], 'LINK'));
	}

	public function testAUserWithoutAnAreaIsSentHome(): void
	{
		$this->actingAs(2);

		$response = $this->container->get(ShowAdministrationController::class)->show(new ServerRequestFactory()->createServerRequest('GET', '/administration'), new Response());

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/', $response->getHeaderLine('Location'));
	}
}
