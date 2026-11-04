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
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Helper\Settings\Parameters;
use App\Modules\Templates\Repositories\TemplatesRepository;
use App\Shared\Acl\AclGrantService;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
use App\Shared\Versioning\UnitVersions;
use Doctrine\DBAL\Exception;
use JsonException;
use Throwable;

class TemplatesService
{
	private const string MODULE_NAME = 'templates';

	public function __construct(
		private readonly TemplatesRepository $templatesRepository,
		private readonly AclValidator $aclValidator,
		private readonly UserSession $userSession,
		private readonly AclGrantService $aclGrantService,
		private readonly UnitVersions $unitVersions,
		private readonly AuditLog $auditLog,
		private readonly Transactions $transactions
	) {}

    /**
     * @throws CoreException
     * @throws UserException
     * @throws Exception
     */
    public function checkCreateRights(): bool
	{
		if ($this->aclValidator->canCreate($this->userSession->getUID()))
			return true;

		return false;
	}

	/**
	 * @param array<string,mixed> $saveData
	 * @throws Exception
	 */
	public function update(int $templateId, array $saveData): int
	{
		return $this->templatesRepository->update($templateId, $this->collectDataForSettingsUpdate($saveData));
	}

	/**
	 * The content of the composer. The content it replaces becomes a version (while the versioning is on).
	 *
	 * @param array<string,mixed> $template as loaded with the rights of the user
	 * @throws Exception
	 * @throws JsonException
	 * @throws UserException
	 */
	public function updateContent(array $template, string $content): int
	{
		$templateId = (int) $template['template_id'];
		$previous   = $template['content'] ?? null;
		if (is_string($previous) && $previous !== '' && $previous !== $content)
			$this->unitVersions->record(self::MODULE_NAME, $templateId, $this->userSession->getUID(), ['content' => $previous]);

		return $this->templatesRepository->update($templateId, ['content' => $content]);
	}

	public function isVersioningEnabled(): bool
	{
		return $this->unitVersions->isEnabled();
	}

	/**
	 * @return list<array{version:int, created_at:string, username:string}> the newest first
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function findVersions(int $templateId): array
	{
		$this->loadCheckedTemplate($templateId, false);

		return $this->unitVersions->findVersions(self::MODULE_NAME, $templateId);
	}

	/**
	 * The content of the version replaces the current one, which becomes a version itself:
	 * a restore can be undone. The thumbnail is renewed with the next save in the composer.
	 *
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws JsonException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function restoreVersion(int $templateId, int $version): void
	{
		$template = $this->loadCheckedTemplate($templateId, false);
		$snapshot = $this->unitVersions->findSnapshot(self::MODULE_NAME, $templateId, $version);
		if ($snapshot === null || !is_string($snapshot['content'] ?? null))
			throw new NotFoundException(self::MODULE_NAME, 'version_not_found', ['template_id' => $templateId, 'version' => $version]);

		$this->updateContent($template, $snapshot['content']);
		$this->auditLog->record($this->userSession->getUID(), self::MODULE_NAME, AuditAction::VERSION_RESTORED, $templateId, null, ['version' => $version]);
	}

	/**
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function delete(int $templateId): int
	{
		// editing is not enough: an editor of a shared template must not delete it.
		// Whether it is used, Composer\Orchestrator::delete() checks.
		$template = $this->loadCheckedTemplate($templateId, true);

		// the shares and versions go with the template or nothing goes
		$this->transactions->begin();
		try
		{
			$deleted = $this->templatesRepository->delete($templateId);
			if ($deleted > 0)
			{
				$this->aclGrantService->removeUnitShares(self::MODULE_NAME, [$templateId]);
				$this->unitVersions->removeUnits(self::MODULE_NAME, [$templateId]);
				// the name: the list of the audit log finds no template anymore
				$this->auditLog->record($this->userSession->getUID(), self::MODULE_NAME, AuditAction::UNIT_DELETED, $templateId, null, ['name' => $template['name']]);
			}
			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}

		return $deleted;
	}

    /**
     * @param int $templateId
     * @return array{UID: int, company_id: int, template_id: int, name: string, content: string|null, ...}
     * @throws CoreException
     * @throws Exception
     * @throws FrameworkException
     * @throws NotFoundException
     * @throws AccessDeniedException
     * @throws UserException
     */
	public function loadWithUserById(int $templateId): array
	{
		return $this->loadCheckedTemplate($templateId, false);
	}

	/**
	 * A template which goes into a playlist: a viewer may use it, too.
	 *
	 * @return array<int|string,mixed>
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function loadUsableById(int $templateId): array
	{
		try
		{
			return $this->loadCheckedTemplate($templateId, false);
		}
		catch (AccessDeniedException)
		{
			if (!$this->aclValidator->isViewerWithAccessOnUnit($this->userSession->getUID(), $templateId))
				throw new AccessDeniedException(self::MODULE_NAME, 'template_not_usable', ['template_id' => $templateId]);

			return $this->templatesRepository->findFirstWithUserName($templateId);
		}
	}

	/**
	 * For the context menu: may the user delete the template?
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function isDeletable(int $templateId): bool
	{
		try
		{
			$this->loadCheckedTemplate($templateId, true);
			return true;
		}
		catch (NotFoundException|AccessDeniedException)
		{
			return false;
		}
	}

	/**
	 * @return array{UID: int, company_id: int, template_id: int, name: string, content: string|null, ...}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws AccessDeniedException
	 * @throws UserException
	 */
	private function loadCheckedTemplate(int $templateId, bool $forDelete): array
	{
		/** @var array{UID: int, company_id: int, template_id: int, name: string, content: string|null, ...}|array{} $template */
		$template = $this->templatesRepository->findFirstWithUserName($templateId);
		if ($template === [])
			throw new NotFoundException('templates', 'template_not_found', ['template_id' => $templateId]);

		if (!$this->aclValidator->isTemplateEditable($this->userSession->getUID(), $template))
			throw new AccessDeniedException('templates', 'template_not_editable', ['template_id' => $templateId]);

		if ($forDelete && !$this->aclValidator->isAllowedToDeleteTemplate($this->userSession->getUID(), $template))
			throw new AccessDeniedException('templates', 'template_not_deletable', ['template_id' => $templateId]);

		return $template;
	}

    /**
     * @param array<string,mixed> $saveData
     * @return int
     * @throws Exception
     * @throws UserException
     */
	public function insert(array $saveData): int
	{
		return (int) $this->templatesRepository->insert($this->collectDataForSettingsInsert($saveData));
	}

    /**
     * @param array<string,mixed> $postData
     * @return array<string,mixed>
     * @throws UserException
     */
	private function collectDataForSettingsInsert(array $postData): array
	{
		$saveData = [];
		if (array_key_exists(BaseParameters::PARAMETER_UID, $postData))
			$saveData[BaseParameters::PARAMETER_UID] = $postData[BaseParameters::PARAMETER_UID];
		else
			$saveData[BaseParameters::PARAMETER_UID] = $this->userSession->getUID();

		$saveData[Parameters::PARAMETER_TYPE] = $postData[Parameters::PARAMETER_TYPE];

		return $this->collectCommonSettings($postData, $saveData);
	}

	/**
	 * @param array<string,mixed> $postData
	 * @return array<string,mixed>
	 */
	private function collectDataForSettingsUpdate(array $postData): array
	{
		$saveData = [];
		if (array_key_exists(BaseParameters::PARAMETER_UID, $postData))
			$saveData[BaseParameters::PARAMETER_UID] = $postData[BaseParameters::PARAMETER_UID];

		if (array_key_exists('content', $postData))
			$saveData['content'] = $postData['content'];

		return $this->collectCommonSettings($postData, $saveData);
	}


	/**
	 * @param array<string,mixed> $postData
	 * @param array<string,mixed> $saveData
	 * @return array<string,mixed>
	 */
	private function collectCommonSettings(array $postData, array $saveData): array
	{
		if (isset($postData[Parameters::PARAMETER_NAME]))
			$saveData[Parameters::PARAMETER_NAME] = $postData[Parameters::PARAMETER_NAME];

		if (isset($postData[Parameters::PARAMETER_VISIBILITY]))
			$saveData[Parameters::PARAMETER_VISIBILITY] = $postData[Parameters::PARAMETER_VISIBILITY];

		return $saveData;
	}
}