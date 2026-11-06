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

namespace Tests\Blackbox\Modules\Auth;

use PHPUnit\Framework\Attributes\Group;
use Tests\Blackbox\Support\BlackboxTestCase;

/**
 * The SSO over HTTP, as far as it goes without a provider: the button, and the way back from the provider,
 * which must not replace the session that waits for the answer. The flow with a provider: SsoLoginTest.
 */
#[Group('blackbox')]
class SsoFlowTest extends BlackboxTestCase
{
	private function turnOn(): void
	{
		$this->deployment()->changeSettings('users', ['Sso' => ['enabled' => '1', 'name' => 'Example', 'issuer' => 'https://idp.example.test', 'client_id' => 'garlic-hub']]);
	}

	public function testTheLoginPageOffersTheSsoOnlyWhenItIsOn(): void
	{
		$browser = $this->browser();
		static::assertStringNotContainsString('href="/login/sso"', (string) $browser->get('/login')->getBody());
		static::assertSame('/login', $browser->get('/login/sso')->getHeaderLine('Location'));

		$this->turnOn();
		static::assertStringContainsString('href="/login/sso"', (string) $browser->get('/login')->getBody());
	}

	/**
	 * The browser comes from the site of the provider and does not send the session cookie (SameSite=Strict).
	 * A new session cookie in the answer would replace the one with the state, nonce and verifier.
	 */
	public function testTheWayBackFromTheProviderStartsNoSession(): void
	{
		$this->turnOn();
		$response = $this->browser()->get('/login/sso/callback?code=abc&state=xyz');

		static::assertSame(200, $response->getStatusCode());
		static::assertSame([], array_values(array_filter($response->getHeader('Set-Cookie'), static fn(string $cookie): bool => str_starts_with($cookie, 'GhSid='))));
		static::assertStringContainsString('<meta http-equiv="refresh" content="0;url=/login/sso/finish?code=abc&amp;state=xyz">', (string) $response->getBody());
	}

	public function testAnAnswerWhichNobodyAskedForIsNoLogin(): void
	{
		$this->turnOn();
		$browser = $this->browser();

		static::assertSame('/login', $browser->get('/login/sso/finish?code=abc&state=xyz')->getHeaderLine('Location'));
		static::assertSame('/login', $browser->get('/')->getHeaderLine('Location'));
	}
}
