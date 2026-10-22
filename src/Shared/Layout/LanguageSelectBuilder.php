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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\Translator;

/**
 * Language switch with all available locales which have a translated name.
 */
readonly class LanguageSelectBuilder
{
	public function __construct(private Translator $translator, private Locales $locales)
	{
	}

	/**
	 * @return list<array{LOCALE_LONG: string, LOCALE_SMALL: string, LANGUAGE_NAME: mixed}>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function build(): array
	{
		$languages = $this->translator->translateArrayForOptions('languages', 'menu');
		$select    = [];
		foreach ($this->locales->getAvailableLocales() as $localeCode)
		{
			if (!isset($languages[$localeCode]))
				continue;

			$select[] = [
				'LOCALE_LONG'   => $localeCode,
				'LOCALE_SMALL'  => substr($localeCode, 0, 2),
				'LANGUAGE_NAME' => $languages[$localeCode]
			];
		}

		return $select;
	}
}
