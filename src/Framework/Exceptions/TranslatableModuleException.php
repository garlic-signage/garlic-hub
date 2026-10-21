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

namespace App\Framework\Exceptions;

use Exception;

/**
 * An expected error which is shown to the user.
 *
 * It carries a translation key and replacements instead of a finished text, so the
 * error handler translates it into the language of the user. The exception message
 * stays technical and is meant for the logs only.
 */
class TranslatableModuleException extends ModuleException
{
	/**
	 * @param array<string,string|int> $replacements
	 */
	public function __construct(
		string $moduleName,
		private readonly string $translationKey,
		private readonly array $replacements = [],
		?Exception $previous = null
	)
	{
		$message = $moduleName . ': ' . $translationKey;
		if ($replacements !== [])
			$message .= ' ' . json_encode($replacements);

		parent::__construct($moduleName, $message, 0, $previous);
	}

	public function getTranslationKey(): string
	{
		return $this->translationKey;
	}

	/**
	 * @return array<string,string|int>
	 */
	public function getReplacements(): array
	{
		return $this->replacements;
	}
}
