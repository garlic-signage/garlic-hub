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

namespace Tests\Unit\Modules\Playlists\Helper\Widgets;

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Widget\ConfigXML;
use App\Modules\Playlists\Helper\Widgets\ContentDataPreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ContentDataPreparerTest extends TestCase
{
	private ConfigXML&MockObject $configXmlMock;
	private ContentDataPreparer $contentDataPreparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->configXmlMock = $this->createMock(ConfigXML::class);
		$this->contentDataPreparer = new ContentDataPreparer($this->configXmlMock);
	}

	/**
	 * @throws ModuleException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDeterminePreferencesSuccess(): void
	{
		$configData = '<config></config>';
		$preferences = [
			'preference1' => ['type' => 'text'],
			'preference2' => ['type' => 'integer']
		];

		$this->configXmlMock->method('load')
			->with($configData)
			->willReturnSelf();

		$this->configXmlMock->method('hasEditablePreferences')
			->willReturn(true);

		$this->configXmlMock->method('parseBasic')
			->willReturnSelf();

		$this->configXmlMock->method('parsePreferences')
			->willReturnSelf();

		$this->configXmlMock->method('getPreferences')
			->willReturn($preferences);

		$result = $this->contentDataPreparer->determinePreferences($configData);

		static::assertSame($preferences, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareContentDataSuccess(): void
	{
		$configData = '<config></config>';
		$requestData = ['preference1' => '<value>', 'preference2' => '123'];
		$preferences = [
			'preference1' => ['types' => 'combo'],
			'preference2' => ['types' => 'integer']
		];

		$this->configXmlMock->method('load')
			->with($configData)
			->willReturnSelf();
		$this->configXmlMock->method('hasEditablePreferences')
			->willReturn(true);
		$this->configXmlMock->method('parseBasic')
			->willReturnSelf();
		$this->configXmlMock->method('parsePreferences')
			->willReturnSelf();
		$this->configXmlMock->method('getPreferences')
			->willReturn($preferences);

		$result = $this->contentDataPreparer->prepareContentData($configData, $requestData);

		static::assertSame(['preference1' => '<value>', 'preference2' => 123], $result);
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareContentDataThrowsModuleExceptionForMandatoryField(): void
	{
		$configData = '<config></config>';
		$requestData = [];
		$preferences = [
			'preference1' => ['types' => 'text', 'mandatory' => 'true']
		];

		$this->configXmlMock->method('load')
			->with($configData)
			->willReturnSelf();
		$this->configXmlMock->method('hasEditablePreferences')
			->willReturn(true);
		$this->configXmlMock->method('parseBasic')
			->willReturnSelf();
		$this->configXmlMock->method('parsePreferences')
			->willReturnSelf();
		$this->configXmlMock->method('getPreferences')
			->willReturn($preferences);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('widget_field_mandatory {"field":"preference1"}');

		$this->contentDataPreparer->prepareContentData($configData, $requestData);
	}

	/**
	 * @throws ModuleException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareContentDataWithInitIgnoresMandatoryFields(): void
	{
		$configData = '<config></config>';
		$requestData = [];
		$preferences = [
			'preference1' => ['types' => 'text', 'mandatory' => 'true']
		];

		$this->configXmlMock->method('load')
			->with($configData)
			->willReturnSelf();
		$this->configXmlMock->method('hasEditablePreferences')
			->willReturn(true);
		$this->configXmlMock->method('parseBasic')
			->willReturnSelf();
		$this->configXmlMock->method('parsePreferences')
			->willReturnSelf();
		$this->configXmlMock->method('getPreferences')
			->willReturn($preferences);

		$result = $this->contentDataPreparer->prepareContentData($configData, $requestData, true);

		static::assertSame([], $result);
	}

	/**
	 * @throws ModuleException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareContentDataKeepsOnlyThePreferencesOfTheWidget(): void
	{
		$configData = '<config></config>';
		$this->configXmlMock->method('load')->with($configData)->willReturnSelf();
		$this->configXmlMock->method('hasEditablePreferences')->willReturn(true);
		$this->configXmlMock->method('parseBasic')->willReturnSelf();
		$this->configXmlMock->method('parsePreferences')->willReturnSelf();
		$this->configXmlMock->method('getPreferences')->willReturn([
			'text'    => ['types' => 'text'],
			'count'   => ['types' => 'integer'],
			'missing' => ['types' => 'text']
		]);

		$result = $this->contentDataPreparer->prepareContentData($configData, ['csrf_token' => 'secret', 'item_id' => '1', 'text' => '', 'count' => '0', 'unknown' => 'x']);

		static::assertSame(['text' => '', 'count' => 0], $result);
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDeterminePreferencesThrowsModuleExceptionIfNoEditablePreferences(): void
	{
		$configData = '<config></config>';

		$this->configXmlMock->method('load')
			->with($configData)
			->willReturnSelf();

		$this->configXmlMock->method('hasEditablePreferences')
			->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('widget_not_editable');

		$this->contentDataPreparer->determinePreferences($configData);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDeterminePreferencesThrowsFrameworkExceptionOnParseError(): void
	{
		$configData = '<invalid></config>';

		$this->configXmlMock->method('load')
			->with($configData)
			->willThrowException(new FrameworkException('Invalid config XML.'));

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Invalid config XML.');

		$this->contentDataPreparer->determinePreferences($configData);
	}
}
