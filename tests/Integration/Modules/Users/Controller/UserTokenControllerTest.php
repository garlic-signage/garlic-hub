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

use App\Modules\Users\Controller\UserTokenController;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UserTokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * Refreshing and deleting the tokens of a user (the buttons of the edit form). Only the admin may. Users: 1 admin, 2 anna, 3 bernd.
 */
#[Group('acl')]
class UserTokenControllerTest extends UsersControllerTestCase
{
	private UserTokenController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(UserTokenController::class);
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function send(string $action, array $body): ResponseInterface
	{
		$request = $this->request($action === 'delete' ? 'DELETE' : 'POST', '/users/tokens', $body);

		return $action === 'delete' ? $this->controller->delete($request, $this->newResponse()) : $this->controller->refresh($request, $this->newResponse());
	}

	private function createToken(int $UID, TokenPurposes $purpose): string
	{
		$tokens = $this->container->get(UserTokenService::class);
		$token  = $tokens->generateToken();
		$tokens->insertToken($UID, $token, $purpose);

		return $token;
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function actions(): array
	{
		return ['refresh' => ['refresh'], 'delete' => ['delete']];
	}

	public function testTheAdminRefreshesAToken(): void
	{
		$this->actingAs(1);
		$old = $this->createToken(3, TokenPurposes::PASSWORD_RESET);

		$response = $this->send('refresh', ['UID' => '3', 'purpose' => 'password_reset']);

		$this->assertSuccess($response);
		$new = $this->json($response)['token'] ?? null;
		static::assertIsString($new);
		static::assertNotSame(bin2hex($old), $new);
		// the answer shows the new token, so the admin can pass it on
		static::assertNotNull($this->container->get(UserTokenService::class)->findByToken((string) hex2bin($new)));
		static::assertNull($this->container->get(UserTokenService::class)->findByToken($old));
		static::assertSame(1, $this->countTokens(3));
	}

	public function testTheAdminDeletesAToken(): void
	{
		$this->actingAs(1);
		$this->createToken(3, TokenPurposes::PASSWORD_RESET);
		$this->createToken(3, TokenPurposes::INITIAL_PASSWORD);

		$this->assertSuccess($this->send('delete', ['UID' => '3', 'purpose' => 'password_reset']));

		static::assertSame([TokenPurposes::INITIAL_PASSWORD->value], $this->connection->fetchFirstColumn('SELECT purpose FROM user_tokens WHERE UID = 3'));
	}

	#[DataProvider('actions')]
	public function testAUserHandlesNoTokens(string $action): void
	{
		$this->actingAs(2);
		$this->createToken(3, TokenPurposes::PASSWORD_RESET);
		$before = $this->connection->fetchOne('SELECT token FROM user_tokens WHERE UID = 3');

		$this->assertError($this->send($action, ['UID' => '3', 'purpose' => 'password_reset']), 'No rights to handle tokens.', 403);

		static::assertSame($before, $this->connection->fetchOne('SELECT token FROM user_tokens WHERE UID = 3'));
	}

	/**
	 * @return array<string,array{string,array<string,mixed>,string}> the action, the body and the error
	 */
	public static function incompleteRequests(): array
	{
		$result = [];
		foreach (['refresh', 'delete'] as $action)
		{
			$result[$action . ' without uid']      = [$action, ['purpose' => 'password_reset'], 'UID not transmitted.'];
			$result[$action . ' with uid zero']    = [$action, ['UID' => '0', 'purpose' => 'password_reset'], 'UID not transmitted.'];
			$result[$action . ' with uid as text'] = [$action, ['UID' => 'abc', 'purpose' => 'password_reset'], 'UID not transmitted.'];
			$result[$action . ' without purpose']  = [$action, ['UID' => '3'], 'Purpose not transmitted.'];
			$result[$action . ' empty purpose']    = [$action, ['UID' => '3', 'purpose' => ''], 'Purpose not transmitted.'];
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('incompleteRequests')]
	public function testARequestNeedsUserAndPurpose(string $action, array $body, string $error): void
	{
		$this->actingAs(1);
		$this->createToken(3, TokenPurposes::PASSWORD_RESET);

		$this->assertError($this->send($action, $body), $error);

		static::assertSame(1, $this->countTokens(3));
	}

	#[DataProvider('actions')]
	public function testAnUnknownPurposeIsNotEditable(string $action): void
	{
		$this->actingAs(1);
		$this->createToken(3, TokenPurposes::PASSWORD_RESET);

		$this->assertError($this->send($action, ['UID' => '3', 'purpose' => 'anything']), 'Token not editable.');

		static::assertSame(1, $this->countTokens(3));
	}

	#[DataProvider('actions')]
	public function testAUserWithoutTokenOfThePurposeHasNothingToEdit(string $action): void
	{
		$this->actingAs(1);

		$this->assertError($this->send($action, ['UID' => '3', 'purpose' => 'password_reset']), 'Token not editable.');
	}

	#[DataProvider('actions')]
	public function testAListAsUserIsNoUser(string $action): void
	{
		$this->actingAs(1);
		$this->createToken(1, TokenPurposes::PASSWORD_RESET);

		$this->assertError($this->send($action, ['UID' => ['3'], 'purpose' => 'password_reset']), 'UID not transmitted.');

		static::assertSame(1, $this->countTokens(1));
	}

	#[DataProvider('actions')]
	public function testAListAsPurposeIsNoPurpose(string $action): void
	{
		$this->actingAs(1);

		$this->assertError($this->send($action, ['UID' => '3', 'purpose' => ['password_reset']]), 'Purpose not transmitted.');
	}

}
