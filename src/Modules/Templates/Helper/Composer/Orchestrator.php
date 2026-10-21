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
use App\Framework\Media\MediaUrlSigner;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Templates\Services\TemplatesService;
use App\Modules\Templates\Services\TemplatesUsageService;
use Doctrine\DBAL\Exception;

/**
 * Handles orchestration of validation, fetching, and saving processes.
 */
class Orchestrator extends BaseTemplateOrchestrator
{
	/** @var array<string,mixed> */
	private array $template = [];

	public function __construct(
		private readonly TemplatePreparer    $templatePreparer,
		private readonly TemplatesUsageService $templatesUsageService,
		private readonly ExportImage         $exportImage,
		private readonly TemplatesService    $templatesService,
		Config $config,
		MediaUrlSigner $mediaUrlSigner
	)
	{
		parent::__construct($config, $mediaUrlSigner);
	}

	/**
	 * Returns false when the template does not exist or is not editable for the current user.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function checkEditRights(int $templateId): bool
	{
		try
		{
			$this->template = $this->templatesService->loadWithUserById($templateId);
			return true;
		}
		catch (NotFoundException|AccessDeniedException)
		{
			// the callers answer with their own response, html redirect or json
			return false;
		}
	}

	/**
	 * @return array{main_layout: array<string,mixed>, this_layout: array{template: string, data: array<string,mixed>}}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function build(int $templateId): array
	{
		$replaced = $this->templatePreparer->replace($templateId);
		return $this->templatePreparer->prepare((string) $this->template['name'], $replaced);
	}

    /**
     * @param int $templateId
     * @param string $content
     * @param string $imageBase64
     * @return int
     * @throws Exception
     * @throws FrameworkException
     * @throws ModuleException
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

	/**
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws TranslatableModuleException
	 * @throws UserException
	 */
	public function delete(int $templateId): void
	{
		if ($this->templatesUsageService->determineTemplatesInUse([$templateId]) !== [])
			throw new TranslatableModuleException('templates', 'template_in_use', ['template_id' => $templateId]);

		$this->templatesService->delete($templateId);
	}

	/**
	 * Content of the template loaded by checkEditRights() with fresh signed media urls.
	 */
	public function getContent(): string
	{
		$content = $this->template['content'] ?? null;
		if (!is_string($content) || $content === '')
			return '';

		$json = json_decode($content, true);
		if (!is_array($json) || !isset($json['objects']) || !is_array($json['objects']))
			return '';

		$this->restoreSrc($json['objects']);

		return json_encode($json) ?: '';
	}
}