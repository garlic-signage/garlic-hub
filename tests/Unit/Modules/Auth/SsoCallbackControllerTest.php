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

namespace Tests\Unit\Modules\Auth;

use App\Framework\Localization\Translate\Translator;
use App\Modules\Auth\SsoCallbackController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class SsoCallbackControllerTest extends TestCase
{
	/**
	 * The session cookie is SameSite=Strict: the answer of the provider goes on by a page of the hub.
	 */
	#[Group('units')]
	public function testTheAnswerOfTheProviderGoesOnByAPageOfTheHub(): void
	{
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturn('Continue');
		$request  = new ServerRequestFactory()->createServerRequest('GET', '/login/sso/callback')->withQueryParams(['code' => 'a"><script>', 'state' => 's&t']);

		$response = new SsoCallbackController($translator)->callback($request, new Response());

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
		$body = (string) $response->getBody();
		$url = '/login/sso/finish?code=a%22%3E%3Cscript%3E&amp;state=s%26t';
		static::assertSame('<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $url . '">'
			. '<meta name="referrer" content="no-referrer"><title>SSO</title></head><body><a href="' . $url . '">Continue</a></body></html>', $body);
		static::assertStringNotContainsString('<script>', $body);
	}
}
