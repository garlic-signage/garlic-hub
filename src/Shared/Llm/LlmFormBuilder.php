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
 * The data of the form of an LLM (templates/generic/llm_form.mustache), of the account and of the company.
 * The key is never shown, the field tells only whether one is stored.
 */
readonly class LlmFormBuilder
{
	public function __construct(private Translator $translator, private CsrfToken $csrfToken)
	{}

	/**
	 * @param array{api_type:string, base_url:string, model:string, has_key:bool}|null $settings
	 * @param string|null $releasedFor the release of the LLM of a company, null for the one of a user
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function build(?array $settings, string $formAction, ?string $releasedFor = null): array
	{
		$apiType = $settings['api_type'] ?? LlmConnection::API_OPENAI;

		return [
			'FORM_ACTION'   => $formAction,
			'CSRF_TOKEN'    => $this->csrfToken->getToken(),
			'LANG_API_TYPE' => $this->translate('llm_api_type'),
			'api_types'     => $this->options('llm_api_type_selects', $apiType),
			'LANG_BASE_URL' => $this->translate('llm_base_url'),
			'BASE_URL'      => $settings['base_url'] ?? '',
			'LANG_MODEL'    => $this->translate('llm_model'),
			'MODEL'         => $settings['model'] ?? '',
			'LANG_API_KEY'  => $this->translate('llm_api_key'),
			'API_KEY_HINT'  => $this->translate(($settings['has_key'] ?? false) ? 'llm_api_key_stored' : 'llm_api_key_optional'),
			'release'       => $releasedFor === null ? [] : [
				'LANG_RELEASED_FOR' => $this->translate('llm_released_for'),
				'releases'          => $this->options('llm_released_for_selects', $releasedFor)
			],
			'LANG_SAVE'     => $this->translate('save'),
			'can_delete'    => $settings === null ? [] : ['DELETE_ACTION' => $formAction . '/delete', 'LANG_DELETE' => $this->translate('delete')]
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
