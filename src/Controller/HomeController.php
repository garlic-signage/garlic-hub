<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Controller;

use App\Framework\Dashboards\DashboardsAggregator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HomeController - Main controller for the Garlic Hub platform start page
 *
 * This class manages the display of the main page (dashboard) and the
 * legal notices of the Digital Signage Management Platform.
 *
 * @author  Nikolaos Sagiadinos <garlic@saghiadinos.de>
 * @since   2024
 */
class HomeController
{
	private DashboardsAggregator $dashboardAggregator;

	public function __construct(DashboardsAggregator $dashboardAggregator)
	{
		$this->dashboardAggregator = $dashboardAggregator;
	}

	public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$data = $this->generateHomePageData();
		$this->writeResponseData($response, $data);

		return $this->setContentType($response);
	}

	public function legals(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$data = [
			'main_layout' => [
				'LANG_PAGE_TITLE' => 'Garlic Hub - Website Legal Notice',
			],
			'this_layout' => [
				'template' => 'legals/legals',
				'data' => []
			]
		];

		$this->writeResponseData($response, $data);
		return $this->setContentType($response);
	}


	/**
	 * @return array<string,mixed>
	 */
	private function generateHomePageData(): array
	{
		return [
			'main_layout' => [
				'LANG_PAGE_TITLE' => 'Garlic Hub - Dashboard',
			],
			'this_layout' => [
				'template' => 'home',
				'data' => [
					'LANG_PAGE_HEADER' => 'Garlic Hub - Dashboard',
					'dashboard' => $this->dashboardAggregator->renderDashboardsContents()
				],
			],
		];
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function writeResponseData(ResponseInterface $response, array $data): void
	{
		$response->getBody()->write(serialize($data));
	}

	private function setContentType(ResponseInterface $response): ResponseInterface
	{
		return $response->withHeader('Content-Type', 'text/html');
	}

}