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

namespace Tests\Unit\Shared\Llm;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Shared\Llm\LlmFormBuilder;
use App\Shared\Llm\LlmTask;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The form of the AI providers and tasks: of the company (with the modes) and of a user (only the tasks the company
 * gives him, the ones of the company locked).
 */
class LlmFormBuilderTest extends TestCase
{
	private const array CONNECTION = ['connection_id' => 4, 'name' => 'Firma', 'api_type' => 'openai', 'base_url' => 'https://llm.example.test/v1', 'has_key' => true];

	private CsrfToken $csrfToken;
	private LlmFormBuilder $builder;

	protected function setUp(): void
	{
		parent::setUp();
		$this->csrfToken = new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession());
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturnCallback(static fn(string $key): array => match ($key)
		{
			'llm_api_type_selects' => ['openai' => 'OpenAI', 'automatic1111' => 'Automatic1111'],
			'llm_mode_selects'     => ['disabled' => 'Aus', 'company_all' => 'Alle'],
			default                => []
		});
		$this->builder = new LlmFormBuilder($translator, $this->csrfToken);
	}

	/**
	 * @return list<array{VALUE:string, LANG_VALUE:string, is_selected:bool}>
	 */
	private static function apiTypes(string $selected): array
	{
		return [
			['VALUE' => 'openai', 'LANG_VALUE' => 'OpenAI', 'is_selected' => $selected === 'openai'],
			['VALUE' => 'automatic1111', 'LANG_VALUE' => 'Automatic1111', 'is_selected' => $selected === 'automatic1111']
		];
	}

	#[Group('units')]
	public function testTheLabelsAndActions(): void
	{
		$form = $this->builder->build('/settings/llm', [], []);

		$labels = [];
		foreach (['CONNECTIONS' => 'llm_connections', 'CONNECTIONS_HINT' => 'llm_connections_hint', 'NEW_CONNECTION' => 'llm_new_connection',
			'NAME' => 'llm_name', 'NAME_HINT' => 'llm_name_hint', 'API_TYPE' => 'llm_api_type', 'BASE_URL' => 'llm_base_url',
			'API_KEY' => 'llm_api_key', 'TEST' => 'llm_test_connection', 'TEST_TITLE' => 'llm_test_title', 'TASKS' => 'llm_tasks',
			'MODE' => 'llm_mode', 'PROVIDER' => 'llm_provider', 'MODEL' => 'llm_model', 'LOAD_MODELS' => 'llm_load_models',
			'MODELS_TITLE' => 'llm_models_title', 'MODELS_FILTER' => 'llm_models_filter', 'MODELS_EMPTY' => 'llm_models_empty',
			'CLOSE' => 'close', 'SAVE' => 'save', 'DELETE' => 'delete', 'CONFIRM_DELETE' => 'llm_confirm_delete_connection'] as $label => $key)
			$labels['LANG_' . $label] = 'main.' . $key;

		static::assertSame([
			'CSRF_TOKEN'        => $this->csrfToken->getToken(),
			'CONNECTION_ACTION' => '/settings/llm/connection',
			'DELETE_ACTION'     => '/settings/llm/connection/delete',
			'MODELS_ACTION'     => '/settings/llm/models',
			'TASKS_ACTION'      => '/settings/llm/tasks',
		] + $labels, array_diff_key($form, array_flip(['connections', 'new_connection', 'no_connections', 'tasks', 'has_tasks'])));
		static::assertSame([], $form['connections']);
		static::assertTrue($form['no_connections']);
		static::assertSame(['CONNECTION_ID' => 0, 'NAME' => '', 'BASE_URL' => '', 'api_types' => self::apiTypes('openai'), 'API_KEY_HINT' => 'main.llm_api_key_optional', 'can_delete' => false], $form['new_connection']);
	}

	#[Group('units')]
	public function testTheCompanyChoosesAModeAndAProviderForEveryTask(): void
	{
		$form = $this->builder->build('/settings/llm', [self::CONNECTION], [LlmTask::TEXT => ['mode' => 'company_all', 'connection_id' => '4', 'model' => 'small']]);

		static::assertSame([['CONNECTION_ID' => 4, 'NAME' => 'Firma', 'BASE_URL' => 'https://llm.example.test/v1', 'api_types' => self::apiTypes('openai'), 'API_KEY_HINT' => 'main.llm_api_key_stored', 'can_delete' => true]], $form['connections']);
		static::assertFalse($form['no_connections']);
		static::assertTrue($form['has_tasks']);
		static::assertSame(LlmTask::TASKS, array_column($form['tasks'], 'TASK'));
		static::assertSame([
			'TASK'           => LlmTask::TEXT,
			'LANG_TASK'      => 'main.llm_task_text',
			'LANG_TASK_HINT' => 'main.llm_task_text_hint',
			'company'        => ['modes' => [['VALUE' => 'disabled', 'LANG_VALUE' => 'Aus', 'is_selected' => false], ['VALUE' => 'company_all', 'LANG_VALUE' => 'Alle', 'is_selected' => true]]],
			'locked'         => [],
			'editable'       => ['providers' => [
				['VALUE' => '0', 'LANG_VALUE' => 'main.llm_choose_provider', 'is_selected' => false],
				['VALUE' => '4', 'LANG_VALUE' => 'Firma', 'is_selected' => true]
			], 'MODEL' => 'small']
		], $form['tasks'][0]);
		static::assertSame([['VALUE' => 'disabled', 'LANG_VALUE' => 'Aus', 'is_selected' => true], ['VALUE' => 'company_all', 'LANG_VALUE' => 'Alle', 'is_selected' => false]], $form['tasks'][1]['company']['modes'] ?? null, 'without a row the task is off');
		static::assertSame('0', $form['tasks'][1]['editable']['providers'][0]['VALUE'] ?? null);
		static::assertTrue($form['tasks'][1]['editable']['providers'][0]['is_selected'] ?? null);
		static::assertSame('', $form['tasks'][1]['editable']['MODEL'] ?? null);
	}

	#[Group('units')]
	public function testAUserSeesOnlyTheTasksOfTheCompanyForHim(): void
	{
		$form = $this->builder->build('/account/llm', [self::CONNECTION], [], [LlmTask::IMAGE_TAGGING], [LlmTask::TEXT]);

		static::assertSame([LlmTask::TEXT, LlmTask::IMAGE_TAGGING], array_column($form['tasks'], 'TASK'));
		static::assertSame([], $form['tasks'][0]['company']);
		static::assertSame([], $form['tasks'][0]['locked']);
		static::assertSame(['VALUE' => '0', 'LANG_VALUE' => 'main.llm_no_provider', 'is_selected' => true], $form['tasks'][0]['editable']['providers'][0] ?? null);
		static::assertSame(['LANG_LOCKED' => 'main.llm_task_of_company'], $form['tasks'][1]['locked']);
		static::assertSame([], $form['tasks'][1]['editable']);

		$none = $this->builder->build('/account/llm', [], [], [], []);
		static::assertSame([], $none['tasks']);
		static::assertFalse($none['has_tasks']);
	}
}
