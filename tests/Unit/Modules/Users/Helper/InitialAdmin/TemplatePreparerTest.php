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

namespace Tests\Unit\Modules\Users\Helper\InitialAdmin;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Users\Helper\InitialAdmin\TemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class TemplatePreparerTest extends TestCase
{
	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
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
				'title'             => 'users.create_admin',
				'additional_css'    => ['/css/users/edit.css', '/css/profile/password.css'],
				'footer_modules'    => ['/js/profile/password/init.js'],
				'template_name'     => 'users/edit',
				'form_action'       => '/create-initial',
				'save_button_label' => 'main.save'
			])
			->willReturn($result);

		$preparer = new TemplatePreparer($translatorStub, $formTemplatePreparerMock);

		static::assertSame($result, $preparer->prepare(['hidden' => [], 'visible' => []]));
	}
}
