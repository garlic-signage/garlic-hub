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

use OpenAI\Client;
use Throwable;

/**
 * The OpenAI chat completions API, which the local servers (Ollama, LM Studio, llama.cpp, vLLM) and most
 * providers (OpenAI, Gemini, Claude) offer, too. The only class which knows the library openai-php/client.
 */
readonly class OpenAiCompatibleClient implements LlmClient
{
	public function __construct(private Client $client, private string $model)
	{}

	public function complete(LlmRequest $request): string
	{
		$parameters = [
			'model'    => $this->model,
			'messages' => $this->buildMessages($request)
		];
		if ($request->json)
			$parameters['response_format'] = ['type' => 'json_object'];

		try
		{
			$content = $this->client->chat()->create($parameters)->choices[0]->message->content ?? null;
		}
		catch (Throwable $e)
		{
			throw new LlmException('llm_not_reachable', $e instanceof \Exception ? $e : null);
		}

		if ($content === null || trim($content) === '')
			throw new LlmException('llm_no_answer');

		return $content;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function buildMessages(LlmRequest $request): array
	{
		$messages = [];
		if ($request->system !== '')
			$messages[] = ['role' => 'system', 'content' => $request->system];

		foreach ($request->messages as $message)
		{
			if ($message->images === [])
			{
				$messages[] = ['role' => $message->role, 'content' => $message->text];
				continue;
			}

			$content = [['type' => 'text', 'text' => $message->text]];
			foreach ($message->images as $image)
				$content[] = ['type' => 'image_url', 'image_url' => ['url' => $image]];

			$messages[] = ['role' => $message->role, 'content' => $content];
		}

		return $messages;
	}
}
