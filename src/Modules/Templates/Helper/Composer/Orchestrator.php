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


namespace App\Modules\Templates\Helper\Composer;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Templates\Services\TemplatesService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Handles orchestration of validation, fetching, and saving processes.
 */
class Orchestrator
{
	/** @var array<string,string>  */
	private array $template;

	public function __construct(
		private readonly TemplatePreparer    $templatePreparer,
		private readonly TemplatesService    $templateService,
	) {}


	public function checkEditRights(int $templateId): bool
	{
		$template =  $this->templateService->loadWithUserById($templateId);
		if ($template == [])
			return false;

		$this->template = $template;
		return true;
	}

	public function build(int $templateId): array
	{
		$replaced = $this->templatePreparer->replace($templateId);
		return $this->templatePreparer->prepare($this->template['name'], $replaced);

	}

	/**
	 * @return array{success: bool, errors?: string[]}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function store(int $templateId, string $content): array
	{
		// Todo Later: Validate content with opis/json-schema if canvas
		$saveData = ['content' => $content];
		if ($this->templateService->update($templateId, $saveData) === 0)
			return ['success' => false, 'errors' => ['No save possible']];

		return ['success' => true];
	}

}