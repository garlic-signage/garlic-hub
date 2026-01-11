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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Modules\Player\Helper\Datatable\ControllerFacade;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * A controller responsible for displaying and managing datatables within the application.
 * This class handles the preparation of the data grid, processes submitted user input,
 * and formats the UI template for rendering.
 */
readonly class ShowDatatableController
{
	private ControllerFacade $facade;
	private DatatableTemplatePreparer $templateFormatter;
	public function __construct(ControllerFacade $facade, DatatableTemplatePreparer $templateFormatter)
	{
		$this->facade            = $facade;
		$this->templateFormatter = $templateFormatter;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$this->facade->configure($request->getAttribute('translator'), $request->getAttribute('session'));
		$this->facade->processSubmittedUserInput();

		$this->facade->prepareDataGrid();
		$dataGrid = $this->facade->prepareUITemplate();

		$templateData = $this->templateFormatter->preparerUITemplate($dataGrid);
		$templateData['this_layout']['data']['player_settings_contextmenu'] = $this->facade->preparePlayerSettingsContextMenu();
		$response->getBody()->write(serialize($templateData));

		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
	}

}