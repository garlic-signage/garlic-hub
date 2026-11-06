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

namespace App\Framework\Llm;

/**
 * Where a model is reached: the type of the API decides about the adapter, see LlmClientFactory.
 * A local LLM may have no key.
 */
final readonly class LlmConnection
{
	public const string API_OPENAI = 'openai';
	public const array API_TYPES   = [self::API_OPENAI];

	public function __construct(
		public string $apiType,
		public string $baseUrl,
		public string $model,
		public string $apiKey = ''
	) {}
}
