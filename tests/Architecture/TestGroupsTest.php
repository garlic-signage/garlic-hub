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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Integration\Support\RepositoryTestCase;

/**
 * PHPUnit does not inherit groups from a parent class: a test class without its group is not found by
 * "phpunit --group db" and nobody notices. This test checks the groups of all integration tests:
 *
 *  - db:   tests of repositories and the database against the real schema (RepositoryTestCase)
 *  - acl:  who sees and may do what, through the real services (ContainerTestCase)
 *  - auth: login, remember me cookie, passwords and tokens (ContainerTestCase)
 */
class TestGroupsTest extends TestCase
{
	private const array GROUPS = ['db', 'acl', 'auth'];

	public function testThereAreIntegrationTests(): void
	{
		static::assertNotEmpty($this->integrationTestClasses());
	}

	public function testEveryIntegrationTestHasAKnownGroup(): void
	{
		foreach ($this->integrationTestClasses() as $class)
		{
			$groups = $this->groupsOf($class);

			static::assertNotSame([], $groups, $class . ' has no #[Group(...)]. PHPUnit does not inherit it from the parent class.');
			static::assertSame([], array_values(array_diff($groups, self::GROUPS)), $class . ' uses a group which is not in ' . implode(', ', self::GROUPS));
		}
	}

	public function testRepositoryTestsAreInTheGroupDb(): void
	{
		foreach ($this->integrationTestClasses() as $class)
		{
			if (is_subclass_of($class, RepositoryTestCase::class))
				static::assertSame(['db'], $this->groupsOf($class), $class . ' extends RepositoryTestCase and belongs to the group db only.');
		}
	}

	public function testTestsWithTheContainerAreNotInTheGroupDb(): void
	{
		foreach ($this->integrationTestClasses() as $class)
		{
			if (!is_subclass_of($class, ContainerTestCase::class))
				continue;

			$groups = $this->groupsOf($class);
			static::assertNotContains('db', $groups, $class . ' uses the container: group acl or auth, not db.');
			static::assertNotSame([], array_intersect($groups, ['acl', 'auth']), $class . ' uses the container and needs the group acl or auth.');
		}
	}

	public function testOtherIntegrationTestsAreInTheGroupDb(): void
	{
		foreach ($this->integrationTestClasses() as $class)
		{
			if (!is_subclass_of($class, RepositoryTestCase::class) && !is_subclass_of($class, ContainerTestCase::class))
				static::assertContains('db', $this->groupsOf($class), $class . ' works on the database and belongs to the group db.');
		}
	}

	public function testTheFileNameIsTheClassName(): void
	{
		foreach ($this->integrationTestFiles() as $file)
		{
			$class = $this->classOf($file);
			static::assertTrue(class_exists($class), 'The file ' . $file . ' does not contain the class ' . $class . ' (namespace or name wrong).');
		}
	}

	/**
	 * @param class-string $class
	 * @return list<string> the groups of the class itself
	 */
	private function groupsOf(string $class): array
	{
		$groups = [];
		foreach ((new ReflectionClass($class))->getAttributes(Group::class) as $attribute)
			$groups[] = $attribute->newInstance()->name();

		sort($groups);

		return $groups;
	}

	/**
	 * @return list<class-string<TestCase>>
	 */
	private function integrationTestClasses(): array
	{
		$classes = [];
		foreach ($this->integrationTestFiles() as $file)
		{
			$class = $this->classOf($file);
			if (class_exists($class) && is_subclass_of($class, TestCase::class) && !(new ReflectionClass($class))->isAbstract())
				$classes[] = $class;
		}

		return $classes;
	}

	/**
	 * @return list<string>
	 */
	private function integrationTestFiles(): array
	{
		$files    = [];
		$root     = (string) realpath(__DIR__ . '/../Integration');
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file)
		{
			if ($file instanceof \SplFileInfo && str_ends_with($file->getFilename(), 'Test.php') && !str_contains($file->getPathname(), '/Support/'))
				$files[] = $file->getPathname();
		}
		sort($files);

		return $files;
	}

	private function classOf(string $file): string
	{
		$relative = substr($file, strlen((string) realpath(__DIR__ . '/..')) + 1, -4);

		return 'Tests\\' . str_replace('/', '\\', $relative);
	}
}
