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

namespace Tests\Blackbox\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Blackbox\Support\BlackboxTestCase;

/**
 * Only scripts of the server and inline scripts with the nonce of the request may run. A page with an inline script
 * without the nonce, or with an inline handler like onclick, would not work in a browser with this policy.
 */
#[Group('blackbox')]
class ContentSecurityPolicyTest extends BlackboxTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->loadFixture('mediapool/files.sql');
		$this->loadFixture('playlists/items.sql');
	}

	/**
	 * @return array<string,array{string}> the pages with scripts of their own and a page with messages
	 */
	public static function pages(): array
	{
		return [
			'start'                => ['/'],
			'player list'          => ['/player'],
			'playlist list'        => ['/playlists'],
			'playlist composer'    => ['/playlists/compose/1'],
			'mediapool'            => ['/mediapool'],
			'template list'        => ['/templates'],
			'template composer'    => ['/templates/composer/1'],
			'template settings'    => ['/templates/settings/canvas'],
			'user list'            => ['/users'],
			'user form'            => ['/users/edit/2'],
			'own password'         => ['/account/password'],
		];
	}

	#[DataProvider('pages')]
	public function testEveryInlineScriptHasTheNonceOfTheRequest(string $path): void
	{
		$browser = $this->browser();
		$browser->login('admin', 'Test1234!');

		$response = $browser->get($path);
		$html     = (string) $response->getBody();

		static::assertSame(200, $response->getStatusCode(), $path);
		static::assertSame(1, preg_match("/script-src 'self' 'nonce-([0-9a-f]{32})'/", $response->getHeaderLine('Content-Security-Policy'), $policy), $path);
		$scripts = [];
		preg_match_all('/<script\b([^>]*)>/i', $html, $scripts);
		$inline = array_filter($scripts[1], static fn(string $attributes): bool => !str_contains($attributes, ' src=') && !str_starts_with(trim($attributes), 'src='));
		static::assertNotSame([], $inline, $path . ' has inline scripts');
		foreach ($inline as $attributes)
			static::assertStringContainsString('nonce="' . $policy[1] . '"', $attributes, $path);
	}

	#[DataProvider('pages')]
	public function testNoInlineHandlerIsLeft(string $path): void
	{
		$browser = $this->browser();
		$browser->login('admin', 'Test1234!');

		static::assertSame(0, preg_match('/<[a-z][^>]*\son[a-z]+\s*=/i', (string) $browser->get($path)->getBody()), $path);
	}

	public function testTheNonceIsNewForEveryRequest(): void
	{
		$browser = $this->browser();
		$first   = $browser->get('/login')->getHeaderLine('Content-Security-Policy');
		$second  = $browser->get('/login')->getHeaderLine('Content-Security-Policy');

		static::assertNotSame($first, $second);
	}

	public function testAGuestPageHasTheNonceToo(): void
	{
		$response = $this->browser()->get('/login');

		static::assertSame(1, preg_match("/nonce-([0-9a-f]{32})'/", $response->getHeaderLine('Content-Security-Policy'), $policy));
		static::assertStringContainsString('<script type="module" nonce="' . $policy[1] . '">', (string) $response->getBody());
	}
}
