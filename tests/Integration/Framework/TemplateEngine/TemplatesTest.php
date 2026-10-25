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
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * The real templates of the application with the engine of the application: a typing error in a template
 * should show up here and not on the one page which uses it.
 */
#[Group('io')]
class TemplatesTest extends TestCase
{
	private const string DIRECTORY = __DIR__ . '/../../../../templates';

	private static function directory(): string
	{
		return (string) realpath(self::DIRECTORY);
	}

	private MustacheAdapter $adapter;

	protected function setUp(): void
	{
		parent::setUp();
		$this->adapter = new MustacheAdapter(new MustacheEngineFactory()->create(self::directory()));
	}

	/**
	 * @return array<string, array{string}> name of the template => name for the engine
	 */
	public static function templateProvider(): array
	{
		$names    = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::directory(), FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file)
		{
			if ($file->getExtension() !== 'mustache')
				continue;

			$name         = substr($file->getPathname(), strlen(self::directory()) + 1, -strlen('.mustache'));
			$names[$name] = [$name];
		}
		ksort($names);

		return $names;
	}

	public function testThereAreTemplates(): void
	{
		static::assertGreaterThan(30, count(self::templateProvider()));
	}

	/**
	 * Every template compiles and renders without data.
	 */
	#[DataProvider('templateProvider')]
	public function testTheTemplateRenders(string $name): void
	{
		static::assertIsString($this->adapter->render($name));
	}

	/**
	 * Partials are loaded when the section which holds them is rendered, so an empty render does not find a
	 * missing partial: the call is looked up in the source.
	 */
	#[DataProvider('templateProvider')]
	public function testEveryPartialExists(string $name): void
	{
		$source = (string) file_get_contents(self::directory() . '/' . $name . '.mustache');
		preg_match_all('/\{\{>\s*([^\s}]+)\s*\}\}/', $source, $matches);

		// a template without partials has nothing to check
		$this->addToAssertionCount(1);
		foreach ($matches[1] as $partial)
		{
			static::assertFileExists(self::directory() . '/generic/' . $partial . '.mustache', "$name calls the partial $partial.");
		}
	}

	public function testThePageIsRenderedIntoTheMainLayout(): void
	{
		$content = $this->adapter->render('home', [
			'LANG_PAGE_HEADER' => 'Dashboard',
			'dashboard'        => [['LANG_DASHBOARD_TITLE' => 'System <load>', 'DASHBOARD_CONTENT' => '<ul><li>1.00</li></ul>']],
		]);

		$html = $this->adapter->render('layouts/main_layout', [
			'APP_NAME'        => 'Garlic Hub',
			'LANG_PAGE_TITLE' => 'Start & Home',
			'CSRF_TOKEN'      => 'abc123',
			'MAIN_CONTENT'    => $content,
		]);

		static::assertStringContainsString('<title>Garlic Hub: Start &amp; Home</title>', $html);
		static::assertStringContainsString('<meta name="csrf-token" content="abc123">', $html);
		static::assertStringContainsString('<h1>Dashboard</h1>', $html);
		// the title of a dashboard is escaped, its content is HTML of the service
		static::assertStringContainsString('<h2>System &lt;load&gt;</h2>', $html);
		static::assertStringContainsString('<ul><li>1.00</li></ul>', $html);
	}
}
