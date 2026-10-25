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

namespace Tests\Unit\Modules\Player\Helper\NetworkSettings;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Validation\Sanitizer;
use App\Modules\Player\Helper\NetworkSettings\FormInputHandler;
use App\Modules\Player\Helper\NetworkSettings\Parameters;
use App\Modules\Player\Helper\NetworkSettings\Validator;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The input of the form for the network of a player. Parameters, CSRF token and validator are the real ones,
 * the translator answers "module.key".
 */
class ValidatorTest extends TestCase
{
	private FormInputHandler $handler;
	private CsrfToken $csrfToken;

	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$session          = new MemorySession();
		$this->csrfToken  = new CsrfToken(new Crypt(Key::createNewRandomKey()), $session);
		$parameters       = new Parameters(new Sanitizer(), $session);
		$this->handler    = new FormInputHandler($parameters, new Validator($translator, $parameters, $this->csrfToken));
	}

	/**
	 * @param array<string,mixed> $post
	 * @return list<string>
	 */
	private function errorsOf(array $post): array
	{
		return array_values($this->handler->validate(['csrf_token' => $this->csrfToken->getToken()] + $post));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function validAddresses(): array
	{
		return [
			'host with port and path'  => ['http://192.168.1.20:8080/v2'],
			'secure'                   => ['https://player.example.com/api'],
			'host name of the network' => ['http://player-1.local:8080'],
			'localhost'                => ['http://localhost:8080/v2'],
			'a short name'             => ['http://kasse'],
			'ipv6'                     => ['http://[fd00::1]:8080/v2'],
			'upper case scheme'        => ['HTTP://192.168.1.20/v2'],
			'trailing slash'           => ['http://192.168.1.20:8080/v2/'],
		];
	}

	#[Group('units')]
	#[DataProvider('validAddresses')]
	public function testAnAddressOfThePlayerInTheNetworkIsValid(string $address): void
	{
		static::assertSame([], $this->errorsOf(['is_intranet' => '1', 'api_endpoint' => $address]));
		static::assertSame($address, $this->handler->getParsed()['api_endpoint']);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function invalidAddresses(): array
	{
		return [
			'a file'                   => ['file:///etc/passwd'],
			'ftp'                      => ['ftp://192.168.1.20/'],
			'javascript'               => ['javascript:alert(1)'],
			'gopher'                   => ['gopher://127.0.0.1:25/'],
			'no scheme'                => ['192.168.1.20:8080/v2'],
			'no host'                  => ['http:///v2'],
			'only the scheme'          => ['http://'],
			'user and password'        => ['http://admin:secret@192.168.1.20/'],
			'a user to hide the host'  => ['http://192.168.1.20@evil.example/'],
			'with a query'             => ['http://192.168.1.20/v2?x=1'],
			'with a fragment'          => ['http://192.168.1.20/v2#x'],
			'with a space'             => ['http://192.168.1.20/v2 /x'],
			'with a line break'        => ["http://192.168.1.20/v2\nHost: evil"],
			'a tab'                    => ["http://192.168.1.20/\tv2"],
			'a host with a bad sign'   => ['http://pla<yer>.local/'],
			'a host with an underscore only sign' => ['http://pl@yer/'],
			'too long'                 => ['http://' . str_repeat('a', 300) . '.local/'],
		];
	}

	/**
	 * The server calls this address: only the base of an address of the web is taken.
	 */
	#[Group('units')]
	#[DataProvider('invalidAddresses')]
	public function testAnAddressWhichIsNoBaseOfAWebAddressIsRefused(string $address): void
	{
		static::assertSame(['player.invalid_api_endpoint'], $this->errorsOf(['api_endpoint' => $address]));
	}

	#[Group('units')]
	public function testThePlayerInTheIntranetNeedsAnAddress(): void
	{
		static::assertSame(['player.no_api_endpoint'], $this->errorsOf(['is_intranet' => '1', 'api_endpoint' => '']));
		static::assertSame(['player.no_api_endpoint'], $this->errorsOf(['is_intranet' => '1']));
	}

	#[Group('units')]
	public function testAPlayerWhichIsNotInTheIntranetNeedsNoAddress(): void
	{
		static::assertSame([], $this->errorsOf(['is_intranet' => '0', 'api_endpoint' => '']));
		static::assertSame([], $this->errorsOf([]));
	}

	#[Group('units')]
	public function testAWrongAddressIsRefusedAlsoForAPlayerWhichIsNotInTheIntranet(): void
	{
		static::assertSame(['player.invalid_api_endpoint'], $this->errorsOf(['is_intranet' => '0', 'api_endpoint' => 'file:///x']));
	}

	/**
	 * @return array<string,array{array<string,mixed>,bool,string}> the input, and what is understood of it
	 */
	public static function wrongShapes(): array
	{
		return [
			'address is a list'  => [['api_endpoint' => ['http://192.168.1.20']], false, ''],
			'intranet is a list' => [['is_intranet' => ['1'], 'api_endpoint' => 'http://192.168.1.20'], false, 'http://192.168.1.20'],
		];
	}

	/**
	 * Input in a form the page never sends is not an error of the server: it is the input of an empty field.
	 *
	 * @param array<string,mixed> $post
	 */
	#[Group('units')]
	#[DataProvider('wrongShapes')]
	public function testInputInTheWrongShapeIsTheDefault(array $post, bool $isIntranet, string $address): void
	{
		static::assertSame([], $this->errorsOf($post));
		static::assertSame($isIntranet, $this->handler->getParsed()['is_intranet']);
		static::assertSame($address, $this->handler->getParsed()['api_endpoint']);
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongTokens(): array
	{
		return ['forged' => [['csrf_token' => 'forged']], 'empty' => [['csrf_token' => '']], 'a list' => [['csrf_token' => ['x']]]];
	}

	/**
	 * @param array<string,mixed> $token
	 */
	#[Group('units')]
	#[DataProvider('wrongTokens')]
	public function testTheTokenOfThePageIsNeeded(array $token): void
	{
		$this->csrfToken->getToken();

		static::assertSame(['security.csrf_token_mismatch'], $this->handler->validate($token + ['api_endpoint' => 'http://192.168.1.20']));
	}

	#[Group('units')]
	public function testTheParsedValuesAreTheTwoSettingsAndTheToken(): void
	{
		$this->errorsOf(['is_intranet' => '1', 'api_endpoint' => 'http://192.168.1.20:8080/v2']);

		$parsed = $this->handler->getParsed();
		static::assertTrue($parsed['is_intranet']);
		static::assertSame('http://192.168.1.20:8080/v2', $parsed['api_endpoint']);
		static::assertEqualsCanonicalizing(['csrf_token', 'is_intranet', 'api_endpoint'], array_keys($parsed));
	}
}
