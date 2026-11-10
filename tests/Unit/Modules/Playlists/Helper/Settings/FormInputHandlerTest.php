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
use App\Framework\Validation\Sanitizer;
use App\Modules\Playlists\Helper\Settings\FormInputHandler;
use App\Modules\Playlists\Helper\Settings\Parameters;
use App\Modules\Playlists\Helper\Settings\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The input of the playlist settings form: parsed by the real parameters and checked by the real validator.
 */
class FormInputHandlerTest extends TestCase
{
	private Parameters $parameters;
	private Validator $validator;
	private FormInputHandler $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $key === 'playlist_mode_unsupported' ? 'Unsupported: %s' : ($key === 'screen_resolution_invalid' ? 'Resolution 1 to %d' : $module . '.' . $key));

		$this->parameters = new Parameters(new Sanitizer(), new MemorySession());
		$this->validator  = new Validator($translator, $this->parameters);
		$this->handler    = new FormInputHandler($this->parameters, $this->validator);
	}

	#[Group('units')]
	public function testAValidNewPlaylistHasNoErrorsAndIsParsed(): void
	{
		$this->parameters->addPlaylistMode();
		$this->parameters->addResolution();

		$errors = $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_mode' => 'master', 'screen_width' => '1280', 'screen_height' => '720']);

		static::assertSame([], $errors);
		static::assertSame(
			['playlist_name' => 'Lobby', 'playlist_mode' => 'master', 'screen_width' => 1280, 'screen_height' => 720],
			$this->handler->getParsed()
		);
	}

	#[Group('units')]
	public function testAValidExistingPlaylistNeedsItsId(): void
	{
		$this->parameters->addPlaylistId();

		static::assertSame([], $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_id' => '12']));
		static::assertSame(12, $this->handler->getParsed()['playlist_id'] ?? null);
	}

	#[Group('units')]
	public function testAMissingNameAndNoModeOrIdAreErrors(): void
	{
		$this->parameters->addPlaylistMode();

		$errors = $this->handler->validate(['playlist_name' => '']);

		static::assertSame(['playlists.no_playlist_name', 'playlists.parameters_missing'], $errors);
	}

	#[Group('units')]
	public function testAnUnsupportedModeIsAnError(): void
	{
		$this->parameters->addPlaylistMode();

		$errors = $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_mode' => 'evil']);

		static::assertSame(['Unsupported: evil'], $errors);
	}

	/**
	 * A mode as array is no mode and no id either, so the form gets both messages and no exception.
	 */
	#[Group('units')]
	public function testAModeOfTheWrongTypeIsAnError(): void
	{
		$this->parameters->addPlaylistMode();

		$errors = $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_mode' => ['master']]);

		static::assertSame(['playlists.parameters_missing', 'Unsupported: '], $errors);
	}

	/**
	 * An id is a positive number or digits as text.
	 */
	#[Group('units')]
	public function testOnlyAPositiveIdIsAnId(): void
	{
		$this->parameters->addPlaylistId();
		$this->handler->validate(['playlist_name' => 'Lobby', 'playlist_id' => '12']);

		static::assertSame([], $this->validator->validateUserInput(['playlist_id' => 1]));
		static::assertSame([], $this->validator->validateUserInput(['playlist_id' => '1']));
		static::assertSame(['playlists.parameters_missing'], $this->validator->validateUserInput(['playlist_id' => 0]));
		static::assertSame(['playlists.parameters_missing'], $this->validator->validateUserInput(['playlist_id' => 'abc']));
	}

	#[Group('units')]
	public function testAnIdOfTheWrongTypeIsNoId(): void
	{
		$this->parameters->addPlaylistId();

		$errors = $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_id' => ['12']]);

		static::assertSame(['playlists.parameters_missing'], $errors);
	}
	/**
	 * A width or height which is no pixel number between 1 and 16384 is an error, not saved as it is.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function invalidPixels(): array
	{
		return [
			'text'           => ['abc'],
			'negative'       => ['-5'],
			'zero'           => ['0'],
			'too large'      => ['16385'],
			'text after'     => ['800px'],
			'text before'    => ['x800'],
			'newline'        => ["800\n"],
			'decimal'        => ['800.5'],
			'array'          => [['800']],
			'zero as int'    => [0],
			'too large int'  => [16385],
		];
	}

	#[Group('units')]
	#[DataProvider('invalidPixels')]
	public function testAnInvalidWidthIsAnError(mixed $width): void
	{
		$this->parameters->addPlaylistMode();
		$this->parameters->addResolution();

		$errors = $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_mode' => 'master', 'screen_width' => $width, 'screen_height' => '720']);

		static::assertSame(['Resolution 1 to 16384'], $errors);
	}

	#[Group('units')]
	#[DataProvider('invalidPixels')]
	public function testAnInvalidHeightIsAnError(mixed $height): void
	{
		$this->parameters->addPlaylistMode();
		$this->parameters->addResolution();

		$errors = $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_mode' => 'master', 'screen_width' => '1280', 'screen_height' => $height]);

		static::assertSame(['Resolution 1 to 16384'], $errors);
	}

	#[Group('units')]
	public function testBothSizesInvalidAreOneError(): void
	{
		$this->parameters->addPlaylistMode();
		$this->parameters->addResolution();

		$errors = $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_mode' => 'master', 'screen_width' => 'abc', 'screen_height' => '-1']);

		static::assertSame(['Resolution 1 to 16384'], $errors);
	}

	#[Group('units')]
	public function testTheBoundsOfTheResolutionAreValid(): void
	{
		$this->parameters->addPlaylistMode();
		$this->parameters->addResolution();

		static::assertSame([], $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_mode' => 'master', 'screen_width' => '1', 'screen_height' => '16384']));
		static::assertSame([], $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_mode' => 'master', 'screen_width' => 16384, 'screen_height' => 1]));
	}

	/**
	 * Without a posted size the resolution of the playlist stays, so the missing size is no error.
	 */
	#[Group('units')]
	public function testAMissingSizeIsNoError(): void
	{
		$this->parameters->addPlaylistId();
		$this->parameters->addResolution();

		static::assertSame([], $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_id' => '1', 'screen_width' => '800']));
		static::assertSame([], $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_id' => '1', 'screen_height' => '600']));
	}

	/**
	 * A playlist mode without resolution ignores a posted size.
	 */
	#[Group('units')]
	public function testASizeIsNotCheckedWithoutResolution(): void
	{
		$this->parameters->addPlaylistMode();

		static::assertSame([], $this->handler->validate(['playlist_name' => 'Lobby', 'playlist_mode' => 'master', 'screen_width' => 'abc']));
	}
}
