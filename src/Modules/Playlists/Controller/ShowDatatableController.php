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

namespace App\Modules\Playlists\Controller;

use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Modules\Playlists\Helper\Datatable\Orchestrator;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Represents a controller responsible for managing the display and functionality
 * of a datatable, integrating a facade and template preparation for generating responses.
 */
readonly class ShowDatatableController
{
	public function __construct(
		private Orchestrator              $orchestrator,
		private DatatableTemplatePreparer $templateFormatter
	) {}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$this->orchestrator->configure();
		$this->orchestrator->processSubmittedUserInput($request->getQueryParams());

		$this->orchestrator->prepareDataGrid();
		$dataGrid = $this->orchestrator->prepareUITemplate();

		$templateData = $this->templateFormatter->preparerUITemplate($dataGrid);
		$templateData['this_layout']['data']['create_playlist_contextmenu'] = $this->orchestrator->prepareContextMenu();
		return new PageResponse($templateData);
	}
}