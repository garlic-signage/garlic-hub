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

namespace Tests\Unit\Modules\Templates\Helper\Settings;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Templates\Helper\Settings\TemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class TemplatePreparerTest extends TestCase
{
	private TemplatePreparer $preparer;

	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		// the form preparer gives back what it gets: the test sees what the template settings hand over
		$formPreparer = $this->createStub(FormTemplatePreparer::class);
		$formPreparer->method('prepareUITemplate')->willReturnCallback(static fn(array $sections): array => $sections);

		$this->preparer = new TemplatePreparer($translator, $formPreparer);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function common(string $title): array
	{
		return [
			'hidden'            => [],
			'title'             => $title,
			'additional_css'    => ['/css/templates/settings.css'],
			'footer_modules'    => ['/js/templates/settings/init.js'],
			'template_name'     => 'templates/edit',
			'form_action'       => '/templates/settings',
			'save_button_label' => 'main.save',
		];
	}

	#[Group('units')]
	public function testTheSettingsOfATemplate(): void
	{
		static::assertSame($this->common('templates.setting'), $this->preparer->prepareEditSettings(['hidden' => []]));
	}

	#[Group('units')]
	public function testTheFormOfANewTemplate(): void
	{
		static::assertSame($this->common('templates.add'), $this->preparer->prepareCreateSettings(['hidden' => []]));
	}
}
