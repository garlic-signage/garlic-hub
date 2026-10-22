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

use App\Framework\Http\Session;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Modules\Player\Helper\Datatable\DatatableFacade;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A controller responsible for displaying and managing datatables within the application.
 * This class handles the preparation of the data grid, processes submitted user input,
 * and formats the UI template for rendering.
 */
readonly class ShowDatatableController
{
	private DatatableFacade $facade;
	private DatatableTemplatePreparer $templateFormatter;
	private Session $session;

	public function __construct(DatatableFacade $facade, DatatableTemplatePreparer $templateFormatter, Session $session)
	{
		$this->facade            = $facade;
		$this->templateFormatter = $templateFormatter;
		$this->session           = $session;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$this->facade->configure($request->getAttribute('translator'), $this->session);
		$this->facade->processSubmittedUserInput($request->getQueryParams());

		$this->facade->prepareDataGrid();
		$dataGrid = $this->facade->prepareUITemplate();

		$templateData = $this->templateFormatter->preparerUITemplate($dataGrid);
		$templateData['this_layout']['data']['player_actions_contextmenu'] = $this->facade->prepareContextMenu();
		return new PageResponse($templateData);
	}

}