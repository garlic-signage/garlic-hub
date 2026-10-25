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


namespace Tests\Unit\Modules\Account\Controller;

use App\Modules\Account\Controller\ShowSettingsController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class ShowSettingsControllerTest extends TestCase
{
	/**
	 * In the edge version the settings are the password only.
	 */
	#[Group('units')]
	public function testTheSettingsPageLeadsToThePasswordPage(): void
	{
		$request  = new ServerRequestFactory()->createServerRequest('GET', '/account/settings');
		$response = new ShowSettingsController()->show($request, new Response());

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/account/password', $response->getHeaderLine('Location'));
	}
}
