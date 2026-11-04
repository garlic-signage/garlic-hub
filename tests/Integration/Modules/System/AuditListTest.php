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

namespace Tests\Integration\Modules\System;

use App\Framework\Config\ConfigOverrides;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\System\Controller\ShowAuditController;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The list of the audit log. Users: 1 admin (module admin of system), 2 anna, 3 bernd.
 * Playlist 1 is "Annas Playlist 1".
 */
#[Group('acl')]
class AuditListTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('acl/playlists.sql');
	}

	/**
	 * @param array<string,mixed> $details
	 */
	private function entry(string $createdAt, int $UID, string $module, string $action, ?string $unitId = null, ?int $targetUID = null, array $details = []): void
	{
		$this->connection->insert('audit_log', [
			'created_at' => $createdAt, 'UID' => $UID, 'module' => $module, 'action' => $action,
			'unit_id' => $unitId, 'target_UID' => $targetUID, 'details' => $details === [] ? null : json_encode($details)
		]);
	}

	/**
	 * @param array<string,string> $query
	 */
	private function show(array $query = []): ResponseInterface
	{
		$request = new ServerRequestFactory()->createServerRequest('GET', '/system/audit')->withQueryParams($query);

		return $this->container->get(ShowAuditController::class)->show($request, new Response());
	}

	/**
	 * @return array<string,mixed>
	 */
	private function data(ResponseInterface $response): array
	{
		static::assertInstanceOf(PageResponse::class, $response);

		return $response->getTemplateData();
	}

	/**
	 * @param array<string,string> $query
	 * @return list<array<string,string>> column => text, the newest entry first
	 */
	private function rows(array $query = []): array
	{
		$rows = [];
		foreach ($this->data($this->show($query))['elements_results'] as $row)
		{
			$columns = [];
			foreach ($row['elements_result_element'] as $cell)
				$columns[$cell['CONTROL_NAME_BODY']] = $cell['is_text']['CONTROL_ELEMENT_VALUE_TEXT'];

			$rows[] = $columns;
		}

		return $rows;
	}

	public function testOnlyTheModuleAdminOfSystemSeesTheLog(): void
	{
		$this->actingAs(2);

		$response = $this->show();

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/', $response->getHeaderLine('Location'));
	}

	public function testTheEntriesShowTheNamesOfUsersAndUnits(): void
	{
		$this->entry('2026-11-01 10:00:00', 1, 'playlists', 'role_granted', null, 2, ['role' => 'editor']);
		$this->entry('2026-11-02 10:00:00', 1, 'playlists', 'unit_shared', '1', 2, ['role' => 'editor']);
		$this->entry('2026-11-03 10:00:00', 1, 'playlists', 'unit_unshared', '99', 3);
		$this->entry('2026-11-04 10:00:00', 1, 'system', 'settings_changed', null, null, ['changed' => ['Audit.enabled' => '1']]);
		$this->actingAs(1);

		static::assertSame([
			['created_at' => '2026-11-04 10:00:00', 'UID' => 'admin', 'action' => 'Settings changed', 'module' => 'system', 'target_UID' => '', 'details' => 'Changed: Audit.enabled = 1'],
			// a deleted unit has no name anymore
			['created_at' => '2026-11-03 10:00:00', 'UID' => 'admin', 'action' => 'Share removed', 'module' => 'playlists', 'target_UID' => 'bernd, Unit: #99', 'details' => ''],
			['created_at' => '2026-11-02 10:00:00', 'UID' => 'admin', 'action' => 'Unit shared', 'module' => 'playlists', 'target_UID' => 'anna, Unit: Annas Playlist 1', 'details' => 'Role: Editor'],
			['created_at' => '2026-11-01 10:00:00', 'UID' => 'admin', 'action' => 'Role granted', 'module' => 'playlists', 'target_UID' => 'anna', 'details' => 'Role: Editor'],
		], $this->rows());
	}

	public function testAFailedLoginShowsTheUserOrWhatWasTypedIn(): void
	{
		$this->entry('2026-11-01 10:00:00', 0, 'auth', 'login_failed', null, 2, ['username' => 'anna', 'ip_address' => '10.0.0.1']);
		$this->entry('2026-11-02 10:00:00', 0, 'auth', 'login_failed', null, null, ['username' => '<b>adminn</b>', 'ip_address' => '10.0.0.2']);
		$this->actingAs(1);

		$rows = $this->rows();

		// the template escapes what was typed in
		static::assertSame(['„<b>adminn</b>" (unknown)', '', 'IP address: 10.0.0.2'], [$rows[0]['UID'], $rows[0]['target_UID'], $rows[0]['details']]);
		static::assertSame(['anna', 'anna', 'IP address: 10.0.0.1'], [$rows[1]['UID'], $rows[1]['target_UID'], $rows[1]['details']]);
	}

	public function testTheFilterLimitsTheEntries(): void
	{
		$this->entry('2026-10-31 23:59:59', 1, 'users', 'user_created', null, 3);
		$this->entry('2026-11-01 00:00:00', 1, 'users', 'user_deleted', null, 3);
		$this->entry('2026-11-02 10:00:00', 0, 'auth', 'login_failed', null, null, ['username' => 'x', 'ip_address' => '10.0.0.1']);
		$this->actingAs(1);

		static::assertSame(['User deleted', 'Login failed'], array_reverse(array_column($this->rows(['date_from' => '2026-11-01']), 'action')));
		static::assertSame(['User created'], array_column($this->rows(['date_from' => '', 'date_to' => '2026-10-31']), 'action'));
		static::assertSame(['Login failed'], array_column($this->rows(['date_to' => '', 'action' => 'login_failed']), 'action'));
		static::assertSame(['User deleted', 'User created'], array_column($this->rows(['action' => '', 'module' => 'users', 'username' => 'adm']), 'action'));
	}

	public function testTheFilterOfWhoFindsWhatWasTypedInAtAFailedLogin(): void
	{
		$this->entry('2026-11-01 10:00:00', 1, 'users', 'user_created', null, 3, ['username' => 'mallory']);
		$this->entry('2026-11-02 10:00:00', 0, 'auth', 'login_failed', null, null, ['username' => 'mallory', 'ip_address' => '10.0.0.1']);
		$this->actingAs(1);

		// the user created by the admin has the name in the details, too: it is no failed login
		static::assertSame(['Login failed'], array_column($this->rows(['username' => 'mallo']), 'action'));
	}

	public function testThePageSaysWhenTheLogIsOff(): void
	{
		$this->actingAs(1);
		static::assertNotSame([], $this->data($this->show())['audit_disabled']);
	}

	public function testThePageSaysNothingWhenTheLogIsOn(): void
	{
		$this->container->get(ConfigOverrides::class)->save('system', ['Scheduler' => ['enabled' => '1'], 'Audit' => ['enabled' => '1']]);
		$this->actingAs(1);

		$data = $this->data($this->show());

		static::assertSame([], $data['audit_disabled']);
		static::assertSame('/system/audit', $data['FORM_ACTION']);
	}
}
