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

namespace Tests\Integration\Modules\Users\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Modules\Auth\LoginThrottle;
use App\Modules\Users\Controller\UserTokenController;
use App\Modules\Users\Services\UsersAdminService;
use App\Modules\Users\Services\UsersDatatableService;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UsersService;
use App\Shared\User\UserTokenService;
use Phpfastcache\Helper\Psr16Adapter;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Who sees which users, who may delete users and handle their tokens.
 * Fixtures: 1 admin, 2 anna, 3 bernd.
 */
#[Group('acl')]
class UserAccessTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	public function testAdminSeesAllUsers(): void
	{
		static::assertSame(['admin', 'anna', 'bernd'], $this->usernamesVisibleFor(1));
	}

	public function testUserSeesOnlyHerself(): void
	{
		static::assertSame(['anna'], $this->usernamesVisibleFor(2));
		static::assertSame(['bernd'], $this->usernamesVisibleFor(3));
	}

	public function testTheListNeverContainsPasswordsOrSessions(): void
	{
		$this->actingAs(1);
		$service = $this->container->get(UsersDatatableService::class);
		$service->loadDatatable();

		foreach ($service->getCurrentFilterResults() as $row)
		{
			static::assertArrayNotHasKey('password', $row);
			static::assertArrayNotHasKey('session_id', $row);
		}
	}

	public function testDeletingAUserAnonymizesIt(): void
	{
		$this->actingAs(1);
		$service = $this->container->get(UsersAdminService::class);
		$UID     = $service->insertNewUser(['username' => 'carla', 'email' => 'carla@example.com']);
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES ($UID, 2, 'player')");
		$this->connection->executeStatement("UPDATE user_main SET password = 'secret', session_id = 'abc', gender = 'f' WHERE UID = $UID");
		$this->createToken($UID, TokenPurposes::AUTOLOGIN);

		$service->deleteUser($UID);

		$user = $this->connection->fetchAssociative('SELECT * FROM user_main WHERE UID = ?', [$UID]);
		static::assertIsArray($user);
		static::assertSame('carla', $user['username']);
		static::assertSame('deleted-' . $UID . '@garlic-hub.com', $user['email']);
		static::assertSame('', $user['password']);
		static::assertNull($user['session_id']);
		static::assertNull($user['gender']);
		static::assertSame(0, (int) $user['status']);
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = ?', [$UID]));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl WHERE UID = ?', [$UID]));
	}

	public function testDeletingAUserKeepsHisPlayersAndPlaylists(): void
	{
		$this->actingAs(1);
		$service = $this->container->get(UsersAdminService::class);
		$UID     = $service->insertNewUser(['username' => 'carla', 'email' => 'carla@example.com']);
		$this->connection->executeStatement("INSERT INTO player (uuid, UID, player_name) VALUES ('44444444-4444-4444-8444-444444444444', $UID, 'Carlas Player')");
		$this->connection->executeStatement("INSERT INTO playlists (UID, playlist_name) VALUES ($UID, 'Carlas Liste')");

		$service->deleteUser($UID);

		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM player WHERE UID = ?', [$UID]));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists WHERE UID = ?', [$UID]));
	}

	public function testDeletingAUserRemovesTheMediaDirectoryAndMarksTheMediaAsDeleted(): void
	{
		$this->actingAs(1);
		$service = $this->container->get(UsersAdminService::class);
		$UID     = $service->insertNewUser(['username' => 'carla', 'email' => 'carla@example.com']);
		$rootId  = (int) $this->connection->fetchOne('SELECT node_id FROM mediapool_nodes WHERE UID = ? AND is_user_folder = 1', [$UID]);
		static::assertGreaterThan(0, $rootId);
		$this->connection->executeStatement(
			"INSERT INTO mediapool_files (media_id, node_id, UID, checksum, mimetype, filename, extension) VALUES ('media-id-0000001', $rootId, $UID, 'checksum-image', 'image/jpeg', 'bild.jpg', 'jpg')"
		);
		$otherMedia = 'media-id-0000002';
		$this->connection->executeStatement(
			"INSERT INTO mediapool_files (media_id, node_id, UID, checksum, mimetype, filename, extension) VALUES ('$otherMedia', 1, 1, 'checksum-other', 'image/png', 'other.png', 'png')"
		);

		$service->deleteUser($UID);

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_nodes WHERE UID = ?', [$UID]));
		static::assertSame(1, (int) $this->connection->fetchOne("SELECT deleted FROM mediapool_files WHERE media_id = 'media-id-0000001'"));
		// the record stays, only the flag is set
		static::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM mediapool_files WHERE media_id = 'media-id-0000001'"));
		static::assertSame(0, (int) $this->connection->fetchOne("SELECT deleted FROM mediapool_files WHERE media_id = '$otherMedia'"));
	}

	public function testOtherUsersAreNotChangedByTheDeletion(): void
	{
		$this->actingAs(1);
		$before = $this->connection->fetchAllAssociative('SELECT * FROM user_main WHERE UID <> 3 ORDER BY UID');

		$this->container->get(UsersAdminService::class)->deleteUser(3);

		static::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM user_main WHERE UID <> 3 ORDER BY UID'));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl WHERE UID = 1 AND module = \'player\''));
	}

	public function testTheEmailOfADeletedUserIsFreeAgainButTheUsernameStaysTaken(): void
	{
		$this->actingAs(1);
		$service = $this->container->get(UsersAdminService::class);
		$service->deleteUser(3);

		$newUID = $service->insertNewUser(['username' => 'bernd-neu', 'email' => 'bernd@example.com']);
		static::assertGreaterThan(3, $newUID);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('username_exists');
		$service->insertNewUser(['username' => 'bernd', 'email' => 'other@example.com']);
	}

	public function testTheDeletedUserCanNotLogIn(): void
	{
		$this->actingAs(1);
		$this->container->get(UsersAdminService::class)->deleteUser(3);
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

		$users = $this->container->get(UsersService::class);
		static::assertSame([], $users->findUser('bernd@example.com'));
		foreach (['', 'not-a-real-hash', 'Test1234!'] as $password)
		{
			$authService = new AuthService(
				$users,
				$this->container->get(AutoLoginService::class),
				$this->container->get('ModuleLogger'),
				new LoginThrottle($this->container->get(Psr16Adapter::class))
			);
			static::assertNull($authService->login('bernd', $password));
		}
	}

	public function testDeletingTwiceDoesNotFail(): void
	{
		$this->actingAs(1);
		$service = $this->container->get(UsersAdminService::class);

		$service->deleteUser(3);
		$service->deleteUser(3);

		static::assertSame('deleted-3@garlic-hub.com', $this->connection->fetchOne('SELECT email FROM user_main WHERE UID = 3'));
	}

	public function testDeletedUserLosesTheRights(): void
	{
		$this->actingAs(1);
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (3, 1, 'playlists')");

		$this->container->get(UsersAdminService::class)->deleteUser(3);

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl WHERE UID = 3'));
	}

	public function testTheAdminMayNotDeleteHimself(): void
	{
		$this->actingAs(1);

		try
		{
			$this->container->get(UsersAdminService::class)->deleteUser(1);
			static::fail('Without the admin nobody could create or change users anymore.');
		}
		catch (AccessDeniedException)
		{
			static::assertSame(3, (int) $this->connection->fetchOne('SELECT status FROM user_main WHERE UID = 1'));
			static::assertGreaterThan(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl WHERE UID = 1'));
		}
	}

	/**
	 * The row stays, so the UID is never given to the next new user, whatever the database does.
	 */
	public function testANewUserNeverGetsTheUidOfADeletedUser(): void
	{
		$this->actingAs(1);
		$service = $this->container->get(UsersAdminService::class);

		$service->deleteUser(3);
		$newUID = $service->insertNewUser(['username' => 'carla', 'email' => 'carla@example.com']);

		static::assertSame(4, $newUID);
	}

	public function testANewUserDoesNotInheritTheRightsOfADeletedUser(): void
	{
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (3, 8, 'player')");
		$this->actingAs(1);
		$service = $this->container->get(UsersAdminService::class);

		$service->deleteUser(3);
		$newUID = $service->insertNewUser(['username' => 'carla', 'email' => 'carla@example.com']);

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl WHERE UID = ?', [$newUID]));
	}

	public function testUserMayNotDeleteAnotherUser(): void
	{
		$this->actingAs(2);

		try
		{
			$this->container->get(UsersAdminService::class)->deleteUser(3);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
		}
	}

	public function testUserMayNotDeleteHerself(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->container->get(UsersAdminService::class)->deleteUser(2);
	}

	public function testDeletingAnUnknownUserIsNotFound(): void
	{
		$this->actingAs(1);

		$this->expectException(NotFoundException::class);
		$this->container->get(UsersAdminService::class)->deleteUser(999);
	}

	public function testUserMayNotRefreshTheTokenOfAnotherUser(): void
	{
		$this->actingAs(2);
		$token = $this->createToken(3, TokenPurposes::PASSWORD_RESET);
		$before = $this->tokenHashes(3);

		$response = $this->tokenRequest('refresh', ['UID' => 3, 'purpose' => 'password_reset']);

		static::assertSame(403, $response->getStatusCode());
		static::assertSame($before, $this->tokenHashes(3));
		static::assertNotSame('', $token);
	}

	public function testUserMayNotDeleteTheTokensOfAnotherUser(): void
	{
		$this->actingAs(2);
		$this->createToken(3, TokenPurposes::PASSWORD_RESET);

		$response = $this->tokenRequest('delete', ['UID' => 3, 'purpose' => 'password_reset']);

		static::assertSame(403, $response->getStatusCode());
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = 3'));
	}

	public function testAdminMayRefreshAndDeleteTokens(): void
	{
		$this->actingAs(1);
		$old = $this->createToken(3, TokenPurposes::PASSWORD_RESET);
		$hashBefore = $this->tokenHashes(3);

		$refresh = $this->tokenRequest('refresh', ['UID' => 3, 'purpose' => 'password_reset']);
		static::assertSame(200, $refresh->getStatusCode());
		static::assertNotSame($hashBefore, $this->tokenHashes(3));
		static::assertNull($this->container->get(UserTokenService::class)->findByToken($old));

		$delete = $this->tokenRequest('delete', ['UID' => 3, 'purpose' => 'password_reset']);
		static::assertSame(200, $delete->getStatusCode());
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = 3'));
	}

	public function testUnknownPurposeIsNotEditable(): void
	{
		$this->actingAs(1);

		$response = $this->tokenRequest('delete', ['UID' => 3, 'purpose' => 'anything']);

		static::assertSame(400, $response->getStatusCode());
	}

	/**
	 * @return list<string>
	 */
	private function usernamesVisibleFor(int $UID): array
	{
		$this->actingAs($UID);
		$service = $this->container->get(UsersDatatableService::class);
		$service->loadDatatable();

		return array_column($service->getCurrentFilterResults(), 'username');
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function tokenRequest(string $action, array $data): ResponseInterface
	{
		$request = (new ServerRequestFactory())->createServerRequest($action === 'delete' ? 'DELETE' : 'POST', '/users/tokens')
			->withParsedBody($data);

		return $this->container->get(UserTokenController::class)->$action($request, new Response());
	}

	private function createToken(int $UID, TokenPurposes $purpose): string
	{
		$tokenService = $this->container->get(UserTokenService::class);
		$token        = $tokenService->generateToken();
		$tokenService->insertToken($UID, $token, $purpose);

		return $token;
	}

	/**
	 * @return list<string>
	 */
	private function tokenHashes(int $UID): array
	{
		return array_map('strval', $this->connection->fetchFirstColumn('SELECT token FROM user_tokens WHERE UID = ? ORDER BY token', [$UID]));
	}
}
