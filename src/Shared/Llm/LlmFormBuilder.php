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

namespace App\Shared\Llm;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Llm\LlmConnection;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;

/**
 * The data of the AI forms of the account and of the company (templates/generic/llm_settings.mustache): the
 * providers, each in its own form, and one form with a row per task. A key is never shown, the field tells only
 * whether one is stored. The models of a provider are offered in a dialog (/js/core/LlmModelsDialog.js), the
 * field stays free for an endpoint without a list.
 */
readonly class LlmFormBuilder
{
	public function __construct(private Translator $translator, private CsrfToken $csrfToken)
	{}

	/**
	 * @param list<array{connection_id:int, name:string, api_type:string, base_url:string, has_key:bool}> $connections
	 * @param array<string,array<string,mixed>> $taskRows the stored tasks by task
	 * @param list<string> $lockedTasks the tasks the company provides: the user sees only that
	 * @param list<string>|null $ownTasks the tasks the user sets up, null for a company: it has all of them with a mode
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function build(string $baseAction, array $connections, array $taskRows, array $lockedTasks = [], ?array $ownTasks = null): array
	{
		$isCompany = $ownTasks === null;
		$labels    = [
			'CSRF_TOKEN'            => $this->csrfToken->getToken(),
			'CONNECTION_ACTION'     => $baseAction . '/connection',
			'DELETE_ACTION'         => $baseAction . '/connection/delete',
			'MODELS_ACTION'         => $baseAction . '/models',
			'TASKS_ACTION'          => $baseAction . '/tasks',
			'LANG_CONNECTIONS'      => $this->translate('llm_connections'),
			'LANG_CONNECTIONS_HINT' => $this->translate('llm_connections_hint'),
			'LANG_NEW_CONNECTION'   => $this->translate('llm_new_connection'),
			'LANG_NAME'             => $this->translate('llm_name'),
			'LANG_NAME_HINT'        => $this->translate('llm_name_hint'),
			'LANG_API_TYPE'         => $this->translate('llm_api_type'),
			'LANG_BASE_URL'         => $this->translate('llm_base_url'),
			'LANG_API_KEY'          => $this->translate('llm_api_key'),
			'LANG_TEST'             => $this->translate('llm_test_connection'),
			'LANG_TEST_TITLE'       => $this->translate('llm_test_title'),
			'LANG_TASKS'            => $this->translate('llm_tasks'),
			'LANG_MODE'             => $this->translate('llm_mode'),
			'LANG_PROVIDER'         => $this->translate('llm_provider'),
			'LANG_MODEL'            => $this->translate('llm_model'),
			'LANG_LOAD_MODELS'      => $this->translate('llm_load_models'),
			'LANG_MODELS_TITLE'     => $this->translate('llm_models_title'),
			'LANG_MODELS_FILTER'    => $this->translate('llm_models_filter'),
			'LANG_MODELS_EMPTY'     => $this->translate('llm_models_empty'),
			'LANG_CLOSE'            => $this->translate('close'),
			'LANG_SAVE'             => $this->translate('save'),
			'LANG_DELETE'           => $this->translate('delete'),
			'LANG_CONFIRM_DELETE'   => $this->translate('llm_confirm_delete_connection')
		];

		$items = array_map($this->buildConnection(...), $connections);
		$tasks = [];
		foreach (LlmTask::TASKS as $task)
		{
			if (!$isCompany && !in_array($task, $lockedTasks, true) && !in_array($task, $ownTasks, true))
				continue;

			$tasks[] = $this->buildTask($task, $taskRows[$task] ?? [], $connections, $isCompany, in_array($task, $lockedTasks, true));
		}

		return $labels + [
			'connections'    => $items,
			'new_connection' => $this->buildConnection(['connection_id' => 0, 'name' => '', 'api_type' => LlmConnection::API_OPENAI, 'base_url' => '', 'has_key' => false]),
			'no_connections' => $items === [],
			'tasks'          => $tasks,
			'has_tasks'      => $tasks !== []
		];
	}

	/**
	 * @param array{connection_id:int, name:string, api_type:string, base_url:string, has_key:bool} $connection
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function buildConnection(array $connection): array
	{
		return [
			'CONNECTION_ID' => $connection['connection_id'],
			'NAME'          => $connection['name'],
			'BASE_URL'      => $connection['base_url'],
			'api_types'     => $this->options('llm_api_type_selects', $connection['api_type']),
			'API_KEY_HINT'  => $this->translate($connection['has_key'] ? 'llm_api_key_stored' : 'llm_api_key_optional'),
			'can_delete'    => $connection['connection_id'] > 0
		];
	}

	/**
	 * @param array<string,mixed> $row
	 * @param list<array{connection_id:int, name:string, api_type:string, base_url:string, has_key:bool}> $connections
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function buildTask(string $task, array $row, array $connections, bool $isCompany, bool $isLocked): array
	{
		$selected = is_numeric($row['connection_id'] ?? null) ? (int) $row['connection_id'] : 0;
		$options  = [['VALUE' => '0', 'LANG_VALUE' => $this->translate($isCompany ? 'llm_choose_provider' : 'llm_no_provider'), 'is_selected' => $selected === 0]];
		foreach ($connections as $connection)
			$options[] = ['VALUE' => (string) $connection['connection_id'], 'LANG_VALUE' => $connection['name'], 'is_selected' => $connection['connection_id'] === $selected];

		return [
			'TASK'           => $task,
			'LANG_TASK'      => $this->translate('llm_task_' . $task),
			'LANG_TASK_HINT' => $this->translate('llm_task_' . $task . '_hint'),
			'company'        => $isCompany ? ['modes' => $this->options('llm_mode_selects', (string) ($row['mode'] ?? CompanyLlmService::MODE_DISABLED))] : [],
			'locked'         => $isLocked ? ['LANG_LOCKED' => $this->translate('llm_task_of_company')] : [],
			'editable'       => $isLocked ? [] : ['providers' => $options, 'MODEL' => (string) ($row['model'] ?? '')]
		];
	}

	/**
	 * @return list<array{VALUE:string, LANG_VALUE:string, is_selected:bool}>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function options(string $selects, string $selected): array
	{
		$options = [];
		foreach ($this->translator->translateArrayForOptions($selects, 'main') as $value => $name)
			$options[] = ['VALUE' => (string) $value, 'LANG_VALUE' => $name, 'is_selected' => (string) $value === $selected];

		return $options;
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function translate(string $key): string
	{
		return $this->translator->translate($key, 'main');
	}
}
