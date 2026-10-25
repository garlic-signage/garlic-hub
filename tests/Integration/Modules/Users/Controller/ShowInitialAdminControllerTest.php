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

namespace Tests\Integration\Modules\Users\Controller;

use App\Modules\Users\Controller\ShowInitialAdminController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Http\Message\ResponseInterface;

/**
 * The first admin /create-initial on a fresh installation: no users and no lock file. The lock file is a constant of
 * the application, so every test runs in its own process and defines it in the temp dir.
 */
#[Group('acl')]
#[Group('io')]
#[RunTestsInSeparateProcesses]
class ShowInitialAdminControllerTest extends UsersControllerTestCase
{
	private const string PASSWORD = 'Secret123!';

	private string $lockFile;
	private ShowInitialAdminController $controller;

	protected function loadUsers(): void
	{
		// a fresh installation
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->lockFile = sys_get_temp_dir() . '/garlic-hub-tests/install-' . getmypid() . '/install.lock';
		@mkdir(dirname($this->lockFile), 0777, true);
		@unlink($this->lockFile);
		define('INSTALL_LOCK_FILE', $this->lockFile);

		$this->controller = $this->container->get(ShowInitialAdminController::class);
	}

	protected function tearDown(): void
	{
		@unlink($this->lockFile);
		@rmdir(dirname($this->lockFile));
		parent::tearDown();
	}

	/**
	 * @param array<string,mixed> $changes
	 * @return array<string,mixed>
	 */
	private static function admin(array $changes = []): array
	{
		return $changes + ['username' => 'root', 'email' => 'root@example.com', 'locale' => 'de_DE', 'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD];
	}

	/**
	 * @param array<string,mixed>|null $body
	 */
	private function store(?array $body): ResponseInterface
	{
		$request = $this->request('POST', '/create-initial', $body === null ? null : $this->withToken($body));

		return $this->controller->store($request, $this->newResponse());
	}

	private function show(): ResponseInterface
	{
		return $this->controller->show($this->request('GET', '/create-initial'), $this->newResponse());
	}

	public function testTheFormIsShownOnAFreshInstallation(): void
	{
		$page = $this->page($this->show());

		static::assertSame(['username', 'email', 'password', 'password_confirm', 'locale', 'csrf_token'], array_keys($this->fields($page)));
		static::assertFileDoesNotExist($this->lockFile);
	}

	public function testTheFormIsNotShownAfterTheInstallation(): void
	{
		file_put_contents($this->lockFile, 'locked');

		$this->assertRedirect($this->show(), '/');
	}

	public function testTheFormIsNotShownWhenAnAdminExistsAndTheLockFileIsRestored(): void
	{
		$this->loadFixture('acl/users.sql');

		$this->assertRedirect($this->show(), '/');
		// the missing lock file is created again
		static::assertFileExists($this->lockFile);
	}

	public function testTheAdminIsStoredAndTheLoginIsShown(): void
	{
		$response = $this->store(self::admin());

		$this->assertRedirect($response, '/login');
		static::assertSame(['Admin User “root“ successfully stored. You can now login with your username and password.'], $this->flash('success'));
		$user = $this->userRow(1);
		static::assertSame(['root', 'root@example.com', 'de_DE'], [$user['username'], $user['email'], $user['locale']]);
		static::assertTrue(password_verify(self::PASSWORD, (string) $user['password']));
		static::assertFileExists($this->lockFile);
	}

	public function testTheSecondInstallationIsRefused(): void
	{
		$this->assertRedirect($this->store(self::admin()), '/login');

		$this->assertRedirect($this->store(self::admin(['username' => 'second', 'email' => 'second@example.com'])), '/');

		static::assertSame(1, $this->countUsers());
	}

	/**
	 * @return array<string,array{array<string,mixed>,list<string>}> the changes of the input and the keys of the errors
	 */
	public static function wrongInput(): array
	{
		return [
			'no username'       => [['username' => ''], ['no_username']],
			'wrong email'       => [['email' => 'root.example.com'], ['no_email']],
			'no password'       => [['password' => '', 'password_confirm' => ''], ['no_password', 'no_password_confirm', 'password_explanation']],
			'no confirmation'   => [['password_confirm' => ''], ['no_password_confirm', 'no_passwords_match']],
			'different'         => [['password_confirm' => 'Secret123?'], ['no_passwords_match']],
			'too simple'        => [['password' => 'secret', 'password_confirm' => 'secret'], ['password_explanation']],
			'without a number'  => [['password' => 'SecretSecret', 'password_confirm' => 'SecretSecret'], ['password_explanation']],
		];
	}

	/**
	 * @param array<string,mixed> $changes
	 * @param list<string> $errors
	 */
	#[DataProvider('wrongInput')]
	public function testAnAdminWithWrongInputIsNotStored(array $changes, array $errors): void
	{
		$page = $this->page($this->store(self::admin($changes)));

		static::assertSame(0, $this->countUsers());
		static::assertFileDoesNotExist($this->lockFile);
		static::assertCount(count($errors), $this->flash('error'));
		foreach ($errors as $key)
			static::assertStringContainsString($this->text($key), implode(' ', $this->flash('error')));
		static::assertArrayHasKey('username', $this->fields($page));
	}

	public function testTheFormKeepsTheInputButNotThePasswordAfterAnError(): void
	{
		$page = $this->page($this->store(self::admin(['email' => 'wrong'])));

		$fields = $this->fields($page);
		static::assertSame(['root', 'wrong'], [$fields['username']['value'], $fields['email']['value']]);
		static::assertSame('', $fields['password']['value'] ?? '');
		static::assertSame('', $fields['password_confirm']['value'] ?? '');
	}

	public function testTheAdminNeedsTheCsrfToken(): void
	{
		$request = $this->request('POST', '/create-initial', self::admin());

		$this->page($this->controller->store($request, $this->newResponse()));

		static::assertSame(0, $this->countUsers());
		static::assertCount(1, $this->flash('error'));
	}

	public function testARequestWithoutBodyIsAFormWithErrors(): void
	{
		$page = $this->page($this->store(null));

		static::assertSame(0, $this->countUsers());
		static::assertNotSame([], $this->flash('error'));
		static::assertArrayHasKey('username', $this->fields($page));
	}

	public function testAListAsLocaleIsTheDefaultLocale(): void
	{
		$this->assertRedirect($this->store(self::admin(['locale' => ['de_DE']])), '/login');

		static::assertSame('en_US', $this->userRow(1)['locale']);
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function textFieldsWhichAreLists(): array
	{
		return ['username' => [['username' => ['root']]], 'email' => [['email' => ['root@example.com']]], 'password' => [['password' => ['Secret123!'], 'password_confirm' => ['Secret123!']]]];
	}

	/**
	 * @param array<string,mixed> $changes
	 */
	#[DataProvider('textFieldsWhichAreLists')]
	public function testAListInAFieldIsAMissingValue(array $changes): void
	{
		$page = $this->page($this->store(self::admin($changes)));

		static::assertSame(0, $this->countUsers());
		static::assertNotSame([], $this->flash('error'));
		static::assertArrayHasKey('username', $this->fields($page));
	}
}
