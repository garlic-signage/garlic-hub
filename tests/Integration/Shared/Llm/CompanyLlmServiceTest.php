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

namespace Tests\Integration\Shared\Llm;

use App\Shared\Llm\CompanyLlmService;
use App\Shared\Llm\LlmTask;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The decisions of the company per task: one wrong row does not stop the others.
 */
#[Group('auth')]
class CompanyLlmServiceTest extends ContainerTestCase
{
	private CompanyLlmService $service;
	private int $connectionId;

	protected function setUp(): void
	{
		parent::setUp();
		$this->service = $this->container->get(CompanyLlmService::class);
		static::assertSame([], $this->service->saveConnection(1, 0, ['name' => 'Firma', 'api_type' => 'openai', 'base_url' => 'https://llm.example.test/v1', 'api_key' => '']));
		$this->connectionId = (int) $this->connection->fetchOne('SELECT MAX(connection_id) FROM company_llm_connections');
	}

	public function testTheTasksAfterAWrongOneAreStoredAndEveryErrorIsReportedOnce(): void
	{
		$errors = $this->service->saveTasks(1, [
			'chat'                    => ['mode' => CompanyLlmService::MODE_USER_OWN, 'connection_id' => 0, 'model' => ''],
			'other'                   => ['mode' => CompanyLlmService::MODE_USER_OWN, 'connection_id' => 0, 'model' => ''],
			LlmTask::TEXT             => ['mode' => CompanyLlmService::MODE_COMPANY_ALL, 'connection_id' => 0, 'model' => ''],
			LlmTask::IMAGE_GENERATION => ['mode' => CompanyLlmService::MODE_COMPANY_ADMINS, 'connection_id' => $this->connectionId, 'model' => ' small '],
		]);

		static::assertSame(['llm_invalid_mode', 'llm_missing_connection'], $errors);
		static::assertSame([CompanyLlmService::MODE_COMPANY_ADMINS, 'small'], $this->connection->fetchNumeric("SELECT mode, model FROM company_llm_tasks WHERE company_id = 1 AND task = 'image_generation'"));
	}
}
