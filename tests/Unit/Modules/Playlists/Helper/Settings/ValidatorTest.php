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

use App\Framework\Core\CsrfToken;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\Settings\Parameters;
use App\Modules\Playlists\Helper\Settings\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
	private Validator $validator;
	private Translator&MockObject $translatorMock;
	private CsrfToken&MockObject $csrfTokenMock;
	private Parameters&MockObject $parametersMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorMock = $this->createMock(Translator::class);
		$this->parametersMock = $this->createMock(Parameters::class);
		$this->csrfTokenMock  = $this->createMock(CsrfToken::class);

		$this->validator = new Validator($this->translatorMock, $this->parametersMock, $this->csrfTokenMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUserInputWithErrors(): void
	{
		$this->checkCsrfTokenTrue();
		$this->parametersMock->method('getValueOfParameter')
			->with(Parameters::PARAMETER_PLAYLIST_NAME)
			->willReturn(null);

		$this->translatorMock->method('translate')
			->willReturnMap([
				['no_playlist_name', 'playlists', [], 'Playlist name is missing.'],
				['parameters_missing', 'playlists', [], 'Parameter are missing.'],
				['playlist_mode_unsupported', 'playlists', [], 'Unsupported Playlist.']
			]);

		$errors = $this->validator->validateUserInput([Parameters::PARAMETER_PLAYLIST_MODE => 'unsupported']);
		$expectedErrors = [
			'Playlist name is missing.',
			 'Unsupported Playlist.'
		];
		static::assertEquals($expectedErrors, $errors);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUserInputWithErrors2(): void
	{
		$this->checkCsrfTokenTrue();
		$this->parametersMock->method('getValueOfParameter')
			->with(Parameters::PARAMETER_PLAYLIST_NAME)
			->willReturn(null);

		$this->translatorMock->method('translate')
			->willReturnMap([
				['no_playlist_name', 'playlists', [], 'Playlist name is missing.'],
				['parameters_missing', 'playlists', [], 'Parameter are missing.'],
				['playlist_mode_unsupported', 'playlists', [], 'Unsupported Playlist.']
			]);

		$errors = $this->validator->validateUserInput([]);
		$expectedErrors = [
			'Playlist name is missing.',
			'Parameter are missing.'
		];
		static::assertEquals($expectedErrors, $errors);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUserInputPasses(): void
	{
		$this->checkCsrfTokenTrue();
		$this->parametersMock->method('getValueOfParameter')
			->with(Parameters::PARAMETER_PLAYLIST_NAME)
			->willReturn('Playlist name');

		$this->translatorMock->expects($this->never())->method('translate');

		$userInput = [
			'playlist_name' => 'Playlist name',
			Parameters::PARAMETER_PLAYLIST_ID => 12,
			Parameters::PARAMETER_PLAYLIST_MODE => 'multizone'
		];

		$errors = $this->validator->validateUserInput($userInput);
		static::assertEmpty($errors);
	}

	private function checkCsrfTokenTrue(): void
	{
		$this->parametersMock->expects($this->once())->method('getCsrfToken')
			->willReturn('test');
		$this->csrfTokenMock->expects($this->once())->method('validateToken')
			->with('test')
			->willReturn(true);
	}
}
