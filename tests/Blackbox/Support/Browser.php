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

namespace Tests\Blackbox\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Psr\Http\Message\ResponseInterface;

/**
 * A visitor: one cookie jar, so the session stays between the requests. Redirects are not followed,
 * a test sees every answer of the server.
 */
final class Browser
{
	private Client $client;
	private CookieJar $cookies;

	public function __construct(string $baseUrl)
	{
		$this->cookies = new CookieJar();
		$this->client  = new Client([
			'base_uri'        => $baseUrl,
			'cookies'         => $this->cookies,
			'allow_redirects' => false,
			'http_errors'     => false,
			'timeout'         => 20,
			// a name which is neither localhost nor ddev: the server then treats the visitor as a remote one
			'headers'         => ['Host' => 'garlic-hub.test'],
		]);
	}

	/**
	 * @param array<string,mixed> $options Guzzle options (headers, form_params, query ...)
	 */
	public function request(string $method, string $path, array $options = []): ResponseInterface
	{
		return $this->client->request($method, $path, $options);
	}

	public function get(string $path): ResponseInterface
	{
		return $this->request('GET', $path);
	}

	/**
	 * @param array<string,mixed> $fields
	 */
	public function post(string $path, array $fields): ResponseInterface
	{
		return $this->request('POST', $path, ['form_params' => $fields]);
	}

	/**
	 * The CSRF token which the server gave to this visitor: every page has it in a meta tag, the
	 * forms also in a hidden field.
	 */
	public function csrfToken(string $page = '/'): string
	{
		$html = (string) $this->get($page)->getBody();
		if (preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $m) === 1)
			return $m[1];

		if (preg_match('/name="csrf_token"[^>]*value="([^"]+)"/', $html, $m) === 1 || preg_match('/value="([^"]+)"[^>]*name="csrf_token"/', $html, $m) === 1)
			return $m[1];

		throw new \RuntimeException('No CSRF token on ' . $page);
	}

	public function login(string $username, string $password, bool $autologin = false): ResponseInterface
	{
		$fields = ['username' => $username, 'password' => $password, 'csrf_token' => $this->csrfToken('/login')];
		if ($autologin)
			$fields['autologin'] = '1';

		return $this->post('/login', $fields);
	}

	public function cookie(string $name): ?SetCookie
	{
		foreach ($this->cookies->toArray() as $cookie)
		{
			if ($cookie['Name'] === $name)
				return new SetCookie($cookie);
		}

		return null;
	}

	/**
	 * Like a browser which forgets one cookie, for example the session cookie when it is closed.
	 */
	public function forgetCookie(string $name): void
	{
		foreach ($this->cookies->toArray() as $cookie)
		{
			if ($cookie['Name'] === $name)
				$this->cookies->clear($cookie['Domain'], $cookie['Path'], $name);
		}
	}
}
