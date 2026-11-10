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

namespace Tests\Integration\Shared\Audit;

use App\Framework\Config\ConfigOverrides;
use App\Modules\Account\Services\AccountService;
use App\Modules\Auth\AuthService;
use App\Modules\System\Scheduler\SystemScheduledTasks;
use App\Modules\Playlists\Services\PlaylistsService;
use App\Modules\Templates\Services\TemplatesService;
use App\Modules\Users\Services\UsersAdminService;
use App\Shared\Acl\AclGrantService;
use App\Shared\Acl\AclSections;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
use App\Shared\Audit\DatabaseAuditLog;
use App\Shared\Audit\NullAuditLog;
use App\Shared\Settings\SettingsService;
use App\Modules\Account\Helper\TwoFactor\Orchestrator as TwoFactorOrchestrator;
use App\Modules\Auth\LoginThrottle;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * What the audit log records: rights, shares, settings, users and failed logins.
 * Users: 1 admin (module admin of all modules), 2 anna, 3 bernd.
 */
#[Group('acl')]
class AuditLogTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('acl/playlists.sql');
	}

	protected function tearDown(): void
	{
		unset($_SERVER['REMOTE_ADDR']);
		parent::tearDown();
	}

	private function enable(): void
	{
		// before the first service with the audit log is created
		$this->container->get(ConfigOverrides::class)->save('system', ['Scheduler' => ['enabled' => '1'], 'Audit' => ['enabled' => '1']]);
	}

	/**
	 * @return list<array{UID:int, module:string, action:string, unit_id:string|null, target_UID:int|null, details:array<string,mixed>|null}>
	 */
	private function entries(): array
	{
		$entries = [];
		foreach ($this->connection->fetchAllAssociative('SELECT UID, module, action, unit_id, target_UID, details FROM audit_log ORDER BY id') as $row)
		{
			$details   = is_string($row['details']) ? json_decode($row['details'], true) : null;
			$entries[] = [
				'UID'        => (int) $row['UID'],
				'module'     => (string) $row['module'],
				'action'     => (string) $row['action'],
				'unit_id'    => $row['unit_id'] === null ? null : (string) $row['unit_id'],
				'target_UID' => $row['target_UID'] === null ? null : (int) $row['target_UID'],
				'details'    => is_array($details) ? $details : null,
			];
		}

		return $entries;
	}

	public function testTheAuditLogIsOffByDefault(): void
	{
		static::assertInstanceOf(NullAuditLog::class, $this->container->get(AuditLog::class));

		$this->container->get(AclGrantService::class)->setRole(1, 2, 'playlists', AclSections::EDITOR, true);

		static::assertSame([], $this->entries());
	}

	public function testTheAuditLogNeedsTheScheduler(): void
	{
		$this->container->get(ConfigOverrides::class)->save('system', ['Audit' => ['enabled' => '1']]);

		static::assertInstanceOf(NullAuditLog::class, $this->container->get(AuditLog::class));
	}

	public function testARoleIsRecorded(): void
	{
		$this->enable();
		static::assertInstanceOf(DatabaseAuditLog::class, $this->container->get(AuditLog::class));
		$service = $this->container->get(AclGrantService::class);

		$service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$service->setRole(1, 2, 'playlists', AclSections::EDITOR, false);

		static::assertSame([
			['UID' => 1, 'module' => 'playlists', 'action' => 'role_granted', 'unit_id' => null, 'target_UID' => 2, 'details' => ['role' => 'editor']],
			['UID' => 1, 'module' => 'playlists', 'action' => 'role_revoked', 'unit_id' => null, 'target_UID' => 2, 'details' => ['role' => 'editor']],
		], $this->entries());
	}

	public function testAShareIsRecorded(): void
	{
		$this->enable();
		$service = $this->container->get(AclGrantService::class);
		$service->setRole(1, 3, 'playlists', AclSections::EDITOR, true);

		$service->setUnitRole(1, 3, 'playlists', 1, AclSections::EDITOR);
		$service->setUnitRole(1, 3, 'playlists', 1, null);

		static::assertSame([
			['UID' => 1, 'module' => 'playlists', 'action' => 'unit_shared', 'unit_id' => '1', 'target_UID' => 3, 'details' => ['role' => 'editor']],
			['UID' => 1, 'module' => 'playlists', 'action' => 'unit_unshared', 'unit_id' => '1', 'target_UID' => 3, 'details' => null],
		], array_slice($this->entries(), 1));
	}

	public function testChangedSettingsAreRecordedWithTheirNewValues(): void
	{
		$this->enable();

		$this->container->get(SettingsService::class)->save(1, 'playlists', ['Defaults.duration' => '20', 'Defaults.volume' => '100', 'Defaults.fit' => 'meetBest', 'Defaults.media_align' => 'center']);

		static::assertSame([
			['UID' => 1, 'module' => 'playlists', 'action' => 'settings_changed', 'unit_id' => null, 'target_UID' => null, 'details' => ['changed' => ['Defaults.duration' => '20']]],
		], $this->entries());
	}

	public function testSavingWithoutChangesRecordsNothing(): void
	{
		$this->enable();

		$this->container->get(SettingsService::class)->save(1, 'playlists', ['Defaults.duration' => '15', 'Defaults.volume' => '100', 'Defaults.fit' => 'meetBest', 'Defaults.media_align' => 'center']);

		static::assertSame([], $this->entries());
	}

	public function testTheChangesOfUsersAreRecordedWithoutTheirValues(): void
	{
		$this->enable();
		$this->actingAs(1);
		$service = $this->container->get(UsersAdminService::class);

		$UID = $service->insertNewUser(['username' => 'carla', 'email' => 'carla@example.com']);
		$service->updateUser($UID, ['username' => 'carla', 'email' => 'carla@example.org']);
		$service->createPasswordResetToken(3);
		$service->deleteUser($UID);

		static::assertSame([
			['UID' => 1, 'module' => 'users', 'action' => 'user_created', 'unit_id' => null, 'target_UID' => $UID, 'details' => ['username' => 'carla']],
			['UID' => 1, 'module' => 'users', 'action' => 'user_updated', 'unit_id' => null, 'target_UID' => $UID, 'details' => ['fields' => ['email']]],
			['UID' => 1, 'module' => 'users', 'action' => 'password_reset_created', 'unit_id' => null, 'target_UID' => 3, 'details' => null],
			['UID' => 1, 'module' => 'users', 'action' => 'user_deleted', 'unit_id' => null, 'target_UID' => $UID, 'details' => null],
		], $this->entries());
	}

	public function testANewPasswordIsRecorded(): void
	{
		$this->enable();

		$this->container->get(AccountService::class)->updatePassword(2, 'New-Pass1!');

		static::assertSame([
			['UID' => 2, 'module' => 'account', 'action' => 'password_changed', 'unit_id' => null, 'target_UID' => 2, 'details' => null],
		], $this->entries());
	}

	public function testAFailedLoginIsRecordedButNotASuccessfulOne(): void
	{
		$this->enable();
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

		$this->container->get(AuthService::class)->login('anna', 'wrong');
		$this->container->get(AuthService::class)->login('nobody', 'wrong');

		static::assertSame([
			['UID' => 0, 'module' => 'auth', 'action' => 'login_failed', 'unit_id' => null, 'target_UID' => 2, 'details' => ['username' => 'anna', 'ip_address' => '10.0.0.1']],
			['UID' => 0, 'module' => 'auth', 'action' => 'login_failed', 'unit_id' => null, 'target_UID' => null, 'details' => ['username' => 'nobody', 'ip_address' => '10.0.0.1']],
		], $this->entries());
	}

	public function testAFailedLoginWithABrokenCharacterIsRecorded(): void
	{
		$this->enable();
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

		static::assertNull($this->container->get(AuthService::class)->login("ann\xC3a", 'wrong'));

		static::assertSame(['username' => 'ann?a', 'ip_address' => '10.0.0.1'], $this->entries()[0]['details']);
	}

	public function testUmlautsOfTheDetailsAreStoredAsTheyAre(): void
	{
		$this->enable();

		$this->container->get(AuditLog::class)->record(1, 'playlists', AuditAction::UNIT_DELETED, 5, null, ['name' => 'Grüße']);

		static::assertSame('{"name":"Grüße"}', $this->connection->fetchOne('SELECT details FROM audit_log'));
		static::assertSame('5', $this->entries()[0]['unit_id']);
	}

	public function testDeletedPlaylistsAndTemplatesAreRecordedWithTheirName(): void
	{
		$this->loadFixture('templates/templates.sql');
		$this->enable();
		$this->actingAs(1);

		$this->container->get(PlaylistsService::class)->delete(3);
		$this->container->get(TemplatesService::class)->delete(3);

		static::assertSame([
			['UID' => 1, 'module' => 'playlists', 'action' => 'unit_deleted', 'unit_id' => '3', 'target_UID' => null, 'details' => ['name' => 'Bernds Playlist']],
			['UID' => 1, 'module' => 'templates', 'action' => 'unit_deleted', 'unit_id' => '3', 'target_UID' => null, 'details' => ['name' => 'Bernds Vorlage']],
		], $this->entries());
	}

	public function testTheSchedulerDeletesTheOldEntries(): void
	{
		$this->container->get(ConfigOverrides::class)->save('system', ['Audit' => ['retention_days' => '30']]);
		$this->connection->insert('audit_log', ['created_at' => date('Y-m-d H:i:s', strtotime('-31 days')), 'UID' => 1, 'module' => 'users', 'action' => 'user_deleted']);
		$this->connection->insert('audit_log', ['created_at' => date('Y-m-d H:i:s', strtotime('-29 days')), 'UID' => 1, 'module' => 'users', 'action' => 'user_created']);

		foreach ($this->container->get(SystemScheduledTasks::class)->tasks() as $task)
			$task->run();

		static::assertSame(['user_created'], array_column($this->entries(), 'action'));
	}

	/**
	 * Wrong codes in the account from many addresses lock the second factor of the user: recorded once.
	 */
	public function testTheLockOfTheSecondFactorIsRecorded(): void
	{
		$this->enable();
		$this->actingAs(2);
		$orchestrator = $this->container->get(TwoFactorOrchestrator::class);

		for ($i = 1; $i <= LoginThrottle::MAX_SECOND_FACTOR_FAILURES_PER_USER; $i++)
		{
			$_SERVER['REMOTE_ADDR'] = '10.0.1.' . $i;
			$orchestrator->createRecoveryCodes('000000');
		}
		$_SERVER['REMOTE_ADDR'] = '10.0.2.1';

		static::assertSame('two_factor_blocked', $orchestrator->disable('000000'));
		static::assertSame([['UID' => 2, 'module' => 'account', 'action' => 'two_factor_locked', 'unit_id' => null, 'target_UID' => 2, 'details' => ['ip_address' => '10.0.1.10']]], $this->entries());
	}
}
