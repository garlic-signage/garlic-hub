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

namespace Tests\Integration\Framework\TemplateEngine;

use App\Framework\TemplateEngine\MustacheAdapter;
use App\Framework\TemplateEngine\MustacheEngineFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The engine of the application, built by the factory on a directory with templates: how it escapes, where it
 * finds templates and partials and how it caches.
 */
#[Group('io')]
class MustacheEngineFactoryTest extends TestCase
{
	private string $directory;

	protected function setUp(): void
	{
		parent::setUp();

		$this->directory = sys_get_temp_dir() . '/mustache_test_' . uniqid();
		mkdir($this->directory . '/generic', 0777, true);
		mkdir($this->directory . '/module', 0777, true);
		file_put_contents($this->directory . '/module/page.mustache', 'Page {{title}} {{> box}}');
		file_put_contents($this->directory . '/generic/box.mustache', '[{{content}}]');
		file_put_contents($this->directory . '/value.mustache', '<input value="{{value}}" title=\'{{value}}\'> {{{value}}}');
	}

	protected function tearDown(): void
	{
		$this->remove($this->directory);
		parent::tearDown();
	}

	private function remove(string $path): void
	{
		foreach (glob($path . '/*') ?: [] as $entry)
		{
			is_dir($entry) ? $this->remove($entry) : unlink($entry);
		}
		rmdir($path);
	}

	private function adapter(?string $cacheDir = null): MustacheAdapter
	{
		return new MustacheAdapter(new MustacheEngineFactory()->create($this->directory, $cacheDir));
	}

	public function testTemplatesInSubDirectoriesAreFoundByTheirName(): void
	{
		static::assertSame('Page Hello [x]', $this->adapter()->render('module/page', ['title' => 'Hello', 'content' => 'x']));
	}

	public function testPartialsAreLoadedFromTheGenericDirectory(): void
	{
		static::assertSame('Page  [text]', $this->adapter()->render('module/page', ['content' => 'text']));
	}

	/**
	 * Quotes are escaped too, so a value is safe in a double and in a single quoted attribute.
	 */
	public function testAllHtmlSpecialSignsAreEscaped(): void
	{
		$value = '<b>"Tom" & Jerry\'s</b>';
		$html  = $this->adapter()->render('value', ['value' => $value]);

		$escaped = '&lt;b&gt;&quot;Tom&quot; &amp; Jerry&#039;s&lt;/b&gt;';
		static::assertSame('<input value="' . $escaped . '" title=\'' . $escaped . '\'> ' . $value, $html);
	}

	public function testInvalidUtf8IsSubstitutedInsteadOfLeavingAnEmptyValue(): void
	{
		static::assertStringContainsString("a\u{FFFD}b", $this->adapter()->render('value', ['value' => "a\xB1b"]));
	}

	public function testATrailingSlashOfTheDirectoryDoesNotMatter(): void
	{
		$adapter = new MustacheAdapter(new MustacheEngineFactory()->create($this->directory . '/'));

		static::assertSame('Page Hello []', $adapter->render('module/page', ['title' => 'Hello']));
	}

	/**
	 * Mustache compiles equal sources to the same PHP class and writes the cache only for a class which is not
	 * loaded yet. The tests of the cache therefore use a source of their own.
	 */
	public function testCompiledTemplatesAreCachedInTheCacheDirectory(): void
	{
		$cache = $this->directory . '/cache';
		file_put_contents($this->directory . '/unique.mustache', 'Unique ' . uniqid() . ' {{title}}');

		$this->adapter($cache)->render('unique', ['title' => 'Hello']);

		static::assertNotSame([], glob($cache . '/*') ?: [], 'The compiled template is not cached.');
	}

	public function testWithoutACacheDirectoryNothingIsWritten(): void
	{
		$this->adapter()->render('module/page', ['title' => 'Hello']);
		$this->adapter('')->render('module/page', ['title' => 'Hello']);

		static::assertFalse(is_dir($this->directory . '/cache'));
	}

	/**
	 * The cache key contains a hash of the source: a changed template is rendered new, not from the cache.
	 */
	public function testAChangedTemplateIsNotServedFromTheCache(): void
	{
		$cache = $this->directory . '/cache';
		$id    = uniqid();
		file_put_contents($this->directory . '/changing.mustache', 'Before ' . $id . ' {{title}}');
		static::assertSame('Before ' . $id . ' Hello', $this->adapter($cache)->render('changing', ['title' => 'Hello']));

		file_put_contents($this->directory . '/changing.mustache', 'After ' . $id . ' {{title}}');

		static::assertSame('After ' . $id . ' Hello', $this->adapter($cache)->render('changing', ['title' => 'Hello']));
	}
}
