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

namespace App\Modules\Templates\Services;

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Helper\Composer\ExportImage;
use App\Modules\Templates\Repositories\TemplatesRepository;
use Doctrine\DBAL\Exception;
use JsonException;
use Throwable;

/**
 * The templates which come with garlic-hub (resources/templates): a module admin installs them as global templates
 * of his own. Each one keeps its seed key (directory/file name), so installing again adds only the new ones of an
 * update and the ones deleted since then.
 *
 * A template of the pack is <key>.json with name, description and content, and its image <key>.jpg.
 */
class TemplatePackService
{
	private const string MODULE_NAME = 'templates';
	private const string VISIBILITY  = 'global';
	/** the directories of the pack and the type of their templates */
	private const array DIRECTORY_TYPES = ['fabric' => 'canvas'];

	public function __construct(
		private readonly TemplatesRepository $templatesRepository,
		private readonly AclValidator $aclValidator,
		private readonly UserSession $userSession,
		private readonly ExportImage $exportImage,
		private readonly Transactions $transactions,
		private readonly string $packDir
	) {}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function canInstall(): bool
	{
		return $this->aclValidator->isModuleAdmin($this->userSession->getUID());
	}

	/**
	 * A template which fails stops the installation, the ones before stay installed.
	 *
	 * @return int the number of installed templates
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws JsonException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function install(): int
	{
		if (!$this->canInstall())
			throw new AccessDeniedException(self::MODULE_NAME, 'template_pack_not_installable');

		$installed = $this->templatesRepository->findSeedKeys();
		$count     = 0;
		foreach ($this->findPackFiles() as $seedKey => [$file, $type])
		{
			if (in_array($seedKey, $installed, true))
				continue;

			$this->installTemplate($seedKey, $file, $type);
			$count++;
		}

		return $count;
	}

	/**
	 * @return array<string,array{string,string}> seed key => [json file, type of the template]
	 */
	private function findPackFiles(): array
	{
		$files = [];
		foreach (self::DIRECTORY_TYPES as $directory => $type)
		{
			$found = glob($this->packDir . '/' . $directory . '/*.json') ?: [];
			sort($found);
			foreach ($found as $file)
				$files[$directory . '/' . basename($file, '.json')] = [$file, $type];
		}

		return $files;
	}

	/**
	 * The template and its image or nothing.
	 *
	 * @throws Exception
	 * @throws JsonException
	 * @throws ModuleException
	 */
	private function installTemplate(string $seedKey, string $file, string $type): void
	{
		$template  = $this->readTemplate($seedKey, $file);
		$imageFile = substr($file, 0, -strlen('.json')) . '.jpg';
		if (!is_file($imageFile))
			throw new ModuleException(self::MODULE_NAME, 'The template ' . $seedKey . ' of the pack has no image.');

		$image = (string) file_get_contents($imageFile);

		$this->transactions->begin();
		try
		{
			$templateId = (int) $this->templatesRepository->insert([
				'UID'         => $this->userSession->getUID(),
				'visibility'  => self::VISIBILITY,
				'type'        => $type,
				'name'        => $template['name'],
				'description' => $template['description'],
				'content'     => json_encode($template['content'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
				'seed_key'    => $seedKey
			]);

			$this->exportImage->decode('data:image/jpeg;base64,' . base64_encode($image));
			if (!$this->exportImage->exportBase64($templateId))
				throw new ModuleException(self::MODULE_NAME, 'The image of the template ' . $seedKey . ' can not be stored: ' . $this->exportImage->getErrorText());

			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}
	}

	/**
	 * @return array{name: string, description: string, content: array<mixed>}
	 * @throws JsonException
	 * @throws ModuleException
	 */
	private function readTemplate(string $seedKey, string $file): array
	{
		$template = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($template) || !is_string($template['name'] ?? null) || !is_array($template['content'] ?? null))
			throw new ModuleException(self::MODULE_NAME, 'The template ' . $seedKey . ' of the pack has no name or content.');

		return [
			'name'        => $template['name'],
			'description' => is_string($template['description'] ?? null) ? $template['description'] : '',
			'content'     => $template['content']
		];
	}
}
