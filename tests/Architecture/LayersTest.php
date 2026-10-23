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

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Rules for the structure of src/, see ARCHITECTURE.md:
 *
 *     Modules  ->  Shared  ->  Framework
 *
 * and, between the modules, only the dependencies in MODULE_DEPENDENCIES. The test reads the
 * names that are used in the code (use statements and fully qualified names), not comments or strings.
 */
class LayersTest extends TestCase
{
	/**
	 * What a module may use of another module. Everybody may use Auth (the session of the user).
	 * A new line here is a decision about the structure of the project: ask before adding one.
	 *
	 * @var array<string,list<string>>
	 */
	private const array MODULE_DEPENDENCIES = [
		'Auth'      => [],
		'Mediapool' => ['Auth'],
		'Templates' => ['Auth', 'Mediapool'],
		'Playlists' => ['Auth', 'Mediapool', 'Templates'],
		'Player'    => ['Auth', 'Playlists'],
		'Users'     => ['Auth', 'Mediapool'],
		'Account'   => ['Auth', 'Users'],
	];

	/**
	 * Violations which exist today. The test fails for every new one, and it fails when one of these
	 * is repaired, so the list gets shorter and stays true.
	 *
	 * @var list<string>
	 */
	private const array KNOWN_VIOLATIONS = [
		'src/Framework/Utils/Datatable/AbstractDatatableBuilder.php uses App\Shared\Acl\AbstractAclValidator',
	];

	public function testTheFrameworkKnowsNeitherSharedNorModules(): void
	{
		$violations = $this->violations(static fn(string $layer, ?string $module, string $usedLayer, ?string $usedModule): bool => $layer === 'Framework' && in_array($usedLayer, ['Shared', 'Modules'], true));

		$this->assertOnlyKnown($violations, 'Framework');
	}

	public function testSharedKnowsNoModules(): void
	{
		$violations = $this->violations(static fn(string $layer, ?string $module, string $usedLayer, ?string $usedModule): bool => $layer === 'Shared' && $usedLayer === 'Modules');

		$this->assertOnlyKnown($violations, 'Shared');
	}

	public function testModulesOnlyUseTheModulesTheyAreAllowedTo(): void
	{
		$violations = $this->violations(
			static fn(string $layer, ?string $module, string $usedLayer, ?string $usedModule): bool => $layer === 'Modules'
				&& $usedLayer === 'Modules'
				&& $usedModule !== $module
				&& !in_array($usedModule, self::MODULE_DEPENDENCIES[$module] ?? [], true)
		);

		$this->assertOnlyKnown($violations, 'Modules');
	}

	public function testEveryModuleIsInTheListOfDependencies(): void
	{
		$modules = array_map('basename', glob(__DIR__ . '/../../src/Modules/*', GLOB_ONLYDIR) ?: []);

		foreach ($modules as $module)
			static::assertArrayHasKey($module, self::MODULE_DEPENDENCIES, 'The module ' . $module . ' is not in MODULE_DEPENDENCIES.');
	}

	public function testTheModulesHaveNoCircularDependencies(): void
	{
		foreach (array_keys(self::MODULE_DEPENDENCIES) as $module)
			static::assertNotContains($module, $this->reachableFrom($module), 'The module ' . $module . ' depends on itself through other modules.');
	}

	public function testTheKnownViolationsAreStillThere(): void
	{
		$all = array_merge(
			$this->violations(static fn(string $layer, ?string $module, string $usedLayer, ?string $usedModule): bool => $layer === 'Framework' && in_array($usedLayer, ['Shared', 'Modules'], true)),
			$this->violations(static fn(string $layer, ?string $module, string $usedLayer, ?string $usedModule): bool => $layer === 'Shared' && $usedLayer === 'Modules'),
		);

		foreach (self::KNOWN_VIOLATIONS as $known)
			static::assertContains($known, $all, 'Repaired? Then remove it from KNOWN_VIOLATIONS: ' . $known);
	}

	/**
	 * @param list<string> $violations
	 */
	private function assertOnlyKnown(array $violations, string $layer): void
	{
		$new = array_values(array_diff($violations, self::KNOWN_VIOLATIONS));

		static::assertSame([], $new, "New dependencies against the rules of the layer $layer (ARCHITECTURE.md):\n" . implode("\n", $new));
	}

	/**
	 * @return list<string> the modules a module can reach through the allowed dependencies
	 */
	private function reachableFrom(string $module): array
	{
		$seen  = [];
		$queue = self::MODULE_DEPENDENCIES[$module] ?? [];
		while ($queue !== [])
		{
			$next = array_shift($queue);
			if (in_array($next, $seen, true))
				continue;

			$seen[] = $next;
			$queue  = array_merge($queue, self::MODULE_DEPENDENCIES[$next] ?? []);
		}

		return $seen;
	}

	/**
	 * @param callable(string,?string,string,?string):bool $isViolation layer, module, used layer, used module
	 * @return list<string>
	 */
	private function violations(callable $isViolation): array
	{
		$violations = [];
		$root       = (string) realpath(__DIR__ . '/../../src');
		foreach ($this->phpFiles($root) as $file)
		{
			$own = $this->classify($this->ownNamespace($file));
			if ($own === null)
				continue;

			foreach ($this->usedNames($file) as $name)
			{
				$used = $this->classify($name);
				if ($used !== null && $isViolation($own[0], $own[1], $used[0], $used[1]))
					$violations[] = 'src' . substr($file, strlen($root)) . ' uses ' . $name;
			}
		}
		sort($violations);

		return array_values(array_unique($violations));
	}

	/**
	 * @return array{string,?string}|null layer and module of a name of the application
	 */
	private function classify(string $name): ?array
	{
		$parts = explode('\\', ltrim($name, '\\'));
		if ($parts[0] !== 'App' || count($parts) < 2)
			return null;

		if ($parts[1] === 'Modules' && isset($parts[2]))
			return ['Modules', $parts[2]];

		return [$parts[1], null];
	}

	private function ownNamespace(string $file): string
	{
		return preg_match('/^namespace\s+([^;]+);/m', (string) file_get_contents($file), $m) === 1 ? $m[1] : '';
	}

	/**
	 * Names of the application which the code uses: use statements and fully qualified names.
	 *
	 * @return list<string>
	 */
	private function usedNames(string $file): array
	{
		$names     = [];
		$tokens    = token_get_all((string) file_get_contents($file));
		$previous  = null;
		foreach ($tokens as $token)
		{
			if (!is_array($token) || in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))
				continue;

			if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && $previous !== T_NAMESPACE)
			{
				$name = ltrim($token[1], '\\');
				if (str_starts_with($name, 'App\\'))
					$names[] = $name;
			}
			$previous = $token[0];
		}

		return array_values(array_unique($names));
	}

	/**
	 * @return list<string>
	 */
	private function phpFiles(string $directory): array
	{
		$files    = [];
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file)
		{
			if ($file instanceof \SplFileInfo && $file->getExtension() === 'php')
				$files[] = $file->getPathname();
		}
		sort($files);

		return $files;
	}
}
