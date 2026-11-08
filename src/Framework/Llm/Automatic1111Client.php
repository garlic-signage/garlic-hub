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

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * The web API of Automatic1111 for images (/sdapi/v1), which Draw Things, Stable Diffusion WebUI Forge and SD.Next
 * offer, too. It makes images only: a provider of this type serves the task of the images.
 *
 * A key "user:password" is sent as basic authentication, like the API of Automatic1111 expects it (--api-auth).
 */
readonly class Automatic1111Client implements LlmClient
{
	public function __construct(
		private ClientInterface $httpClient,
		private string $baseUrl,
		private string $model,
		private string $apiKey = ''
	) {}

	public function complete(LlmRequest $request): string
	{
		throw new LlmException('llm_api_images_only');
	}

	/**
	 * The checkpoints of the server, Draw Things may not offer the list: then the user enters the file name.
	 */
	public function listModels(): array
	{
		try
		{
			$response = $this->send('GET', '/sdapi/v1/sd-models');
		}
		catch (Throwable $e)
		{
			throw new LlmException(LlmFailure::reasonOf($e), $e instanceof \Exception ? $e : null);
		}
		if ($response->getStatusCode() !== 200)
			throw new LlmException(LlmFailure::reasonOf(new LlmException('llm_no_models'), $response->getStatusCode()));

		$models = [];
		$list   = json_decode((string) $response->getBody(), true);
		foreach (is_array($list) ? $list : [] as $model)
		{
			$name = is_array($model) ? ($model['model_name'] ?? $model['title'] ?? null) : null;
			if (is_string($name) && $name !== '')
				$models[] = $name;
		}
		if ($models === [])
			throw new LlmException('llm_no_models');

		$models = array_values(array_unique($models));
		natcasesort($models);

		return array_values($models);
	}

	public function generateImage(string $prompt, string $size, string $negativePrompt = ''): string
	{
		[$width, $height] = array_map(intval(...), explode('x', $size) + [1 => '0']);
		$parameters = ['prompt' => $prompt, 'width' => $width, 'height' => $height, 'batch_size' => 1, 'n_iter' => 1];
		if ($negativePrompt !== '')
			$parameters['negative_prompt'] = $negativePrompt;
		if ($this->model !== '')
		{
			// Automatic1111 changes the checkpoint with the override, Draw Things reads the model of its configuration
			$parameters['override_settings'] = ['sd_model_checkpoint' => $this->model];
			$parameters['model']             = $this->model;
		}

		try
		{
			$response = $this->send('POST', '/sdapi/v1/txt2img', $parameters);
		}
		catch (Throwable $e)
		{
			throw new LlmException('llm_not_reachable', $e instanceof \Exception ? $e : null);
		}
		if ($response->getStatusCode() !== 200)
			throw new LlmException('llm_not_reachable');

		$answer = json_decode((string) $response->getBody(), true);
		$image  = base64_decode((string) (is_array($answer) && is_array($answer['images'] ?? null) && is_string($answer['images'][0] ?? null) ? $answer['images'][0] : ''), true);
		if ($image === false || $image === '')
			throw new LlmException('llm_no_answer');

		return $image;
	}

	/**
	 * @param array<string,mixed>|null $body
	 * @throws \Psr\Http\Client\ClientExceptionInterface
	 */
	private function send(string $method, string $path, ?array $body = null): ResponseInterface
	{
		$headers = ['Accept' => 'application/json'];
		if ($body !== null)
			$headers['Content-Type'] = 'application/json';
		if ($this->apiKey !== '')
			$headers['Authorization'] = 'Basic ' . base64_encode($this->apiKey);

		$request = new Request($method, rtrim($this->baseUrl, '/') . $path, $headers, $body === null ? null : (string) json_encode($body));

		return $this->httpClient->sendRequest($request);
	}
}
