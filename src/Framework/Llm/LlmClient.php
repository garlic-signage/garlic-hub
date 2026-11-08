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
 * A language model behind an API. The features (text correction, ...) know only this interface: the
 * adapter of an API (OpenAiCompatibleClient) can be replaced without them.
 */
interface LlmClient
{
	/**
	 * @return string the answer of the model
	 * @throws LlmException
	 */
	public function complete(LlmRequest $request): string;

	/**
	 * The models the endpoint offers, for the selection in the form of an LLM.
	 *
	 * @return list<string>
	 * @throws LlmException
	 */
	public function listModels(): array;

	/**
	 * An image of the model for the description, e.g. "1536x1024" as size. What should not be in the image goes
	 * only to an API which knows a negative prompt, the others leave it out.
	 *
	 * @return string the image as it is, e.g. a png
	 * @throws LlmException
	 */
	public function generateImage(string $prompt, string $size, string $negativePrompt = ''): string;
}
