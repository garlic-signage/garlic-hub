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
use App\Framework\Llm\LlmConnection;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use App\Modules\Auth\UserSession;
use App\Shared\Llm\UserLlmService;
use Doctrine\DBAL\Exception;

/**
 * The tab of the own LLM in the account: the endpoint, the model and the key. The key is never shown.
 */
readonly class Orchestrator
{
	private const string MODULE_NAME = 'account';

	public function __construct(
		private UserSession $userSession,
		private UserLlmService $userLlmService,
		private Translator $translator,
		private CsrfToken $csrfToken
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
		$settings = $this->userLlmService->findSettings($this->userSession->getUID());
		$apiType  = $settings['api_type'] ?? LlmConnection::API_OPENAI;

		$apiTypes = [];
		foreach ($this->translator->translateArrayForOptions('llm_api_type_selects', self::MODULE_NAME) as $key => $name)
			$apiTypes[] = ['API_TYPE' => $key, 'API_TYPE_NAME' => $name, 'is_selected' => $key === $apiType];

		return [
			'CSRF_TOKEN'       => $this->csrfToken->getToken(),
			'LANG_EXPLANATION' => $this->translate('llm_explanation'),
			'LANG_API_TYPE'    => $this->translate('llm_api_type'),
			'api_types'        => $apiTypes,
			'LANG_BASE_URL'    => $this->translate('llm_base_url'),
			'BASE_URL'         => $settings['base_url'] ?? '',
			'LANG_MODEL'       => $this->translate('llm_model'),
			'MODEL'            => $settings['model'] ?? '',
			'LANG_API_KEY'     => $this->translate('llm_api_key'),
			'API_KEY_HINT'     => $this->translate(($settings['has_key'] ?? false) ? 'llm_api_key_stored' : 'llm_api_key_optional'),
			'LANG_SAVE'        => $this->translator->translate('save', 'main'),
			'can_delete'       => $settings === null ? [] : ['LANG_DELETE' => $this->translator->translate('delete', 'main')]
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

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function translate(string $key): string
	{
		return $this->translator->translate($key, self::MODULE_NAME);
	}
}
