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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Account\Helper\Llm\Orchestrator;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * The forms of the tab of the own LLM in the account. Every form leads back to the tab.
 */
readonly class LlmController
{
	private const string TAB = '/account/settings#llm';

	public function __construct(private Orchestrator $orchestrator, private Translator $translator, private Messages $flash)
	{}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$errors = $this->orchestrator->save($request->getParsedBody());
		if ($errors === [])
			$this->flash->addMessage('success', $this->translator->translate('llm_saved', 'account'));

		foreach ($errors as $error)
			$this->flash->addMessage('error', $this->translator->translate($error, 'main'));

		return $this->backToTab($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$this->orchestrator->delete();
		$this->flash->addMessage('success', $this->translator->translate('llm_deleted', 'account'));

		return $this->backToTab($response);
	}

	private function backToTab(ResponseInterface $response): ResponseInterface
	{
		return $response->withHeader('Location', self::TAB)->withStatus(302);
	}
}
