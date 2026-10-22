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

namespace App\Shared\Layout;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use App\Framework\TemplateEngine\LayoutDataProviderInterface;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Collects everything the main layout needs besides the page content.
 */
readonly class LayoutDataProvider implements LayoutDataProviderInterface
{
	public function __construct(
		private MainMenuBuilder $mainMenuBuilder,
		private UserMenuBuilder $userMenuBuilder,
		private LanguageSelectBuilder $languageSelectBuilder,
		private FlashMessagesBuilder $flashMessagesBuilder,
		private CsrfToken $csrfToken,
		private Locales $locales,
		private Translator $translator,
		private Config $config
	) {}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function collect(ServerRequestInterface $request): array
	{
		$locale = $this->locales->getLanguageCode();
		$data   = [
			'messages'             => $this->flashMessagesBuilder->build(),
			'main_menu'            => $this->mainMenuBuilder->build(),
			'user_menu'            => $this->userMenuBuilder->build(),
			'language_select'      => $this->languageSelectBuilder->build(),
			'CSRF_TOKEN'           => $this->csrfToken->getToken(),
			'CURRENT_LOCALE_LOWER' => strtolower($locale),
			'CURRENT_LOCALE_UPPER' => strtoupper($locale),
			'APP_NAME'             => $this->config->getEnv('APP_NAME'),
			'LANG_LEGAL_NOTICE'    => $this->translator->translate('legal_notice', 'menu'),
			'LANG_PRIVACY'         => $this->translator->translate('privacy', 'menu'),
			'LANG_TERMS'           => $this->translator->translate('terms', 'menu')
		];

		if (filter_var($this->config->getEnv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN))
			$data = array_merge($data, $this->collectDebugStats($request));

		return $data;
	}

	/**
	 * @return array{EXECUTION_TIME: string, MEMORY_USAGE: string, PEAK_MEMORY_USAGE: string}
	 */
	private function collectDebugStats(ServerRequestInterface $request): array
	{
		$startTime   = (float) $request->getAttribute('start_time', microtime(true));
		$startMemory = (int) $request->getAttribute('start_memory', 0);

		return [
			'EXECUTION_TIME'    => number_format(microtime(true) - $startTime, 6) . 'sec',
			'MEMORY_USAGE'      => round((memory_get_usage() - $startMemory) / 1024, 2) . ' KB',
			'PEAK_MEMORY_USAGE' => round(memory_get_peak_usage() / 1024, 2) . ' KB'
		];
	}
}
