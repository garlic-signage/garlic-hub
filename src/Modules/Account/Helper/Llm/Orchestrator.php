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
use App\Framework\Localization\Translate\Translator;
use App\Modules\Auth\UserSession;
use App\Shared\Llm\LlmFormBuilder;
use App\Shared\Llm\LlmResolver;
use App\Shared\Llm\UserLlmService;
use Doctrine\DBAL\Exception;

/**
 * The tab of the own LLM in the account. While the company has an LLM, the own one is not used: the tab says so
 * instead of the form.
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
		$UID = $this->userSession->getUID();

		return $this->formBuilder->build($this->userLlmService->findSettings($UID), '/account/llm') + [
			'LANG_EXPLANATION' => $this->translator->translate('llm_explanation', self::MODULE_NAME),
			'company_llm'      => $this->llmResolver->hasCompanyLlm($UID) ? ['LANG_COMPANY_LLM' => $this->translator->translate('llm_of_company', self::MODULE_NAME)] : []
		];
	}

	/**
	 * @return list<string> the translation keys of the errors
	 * @throws Exception
	 * @throws UserException
	 */
	public function save(mixed $post): array
	{
		$input = RequestInput::of($post);

		return $this->userLlmService->save($this->userSession->getUID(), [
			'api_type' => $input->text('api_type'),
			'base_url' => $input->text('base_url'),
			'model'    => $input->text('model'),
			'api_key'  => $input->text('api_key')
		]);
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function delete(): void
	{
		$this->userLlmService->delete($this->userSession->getUID());
	}
}
