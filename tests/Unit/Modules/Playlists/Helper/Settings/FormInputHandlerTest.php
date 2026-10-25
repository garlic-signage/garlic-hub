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
use App\Framework\Security\CsrfToken;
use App\Framework\Validation\Sanitizer;
use App\Modules\Playlists\Helper\Settings\FormInputHandler;
use App\Modules\Playlists\Helper\Settings\Parameters;
use App\Modules\Playlists\Helper\Settings\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The input of the playlist settings form: parsed by the real parameters and checked by the real validator.
 */
class FormInputHandlerTest extends TestCase
{
	private Parameters $parameters;
	private FormInputHandler $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $key === 'playlist_mode_unsupported' ? 'Unsupported: %s' : $module . '.' . $key);
		$csrfToken = $this->createStub(CsrfToken::class);
		$csrfToken->method('validateToken')->willReturnCallback(static fn(string $token): bool => $token === 'good');

		$this->parameters = new Parameters(new Sanitizer(), new MemorySession());
		$this->handler    = new FormInputHandler($this->parameters, new Validator($translator, $this->parameters, $csrfToken));
	}

	#[Group('units')]
	public function testAValidNewPlaylistHasNoErrorsAndIsParsed(): void
	{
		$this->parameters->addPlaylistMode();
		$this->parameters->addResolution();

		$errors = $this->handler->validate(['csrf_token' => 'good', 'playlist_name' => 'Lobby', 'playlist_mode' => 'master', 'screen_width' => '1280', 'screen_height' => '720']);

		static::assertSame([], $errors);
		static::assertSame(
			['csrf_token' => 'good', 'playlist_name' => 'Lobby', 'playlist_mode' => 'master', 'screen_width' => 1280, 'screen_height' => 720],
			$this->handler->getParsed()
		);
	}

	#[Group('units')]
	public function testAValidExistingPlaylistNeedsItsId(): void
	{
		$this->parameters->addPlaylistId();

		static::assertSame([], $this->handler->validate(['csrf_token' => 'good', 'playlist_name' => 'Lobby', 'playlist_id' => '12']));
		static::assertSame(12, $this->handler->getParsed()['playlist_id'] ?? null);
	}

	#[Group('units')]
	public function testAWrongTokenAMissingNameAndNoModeOrIdAreErrors(): void
	{
		$this->parameters->addPlaylistMode();

		$errors = $this->handler->validate(['csrf_token' => 'wrong', 'playlist_name' => '']);

		static::assertSame(['security.csrf_token_mismatch', 'playlists.no_playlist_name', 'playlists.parameters_missing'], $errors);
	}

	#[Group('units')]
	public function testAnUnsupportedModeIsAnError(): void
	{
		$this->parameters->addPlaylistMode();

		$errors = $this->handler->validate(['csrf_token' => 'good', 'playlist_name' => 'Lobby', 'playlist_mode' => 'evil']);

		static::assertSame(['Unsupported: evil'], $errors);
	}

	/**
	 * A mode as array is no mode and no id either, so the form gets both messages and no exception.
	 */
	#[Group('units')]
	public function testAModeOfTheWrongTypeIsAnError(): void
	{
		$this->parameters->addPlaylistMode();

		$errors = $this->handler->validate(['csrf_token' => 'good', 'playlist_name' => 'Lobby', 'playlist_mode' => ['master']]);

		static::assertSame(['playlists.parameters_missing', 'Unsupported: '], $errors);
	}

	#[Group('units')]
	public function testAnIdOfTheWrongTypeIsNoId(): void
	{
		$this->parameters->addPlaylistId();

		$errors = $this->handler->validate(['csrf_token' => 'good', 'playlist_name' => 'Lobby', 'playlist_id' => ['12']]);

		static::assertSame(['playlists.parameters_missing'], $errors);
	}
}
