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

namespace Tests\Unit\Modules\System\Helper\Audit;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Datatable\Paginator\Preparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\BodyPreparer;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\Datatable\Results\HeaderPreparer;
use App\Framework\Utils\Datatable\Results\TranslatorManager;
use App\Framework\Utils\Datatable\UrlBuilder;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Validation\Sanitizer;
use App\Modules\System\Helper\Audit\DatatablePreparer;
use App\Modules\System\Helper\Audit\Parameters;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The rows of the audit log with the real services of the datatable. The translator gives module.key back.
 * The names from the database are tested in tests/Integration/Modules/System/AuditListTest.
 */
class DatatablePreparerTest extends TestCase
{
	private const array COLUMNS = ['created_at', 'UID', 'action', 'module', 'target_UID', 'details'];

	private DatatablePreparer $preparer;

	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$session    = new MemorySession();
		$urlBuilder = new UrlBuilder();

		$this->preparer = new DatatablePreparer(
			new PrepareService(
				new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), $session)),
				new Preparer($urlBuilder),
				new HeaderPreparer(new TranslatorManager($translator), $urlBuilder),
				new BodyPreparer()
			),
			new Parameters(new Sanitizer(), $session),
			$translator
		);
	}

	/**
	 * @param array<string,mixed> $entry
	 * @return array<string,string> the texts by column
	 */
	private function row(array $entry): array
	{
		$entry  = array_merge(['id' => 7, 'created_at' => '2026-11-10 12:00:00', 'UID' => 1, 'username' => 'admin', 'module' => 'playlists',
			'action' => 'role_granted', 'target_UID' => null, 'target_username' => null, 'unit_id' => null, 'unit_name' => null, 'details' => null], $entry);
		$fields = array_map(static fn(string $name): HeaderField => new HeaderField()->setName($name), [...self::COLUMNS, 'no_column']);

		$body = $this->preparer->prepareTableBody([$entry], $fields, 1);

		static::assertCount(1, $body);
		static::assertSame($entry['id'], $body[0]['UNIT_ID']);
		$texts = [];
		foreach ($body[0]['elements_result_element'] as $element)
			$texts[$element['CONTROL_NAME_BODY']] = $element['is_text']['CONTROL_ELEMENT_VALUE_TEXT'];

		return $texts;
	}

	#[Group('units')]
	public function testAnEntryWithAllColumns(): void
	{
		static::assertSame([
			'created_at' => '2026-11-10 12:00:00',
			'UID'        => 'admin',
			'action'     => 'system.action_role_granted',
			'module'     => 'playlists',
			'target_UID' => 'bernd, system.unit: Lobby',
			'details'    => 'system.detail_role: users.role_editor; system.detail_count: 3; system.detail_changes: a = 1, b = 1; system.detail_ids: 4, 5',
			'no_column'  => ''
		], $this->row(['target_UID' => 3, 'target_username' => 'bernd', 'unit_id' => '5', 'unit_name' => 'Lobby',
			'details' => '{"role":"editor","count":3,"changes":{"a":1,"b":true},"ids":[4,5]}']));
	}

	#[Group('units')]
	public function testDeletedUsersAndUnitsAreTheirIds(): void
	{
		$row = $this->row(['username' => null, 'target_UID' => 9, 'unit_id' => '5']);

		static::assertSame('system.unknown', $row['UID']);
		static::assertSame('#9, system.unit: #5', $row['target_UID']);
	}

	#[Group('units')]
	public function testAFailedLoginShowsTheUserItWasTriedFor(): void
	{
		$row = $this->row(['action' => 'login_failed', 'username' => null, 'target_UID' => 2, 'target_username' => 'anna', 'details' => '{"username":"anna","ip":"1.2.3.4"}']);

		static::assertSame('anna', $row['UID']);
		static::assertSame('system.detail_ip: 1.2.3.4', $row['details']);
	}

	#[Group('units')]
	public function testAFailedLoginWithoutUserShowsWhatWasTypedIn(): void
	{
		$row = $this->row(['action' => 'login_failed', 'username' => null, 'details' => '{"username":"mallory"}']);

		static::assertSame('„mallory" (system.unknown)', $row['UID']);
		static::assertSame('', $row['target_UID']);
		static::assertSame('', $row['details']);
	}

	#[Group('units')]
	public function testDetailsWhichAreNoObjectAreNoDetails(): void
	{
		static::assertSame('', $this->row(['details' => '"text"'])['details']);
		static::assertSame('', $this->row(['details' => '{broken'])['details']);
		static::assertSame('system.detail_list: ', $this->row(['details' => '{"list":[{"deep":1}]}'])['details']);
	}
}
