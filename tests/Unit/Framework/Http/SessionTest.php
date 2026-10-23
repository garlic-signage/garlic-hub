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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
	use PHPMock;
	private Session $session;

	protected function setUp(): void
	{
		parent::setUp();
		session_unset();
		session_destroy();
		$this->session = new Session(); // Or new Session('TestSession') if you want a custom name
		$_SESSION = [];
	}

	#[Group('units')]
	public function testStart(): void
	{
		$this->session->start();
		static::assertEquals(PHP_SESSION_ACTIVE, session_status());
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

		static::assertNotEquals($oldId, $newId);
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
		static::assertEquals('test_value', $this->session->get('test_key'));
		static::assertNull($this->session->get('non_existent_key'));
	}

	#[Group('units')]
	public function testSet(): void
	{
		$this->session->set('test_key', 'test_value');
		static::assertEquals('test_value', $_SESSION['test_key']);

		$testArray = ['a' => 1, 'b' => 2];
		$this->session->set('test_array', $testArray);
		static::assertEquals($testArray, $_SESSION['test_array']);
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
		static::assertNotEquals($id, $newId);
	}

	#[Group('units')]
	public function testIdNotStarted(): void
	{
		$id = $this->session->id();
		static::assertEmpty($id); // Should be empty if the session hasn't started
	}
}
