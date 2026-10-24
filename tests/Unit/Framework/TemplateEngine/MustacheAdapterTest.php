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

namespace Tests\Unit\Framework\TemplateEngine;

use App\Framework\TemplateEngine\MustacheAdapter;
use Mustache\Engine;
use Mustache\Exception\UnknownTemplateException;
use Mustache\Loader\ArrayLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The adapter renders the template with the given name. The templates are in an array here, the engine with the
 * options of the application and the real templates are tested in tests/Integration/Framework/TemplateEngine.
 */
class MustacheAdapterTest extends TestCase
{
	private MustacheAdapter $adapter;

	protected function setUp(): void
	{
		parent::setUp();

		$this->adapter = new MustacheAdapter(new Engine([
			'loader'          => new ArrayLoader([
				'hello'    => 'Hello, {{name}}!',
				'list'     => '{{#items}}<li>{{title}}</li>{{/items}}{{^items}}nothing{{/items}}',
				'raw'      => '{{value}}|{{{value}}}',
				'nested'   => '{{user.name}} from {{user.city}}',
				'page'     => 'before {{> box}} after',
				'static'   => 'no variables',
			]),
			'partials_loader' => new ArrayLoader(['box' => '[{{content}}]']),
		]));
	}

	#[Group('units')]
	public function testRenderInsertsTheData(): void
	{
		static::assertSame('Hello, World!', $this->adapter->render('hello', ['name' => 'World']));
	}

	#[Group('units')]
	public function testRenderWithoutDataLeavesTheVariablesEmpty(): void
	{
		static::assertSame('Hello, !', $this->adapter->render('hello'));
		static::assertSame('no variables', $this->adapter->render('static'));
	}

	#[Group('units')]
	public function testRenderRepeatsASection(): void
	{
		$html = $this->adapter->render('list', ['items' => [['title' => 'one'], ['title' => 'two']]]);

		static::assertSame('<li>one</li><li>two</li>', $html);
	}

	#[Group('units')]
	public function testRenderShowsTheInvertedSectionForEmptyData(): void
	{
		static::assertSame('nothing', $this->adapter->render('list', ['items' => []]));
		static::assertSame('nothing', $this->adapter->render('list'));
	}

	#[Group('units')]
	public function testRenderReadsNestedData(): void
	{
		static::assertSame('Anna from Berlin', $this->adapter->render('nested', ['user' => ['name' => 'Anna', 'city' => 'Berlin']]));
	}

	#[Group('units')]
	public function testRenderResolvesPartials(): void
	{
		static::assertSame('before [text] after', $this->adapter->render('page', ['content' => 'text']));
	}

	/**
	 * Two braces escape, three do not: the three are for HTML which a service has made safe itself.
	 */
	#[Group('units')]
	public function testRenderEscapesDoubleBracesButNotTripleBraces(): void
	{
		static::assertSame('&lt;b&gt;|<b>', $this->adapter->render('raw', ['value' => '<b>']));
	}

	#[Group('units')]
	public function testAnUnknownTemplateThrows(): void
	{
		$this->expectException(UnknownTemplateException::class);

		$this->adapter->render('does_not_exist');
	}
}
