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

use App\Framework\Http\Session;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Framework\Utils\Datatable\DatatableFacadeInterface;
use App\Modules\Playlists\Helper\Datatable\DatatableFacade;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Represents a controller responsible for managing the display and functionality
 * of a datatable, integrating a facade and template preparation for generating responses.
 */
readonly class ShowDatatableController
{
	private DatatableFacadeInterface $facade;
	private DatatableTemplatePreparer $templateFormatter;
	private Session $session;

	public function __construct(DatatableFacadeInterface $facade, DatatableTemplatePreparer $templateFormatter, Session $session)
	{
		$this->facade            = $facade;
		$this->templateFormatter = $templateFormatter;
		$this->session           = $session;
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$this->facade->configure($request->getAttribute('translator'), $this->session);
		$this->facade->processSubmittedUserInput($request->getQueryParams());

		$this->facade->prepareDataGrid();
		$dataGrid = $this->facade->prepareUITemplate();

		$templateData = $this->templateFormatter->preparerUITemplate($dataGrid);

		if ($this->facade instanceof DatatableFacade)
			$templateData['this_layout']['data']['create_playlist_contextmenu'] = $this->facade->prepareContextMenu();

		return new PageResponse($templateData);
	}

}