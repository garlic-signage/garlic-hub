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

namespace Tests\Unit\Modules\Playlists\Helper\Settings;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Validation\Sanitizer;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\Settings\FormElementsCreator;
use App\Modules\Playlists\Helper\Settings\Parameters;
use App\Modules\Playlists\Helper\Settings\SettingsFormBuilder;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\HtmlFields;
use Tests\Support\MemorySession;

/**
 * The form of the playlist settings with the real parameters, creator and FormBuilder: the result is the html of the fields.
 */
class SettingsFormBuilderTest extends TestCase
{
	private Parameters $parameters;
	private SettingsFormBuilder $builder;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$userSession = $this->createStub(UserSession::class);
		$userSession->method('getUID')->willReturn(7);
		$userSession->method('getUsername')->willReturn('heidi');
		$session = new MemorySession();

		$formBuilder     = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), $session));
		$this->parameters = new Parameters(new Sanitizer(), $session);
		$this->builder    = new SettingsFormBuilder($this->parameters, $userSession, new FormElementsCreator($formBuilder, $translator));
	}

	/**
	 * @param array<string,mixed> $values
	 * @return array<string,array<string,string>> the attributes of the fields by name
	 */
	private function build(array $values): array
	{
		return HtmlFields::fromPrepared($this->builder->buildForm($values));
	}

	#[Group('units')]
	public function testTheFormOfASimpleUserHasTheNameTheModeAndTheToken(): void
	{
		$this->parameters->addPlaylistMode();

		$fields = $this->build(['playlist_mode' => 'internal']);

		static::assertSame(['playlist_name', 'playlist_mode', 'csrf_token'], array_keys($fields));
		static::assertSame('', $fields['playlist_name']['value']);
		static::assertSame('internal', $fields['playlist_mode']['value']);
	}

	#[Group('units')]
	public function testAnAdminWhoCreatesAPlaylistIsTheOwnerWithTheResolutionOfAFullHd(): void
	{
		$this->parameters->addPlaylistMode();
		$this->parameters->addResolution();
		$this->parameters->addOwner();

		$fields = $this->build(['playlist_mode' => 'master']);

		static::assertSame(['playlist_name', 'UID_search', 'screen_width', 'screen_height', 'playlist_mode', 'csrf_token'], array_keys($fields));
		static::assertSame(['1920', '1080'], [$fields['screen_width']['value'], $fields['screen_height']['value']]);
		static::assertStringContainsString('heidi', json_encode($this->builder->buildForm(['playlist_mode' => 'master']), JSON_THROW_ON_ERROR));
	}

	#[Group('units')]
	public function testTheFormOfAnExistingPlaylistHasItsValues(): void
	{
		$this->parameters->addPlaylistId();
		$this->parameters->addResolution();
		$this->parameters->addOwner();
		$this->parameters->addTimeLimit();

		$fields = $this->build([
			'UID' => 3, 'username' => 'owner', 'playlist_id' => 456, 'playlist_name' => 'Lobby', 'playlist_mode' => 'multizone',
			'screen_width' => 1080, 'screen_height' => 1920, 'time_limit' => 600,
		]);

		static::assertSame(['playlist_name', 'UID_search', 'screen_width', 'screen_height', 'time_limit', 'playlist_id', 'csrf_token'], array_keys($fields));
		static::assertSame(['Lobby', '1080', '1920', '600', '456'], [$fields['playlist_name']['value'], $fields['screen_width']['value'], $fields['screen_height']['value'], $fields['time_limit']['value'], $fields['playlist_id']['value']]);
		static::assertStringContainsString('owner', json_encode($this->builder->buildForm(['UID' => 3, 'username' => 'owner', 'playlist_mode' => 'multizone']), JSON_THROW_ON_ERROR));
	}

	/**
	 * The values come from the request of the user: only values of the right kind are shown.
	 */
	#[Group('units')]
	public function testValuesOfTheWrongTypeAreNotShown(): void
	{
		$this->parameters->addPlaylistId();
		$this->parameters->addResolution();
		$this->parameters->addTimeLimit();

		$fields = $this->build([
			'playlist_id' => ['1'], 'playlist_name' => ['Lobby'], 'playlist_mode' => 'master',
			'screen_width' => 'wide', 'screen_height' => ['1080'], 'time_limit' => 'long',
		]);

		static::assertSame('', $fields['playlist_name']['value']);
		static::assertSame(['1920', '1080'], [$fields['screen_width']['value'], $fields['screen_height']['value']]);
		static::assertSame('0', $fields['playlist_id']['value']);
		static::assertArrayHasKey('time_limit', $fields);
	}
}
