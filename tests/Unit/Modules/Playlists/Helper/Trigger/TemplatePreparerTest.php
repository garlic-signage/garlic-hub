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


namespace Tests\Unit\Modules\Playlists\Helper\Trigger;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\TemplateEngine\AdapterInterface;
use App\Modules\Playlists\Helper\Trigger\TemplatePreparer;
use App\Modules\Playlists\Helper\Trigger\TriggerPreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplatePreparerTest extends TestCase
{
	private AdapterInterface&MockObject $templateMock;
	private TriggerPreparer&MockObject $triggerPreparerMock;
	private TemplatePreparer $templatePreparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->templateMock        = $this->createMock(AdapterInterface::class);
		$this->triggerPreparerMock = $this->createMock(TriggerPreparer::class);
		$this->templatePreparer    = new TemplatePreparer($this->templateMock, $this->triggerPreparerMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepare(): void
	{
		$itemId = 12;
		$this->triggerPreparerMock->expects($this->once())->method('generate')->with($itemId);
		$this->templatePreparer->prepare($itemId);
	}

	#[Group('units')]
	public function testRender(): void
	{
		$this->triggerPreparerMock->expects($this->once())->method('getTemplateData')
			->willReturn([]);
		$this->templateMock->expects($this->once())->method('render')
			->with('playlists/trigger', []);
		$this->templatePreparer->render();
	}

}
