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

namespace Tests\Unit\Modules\Playlists\Helper\Settings;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Modules\Playlists\Helper\Settings\FormElementsCreator;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\HtmlFields;
use Tests\Support\MemorySession;

/**
 * The creator and the FormBuilder are the real ones: the result is the html of the field.
 */
class FormElementsCreatorTest extends TestCase
{
	private FormBuilder $formBuilder;
	private FormElementsCreator $creator;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$csrfToken = new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession());

		$this->formBuilder = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), $csrfToken);
		$this->creator     = new FormElementsCreator($this->formBuilder, $translator);
	}

	/**
	 * @return array<string,string> the attributes of the input of the field
	 */
	private function attributesOf(\App\Framework\Utils\Html\FieldInterface $field): array
	{
		$attributes = HtmlFields::attributesOf($this->formBuilder, ['field' => $field]);
		static::assertCount(1, $attributes);

		return array_values($attributes)[0];
	}

	#[Group('units')]
	public function testThePlaylistNameIsRequiredAndHasAMinimalLength(): void
	{
		$field = $this->creator->createPlaylistNameField('Lobby');

		static::assertSame(FieldType::TEXT, $field->getType());
		static::assertSame(
			['type' => 'text', 'name' => 'playlist_name', 'id' => 'playlist_name', 'value' => 'Lobby', 'title' => 'playlists.playlist_name', 'label' => 'playlists.playlist_name', 'required' => 'required', 'minlength' => '2', 'aria-describedby' => 'error_playlist_name'],
			$this->attributesOf($field)
		);
	}

	#[Group('units')]
	public function testAValueWithQuotesCanNotLeaveTheAttribute(): void
	{
		$attributes = $this->attributesOf($this->creator->createPlaylistNameField('x" onfocus="alert(1)'));

		static::assertSame('x" onfocus="alert(1)', $attributes['value']);
		static::assertArrayNotHasKey('onfocus', $attributes);
	}

	#[Group('units')]
	public function testTheOwnerIsAnAutocompleteWithTheNameOfTheUser(): void
	{
		$field = $this->creator->createUIDField(3, 'bernd', 7);

		static::assertSame(FieldType::AUTOCOMPLETE, $field->getType());
		static::assertSame('UID', $field->getName());
		static::assertSame('main.owner', $field->getLabel());
		$html = $this->formBuilder->prepareForm(['UID' => $field]);
		$all  = json_encode($html, JSON_THROW_ON_ERROR);
		static::assertStringContainsString('bernd', $all);
		static::assertMatchesRegularExpression('/value=.{0,2}3\b/', $all);
	}

	#[Group('units')]
	public function testTheResolutionAndTheTimeLimitAreNumbersFromZero(): void
	{
		foreach ([
			'screen_width'  => $this->creator->createScreenWidthField(1920),
			'screen_height' => $this->creator->createScreenHeightField(1080),
			'time_limit'    => $this->creator->createTimeLimitField(600, 0),
		] as $name => $field)
		{
			static::assertSame(FieldType::NUMBER, $field->getType(), $name);
			$attributes = $this->attributesOf($field);
			static::assertSame(['number', $name, '0'], [$attributes['type'], $attributes['name'], $attributes['min']], $name);
		}
		static::assertSame('1920', $this->attributesOf($this->creator->createScreenWidthField(1920))['value']);
		static::assertSame('600', $this->attributesOf($this->creator->createTimeLimitField(600, 0))['value']);
		static::assertSame('playlists.time_limit_explanation', $this->attributesOf($this->creator->createTimeLimitField(600, 0))['title']);
	}

	#[Group('units')]
	public function testThePlaylistIdAndTheModeAreHiddenFields(): void
	{
		$id   = $this->creator->createHiddenPlaylistIdField(456);
		$mode = $this->creator->createPlaylistModeField('multizone');

		static::assertSame(FieldType::HIDDEN, $id->getType());
		static::assertSame(FieldType::HIDDEN, $mode->getType());
		static::assertSame(['hidden', 'playlist_id', '456'], array_values(array_intersect_key($this->attributesOf($id), array_flip(['type', 'name', 'value']))));
		static::assertSame(['hidden', 'playlist_mode', 'multizone'], array_values(array_intersect_key($this->attributesOf($mode), array_flip(['type', 'name', 'value']))));
	}

	#[Group('units')]
	public function testTheTokenFieldHasTheTokenOfTheSession(): void
	{
		$field = $this->creator->createCSRFTokenField();

		static::assertSame(FieldType::CSRF, $field->getType());
		$attributes = $this->attributesOf($field);
		static::assertSame('csrf_token', $attributes['name']);
		static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $attributes['value']);
	}

	#[Group('units')]
	public function testTheFormIsSplitInHiddenAndVisibleFields(): void
	{
		$form = $this->creator->prepareForm(['playlist_id' => $this->creator->createHiddenPlaylistIdField(5), 'playlist_name' => $this->creator->createPlaylistNameField('Lobby')]);

		static::assertCount(1, $form['hidden']);
		static::assertCount(1, $form['visible']);
		static::assertSame('playlists.playlist_name', $form['visible'][0]['LANG_ELEMENT_LABEL']);
	}
}
