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

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * A ratchet for type assertions on arrays in src/: an inline "@var array{...} $x" (or list<...>) makes PHPStan
 * believe the shape of an array without checking it. Wrong keys or types show up only at runtime. The way out
 * are typed objects; until then the number of these assertions may only go down.
 *
 * Counted is every @var with an array type which documents no property, constant or function: the doc comment
 * of "private array $x" is a declaration, not an assertion.
 */
class ArrayCastsTest extends TestCase
{
	private const string SRC = __DIR__ . '/../../src';

	/**
	 * The assertions which exist today, per file. The test fails for every new one, and it fails when one is
	 * removed until the number here is lowered too, so the list stays true and only gets shorter.
	 *
	 * @var array<string,int>
	 */
	private const array KNOWN = [
		'src/Framework/Config/Config.php'                                            => 1,
		'src/Framework/Config/IniConfigLoader.php'                                   => 1,
		'src/Framework/Controller/RequestInput.php'                                  => 1,
		'src/Framework/OAuth2/ClientsRepository.php'                                 => 2,
		'src/Framework/Utils/Widget/ConfigXML.php'                                   => 2,
		'src/Modules/Account/Controller/ShowPasswordController.php'                  => 2,
		'src/Modules/Auth/OAuth2Controller.php'                                      => 1,
		'src/Modules/Auth/UserSession.php'                                           => 1,
		'src/Modules/Mediapool/Repositories/FilesRepository.php'                     => 1,
		'src/Modules/Mediapool/Repositories/NodesRepository.php'                     => 2,
		'src/Modules/Mediapool/Services/MediaService.php'                            => 2,
		'src/Modules/Mediapool/Services/NodesService.php'                            => 2,
		'src/Modules/Mediapool/Utils/Image.php'                                      => 1,
		'src/Modules/Player/Helper/NetworkSettings/Orchestrator.php'                 => 2,
		'src/Modules/Player/Helper/PlayerPlaylist/Orchestrator.php'                  => 1,
		'src/Modules/Player/IndexCreation/Builder/Preparers/ScreenTimesPreparer.php' => 2,
		'src/Modules/Player/Services/PlayerRestAPIService.php'                       => 1,
		'src/Modules/Player/Services/PlayerService.php'                              => 1,
		'src/Modules/Playlists/Controller/ConditionalPlayController.php'             => 1,
		'src/Modules/Playlists/Controller/ExportController.php'                      => 1,
		'src/Modules/Playlists/Controller/ItemsController.php'                       => 4,
		'src/Modules/Playlists/Controller/ShowComposeController.php'                 => 1,
		'src/Modules/Playlists/Controller/ShowTemplateComposer.php'                  => 1,
		'src/Modules/Playlists/Controller/TriggerController.php'                     => 1,
		'src/Modules/Playlists/Controller/WidgetsController.php'                     => 1,
		'src/Modules/Playlists/Helper/Datatable/Orchestrator.php'                    => 1,
		'src/Modules/Playlists/Helper/Settings/Orchestrator.php'                     => 1,
		'src/Modules/Playlists/Services/ItemsService.php'                            => 1,
		'src/Modules/Playlists/Services/PlaylistsService.php'                        => 5,
		'src/Modules/Templates/Helper/Datatable/Orchestrator.php'                    => 1,
		'src/Modules/Templates/Services/TemplatesService.php'                        => 1,
		'src/Modules/Users/Helper/InitialAdmin/Orchestrator.php'                     => 1,
		'src/Modules/Users/Helper/Settings/Orchestrator.php'                         => 2,
		'src/Modules/Users/Services/UsersAdminService.php'                           => 3,
		'src/Shared/User/UserTokenService.php'                                       => 1,
	];

	/** what may follow a doc comment which declares something instead of asserting a type */
	private const array DECLARATIONS = [T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY, T_STATIC, T_VAR, T_CONST, T_FUNCTION, T_ABSTRACT, T_FINAL];

	public function testThereAreNoNewArrayAssertions(): void
	{
		$new = [];
		foreach ($this->assertions() as $file => $lines)
		{
			if (count($lines) > (self::KNOWN[$file] ?? 0))
				$new[] = $file . ' (lines ' . implode(', ', $lines) . ')';
		}

		static::assertSame([], $new, "More @var assertions on arrays than allowed. Use a typed object or a checked value instead,\n"
			. "or ask before raising the number in ArrayCastsTest::KNOWN:\n" . implode("\n", $new));
	}

	public function testTheKnownNumbersAreLoweredWhenAssertionsAreRemoved(): void
	{
		$assertions = $this->assertions();
		$lower      = [];
		foreach (self::KNOWN as $file => $known)
		{
			$now = count($assertions[$file] ?? []);
			if ($now < $known)
				$lower[] = $file . ': ' . $known . ' -> ' . $now;
		}

		static::assertSame([], $lower, "Removed? Then lower the numbers in ArrayCastsTest::KNOWN:\n" . implode("\n", $lower));
	}

	/**
	 * @return array<string, list<int>> file relative to the project => the lines of the assertions
	 */
	private function assertions(): array
	{
		$root      = (string) realpath(self::SRC . '/..');
		$files     = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SRC, \FilesystemIterator::SKIP_DOTS));
		$found     = [];
		foreach ($files as $file)
		{
			if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php')
				continue;

			$lines = $this->assertionsIn((string) file_get_contents($file->getPathname()));
			if ($lines !== [])
				$found[substr((string) $file->getRealPath(), strlen($root) + 1)] = $lines;
		}

		return $found;
	}

	/**
	 * @return list<int> the lines of the doc comments which assert an array type
	 */
	private function assertionsIn(string $source): array
	{
		$tokens = token_get_all($source);
		$lines  = [];
		foreach ($tokens as $i => $token)
		{
			if (!is_array($token) || $token[0] !== T_DOC_COMMENT || preg_match('/@var\s+(array|list<|non-empty-array|non-empty-list)/', $token[1]) !== 1)
				continue;

			$next = $i + 1;
			while (isset($tokens[$next]) && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT], true))
				$next++;

			if (isset($tokens[$next]) && is_array($tokens[$next]) && in_array($tokens[$next][0], self::DECLARATIONS, true))
				continue;

			$lines[] = $token[2];
		}

		return $lines;
	}
}
