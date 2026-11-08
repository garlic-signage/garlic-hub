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

use App\Framework\Config\Config;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\UserException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Repositories\TemplatesRepository;
use Doctrine\DBAL\Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Throwable;

/**
 * A copy of a template as start of a new one: only an admin of the template copies it (the module admin, in
 * the Enterprise edition the sub admin of its company, too). The copy belongs to him, is private and has the
 * content and the image of the template, not its versions, shares or the key of the pack.
 */
readonly class TemplateCopyService
{
	private const string MODULE_NAME = 'templates';
	private const int MAX_NAME_LENGTH = 100;

	public function __construct(
		private TemplatesRepository $templatesRepository,
		private AclValidator $aclValidator,
		private UserSession $userSession,
		private Translator $translator,
		private Filesystem $filesystem,
		private Config $config,
		private Transactions $transactions
	) {}

	/**
	 * For the context menu: may the user copy the template?
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function canCopy(int $templateId): bool
	{
		$template = $this->findTemplate($templateId);

		return $template !== null && $this->aclValidator->isAdmin($this->userSession->getUID(), $template);
	}

	/**
	 * @return int the id of the copy
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FilesystemException
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function copy(int $templateId): int
	{
		$template = $this->findTemplate($templateId);
		if ($template === null)
			throw new NotFoundException(self::MODULE_NAME, 'template_not_found', ['template_id' => $templateId]);
		if (!$this->aclValidator->isAdmin($this->userSession->getUID(), $template))
			throw new AccessDeniedException(self::MODULE_NAME, 'template_not_copyable', ['template_id' => $templateId]);

		$name = mb_substr($this->translator->translate('copy_of', self::MODULE_NAME, ['{name}' => $template['name']]), 0, self::MAX_NAME_LENGTH);

		// the row and its image or nothing
		$this->transactions->begin();
		try
		{
			$copyId = (int) $this->templatesRepository->insert([
				'UID'         => $this->userSession->getUID(),
				'visibility'  => 'private',
				'type'        => $template['type'],
				'name'        => $name,
				'description' => $template['description'],
				'content'     => $template['content']
			]);
			$this->copyImage($templateId, $copyId);
			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}

		return $copyId;
	}

	/**
	 * @return array{UID: int, company_id: int, type: string, name: string, description: string|null, content: string|null}|null
	 * @throws Exception
	 */
	private function findTemplate(int $templateId): ?array
	{
		$row = $this->templatesRepository->findFirstWithUserName($templateId);
		if ($row === [])
			return null;

		return [
			'UID'         => (int) $row['UID'],
			'company_id'  => (int) ($row['company_id'] ?? 0),
			'type'        => (string) $row['type'],
			'name'        => (string) $row['name'],
			'description' => is_string($row['description'] ?? null) ? $row['description'] : null,
			'content'     => is_string($row['content'] ?? null) ? $row['content'] : null
		];
	}

	/**
	 * The image and the thumbnail are named by the id of the template. A template which was never saved has none.
	 *
	 * @throws CoreException
	 * @throws FilesystemException
	 */
	private function copyImage(int $templateId, int $copyId): void
	{
		foreach (['originals', 'thumbnails'] as $directory)
		{
			$path = $this->config->getConfigValue($directory, self::MODULE_NAME, 'directories');
			if ($this->filesystem->fileExists($path . '/' . $templateId . '.jpg'))
				$this->filesystem->copy($path . '/' . $templateId . '.jpg', $path . '/' . $copyId . '.jpg');
		}
	}
}
