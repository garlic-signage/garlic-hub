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

namespace Tests\Unit\Modules\Player\Helper\NetworkSettings;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Player\Helper\NetworkSettings\TemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class TemplatePreparerTest extends TestCase
{
	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testPrepare(): void
	{
		$result         = ['main_layout' => ['title' => 'x'], 'this_layout' => ['template' => 'y']];
		$translatorStub = $this->createStub(Translator::class);
		$translatorStub->method('translate')
			->willReturnCallback(fn(string $key, string $module): string => $module . '.' . $key);

		$formTemplatePreparerMock = $this->createMock(FormTemplatePreparer::class);
		$formTemplatePreparerMock->expects($this->once())->method('prepareUITemplate')
			->with([
				'hidden'            => [],
				'visible'           => [],
				'title'             => 'player.api_connectivity: Lobby',
				'additional_css'    => ['/css/player/edit.css'],
				'footer_modules'    => [],
				'template_name'     => 'player/edit',
				'form_action'       => '/player/connectivity',
				'save_button_label' => 'main.save'
			])
			->willReturn($result);

		$preparer = new TemplatePreparer($translatorStub, $formTemplatePreparerMock);

		static::assertSame($result, $preparer->prepare('Lobby', ['hidden' => [], 'visible' => []]));
	}
}
