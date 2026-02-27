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


namespace App\Modules\Templates\Controller;

use App\Modules\Templates\Helper\Composer\Orchestrator;
use App\Modules\Templates\Helper\Composer\TemplatePreparer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * Just load the correct type of template to the composer
 */
class ShowComposerController
{
	private Messages $flash;

	public function __construct(private readonly Orchestrator $orchestrator)
	{}


	public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$templateId = (int)($args['template_id'] ?? 0);
		$this->flash = $request->getAttribute('flash');

		if (!$this->orchestrator->checkEditRights($templateId))
		{
			$this->flash = $request->getAttribute('flash');
			$this->flash->addMessage('error', 'No rights');
			return $response->withHeader('Location', '/templates')->withStatus(302);
		}

		$response->getBody()->write(serialize($this->orchestrator->build($templateId)));
		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);

	}
}