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

namespace Tests\Unit\Modules\Profile\Helper\Password;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Helper\Password\TemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplatePreparerTest extends TestCase
{
	private FormTemplatePreparer&MockObject $formTemplatePreparerMock;
	private TemplatePreparer $preparer;

	/** @var array<string,array<string,string>> */
	private array $result = ['main_layout' => ['title' => 'x'], 'this_layout' => ['template' => 'y']];

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translatorStub = $this->createStub(Translator::class);
		$translatorStub->method('translate')
			->willReturnCallback(fn(string $key, string $module): string => $module . '.' . $key . ' %s');
		$this->formTemplatePreparerMock = $this->createMock(FormTemplatePreparer::class);

		$this->preparer = new TemplatePreparer($translatorStub, $this->formTemplatePreparerMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPreparePasswordForm(): void
	{
		$this->expectPrepared('profile.edit_password %s', '/profile/password');

		static::assertSame($this->result, $this->preparer->preparePasswordForm(['hidden' => [], 'visible' => []]));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareForcedPasswordFormForInitialPassword(): void
	{
		$this->expectPrepared('profile.initial_password_for heidi', '/force-password');

		$this->preparer->prepareForcedPasswordForm(
			['hidden' => [], 'visible' => []],
			['username' => 'heidi', 'purpose' => TokenPurposes::INITIAL_PASSWORD->value]
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareForcedPasswordFormForPasswordReset(): void
	{
		$this->expectPrepared('profile.edit_password_for heidi', '/force-password');

		$this->preparer->prepareForcedPasswordForm(
			['hidden' => [], 'visible' => []],
			['username' => 'heidi', 'purpose' => TokenPurposes::PASSWORD_RESET->value]
		);
	}

	private function expectPrepared(string $title, string $formAction): void
	{
		$this->formTemplatePreparerMock->expects($this->once())->method('prepareUITemplate')
			->with([
				'hidden'            => [],
				'visible'           => [],
				'title'             => $title,
				'form_action'       => $formAction,
				'explanations'      => 'profile.password_explanation %s',
				'additional_css'    => ['/css/profile/password.css'],
				'footer_modules'    => ['/js/profile/password/init.js'],
				'template_name'     => 'profile/edit',
				'save_button_label' => 'main.save %s'
			])
			->willReturn($this->result);
	}
}
