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

namespace App\Shared\Llm;

use App\Framework\Llm\LlmClientFactory;
use App\Framework\Llm\LlmConnection;
use App\Framework\Llm\LlmException;
use App\Framework\Security\Crypt;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;

/**
 * The AI providers of an owner and the model of a provider for each task. A provider is an OpenAI compatible
 * endpoint with its key. The key is stored encrypted and never shown again: an empty field keeps it, as long as
 * the address stays the same. A key does not go to another address.
 */
abstract readonly class LlmSettings
{
	private const int MAX_NAME_LENGTH  = 100;
	private const int MAX_URL_LENGTH   = 255;
	private const int MAX_MODEL_LENGTH = 100;

	public function __construct(
		protected LlmConnectionRepository $connectionRepository,
		protected LlmTaskRepository $taskRepository,
		private Crypt $crypt,
		private LlmClientFactory $clientFactory
	) {}

	/**
	 * @return list<array{connection_id:int, name:string, api_type:string, base_url:string, has_key:bool}>
	 * @throws Exception
	 */
	public function findConnections(int $ownerId): array
	{
		$connections = [];
		foreach ($this->connectionRepository->findAllOfOwner($ownerId) as $row)
		{
			// @infection-ignore-all the casts are for databases which give text, ours gives numbers
			$connections[] = [
				'connection_id' => (int) $row['connection_id'],
				'name'          => (string) $row['name'],
				'api_type'      => (string) $row['api_type'],
				'base_url'      => (string) $row['base_url'],
				'has_key'       => $row['api_key'] !== ''
			];
		}

		return $connections;
	}

	/**
	 * Stores a provider, a new one without $connectionId. Without a name it is called like its host.
	 *
	 * @param array{name:string, api_type:string, base_url:string, api_key:string} $input
	 * @return list<string> the translation keys of the errors, none when it is stored
	 * @throws Exception
	 */
	public function saveConnection(int $ownerId, int $connectionId, array $input): array
	{
		$name    = trim($input['name']);
		$apiType = trim($input['api_type']);
		$baseUrl = rtrim(trim($input['base_url']), '/');
		$apiKey  = trim($input['api_key']);
		// @infection-ignore-all no provider has the id 0, asking for it gives none as well
		$stored  = $connectionId === 0 ? [] : $this->connectionRepository->findOfOwner($ownerId, $connectionId);

		$errors = [];
		if ($connectionId !== 0 && $stored === [])
			return ['llm_unknown_connection'];
		if (!in_array($apiType, LlmConnection::API_TYPES, true))
			$errors[] = 'llm_unknown_api';
		if (!LlmClientFactory::isValidUrl($baseUrl) || strlen($baseUrl) > self::MAX_URL_LENGTH)
			$errors[] = 'llm_invalid_url';
		if (mb_strlen($name) > self::MAX_NAME_LENGTH)
			$errors[] = 'llm_invalid_name';
		if ($errors !== [])
			return $errors;

		// @infection-ignore-all a valid address has a host
		$fields = ['name' => $name === '' ? (string) parse_url($baseUrl, PHP_URL_HOST) : $name, 'api_type' => $apiType, 'base_url' => $baseUrl];
		if ($apiKey !== '')
			$fields['api_key'] = Crypto::encrypt($apiKey, $this->crypt->getEncryptionKey());
		elseif (($stored['base_url'] ?? '') !== $baseUrl)
			$fields['api_key'] = '';

		$this->connectionRepository->store($ownerId, $connectionId, $fields);

		return [];
	}

	/**
	 * The tasks with this provider lose it.
	 *
	 * @throws Exception
	 */
	public function deleteConnection(int $ownerId, int $connectionId): void
	{
		// @infection-ignore-all without the provider no task of the owner has it
		if ($this->connectionRepository->deleteOfOwner($ownerId, $connectionId) > 0)
			$this->releaseTasks($ownerId, $connectionId);
	}

	/**
	 * The models of a provider, for the selection of a task and for the test of a provider. Without an address the
	 * stored provider is asked. With an address, from the form of a provider, the stored key is used when the field
	 * is empty, but like on saving only for the address it was entered for.
	 *
	 * @param array{connection_id:int, api_type:string, base_url:string, api_key:string} $input
	 * @return list<string>
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 */
	public function findModels(int $ownerId, array $input): array
	{
		// @infection-ignore-all no provider has the id 0, asking for it gives none as well
		$stored  = $input['connection_id'] === 0 ? null : $this->findConnection($ownerId, $input['connection_id']);
		$baseUrl = rtrim(trim($input['base_url']), '/');
		if ($baseUrl === '')
		{
			if ($stored === null)
				throw new LlmException('llm_unknown_connection');

			$connection = $stored;
		}
		else
		{
			$apiKey = trim($input['api_key']);
			if ($apiKey === '' && $stored !== null && $stored->baseUrl === $baseUrl)
				$apiKey = $stored->apiKey;

			$connection = new LlmConnection(trim($input['api_type']), $baseUrl, '', $apiKey);
		}

		return $this->clientFactory->create($connection, LlmClientFactory::MODELS_TIMEOUT)->listModels();
	}

	/**
	 * @return array<string,array<string,mixed>> the rows by task
	 * @throws Exception
	 */
	public function findTasks(int $ownerId): array
	{
		return $this->taskRepository->findAllOfOwner($ownerId);
	}

	/**
	 * The provider of the task with its model, null when the task has none.
	 *
	 * @throws CryptoException
	 * @throws Exception
	 */
	public function findConnectionOfTask(int $ownerId, string $task): ?LlmConnection
	{
		// the same rules as for showing the helper: a provider which serves the task, a model where one is needed
		if (!$this->hasModelForTask($ownerId, $task))
			return null;

		$row = $this->taskRepository->findTask($ownerId, $task);

		return $this->findConnection($ownerId, (int) $row['connection_id'], (string) ($row['model'] ?? ''));
	}

	/**
	 * @throws Exception
	 */
	public function hasModelForTask(int $ownerId, string $task): bool
	{
		$row = $this->taskRepository->findTask($ownerId, $task);
		if (!is_numeric($row['connection_id'] ?? null))
			return false;

		$connection = $this->connectionRepository->findOfOwner($ownerId, (int) $row['connection_id']);
		if ($connection === [])
			return false;

		// @infection-ignore-all the casts are for databases which give text, ours gives numbers
		$apiType = (string) $connection['api_type'];

		return self::servesTask($apiType, $task) && (($row['model'] ?? '') !== '' || self::mayOmitModel($apiType));
	}

	/**
	 * Automatic1111 works with the model which the program has loaded: a task may leave it out.
	 */
	private static function mayOmitModel(string $apiType): bool
	{
		return $apiType === LlmConnection::API_AUTOMATIC1111;
	}

	/**
	 * A provider of Automatic1111 makes images only: a task of texts on it is off, also when the type of the
	 * provider was changed after the task was set.
	 */
	private static function servesTask(string $apiType, string $task): bool
	{
		return $apiType !== LlmConnection::API_AUTOMATIC1111 || $task === LlmTask::IMAGE_GENERATION;
	}

	/**
	 * The tasks of a deleted provider: the database does not take care of it, SQLite checks no foreign keys.
	 *
	 * @throws Exception
	 */
	abstract protected function releaseTasks(int $ownerId, int $connectionId): void;

	/**
	 * The provider and the model of a task from a form: no provider means the task has none.
	 *
	 * @return list<string> the translation keys of the errors
	 * @throws Exception
	 */
	protected function validateModel(int $ownerId, int $connectionId, string $model, string $task): array
	{
		if ($connectionId === 0)
			return [];
		$connection = $this->connectionRepository->findOfOwner($ownerId, $connectionId);
		if ($connection === [])
			return ['llm_unknown_connection'];
		// @infection-ignore-all the casts are for databases which give text, ours gives numbers
		$apiType = (string) $connection['api_type'];
		if (($model === '' && !self::mayOmitModel($apiType)) || strlen($model) > self::MAX_MODEL_LENGTH)
			return ['llm_invalid_model'];
		if (!self::servesTask($apiType, $task))
			return ['llm_api_images_only'];

		return [];
	}

	/**
	 * @throws CryptoException
	 * @throws Exception
	 */
	private function findConnection(int $ownerId, int $connectionId, string $model = ''): ?LlmConnection
	{
		$row = $this->connectionRepository->findOfOwner($ownerId, $connectionId);
		if ($row === [])
			return null;

		// @infection-ignore-all the casts are for databases which give text, ours gives numbers
		$apiKey = (string) $row['api_key'];
		if ($apiKey !== '')
			$apiKey = Crypto::decrypt($apiKey, $this->crypt->getEncryptionKey());

		return new LlmConnection((string) $row['api_type'], (string) $row['base_url'], $model, $apiKey);
	}
}
