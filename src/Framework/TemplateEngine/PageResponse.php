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

namespace App\Framework\TemplateEngine;

use App\Framework\Exceptions\FrameworkException;
use Slim\Psr7\Response;

/**
 * Response of a controller that shows an HTML page.
 *
 * It only carries the page data. FinalRenderMiddleware renders the content
 * template, puts it into the main layout and writes the HTML into the body.
 * Slim clones responses on withHeader() & co, so the type survives these calls.
 *
 * Expected page structure:
 *   ['main_layout' => [...], 'this_layout' => ['template' => 'module/name', 'data' => [...]]]
 */
class PageResponse extends Response
{
	private readonly string $template;
	/** @var array<string,mixed> */
	private readonly array $templateData;
	/** @var array<string,mixed> */
	private readonly array $mainLayoutData;

	/**
	 * @param array<string,mixed> $page
	 * @throws FrameworkException
	 */
	public function __construct(array $page, int $status = 200)
	{
		parent::__construct($status);

		$content = $page['this_layout'] ?? null;
		if (!is_array($content) || !isset($content['template']) || !is_string($content['template']))
			throw new FrameworkException('Page data needs this_layout.template.');

		$this->template       = $content['template'];
		$this->templateData   = is_array($content['data'] ?? null) ? $content['data'] : [];
		$this->mainLayoutData = is_array($page['main_layout'] ?? null) ? $page['main_layout'] : [];
	}

	public function getTemplate(): string
	{
		return $this->template;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getTemplateData(): array
	{
		return $this->templateData;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getMainLayoutData(): array
	{
		return $this->mainLayoutData;
	}
}
