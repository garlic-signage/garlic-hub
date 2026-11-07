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
 * The LLM of an owner: an OpenAI compatible endpoint, the model and the key. The key is stored encrypted and
 * never shown again: an empty field keeps it, as long as the address stays the same. A key does not go to
 * another address.
 */
abstract readonly class LlmSettings
{
	private const int MAX_URL_LENGTH   = 255;
	private const int MAX_MODEL_LENGTH = 100;

	public function __construct(protected LlmRepository $repository, private Crypt $crypt, private LlmClientFactory $clientFactory)
	{}

	/**
	 * @return array{api_type:string, base_url:string, model:string, has_key:bool}|null
	 * @throws Exception
	 */
	public function findSettings(int $ownerId): ?array
	{
		$row = $this->repository->findFirstById($ownerId);
		if ($row === [])
			return null;

		return [
			'api_type' => (string) $row['api_type'],
			'base_url' => (string) $row['base_url'],
			'model'    => (string) $row['model'],
			'has_key'  => $row['api_key'] !== ''
		];
	}

	/**
	 * The translation keys of the errors, none when it is stored. The fields are the ones of the owner which are
	 * checked already, e.g. the release of a company.
	 *
	 * @param array{api_type:string, base_url:string, model:string, api_key:string} $input
	 * @param array<string,string> $fields
	 * @return list<string>
	 * @throws Exception
	 */
	public function save(int $ownerId, array $input, array $fields = []): array
	{
		$apiType = trim($input['api_type']);
		$baseUrl = rtrim(trim($input['base_url']), '/');
		$model   = trim($input['model']);
		$apiKey  = trim($input['api_key']);

		$errors = [];
		if (!in_array($apiType, LlmConnection::API_TYPES, true))
			$errors[] = 'llm_unknown_api';
		if (!LlmClientFactory::isValidUrl($baseUrl) || strlen($baseUrl) > self::MAX_URL_LENGTH)
			$errors[] = 'llm_invalid_url';
		if ($model === '' || strlen($model) > self::MAX_MODEL_LENGTH)
			$errors[] = 'llm_invalid_model';
		if ($errors !== [])
			return $errors;

		$fields = ['api_type' => $apiType, 'base_url' => $baseUrl, 'model' => $model] + $fields;
		if ($apiKey !== '')
			$fields['api_key'] = Crypto::encrypt($apiKey, $this->crypt->getEncryptionKey());
		elseif (($this->findSettings($ownerId)['base_url'] ?? '') !== $baseUrl)
			$fields['api_key'] = '';

		$this->repository->store($ownerId, $fields);

		return [];
	}

	/**
	 * The models at the address of the form, for the selection. Without a key in the form the stored one is
	 * used, but like on saving only for the address it was entered for.
	 *
	 * @param array{api_type:string, base_url:string, api_key:string} $input
	 * @return list<string>
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 */
	public function findModels(int $ownerId, array $input): array
	{
		$baseUrl = rtrim(trim($input['base_url']), '/');
		$apiKey  = trim($input['api_key']);
		if ($apiKey === '')
		{
			$stored = $this->findConnection($ownerId);
			if ($stored !== null && $stored->baseUrl === $baseUrl)
				$apiKey = $stored->apiKey;
		}

		$connection = new LlmConnection(trim($input['api_type']), $baseUrl, '', $apiKey);

		return $this->clientFactory->create($connection, LlmClientFactory::MODELS_TIMEOUT)->listModels();
	}

	/**
	 * @throws Exception
	 */
	public function delete(int $ownerId): void
	{
		$this->repository->delete($ownerId);
	}

	/**
	 * @throws CryptoException
	 * @throws Exception
	 */
	public function findConnection(int $ownerId): ?LlmConnection
	{
		$row = $this->repository->findFirstById($ownerId);
		if ($row === [])
			return null;

		$apiKey = (string) $row['api_key'];
		if ($apiKey !== '')
			$apiKey = Crypto::decrypt($apiKey, $this->crypt->getEncryptionKey());

		return new LlmConnection((string) $row['api_type'], (string) $row['base_url'], (string) $row['model'], $apiKey);
	}
}
