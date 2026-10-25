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


namespace App\Modules\Player\Controller;

use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Player\Helper\NetworkSettings\Orchestrator;
use App\Modules\Player\Helper\NetworkSettings\TemplatePreparer;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

class ShowConnectivityController
{
	public function __construct(private readonly Orchestrator $orchestrator,
								private readonly TemplatePreparer $templatePreparer,
								private readonly Messages $flash)
	{}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playerId = (int) ($args['player_id'] ?? 0);
		if ($playerId === 0)
			return $this->redirectWithError($response, 'No player id given.');

		$player = $this->orchestrator->loadPlayer($playerId);
		if ($player === [])
			return $this->redirectWithError($response, 'Player ID not accessible.');

		return $this->outputRenderedForm($player['player_name'], $player);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$input  = RequestInput::of($request->getParsedBody());
		$post   = $input->all();
		$player = $this->orchestrator->loadPlayer($input->id('player_id'));
		if ($player === [])
			return $this->redirectWithError($response, 'Player ID not accessible.');

		$result = $this->orchestrator->store((int) $player['player_id'], $post);
		if (!$result['success'])
		{
			foreach ($result['errors'] ?? [] as $errorText)
			{
				$this->flash->addMessageNow('error', $errorText);
			}

			// the form shows what was understood of the input, not the input
			return $this->outputRenderedForm($player['player_name'], ($result['values'] ?? []) + ['player_id' => $player['player_id']]);
		}

		$this->flash->addMessage('success', 'Player connectivity data successfully stored.');
		return $response->withHeader('Location', '/player')->withStatus(302);
	}

	/**
	 * @param array<string,mixed> $networkData
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function outputRenderedForm(string $playerName, array $networkData): ResponseInterface
	{
		$formData     = $this->orchestrator->buildForm($networkData);
		$templateData = $this->templatePreparer->prepare($playerName, $formData);

		return new PageResponse($templateData);
	}

	private function redirectWithError(ResponseInterface $response, string $message): ResponseInterface
	{
		$this->flash->addMessage('error', $message);
		return $response->withHeader('Location', '/player')->withStatus(302);
	}
}
