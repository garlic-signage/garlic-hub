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

namespace Tests\Unit\Modules\Playlists\Helper\Settings;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Validation\Sanitizer;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\Settings\Parameters;
use App\Modules\Playlists\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Playlists\Services\AclValidator;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

class SettingsParametersPolicyTest extends TestCase
{
	private Parameters $parameters;
	private AclValidator&Stub $aclValidatorStub;
	private SettingsParametersPolicy $policy;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->parameters       = new Parameters(new Sanitizer(), new MemorySession());
		$this->aclValidatorStub = $this->createStub(AclValidator::class);
		$userSessionStub        = $this->createStub(UserSession::class);
		$userSessionStub->method('getUID')->willReturn(7);

		$this->policy = new SettingsParametersPolicy($this->parameters, $this->aclValidatorStub, $userSessionStub);
	}

	/**
	 * @return array<string, array{string, string, bool, bool}>
	 */
	public static function provideAdminCases(): array
	{
		// mode, edition, resolution, time limit
		return [
			'master core'       => ['master', Config::PLATFORM_EDITION_CORE, true, true],
			'master edge'       => ['master', Config::PLATFORM_EDITION_EDGE, true, false],
			'multizone core'    => ['multizone', Config::PLATFORM_EDITION_CORE, true, false],
			'internal core'     => ['internal', Config::PLATFORM_EDITION_CORE, false, true],
			'external core'     => ['external', Config::PLATFORM_EDITION_CORE, false, false],
		];
	}

	/**
	 * @return list<string> the names of the parameters which were added to the default ones of the form
	 */
	private function addedParameters(): array
	{
		$added = [];
		foreach (['playlist_mode', 'playlist_id', 'screen_width', 'screen_height', 'UID', 'time_limit'] as $name)
		{
			if ($this->parameters->hasParameter($name))
				$added[] = $name;
		}

		return $added;
	}

	/**
	 * @return list<string>
	 */
	private static function expected(bool $resolution, bool $owner, bool $timeLimit, string $idOrMode): array
	{
		return array_values(array_filter([
			$idOrMode,
			$resolution ? 'screen_width' : null,
			$resolution ? 'screen_height' : null,
			$owner ? 'UID' : null,
			$timeLimit ? 'time_limit' : null,
		]));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[DataProvider('provideAdminCases')] #[Group('units')]
	public function testAddCreateFormElementsForSimpleAdmin(string $mode, string $edition, bool $resolution, bool $timeLimit): void
	{
		$this->aclValidatorStub->method('isSimpleAdmin')->willReturnMap([[7, true]]);
		$this->expectEdition($edition);

		$this->policy->addCreateFormElements($mode);

		static::assertEqualsCanonicalizing(self::expected($resolution, true, $timeLimit, 'playlist_mode'), $this->addedParameters());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testAddCreateFormElementsWithoutAdminRights(): void
	{
		$this->aclValidatorStub->method('isSimpleAdmin')->willReturnMap([[7, false]]);

		$this->policy->addCreateFormElements('master');

		static::assertEqualsCanonicalizing(['playlist_mode', 'screen_width', 'screen_height'], $this->addedParameters());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[DataProvider('provideAdminCases')] #[Group('units')]
	public function testAddEditFormElementsForAdmin(string $mode, string $edition, bool $resolution, bool $timeLimit): void
	{
		$playlist = ['UID' => 3, 'company_id' => 1, 'playlist_mode' => $mode];
		$this->aclValidatorStub->method('isAdmin')->willReturnMap([[7, $playlist, true]]);
		$this->expectEdition($edition);

		$this->policy->addEditFormElements($playlist);

		static::assertEqualsCanonicalizing(self::expected($resolution, true, $timeLimit, 'playlist_id'), $this->addedParameters());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testAddEditFormElementsWithoutAdminRights(): void
	{
		$this->aclValidatorStub->method('isAdmin')->willReturn(false);

		$this->policy->addEditFormElements(['UID' => 3, 'company_id' => 1, 'playlist_mode' => 'internal']);

		static::assertSame(['playlist_id'], $this->addedParameters());
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	private function expectEdition(string $edition): void
	{
		$configStub = $this->createStub(Config::class);
		$configStub->method('getEdition')->willReturn($edition);
		$this->aclValidatorStub->method('getConfig')->willReturn($configStub);
	}
}
