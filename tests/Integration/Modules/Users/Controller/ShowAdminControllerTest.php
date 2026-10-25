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

use App\Framework\Http\Session;
use App\Modules\Users\Controller\ShowAdminController;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UserTokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * The form of a user /users/new and /users/edit/{UID} and storing it: create, change and the reset of the password.
 * Only the admin may. The controller keeps the input of the request, so every test makes one request.
 * Users: 1 admin, 2 anna, 3 bernd.
 */
#[Group('acl')]
class ShowAdminControllerTest extends UsersControllerTestCase
{
	private ShowAdminController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(ShowAdminController::class);
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function store(array $body, bool $withToken = true): ResponseInterface
	{
		return $this->controller->store($this->request('POST', '/users/edit', $withToken ? $this->withToken($body) : $body), $this->newResponse());
	}

	private function edit(string $UID): ResponseInterface
	{
		return $this->controller->editUserForm($this->request('GET', '/users/edit/' . $UID), $this->newResponse(), ['UID' => $UID]);
	}

	private function create(): ResponseInterface
	{
		return $this->controller->newUserForm($this->request('GET', '/users/new'), $this->newResponse());
	}

	public function testTheAdminGetsTheFormOfANewUser(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->create());

		static::assertSame('users/edit', $page->getTemplate());
		$fields = $this->fields($page);
		static::assertSame(['username', 'email', 'status', 'locale', 'csrf_token'], array_keys($fields));
		static::assertSame(['', ''], [$fields['username']['value'], $fields['email']['value']]);
	}

	public function testAUserGetsAFormWithoutFieldsOfANewUser(): void
	{
		$this->actingAs(2);

		static::assertSame(['csrf_token'], array_keys($this->fields($this->page($this->create()))));
	}

	public function testTheFormOfAUserHasHisValues(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->edit('3'));

		$fields = $this->fields($page);
		static::assertSame(['username', 'email', 'status', 'locale', 'UID', 'csrf_token'], array_keys($fields));
		static::assertSame(['bernd', 'bernd@example.com', '3'], [$fields['username']['value'], $fields['email']['value'], $fields['UID']['value']]);
		static::assertSame('User core data: bernd', $page->getTemplateData()['LANG_PAGE_HEADER']);
		static::assertSame('resetPassword', $page->getTemplateData()['additional_buttons'][0]['ADDITIONAL_BUTTON_NAME']);
	}

	/**
	 * @return array<string,array{string,string}> the UID of the url and the answer
	 */
	public static function formsWithoutUser(): array
	{
		return ['zero' => ['0', 'UID not valid.'], 'text' => ['abc', 'UID not valid.'], 'empty' => ['', 'UID not valid.'], 'unknown' => ['99', 'User not found.']];
	}

	#[DataProvider('formsWithoutUser')]
	public function testThereIsNoFormOfNoUser(string $UID, string $error): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->edit($UID), '/users', $error);
	}

	public function testTheAdminCreatesAUserWhoGetsAnInitialPasswordToken(): void
	{
		$this->actingAs(1);

		$response = $this->store(['UID' => '0', 'username' => 'carla', 'email' => 'carla@example.com', 'status' => '2', 'locale' => 'de_DE']);

		$this->assertRedirect($response, '/users');
		static::assertSame(['User “carla“ successfully stored.'], $this->flash('success'));
		$user = $this->connection->fetchAssociative("SELECT UID, email, status, locale FROM user_main WHERE username = 'carla'");
		static::assertIsArray($user);
		static::assertSame(['carla@example.com', 2, 'de_DE'], [$user['email'], (int) $user['status'], $user['locale']]);
		static::assertSame(1, $this->countTokens((int) $user['UID'], TokenPurposes::INITIAL_PASSWORD->value));
		// the admin gets the token to pass it on
		static::assertTrue($this->container->get(Session::class)->exists(TokenPurposes::INITIAL_PASSWORD->value . '_token'));
	}

	public function testANewUserWithoutUidFieldIsCreatedToo(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['username' => 'carla', 'email' => 'carla@example.com', 'status' => '2', 'locale' => 'de_DE']), '/users');

		static::assertSame(4, $this->countUsers());
	}

	/**
	 * @return array<string,array{array<string,mixed>,list<string>,bool}> the input, the keys of the errors and whether the token is sent
	 */
	public static function wrongInput(): array
	{
		return [
			'no username'    => [['username' => '', 'email' => 'carla@example.com'], ['no_username'], true],
			'no email'       => [['username' => 'carla', 'email' => ''], ['no_email'], true],
			'wrong email'    => [['username' => 'carla', 'email' => 'carla.example.com'], ['no_email'], true],
			'nothing'        => [[], ['no_username', 'no_email'], true],
			'taken username' => [['username' => 'anna', 'email' => 'carla@example.com'], ['username_exists'], true],
			'taken email'    => [['username' => 'carla', 'email' => 'anna@example.com'], ['email_exists'], true],
		];
	}

	/**
	 * @param array<string,mixed> $input
	 * @param list<string> $errors
	 */
	#[DataProvider('wrongInput')]
	public function testANewUserWithWrongInputIsNotCreated(array $input, array $errors, bool $withToken): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['UID' => '0'] + $input, $withToken));

		static::assertSame(3, $this->countUsers());
		static::assertCount(count($errors), $this->flash('error'));
		foreach ($errors as $key)
			static::assertStringContainsString($this->translationOfError($key), implode(' ', $this->flash('error')));
		// the form of a new user: no id or the id 0 which was sent
		static::assertSame('0', $this->fields($page)['UID']['value'] ?? '0');
	}

	private function translationOfError(string $key): string
	{
		if ($key === 'username_exists' || $key === 'email_exists')
			return explode('{', $this->text($key))[0];

		return $this->text($key);
	}

	public function testTheFormKeepsTheInputAfterAnError(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['UID' => '0', 'username' => 'carla', 'email' => 'wrong', 'status' => '2', 'locale' => 'de_DE']));

		$fields = $this->fields($page);
		static::assertSame(['carla', 'wrong'], [$fields['username']['value'], $fields['email']['value']]);
	}

	public function testANewUserNeedsTheCsrfToken(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['UID' => '0', 'username' => 'carla', 'email' => 'carla@example.com'], withToken: false));

		static::assertSame(3, $this->countUsers());
		static::assertNotSame([], $this->flash('error'));
		static::assertSame('carla', $this->fields($page)['username']['value']);
	}

	public function testTheAdminChangesAUser(): void
	{
		$this->actingAs(1);

		$response = $this->store(['UID' => '3', 'username' => 'bernd2', 'email' => 'bernd2@example.com', 'status' => '1', 'locale' => 'de_DE']);

		$this->assertRedirect($response, '/users');
		static::assertSame(['User “bernd2“ successfully stored.'], $this->flash('success'));
		$user = $this->userRow(3);
		static::assertSame(['bernd2', 'bernd2@example.com', 1, 'de_DE'], [$user['username'], $user['email'], (int) $user['status'], $user['locale']]);
		static::assertSame(3, $this->countUsers());
	}

	public function testAChangeWithoutChangesIsNoFailure(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['UID' => '3', 'username' => 'bernd', 'email' => 'bernd@example.com', 'status' => '3', 'locale' => 'en_US']), '/users');

		static::assertSame([], $this->flash('error'));
	}

	/**
	 * @param array<string,mixed> $input
	 * @param list<string> $errors
	 */
	#[DataProvider('wrongInput')]
	public function testAUserWithWrongInputIsNotChanged(array $input, array $errors, bool $withToken): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['UID' => '3'] + $input, $withToken));

		static::assertSame(['bernd', 'bernd@example.com'], [$this->userRow(3)['username'], $this->userRow(3)['email']]);
		static::assertCount(count($errors), $this->flash('error'));
		// the form of the loaded user, with his name in the header and his id
		static::assertSame('User core data: bernd', $page->getTemplateData()['LANG_PAGE_HEADER']);
		static::assertSame('3', $this->fields($page)['UID']['value']);
	}

	public function testAnUnknownUserIsNotChanged(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['UID' => '99', 'username' => 'ghost', 'email' => 'ghost@example.com']), '/users', 'User not found.');

		static::assertSame(3, $this->countUsers());
	}

	public function testTheAdminResetsAPassword(): void
	{
		$this->actingAs(1);

		$response = $this->store(['UID' => '3', 'resetPassword' => '1', 'username' => 'bernd', 'email' => 'bernd@example.com']);

		$this->assertRedirect($response, '/users/edit/3');
		static::assertSame(['User “bernd“ Password reset was successfully.'], $this->flash('success'));
		static::assertSame('', $this->databaseValue('SELECT password FROM user_main WHERE UID = 3'));
		static::assertSame(1, $this->countTokens(3, TokenPurposes::PASSWORD_RESET->value));
		static::assertTrue($this->container->get(Session::class)->exists(TokenPurposes::PASSWORD_RESET->value . '_token'));
	}

	public function testAPasswordIsNotResetTwiceWhileTheLinkIsValid(): void
	{
		$this->actingAs(1);
		$tokens = $this->container->get(UserTokenService::class);
		$tokens->insertToken(3, $tokens->generateToken(), TokenPurposes::PASSWORD_RESET);

		$response = $this->store(['UID' => '3', 'resetPassword' => '1', 'username' => 'bernd', 'email' => 'bernd@example.com']);

		$this->assertRedirect($response, '/users/edit/3');
		static::assertCount(1, $this->flash('error'));
		static::assertSame([], $this->flash('success'));
		static::assertSame(1, $this->countTokens(3));
		static::assertNotSame('', $this->databaseValue('SELECT password FROM user_main WHERE UID = 3'));
	}

	public function testThePasswordIsNotResetWithWrongInput(): void
	{
		$this->actingAs(1);

		$response = $this->store(['UID' => '3', 'resetPassword' => '1', 'username' => '', 'email' => 'bernd@example.com']);

		$this->assertRedirect($response, '/users/edit/3');
		static::assertCount(1, $this->flash('error'));
		static::assertNotSame('', $this->databaseValue('SELECT password FROM user_main WHERE UID = 3'));
		static::assertSame(0, $this->countTokens(3));
	}

	public function testThePasswordOfAnUnknownUserIsNotReset(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['UID' => '99', 'resetPassword' => '1', 'username' => 'x', 'email' => 'x@example.com']), '/users', 'User not found.');
	}

	/**
	 * What a user who sends the request of the form by hand gets. Nothing is changed in any case,
	 * see UserAdminAccessTest.
	 *
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function requestsOfAUser(): array
	{
		return [
			'create'         => [['UID' => '0', 'username' => 'evil', 'email' => 'evil@example.com', 'status' => '3', 'locale' => 'en_US']],
			'change another' => [['UID' => '1', 'username' => 'hacked', 'email' => 'hacked@example.com', 'status' => '0', 'locale' => 'de_DE']],
			'reset another'  => [['UID' => '1', 'resetPassword' => '1', 'username' => 'admin', 'email' => 'admin@example.com']],
		];
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('requestsOfAUser')]
	public function testAUserGetsNoRightsForTheRequestsOfTheAdmin(array $body): void
	{
		static::markTestSkipped('Known bug: the request ends in a ModuleException ("A parameter with name: username is not found") instead of the answer "No rights". Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(2);

		$this->assertRedirect($this->store($body), '/users', 'No rights');
	}

	/**
	 * Known bug: a user who opens the form of another user sees his name.
	 */
	public function testAUserDoesNotGetTheFormOfAnotherUser(): void
	{
		static::markTestSkipped('Known bug: the form of another user, with the name in the header and the reset button, is shown to every user. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(2);

		$this->assertRedirect($this->edit('1'), '/users', 'No rights');
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function textFieldsWhichAreLists(): array
	{
		return ['email' => [['username' => 'carla', 'email' => ['carla@example.com']]], 'username' => [['username' => ['carla'], 'email' => 'carla@example.com']]];
	}

	/**
	 * @param array<string,mixed> $input
	 */
	#[DataProvider('textFieldsWhichAreLists')]
	public function testAListInATextFieldIsAMissingValue(array $input): void
	{
		static::markTestSkipped('Known bug: a list as username or email is a TypeError when the form is shown again, instead of the error for a missing value. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(1);

		$this->page($this->store(['UID' => '0'] + $input));

		static::assertSame(3, $this->countUsers());
		static::assertCount(1, $this->flash('error'));
	}

	/**
	 * Known bug: UID[]=3 is (int) ['3'] = 1, the admin.
	 */
	public function testAListAsUserIsNoUser(): void
	{
		static::markTestSkipped('Known bug: a list as UID is cast to 1, the data of the admin is changed. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(1);

		$this->store(['UID' => ['3'], 'username' => 'hacked', 'email' => 'hacked@example.com']);

		static::assertSame('admin', $this->userRow(1)['username']);
		static::assertSame(3, $this->countUsers());
	}

	/**
	 * @return array<string,array{mixed}>
	 */
	public static function statusWhichIsNoStatus(): array
	{
		return ['unknown' => ['99'], 'negative' => ['-1'], 'text' => ['abc'], 'a list' => [['2']]];
	}

	#[DataProvider('statusWhichIsNoStatus')]
	public function testTheStatusIsOneOfTheKnown(mixed $status): void
	{
		static::markTestSkipped('Known bug: the status of the form is not checked against UserStatus. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(1);

		$this->store(['UID' => '3', 'username' => 'bernd', 'email' => 'bernd@example.com', 'status' => $status, 'locale' => 'en_US']);

		static::assertSame(3, (int) $this->userRow(3)['status']);
	}
}
