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

namespace Tests\Unit\Framework\Http;

use App\Framework\Http\Session;
use App\Framework\Exceptions\FrameworkException;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
	use PHPMock;
	private Session $session;

	/** @var array<string,mixed> */
	private array $serverBackup;

	protected function setUp(): void
	{
		parent::setUp();
		$this->serverBackup = $_SERVER;
		unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
		$this->closeSession();
		$this->session = new Session();
		$_SESSION      = [];
	}

	protected function tearDown(): void
	{
		$this->closeSession();
		$_SESSION = [];
		$_COOKIE  = [];
		$_SERVER  = $this->serverBackup;
		parent::tearDown();
	}

	private function closeSession(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE)
		{
			session_unset();
			session_destroy();
		}
	}

	#[Group('units')]
	public function testTheConstructorSetsTheSessionName(): void
	{
		new Session('TestSession');

		static::assertSame('TestSession', session_name());
	}

	/**
	 * @param array<string,mixed> $server
	 * @param array<string,mixed> $options
	 * @param array<string,mixed> $expectedOptions
	 */
	#[RunInSeparateProcess] #[Group('units')]
	#[DataProvider('startOptionsProvider')]
	public function testStartPassesSecureOptions(array $server, array $options, array $expectedOptions): void
	{
		$_SERVER = array_merge($_SERVER, $server);

		$session_status = $this->getFunctionMock('App\Framework\Http', 'session_status');
		$session_status->expects($this->once())->willReturn(PHP_SESSION_NONE);

		$session_start = $this->getFunctionMock('App\Framework\Http', 'session_start');
		$session_start->expects($this->once())->with($expectedOptions)->willReturn(true);

		$this->session->start($options);
	}

	/**
	 * @return array<string,array{array<string,mixed>,array<string,mixed>,array<string,mixed>}>
	 */
	public static function startOptionsProvider(): array
	{
		$defaults = [
			'cookie_lifetime' => 0,
			'cookie_path'     => '/',
			'cookie_domain'   => '',
			'cookie_secure'   => false,
			'cookie_httponly' => true,
			'cookie_samesite' => 'Strict',
		];

		return [
			'plain http'            => [[], [], $defaults],
			'https sets secure'     => [['HTTPS' => 'on'], [], array_merge($defaults, ['cookie_secure' => true])],
			'behind a proxy'        => [['HTTP_X_FORWARDED_PROTO' => 'https'], [], array_merge($defaults, ['cookie_secure' => true])],
			'own options override'  => [[], ['cookie_lifetime' => 3600, 'cookie_samesite' => 'Lax'], array_merge($defaults, ['cookie_lifetime' => 3600, 'cookie_samesite' => 'Lax'])],
		];
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testStartDoesNothingWhenTheSessionIsActive(): void
	{
		$session_status = $this->getFunctionMock('App\Framework\Http', 'session_status');
		$session_status->expects($this->once())->willReturn(PHP_SESSION_ACTIVE);

		$session_start = $this->getFunctionMock('App\Framework\Http', 'session_start');
		$session_start->expects($this->never());

		$this->session->start();
	}

	/**
	 * A plain setcookie() would drop HttpOnly and SameSite, so the new id gets the flags of start().
	 *
	 * @param array<string,mixed> $server
	 */
	#[RunInSeparateProcess] #[Group('units')]
	#[DataProvider('regenerateCookieProvider')]
	public function testRegenerateIdSetsTheCookieWithTheSecureFlags(array $server, bool $secure): void
	{
		$_SERVER = array_merge($_SERVER, $server);

		$this->getFunctionMock('App\Framework\Http', 'session_status')->expects($this->once())->willReturn(PHP_SESSION_ACTIVE);
		// true deletes the old session, an old id must not stay valid
		$this->getFunctionMock('App\Framework\Http', 'session_regenerate_id')->expects($this->once())->with(true)->willReturn(true);
		$this->getFunctionMock('App\Framework\Http', 'session_name')->expects($this->once())->willReturn('GhSid');
		$this->getFunctionMock('App\Framework\Http', 'session_id')->expects($this->once())->willReturn('new_id');
		$this->getFunctionMock('App\Framework\Http', 'ini_get')->expects($this->once())->with('session.cookie_lifetime')->willReturn('0');

		$setcookie = $this->getFunctionMock('App\Framework\Http', 'setcookie');
		$setcookie->expects($this->once())
			->with('GhSid', 'new_id', ['expires' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict'])
			->willReturn(true);

		$this->session->regenerateID();
	}

	/**
	 * @return array<string,array{array<string,mixed>,bool}>
	 */
	public static function regenerateCookieProvider(): array
	{
		return [
			'plain http' => [[], false],
			'https'      => [['HTTPS' => 'on'], true],
		];
	}

	#[Group('units')]
	public function testStart(): void
	{
		$this->session->start();
		static::assertSame(PHP_SESSION_ACTIVE, session_status());
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testRegenerateID(): void
	{
		$this->session->start();
		$oldId = session_id();
		$this->session->regenerateID();
		$newId = session_id();

		static::assertNotSame($oldId, $newId);
	}

	#[Group('units')]
	public function testRegenerateIDSessionNotActive(): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Session not active for regenerating.');
		$this->session->regenerateID();
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testRegenerateIDSessionNameFails(): void
	{
		$session_status = $this->getFunctionMock('App\Framework\Http', 'session_status');
		$session_status->expects($this->once())->willReturn(PHP_SESSION_ACTIVE);

		$session_regenerate_id = $this->getFunctionMock('App\Framework\Http', 'session_regenerate_id');
		$session_regenerate_id->expects($this->once())->willReturn(true);

		$session_name = $this->getFunctionMock('App\Framework\Http', 'session_name');
		$session_name->expects($this->once())->willReturn(false);

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Session name failed for regenerating.');

		$this->session->regenerateID();
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testRegenerateIDSessionIdFails(): void
	{
		$session_status = $this->getFunctionMock('App\Framework\Http', 'session_status');
		$session_status->expects($this->once())->willReturn(PHP_SESSION_ACTIVE);

		$session_regenerate_id = $this->getFunctionMock('App\Framework\Http', 'session_regenerate_id');
		$session_regenerate_id->expects($this->once())->willReturn(true);

		$session_name = $this->getFunctionMock('App\Framework\Http', 'session_name');
		$session_name->expects($this->once())->willReturn('sessionName');

		$session_id = $this->getFunctionMock('App\Framework\Http', 'session_id');
		$session_id->expects($this->once())->willReturn(false);

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Session Id failed for regenerating.');

		$this->session->regenerateID();
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testRegenerateIDCookieLifeTimeFails(): void
	{
		$session_status = $this->getFunctionMock('App\Framework\Http', 'session_status');
		$session_status->expects($this->once())->willReturn(PHP_SESSION_ACTIVE);

		$session_regenerate_id = $this->getFunctionMock('App\Framework\Http', 'session_regenerate_id');
		$session_regenerate_id->expects($this->once())->willReturn(true);

		$session_name = $this->getFunctionMock('App\Framework\Http', 'session_name');
		$session_name->expects($this->once())->willReturn('sessionName');

		$session_id = $this->getFunctionMock('App\Framework\Http', 'session_id');
		$session_id->expects($this->once())->willReturn('an_id');

		$ini_get = $this->getFunctionMock('App\Framework\Http', 'ini_get');
		$ini_get->expects($this->once())->willReturn(false);


		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Cookie lifetime failed for regenerating.');

		$this->session->regenerateID();
	}


	#[Group('units')]
	public function testGet(): void
	{
		$this->session->start();
		$_SESSION['test_key'] = 'test_value';
		static::assertSame('test_value', $this->session->get('test_key'));
		static::assertNull($this->session->get('non_existent_key'));
	}

	#[Group('units')]
	public function testSet(): void
	{
		$this->session->set('test_key', 'test_value');
		static::assertSame('test_value', $_SESSION['test_key']);

		$testArray = ['a' => 1, 'b' => 2];
		$this->session->set('test_array', $testArray);
		static::assertSame($testArray, $_SESSION['test_array']);
	}

	#[Group('units')]
	public function testDelete(): void
	{
		$this->session->start();
		$_SESSION['test_key'] = 'test_value';
		$this->session->delete('test_key');
		static::assertArrayNotHasKey('test_key', $_SESSION);

		$this->session->delete('non_existent_key'); // Should not cause an error
	}

	#[Group('units')]
	public function testClear(): void
	{
		$_SESSION['test_key'] = 'test_value';
		$_SESSION['another_key'] = 'another_value';
		$this->session->clear();
		static::assertEmpty($this->session->getSession());
	}

	#[Group('units')]
	public function testExists(): void
	{
		$this->session->start();
		$_SESSION['test_key'] = 'test_value';
		static::assertTrue($this->session->exists('test_key'));
		static::assertFalse($this->session->exists('non_existent_key'));
	}

	/**
	 * A media player never sends the session cookie: reading must not create a session for it.
	 */
	#[Group('units')]
	public function testReadingWithoutTheSessionCookieStartsNoSession(): void
	{
		unset($_COOKIE[session_name()]);

		static::assertNull($this->session->get('locale'));
		static::assertFalse($this->session->exists('user'));
		static::assertSame([], $this->session->getSession());
		$this->session->delete('user');

		static::assertSame(PHP_SESSION_NONE, session_status());
	}

	#[Group('units')]
	public function testReadingWithTheSessionCookieStartsTheSession(): void
	{
		$_COOKIE[session_name()] = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

		static::assertNull($this->session->get('locale'));

		static::assertSame(PHP_SESSION_ACTIVE, session_status());
		unset($_COOKIE[session_name()]);
	}

	#[Group('units')]
	public function testWritingStartsTheSessionWithoutTheCookie(): void
	{
		unset($_COOKIE[session_name()]);

		$this->session->set('user', 'anna');

		static::assertSame(PHP_SESSION_ACTIVE, session_status());
		static::assertSame('anna', $this->session->get('user'));
	}

	#[Group('units')]
	public function testClearStartsTheSession(): void
	{
		unset($_COOKIE[session_name()]);

		$this->session->clear();

		static::assertSame(PHP_SESSION_ACTIVE, session_status());
	}

	#[Group('units')]
	public function testStartingTwiceIsHarmless(): void
	{
		$this->session->start();
		$this->session->start();

		static::assertSame(PHP_SESSION_ACTIVE, session_status());
	}

	#[Group('units')]
	public function testId(): void
	{
		$this->session->start();
		$id = $this->session->id();
		static::assertNotEmpty($id);

		$newId =$this->session->id(true); //regenerate
		static::assertNotEmpty($newId);
		static::assertNotSame($id, $newId);
	}

	#[Group('units')]
	public function testIdNotStarted(): void
	{
		$id = $this->session->id();
		static::assertEmpty($id); // Should be empty if the session hasn't started
	}
}
