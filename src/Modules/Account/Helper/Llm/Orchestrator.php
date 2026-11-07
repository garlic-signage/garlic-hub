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

namespace App\Modules\Account\Helper\Llm;

use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Llm\LlmException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Auth\UserSession;
use App\Shared\Llm\LlmFormBuilder;
use App\Shared\Llm\LlmResolver;
use App\Shared\Llm\UserLlmService;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;

/**
 * The tab of the own AI in the account: the providers of the user and the tasks his company lets him set up
 * himself. The tasks the company provides are shown without a choice, the ones which are off not at all.
 */
readonly class Orchestrator
{
	private const string MODULE_NAME = 'account';

	public function __construct(
		private UserSession $userSession,
		private UserLlmService $userLlmService,
		private LlmResolver $llmResolver,
		private LlmFormBuilder $formBuilder,
		private Translator $translator
	) {}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function buildTab(): array
	{
		$UID    = $this->userSession->getUID();
		$access = $this->llmResolver->findAccess($UID);

		return $this->formBuilder->build(
			'/account/llm',
			$this->userLlmService->findConnections($UID),
			$this->userLlmService->findTasks($UID),
			array_keys($access, LlmResolver::ACCESS_COMPANY, true),
			array_keys($access, LlmResolver::ACCESS_OWN, true)
		) + [
			'LANG_EXPLANATION' => $this->translator->translate('llm_explanation', self::MODULE_NAME),
			'LANG_NO_TASKS'    => $this->translator->translate('llm_no_tasks', self::MODULE_NAME)
		];
	}

	/**
	 * @return list<string> the translation keys of the errors
	 * @throws Exception
	 * @throws UserException
	 */
	public function saveConnection(mixed $post): array
	{
		$input = RequestInput::of($post);

		return $this->userLlmService->saveConnection($this->userSession->getUID(), $input->id('connection_id'), [
			'name'     => $input->text('name'),
			'api_type' => $input->text('api_type'),
			'base_url' => $input->text('base_url'),
			'api_key'  => $input->text('api_key')
		]);
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function deleteConnection(mixed $post): void
	{
		$this->userLlmService->deleteConnection($this->userSession->getUID(), RequestInput::of($post)->id('connection_id'));
	}

	/**
	 * Only the tasks the user sets up himself: a task the company provides or switched off stays as it is.
	 *
	 * @return list<string> the translation keys of the errors
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function saveTasks(mixed $post): array
	{
		$UID   = $this->userSession->getUID();
		$input = RequestInput::of($post);
		$tasks = [];
		foreach (array_keys($this->llmResolver->findAccess($UID), LlmResolver::ACCESS_OWN, true) as $task)
		{
			if ($input->hasId('connection_' . $task))
				$tasks[$task] = ['connection_id' => $input->id('connection_' . $task), 'model' => $input->text('model_' . $task)];
		}

		return $this->userLlmService->saveTasks($UID, $tasks);
	}

	/**
	 * @return list<string>
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 * @throws UserException
	 */
	public function findModels(mixed $post): array
	{
		$input = RequestInput::of($post);

		return $this->userLlmService->findModels($this->userSession->getUID(), [
			'connection_id' => $input->id('connection_id'),
			'api_type'      => $input->text('api_type'),
			'base_url'      => $input->text('base_url'),
			'api_key'       => $input->text('api_key')
		]);
	}
}
