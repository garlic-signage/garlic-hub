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

namespace App\Framework\Middleware;

use App\Framework\TemplateEngine\AdapterInterface;
use App\Framework\TemplateEngine\LayoutDataProviderInterface;
use App\Framework\TemplateEngine\PageResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Last step for HTML pages: renders the content of a PageResponse into the main layout.
 * Every other response (redirects, JSON ...) passes unchanged.
 */
readonly class FinalRenderMiddleware implements MiddlewareInterface
{
	private const string MAIN_LAYOUT = 'layouts/main_layout';

	public function __construct(
		private AdapterInterface $templateEngine,
		private LayoutDataProviderInterface $layoutDataProvider,
		private StreamFactory $streamFactory
	) {}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$response = $handler->handle($request);
		if (!$response instanceof PageResponse)
			return $response;

		$mainContent = $this->templateEngine->render($response->getTemplate(), $response->getTemplateData());
		$layoutData  = array_merge(
			$this->layoutDataProvider->collect($request),
			['MAIN_CONTENT' => $mainContent],
			$response->getMainLayoutData()
		);

		$html = $this->templateEngine->render(self::MAIN_LAYOUT, $layoutData);

		return $response->withBody($this->streamFactory->createStream($html))->withHeader('Content-Type', 'text/html');
	}
}
