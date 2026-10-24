<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\Localization\Translate;

use App\Framework\Exceptions\CoreException;
use App\Framework\Localization\Translate\TranslationLoaderInterface;

/**
 * In-memory loader for tests: returns the given translations and remembers what was loaded.
 */
class RecordingTranslationLoader implements TranslationLoaderInterface
{
	/** @var list<array{string,string}> language code and module in the order they were loaded */
	public array $loaded = [];

	/**
	 * @param array<string,array<string,array<string,mixed>>> $data language code => module => translations
	 */
	public function __construct(private readonly array $data) {}

	public function load(string $languageCode, string $module): array
	{
		$this->loaded[] = [$languageCode, $module];
		if (!isset($this->data[$languageCode][$module]))
			throw new CoreException("Translation file not found: $languageCode/$module.ini");

		return $this->data[$languageCode][$module];
	}
}
