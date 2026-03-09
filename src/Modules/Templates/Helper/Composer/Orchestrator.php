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

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Templates\Services\TemplatesService;
use App\Modules\Templates\Services\TemplatesUsageService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Handles orchestration of validation, fetching, and saving processes.
 */
class Orchestrator extends BaseTemplateOrchestrator
{
	/** @var array<string,string>  */
	private array $template;

	public function __construct(
		private readonly TemplatePreparer    $templatePreparer,
		private readonly TemplatesUsageService $templatesUsageService,
		private readonly ExportImage         $exportImage,
		private readonly TemplatesService    $templatesService,
		Config $config
	)
	{

		parent::__construct($config);
	}


	public function checkEditRights(int $templateId): bool
	{
		$template =  $this->templatesService->loadWithUserById($templateId);
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
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function saveTemplate(int $templateId, string $content, string $imageBase64): int
	{
		$content = $this->validate($content);
		if ($content === '' )
			return 0;

		$this->exportImage->decode($imageBase64);
		if ($this->exportImage->exportBase64($templateId) === false)
			return 0;

		return $this->templatesService->update($templateId, ['content' => $content]);
	}

	public function delete(int $templateId): string
	{
		if ($this->templatesUsageService->determineTemplatesInUse([$templateId]) !== [])
			return 'Template is in use.';

		if ($this->templatesService->delete($templateId) === 0)
			return 'Template not deleted.';

		return '';
	}

	public function getContent(): string
	{
		if ( $this->template['content'] === null)
			return '';

		$json = json_decode($this->template['content'], true);
		$this->restoreSrc($json['objects']);

		return json_encode($json);
	}
}