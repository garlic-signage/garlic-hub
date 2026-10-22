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

namespace Tests\Unit\Modules\Templates\Helper\Settings;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Templates\Helper\Settings\TemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplatePreparerTest extends TestCase
{
	private FormTemplatePreparer&MockObject $formTemplatePreparerMock;
	private TemplatePreparer $preparer;

	/** @var array{main_layout: array<string,mixed>, this_layout: array<string,mixed>} */
	private array $result = ['main_layout' => ['title' => 'x'], 'this_layout' => ['template' => 'y']];

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formTemplatePreparerMock = $this->createMock(FormTemplatePreparer::class);
		$translatorStub                 = $this->createStub(Translator::class);
		$translatorStub->method('translate')
			->willReturnCallback(fn(string $key, string $module): string => $module . '.' . $key);

		$this->preparer = new TemplatePreparer($translatorStub, $this->formTemplatePreparerMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareEditSettings(): void
	{
		$this->expectSections('templates.setting');

		static::assertSame($this->result, $this->preparer->prepareEditSettings(['hidden' => [], 'visible' => []]));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareCreateSettings(): void
	{
		$this->expectSections('templates.add');

		static::assertSame($this->result, $this->preparer->prepareCreateSettings(['hidden' => [], 'visible' => []]));
	}

	private function expectSections(string $title): void
	{
		$this->formTemplatePreparerMock->expects($this->once())->method('prepareUITemplate')
			->with([
				'hidden'            => [],
				'visible'           => [],
				'title'             => $title,
				'additional_css'    => ['/css/templates/settings.css'],
				'footer_modules'    => ['/js/templates/settings/init.js'],
				'template_name'     => 'templates/edit',
				'form_action'       => '/templates/settings',
				'save_button_label' => 'main.save'
			])
			->willReturn($this->result);
	}
}
