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
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\UnserializableResponse;
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
	 * The images API. The image has to come in the answer: a url of it is not loaded, the server would fetch an
	 * address which the endpoint chose.
	 */
	public function generateImage(string $prompt, string $size, string $negativePrompt = ''): string
	{
		// the images API has no negative prompt; written into the description, Flux and SD would draw just that
		$parameters = ['model' => $this->model, 'prompt' => $prompt, 'size' => $size, 'n' => 1];
		// the gpt-image models always answer with the image and refuse the parameter, the others answer with a url without it
		if (!str_starts_with($this->model, 'gpt-image'))
			$parameters['response_format'] = 'b64_json';

		try
		{
			$encoded = $this->client->images()->create($parameters)->data[0]->b64_json ?? '';
		}
		catch (Throwable $e)
		{
			throw new LlmException('llm_not_reachable', $e instanceof \Exception ? $e : null);
		}

		$image = base64_decode($encoded, true);
		if ($image === false || $image === '')
			throw new LlmException('llm_no_answer');

		return $image;
	}

	/**
	 * Unlike the answer of the model, the failure tells the reason: the user is filling in the form and has to
	 * know what is wrong. Still only the reason, never the answer of the endpoint.
	 */
	public function listModels(): array
	{
		try
		{
			$models = [];
			foreach ($this->client->models()->list()->data as $model)
				$models[] = $model->id;
		}
		catch (Throwable $e)
		{
			throw new LlmException(self::reasonOf($e), $e instanceof \Exception ? $e : null);
		}

		$models = array_values(array_unique($models));
		natcasesort($models);

		return array_values($models);
	}

	/**
	 * The translation key of the reason why the endpoint gives no models.
	 */
	private static function reasonOf(Throwable $e): string
	{
		// an error status with a JSON error or with an answer which is no JSON
		$status = match (true)
		{
			$e instanceof ErrorException         => $e->getStatusCode(),
			$e instanceof UnserializableResponse => $e->response->getStatusCode(),
			default                              => 0
		};

		return LlmFailure::reasonOf($e, $status);
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
