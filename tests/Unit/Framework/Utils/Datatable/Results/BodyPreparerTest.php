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

namespace Tests\Unit\Framework\Utils\Datatable\Results;

use App\Framework\Utils\Datatable\Results\BodyPreparer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The body preparer gives the elements of a table cell their names for the template. It does not escape,
 * the template does (see tests/Integration/Framework/Utils/DatatableTemplateTest).
 */
class BodyPreparerTest extends TestCase
{
	private BodyPreparer $bodyPreparer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->bodyPreparer = new BodyPreparer();
	}

	/**
	 * @param list<mixed> $arguments
	 * @param array<string,mixed> $expected
	 */
	#[Group('units')]
	#[DataProvider('formatProvider')]
	public function testTheFormats(string $method, array $arguments, array $expected): void
	{
		static::assertSame($expected, $this->bodyPreparer->$method(...$arguments));
	}

	/**
	 * @return array<string, array{string, list<mixed>, array<string,mixed>}> method, arguments, result
	 */
	public static function formatProvider(): array
	{
		$empty = static fn(array $keys): array => array_fill_keys($keys, '');

		return [
			'text'                    => ['formatText', ['Sample Text'], ['CONTROL_ELEMENT_VALUE_TEXT' => 'Sample Text']],
			'text empty'              => ['formatText', [''], ['CONTROL_ELEMENT_VALUE_TEXT' => '']],

			'span'                    => ['formatSpan', ['Name', 'Title', '123', 'test-class'], [
				'CONTROL_ELEMENT_VALUE_NAME' => 'Name', 'CONTROL_ELEMENT_VALUE_TITLE' => 'Title', 'CONTROL_ELEMENT_VALUE_ID' => '123', 'CONTROL_ELEMENT_VALUE_CLASS' => 'test-class',
			]],
			'span without class'      => ['formatSpan', ['Name', 'Title', '123'], [
				'CONTROL_ELEMENT_VALUE_NAME' => 'Name', 'CONTROL_ELEMENT_VALUE_TITLE' => 'Title', 'CONTROL_ELEMENT_VALUE_ID' => '123', 'CONTROL_ELEMENT_VALUE_CLASS' => '',
			]],
			'span empty'              => ['formatSpan', ['', '', ''], $empty(['CONTROL_ELEMENT_VALUE_NAME', 'CONTROL_ELEMENT_VALUE_TITLE', 'CONTROL_ELEMENT_VALUE_ID', 'CONTROL_ELEMENT_VALUE_CLASS'])],

			'link'                    => ['formatLink', ['Name', 'Title', '/edit/5', '5', 'link-class', 'more'], [
				'CONTROL_ELEMENT_VALUE_NAME' => 'Name', 'CONTROL_ELEMENT_VALUE_TITLE' => 'Title', 'CONTROL_ELEMENT_VALUE_LINK' => '/edit/5',
				'CONTROL_ELEMENT_VALUE_ID' => '5', 'CONTROL_ELEMENT_VALUE_CLASS' => 'link-class', 'CONTROL_ELEMENT_ADDITIONAL_TEXT' => 'more',
			]],
			'link without class and text' => ['formatLink', ['Name', 'Title', '/edit/5', '5'], [
				'CONTROL_ELEMENT_VALUE_NAME' => 'Name', 'CONTROL_ELEMENT_VALUE_TITLE' => 'Title', 'CONTROL_ELEMENT_VALUE_LINK' => '/edit/5',
				'CONTROL_ELEMENT_VALUE_ID' => '5', 'CONTROL_ELEMENT_VALUE_CLASS' => '', 'CONTROL_ELEMENT_ADDITIONAL_TEXT' => '',
			]],

			'button'                  => ['formatButton', ['Delete', 'Delete it', 'btn_1', 'danger'], [
				'CONTROL_BUTTON_VALUE' => 'Delete', 'CONTROL_BUTTON_TITLE' => 'Delete it', 'CONTROL_BUTTON_ID' => 'btn_1', 'CONTROL_BUTTON_CLASS' => 'danger',
			]],
			'button without class'    => ['formatButton', ['Delete', 'Delete it', 'btn_1'], [
				'CONTROL_BUTTON_VALUE' => 'Delete', 'CONTROL_BUTTON_TITLE' => 'Delete it', 'CONTROL_BUTTON_ID' => 'btn_1', 'CONTROL_BUTTON_CLASS' => '',
			]],

			'uid'                     => ['formatUID', [7, 'anna'], ['OWNER_UID' => 7, 'OWNER_NAME' => 'anna']],
			'uid zero'                => ['formatUID', [0, ''], ['OWNER_UID' => 0, 'OWNER_NAME' => '']],

			'icon'                    => ['formatIcon', ['bi bi-trash', 'Delete'], ['ICON_CLASS' => 'bi bi-trash', 'ICON_TITLE' => 'Delete']],

			'action'                  => ['formatAction', ['Edit', 'edit', 5, 'bi-pencil'], [
				'LANG_ACTION' => 'Edit', 'ACTION_ID' => 5, 'ACTION_NAME' => 'edit', 'ACTION_ICON_CLASS' => 'bi-pencil',
			]],
			'action with a text id'   => ['formatAction', ['Edit', 'edit', 'uuid-1', 'bi-pencil'], [
				'LANG_ACTION' => 'Edit', 'ACTION_ID' => 'uuid-1', 'ACTION_NAME' => 'edit', 'ACTION_ICON_CLASS' => 'bi-pencil',
			]],

			'action link'             => ['formatActionLink', ['Show', '/show/5', 'show', 5, 'bi-eye'], [
				'LANG_LINK_ACTION' => 'Show', 'LINK_ACTION' => '/show/5', 'LINK_ACTION_ID' => 5, 'LINK_ACTION_NAME' => 'show', 'LINK_ACTION_ICON_CLASS' => 'bi-eye',
			]],

			'action delete'           => ['formatActionDelete', ['Delete', 'Really delete?', '5', 'bi-trash'], [
				'LANG_DELETE_ACTION' => 'Delete', 'DELETE_ID' => '5', 'LANG_CONFIRM_DELETE' => 'Really delete?', 'ELEMENT_DELETE_CLASS' => 'bi-trash',
			]],
		];
	}

	/**
	 * @param list<mixed> $arguments
	 */
	#[Group('units')]
	#[DataProvider('hostileProvider')]
	public function testNothingIsChangedOrEscapedHere(string $method, array $arguments): void
	{
		$result = $this->bodyPreparer->$method(...$arguments);

		foreach ($arguments as $argument)
		{
			static::assertContains($argument, $result, 'The value must arrive as it is, the template escapes it.');
		}
	}

	/**
	 * @return array<string, array{string, list<mixed>}>
	 */
	public static function hostileProvider(): array
	{
		$hostile = '"><script>alert(1)</script> & \'';

		return [
			'text'          => ['formatText', [$hostile]],
			'span'          => ['formatSpan', [$hostile, $hostile . '1', $hostile . '2', $hostile . '3']],
			'link'          => ['formatLink', [$hostile, $hostile . '1', $hostile . '2', $hostile . '3', $hostile . '4', $hostile . '5']],
			'button'        => ['formatButton', [$hostile, $hostile . '1', $hostile . '2', $hostile . '3']],
			'uid'           => ['formatUID', [5, $hostile]],
			'icon'          => ['formatIcon', [$hostile, $hostile . '1']],
			'action'        => ['formatAction', [$hostile, $hostile . '1', $hostile . '2', $hostile . '3']],
			'action link'   => ['formatActionLink', [$hostile, $hostile . '1', $hostile . '2', $hostile . '3', $hostile . '4']],
			'action delete' => ['formatActionDelete', [$hostile, $hostile . '1', $hostile . '2', $hostile . '3']],
		];
	}
}
