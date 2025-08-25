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

namespace App\Framework\Utils\Datatable\Results;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Manages the translation process for provided header fields and language modules.
 */
class TranslatorManager
{
	private readonly Translator $translator;
	/** @var string[] */
	private array $languageModules;

	public function __construct(Translator $translator)
	{
		$this->translator = $translator;
		$this->languageModules = [];
	}

	public function addLanguageModule(string $moduleName): static
	{
		$this->languageModules[] = $moduleName;
		return $this;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function translate(HeaderField $HeaderField): string
	{
		if ($HeaderField->shouldSkipTranslation())
			return '';

		$key  = $HeaderField->getName();

		if ($HeaderField->hasSpecificLangModule())
		{
			return $this->translator->translate($key, $HeaderField->getSpecificLanguageModule());
		}
		else
		{
			foreach($this->languageModules as $module)
			{
				try
				{
					$translated = $this->translator->translate($key, $module);
					if (!empty($translated))
					{
						return $translated;
					}
				}
				catch(FrameworkException $e) { }
			}
		}

		return '';
	}

}