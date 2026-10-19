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

namespace Tests\Unit\Modules\Templates\Helper\Composer;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Templates\Helper\Composer\TemplatePreparer;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class TemplatePreparerTest extends TestCase
{
	private TemplatePreparer $preparer;

	protected function setUp(): void
	{
		parent::setUp();
		$translatorStub = $this->createStub(Translator::class);
		$translatorStub->method('translate')
			->willReturnCallback(fn(string $key, string $module): string => $module . '.' . $key);
		$translatorStub->method('translateArrayForOptions')->willReturn(['jpg' => 'JPEG', 'png' => 'PNG']);

		$this->preparer = new TemplatePreparer($translatorStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testReplaceForTemplate(): void
	{
		$result = $this->preparer->replace(12);

		static::assertSame(12, $result['TEMPLATE_ID']);
		static::assertSame('templates.opacity', $result['LANG_OPACITY']);
		static::assertSame([
			'LANG_LOCK' => 'templates.lock',
			'LANG_UNLOCK' => 'templates.unlock',
			'LANG_SELECTABLE' => 'templates.selectable',
			'LANG_UNSELECTABLE' => 'templates.unselectable',
		], $result['is_selectable']);
		static::assertArrayNotHasKey('reset', $result);
		static::assertArrayNotHasKey('save_formats', $result);
		static::assertArrayNotHasKey('is_playlist_item', $result);
		foreach (array_keys($result) as $key)
		{
			static::assertMatchesRegularExpression('/^[A-Za-z_]+$/', $key);
		}
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testReplaceForPlaylistItem(): void
	{
		$result = $this->preparer->replace(12, 34, 56);

		static::assertSame(['LANG_RESET' => 'templates.reset'], $result['reset']);
		static::assertSame([
			'LANG_FORMATS' => 'templates.image_format',
			'LANG_IMAGE_QUALITY' => 'templates.image_quality',
			'formats' => [
				['FORMAT' => 'jpg', 'LANG_FORMAT' => 'JPEG'],
				['FORMAT' => 'png', 'LANG_FORMAT' => 'PNG'],
			]
		], $result['save_formats']);
		static::assertSame(['ITEM_ID' => 34, 'PLAYLIST_ID' => 56], $result['is_playlist_item']);
		static::assertArrayNotHasKey('is_selectable', $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testReplaceWithItemButWithoutPlaylistIsTemplate(): void
	{
		$result = $this->preparer->replace(12, 34);

		static::assertArrayHasKey('is_selectable', $result);
		static::assertArrayNotHasKey('is_playlist_item', $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testPrepare(): void
	{
		$result = $this->preparer->prepare('Tpl', ['TEMPLATE_ID' => 12]);

		static::assertSame('templates.composer: Tpl', $result['main_layout']['LANG_PAGE_TITLE']);
		static::assertSame(['/js/templates/canvas-composer/init.js'], $result['main_layout']['footer_modules']);
		static::assertContains('/js/external/fabric.min.js', $result['main_layout']['footer_scripts']);
		static::assertSame('templates/canvas', $result['this_layout']['template']);
		static::assertSame(
			['TEMPLATE_ID' => 12, 'LANG_PAGE_HEADER' => 'templates.composer: Tpl'],
			$result['this_layout']['data']
		);
	}
}
