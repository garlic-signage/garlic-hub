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

namespace App\Modules\Account\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Llm\LlmException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Account\Helper\Llm\Orchestrator;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * The forms of the tab of the own AI in the account. Every form leads back to the tab, the models for the
 * selection come as JSON.
 */
readonly class LlmController
{
	private const string TAB = '/account/settings#llm';

	public function __construct(private Orchestrator $orchestrator, private Translator $translator, private Messages $flash, private JsonResponseHandler $responseHandler)
	{}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function storeConnection(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		return $this->withMessages($response, $this->orchestrator->saveConnection($request->getParsedBody()), 'llm_connection_saved');
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function deleteConnection(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$this->orchestrator->deleteConnection($request->getParsedBody());

		return $this->withMessages($response, [], 'llm_connection_deleted');
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function storeTasks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		return $this->withMessages($response, $this->orchestrator->saveTasks($request->getParsedBody()), 'llm_tasks_saved');
	}

	/**
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function models(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try
		{
			$models = $this->orchestrator->findModels($request->getParsedBody());
		}
		catch (LlmException $e)
		{
			return $this->responseHandler->jsonError($response, $this->translator->translateException($e));
		}

		return $this->responseHandler->jsonSuccess($response, ['models' => $models]);
	}

	/**
	 * @param list<string> $errors the translation keys of the errors
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function withMessages(ResponseInterface $response, array $errors, string $success): ResponseInterface
	{
		if ($errors === [])
			$this->flash->addMessage('success', $this->translator->translate($success, 'main'));

		foreach ($errors as $error)
			$this->flash->addMessage('error', $this->translator->translate($error, 'main'));

		return $response->withHeader('Location', self::TAB)->withStatus(302);
	}
}
