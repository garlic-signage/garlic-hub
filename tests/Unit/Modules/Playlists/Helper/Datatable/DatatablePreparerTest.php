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

namespace Tests\Unit\Modules\Playlists\Helper\Datatable;

use App\Framework\Config\Config;
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
use App\Modules\Playlists\Helper\Datatable\DatatablePreparer;
use App\Modules\Playlists\Helper\Datatable\Parameters;
use App\Modules\Playlists\Services\AclValidator;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The rows of the playlist list with the real services of the datatable. The container of the integration tests
 * knows only the edge edition and the owner of a playlist: the rest is tested here.
 */
class DatatablePreparerTest extends TestCase
{
	private AclValidator&Stub $aclValidatorStub;
	private DatatablePreparer $preparer;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $key === 'confirm_delete' ? 'Delete %s?' : $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturn(['master' => 'Master', 'internal' => 'Internal', 'external' => 'External', 'channel' => 'Channel']);
		$session = new MemorySession();
		$urlBuilder = new UrlBuilder();

		$this->aclValidatorStub = $this->createStub(AclValidator::class);
		$this->preparer = new DatatablePreparer(
			new PrepareService(
				new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), $session)),
				new Preparer($urlBuilder),
				new HeaderPreparer(new TranslatorManager($translator), $urlBuilder),
				new BodyPreparer()
			),
			$this->aclValidatorStub,
			new Parameters(new Sanitizer(), $session),
			$translator
		);
	}

	/**
	 * @param list<string> $names
	 * @return list<HeaderField>
	 */
	private function fields(array $names): array
	{
		return array_map(static fn(string $name): HeaderField => new HeaderField()->setName($name), $names);
	}

	/**
	 * @return array{UID: int, company_id: int, playlist_id: int, playlist_name: string, username: string, duration: int, playlist_mode: string}
	 */
	private static function playlist(int $id = 5, int $owner = 2, string $mode = 'master', int $duration = 3725): array
	{
		return ['playlist_id' => $id, 'UID' => $owner, 'username' => 'anna', 'company_id' => 1, 'playlist_name' => 'Lobby', 'playlist_mode' => $mode, 'duration' => $duration];
	}

	private function edition(string $edition): void
	{
		$config = $this->createStub(Config::class);
		$config->method('getEdition')->willReturn($edition);
		$this->aclValidatorStub->method('getConfig')->willReturn($config);
	}

	#[Group('units')]
	public function testWithoutPlaylistsTheBodyIsEmpty(): void
	{
		static::assertSame([], $this->preparer->setUsedPlaylists([])->prepareTableBody([], $this->fields(['playlist_name']), 2));
	}

	#[Group('units')]
	public function testTheCellsOfARow(): void
	{
		$this->aclValidatorStub->method('isSimpleAdmin')->willReturn(false);

		$body = $this->preparer->setUsedPlaylists([])->prepareTableBody([self::playlist()], $this->fields(['playlist_name', 'UID', 'playlist_mode', 'duration', 'playlist_id']), 3);

		static::assertSame(5, $body[0]['UNIT_ID']);
		static::assertSame(
			[
				['CONTROL_NAME_BODY' => 'playlist_name', 'is_link' => ['CONTROL_ELEMENT_VALUE_NAME' => 'Lobby', 'CONTROL_ELEMENT_VALUE_TITLE' => 'playlists.composer', 'CONTROL_ELEMENT_VALUE_LINK' => '/playlists/compose/5', 'CONTROL_ELEMENT_VALUE_ID' => 'playlist_name_5', 'CONTROL_ELEMENT_VALUE_CLASS' => '', 'CONTROL_ELEMENT_ADDITIONAL_TEXT' => '']],
				['CONTROL_NAME_BODY' => 'UID', 'is_UID' => $body[0]['elements_result_element'][1]['is_UID']],
				['CONTROL_NAME_BODY' => 'playlist_mode', 'is_text' => ['CONTROL_ELEMENT_VALUE_TEXT' => 'Master']],
				['CONTROL_NAME_BODY' => 'duration', 'is_text' => ['CONTROL_ELEMENT_VALUE_TEXT' => '01:02:05']],
				['CONTROL_NAME_BODY' => 'playlist_id', 'is_text' => ['CONTROL_ELEMENT_VALUE_TEXT' => '5']],
			],
			$body[0]['elements_result_element']
		);
		static::assertStringContainsString('anna', json_encode($body[0]['elements_result_element'][1]['is_UID'], JSON_THROW_ON_ERROR));
	}

	/**
	 * @return array<string,array{int,string}> the seconds and the time
	 */
	public static function durations(): array
	{
		return ['none' => [0, '00:00:00'], 'a minute' => [60, '00:01:00'], 'an hour and more' => [3725, '01:02:05'], 'exactly a day' => [86400, '24:00:00'], 'more than a day' => [100000, '27:46:40'], 'more than 99 hours' => [360000, '100:00:00'], 'a negative duration' => [-5, '00:00:00']];
	}

	#[DataProvider('durations')]
	#[Group('units')]
	public function testTheDurationIsShownAsTime(int $seconds, string $time): void
	{
		$body = $this->preparer->setUsedPlaylists([])->prepareTableBody([self::playlist(duration: $seconds)], $this->fields(['duration']), 3);

		static::assertSame($time, $body[0]['elements_result_element'][0]['is_text']['CONTROL_ELEMENT_VALUE_TEXT']);
	}

	#[Group('units')]
	public function testTheOwnerGetsTheActionsAndTheDeleteButton(): void
	{
		$this->aclValidatorStub->method('isSimpleAdmin')->willReturn(false);
		$this->aclValidatorStub->method('isAllowedToDeletePlaylist')->willReturn(true);

		$row = $this->preparer->setUsedPlaylists([])->prepareTableBody([self::playlist()], $this->fields(['playlist_name']), 2)[0];

		static::assertSame(
			[
				['LANG_LINK_ACTION' => 'playlists.composer', 'LINK_ACTION' => '/playlists/compose/5', 'LINK_ACTION_ID' => '5', 'LINK_ACTION_NAME' => 'composer', 'LINK_ACTION_ICON_CLASS' => 'pencil'],
				['LANG_LINK_ACTION' => 'playlists.edit_settings', 'LINK_ACTION' => '/playlists/settings/5', 'LINK_ACTION_ID' => '5', 'LINK_ACTION_NAME' => 'edit', 'LINK_ACTION_ICON_CLASS' => 'gear'],
			],
			$row['has_action_link']
		);
		static::assertSame(['LANG_DELETE_ACTION' => 'main.delete', 'DELETE_ID' => '5', 'LANG_CONFIRM_DELETE' => 'Delete Lobby?', 'ELEMENT_DELETE_CLASS' => 'delete-playlist'], $row['has_delete']);
	}

	#[Group('units')]
	public function testAPlaylistWhichIsInUseCanNotBeDeleted(): void
	{
		$this->aclValidatorStub->method('isSimpleAdmin')->willReturn(false);
		$this->aclValidatorStub->method('isAllowedToDeletePlaylist')->willReturn(true);

		$row = $this->preparer->setUsedPlaylists([5 => true])->prepareTableBody([self::playlist()], $this->fields(['playlist_name']), 2)[0];

		static::assertArrayHasKey('has_action_link', $row);
		static::assertArrayNotHasKey('has_delete', $row);
	}

	#[Group('units')]
	public function testWhoMayNotDeleteGetsNoDeleteButton(): void
	{
		$this->aclValidatorStub->method('isSimpleAdmin')->willReturn(false);
		$this->aclValidatorStub->method('isAllowedToDeletePlaylist')->willReturn(false);

		$row = $this->preparer->setUsedPlaylists([])->prepareTableBody([self::playlist()], $this->fields(['playlist_name']), 2)[0];

		static::assertArrayNotHasKey('has_delete', $row);
	}

	#[Group('units')]
	public function testTheAdminGetsTheActionsOfThePlaylistsOfOthers(): void
	{
		$this->aclValidatorStub->method('isSimpleAdmin')->willReturn(true);
		$this->aclValidatorStub->method('isAllowedToDeletePlaylist')->willReturn(true);

		$row = $this->preparer->setUsedPlaylists([])->prepareTableBody([self::playlist(owner: 2)], $this->fields(['playlist_name']), 1)[0];

		static::assertCount(2, $row['has_action_link']);
		static::assertArrayHasKey('has_delete', $row);
	}

	#[Group('units')]
	public function testAUserGetsNoActionsOfThePlaylistsOfOthers(): void
	{
		$this->aclValidatorStub->method('isSimpleAdmin')->willReturn(false);

		$row = $this->preparer->setUsedPlaylists([])->prepareTableBody([self::playlist(owner: 2)], $this->fields(['playlist_name']), 3)[0];

		static::assertArrayNotHasKey('has_action_link', $row);
		static::assertArrayNotHasKey('has_delete', $row);
	}

	#[Group('units')]
	public function testTheCreateMenuOfTheEdgeEditionHasNoExternalAndNoChannelPlaylists(): void
	{
		$this->edition(Config::PLATFORM_EDITION_EDGE);

		static::assertSame(
			[['CREATE_PLAYLIST_MODE' => 'master', 'LANG_CREATE_PLAYLIST_MODE' => 'Master'], ['CREATE_PLAYLIST_MODE' => 'internal', 'LANG_CREATE_PLAYLIST_MODE' => 'Internal']],
			$this->preparer->formatContextMenu()
		);
	}

	#[Group('units')]
	public function testTheCreateMenuOfTheOtherEditionsHasAllModes(): void
	{
		$this->edition(Config::PLATFORM_EDITION_ENTERPRISE);

		static::assertSame(['master', 'internal', 'external', 'channel'], array_column($this->preparer->formatContextMenu(), 'CREATE_PLAYLIST_MODE'));
	}
}
