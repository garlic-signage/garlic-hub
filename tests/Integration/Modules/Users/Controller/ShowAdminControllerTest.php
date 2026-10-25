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
use App\Framework\Localization\Translate\Translator;
use App\Modules\Users\Helper\Settings\Parameters;
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

	public function testTheFormHasTheRulesOfTheFields(): void
	{
		$this->actingAs(1);

		$fields = $this->fields($this->page($this->create()));

		static::assertSame(['text', 'required', '2'], [$fields['username']['type'], $fields['username']['required'], $fields['username']['minlength']]);
		static::assertSame(['email', 'required'], [$fields['email']['type'], $fields['email']['required']]);
		static::assertSame('hidden', $fields['csrf_token']['type']);
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

	public function testTheFormShowsTheLinkTokensOfTheUserButNotTheRememberMeLogins(): void
	{
		$this->actingAs(1);
		$tokens = $this->container->get(UserTokenService::class);
		$tokens->insertToken(3, $tokens->generateToken(), TokenPurposes::PASSWORD_RESET);
		$tokens->insertToken(3, $tokens->generateToken(), TokenPurposes::AUTOLOGIN);

		$page   = $this->page($this->edit('3'));
		$fields = $this->fields($page);

		static::assertSame(['username', 'email', 'status', 'locale', 'password_reset', 'UID', 'csrf_token'], array_keys($fields));
		// without the token in the session the link has no token to show
		static::assertSame('http://garlic-hub.test/force-password?token=', $fields['password_reset']['value']);
		$html = json_encode($page->getTemplateData()['form_element'], JSON_THROW_ON_ERROR);
		static::assertStringContainsString('Password reset', $html);
		static::assertStringContainsString((string) $this->databaseValue("SELECT expires_at FROM user_tokens WHERE purpose = 'password_reset'"), $html);
	}

	public function testTheLinkOfANewPasswordIsShownOnceInTheFormAfterTheReset(): void
	{
		$this->actingAs(1);
		$this->assertRedirect($this->store(['UID' => '3', 'resetPassword' => '1', 'username' => 'bernd', 'email' => 'bernd@example.com']), '/users/edit/3');
		$token = $this->container->get(Session::class)->get(TokenPurposes::PASSWORD_RESET->value . '_token');
		static::assertIsString($token);

		$first = $this->fields($this->page($this->edit('3')));
		$again = $this->fields($this->page($this->edit('3')));

		static::assertSame('http://garlic-hub.test/force-password?token=' . $token, $first['password_reset']['value']);
		static::assertNotNull($this->container->get(UserTokenService::class)->findByToken((string) hex2bin($token)));
		static::assertSame('http://garlic-hub.test/force-password?token=', $again['password_reset']['value']);
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
		$this->actingAs(2);

		$this->assertRedirect($this->store($body), '/users', 'No rights');
	}

	/**
	 * @return array<string,array{string}> the forms of a user: of another, of herself and of nobody
	 */
	public static function formsOfAUser(): array
	{
		return ['another user' => ['1'], 'herself' => ['2'], 'unknown user' => ['99'], 'no user' => ['abc']];
	}

	#[DataProvider('formsOfAUser')]
	public function testAUserGetsNoFormOfAUser(string $UID): void
	{
		$this->actingAs(2);

		// the same answer for every UID, so the user does not learn which users exist
		$this->assertRedirect($this->edit($UID), '/users', 'No rights');
	}

	public function testAUserGetsNoFormOfANewUser(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->create(), '/users', 'No rights');
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
		$this->actingAs(1);

		$this->page($this->store(['UID' => '0'] + $input));

		static::assertSame(3, $this->countUsers());
		static::assertCount(1, $this->flash('error'));
	}

	/**
	 * @return array<string,array{mixed}> a UID which is no id: it is not the user 1 and not a new user
	 */
	public static function usersWhichAreNoIds(): array
	{
		return ['a list' => [['3']], 'a list of the admin' => [['1']], 'text' => ['abc'], 'negative' => ['-3'], 'a number with text' => ['3abc']];
	}

	#[DataProvider('usersWhichAreNoIds')]
	public function testAUidWhichIsNoIdIsNoUser(mixed $UID): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['UID' => $UID, 'username' => 'hacked', 'email' => 'hacked@example.com']), '/users', 'UID not valid.');

		static::assertSame(['admin', 'bernd'], [$this->userRow(1)['username'], $this->userRow(3)['username']]);
		static::assertSame(3, $this->countUsers());
	}

	/**
	 * @return array<string,array{mixed}> a status which the form does not bring (0 is no status to choose)
	 */
	public static function statusWhichIsNoStatus(): array
	{
		return ['unknown' => ['99'], 'negative' => ['-1'], 'text' => ['abc'], 'a list' => [['2']], 'empty' => [''], 'deleted' => ['0'], 'not offered' => ['5']];
	}

	#[DataProvider('statusWhichIsNoStatus')]
	public function testTheStatusOfAUserStaysWhenTheFormBringsNoStatus(mixed $status): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['UID' => '3', 'username' => 'bernd2', 'email' => 'bernd@example.com', 'status' => $status, 'locale' => 'en_US']), '/users');

		static::assertSame(['bernd2', 3], [$this->userRow(3)['username'], (int) $this->userRow(3)['status']]);
	}

	public function testTheStatusOfAUserStaysWhenTheFormIsSentWithoutStatus(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['UID' => '3', 'username' => 'bernd2', 'email' => 'bernd@example.com']), '/users');

		static::assertSame(3, (int) $this->userRow(3)['status']);
	}

	#[DataProvider('statusWhichIsNoStatus')]
	public function testANewUserWithoutAStatusIsNotVerified(mixed $status): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['UID' => '0', 'username' => 'carla', 'email' => 'carla@example.com', 'status' => $status]), '/users');

		static::assertSame(2, (int) $this->databaseValue("SELECT status FROM user_main WHERE username = 'carla'"));
	}

	/**
	 * @return array<string,array{string,int}> the status of the form and the one which is stored
	 */
	public static function statusOfTheForm(): array
	{
		return ['blocked' => ['1', 1], 'not verified' => ['2', 2], 'registered' => ['3', 3], 'admin' => ['9', 9]];
	}

	#[DataProvider('statusOfTheForm')]
	public function testTheStatusOfTheFormIsStored(string $status, int $stored): void
	{
		$this->actingAs(1);

		$this->store(['UID' => '3', 'username' => 'bernd', 'email' => 'bernd@example.com', 'status' => $status]);
		$this->store(['UID' => '0', 'username' => 'carla', 'email' => 'carla@example.com', 'status' => $status]);

		static::assertSame([$stored, $stored], [(int) $this->userRow(3)['status'], (int) $this->databaseValue("SELECT status FROM user_main WHERE username = 'carla'")]);
	}

	public function testTheStatusesOfTheFormAreTheOnesTheSelectOffers(): void
	{
		$this->actingAs(1);
		$options = $this->container->get(Translator::class)->translateArrayForOptions('status_selects', 'users');
		unset($options[0]);

		static::assertSame(Parameters::STATUSES, array_keys($options));
		$select = $this->page($this->edit('3'))->getTemplateData()['form_element'][2]['HTML_ELEMENT'];
		static::assertStringNotContainsString('>Deleted<', $select);
		static::assertStringContainsString('>Blocked<', $select);
	}

	public function testTheAdminHasNoStatusInHisOwnForm(): void
	{
		$this->actingAs(1);

		static::assertSame(['username', 'email', 'locale', 'UID', 'csrf_token'], array_keys($this->fields($this->page($this->edit('1')))));
	}

	public function testTheAdminCanNotChangeHisOwnStatus(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['UID' => '1', 'username' => 'admin', 'email' => 'admin@example.com', 'status' => '1', 'locale' => 'de_DE']), '/users');

		static::assertSame([3, 'de_DE'], [(int) $this->userRow(1)['status'], $this->userRow(1)['locale']]);
	}

	public function testADeletedUserIsNotEdited(): void
	{
		$this->actingAs(1);
		$this->connection->executeStatement("UPDATE user_main SET status = 0, email = 'deleted-3@garlic-hub.com', password = '' WHERE UID = 3");

		$this->assertRedirect($this->edit('3'), '/users', 'User not found.');
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('requestsForADeletedUser')]
	public function testADeletedUserIsNotChangedAndGetsNoPasswordReset(array $body): void
	{
		$this->actingAs(1);
		$this->connection->executeStatement("UPDATE user_main SET status = 0, email = 'deleted-3@garlic-hub.com', password = '' WHERE UID = 3");

		$this->assertRedirect($this->store($body), '/users', 'User not found.');

		static::assertSame([0, 'deleted-3@garlic-hub.com', 0], [(int) $this->userRow(3)['status'], $this->userRow(3)['email'], $this->countTokens(3)]);
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function requestsForADeletedUser(): array
	{
		return [
			'revive' => [['UID' => '3', 'username' => 'bernd', 'email' => 'bernd@example.com', 'status' => '3']],
			'reset'  => [['UID' => '3', 'resetPassword' => '1', 'username' => 'bernd', 'email' => 'bernd@example.com']],
		];
	}
}
