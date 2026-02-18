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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Services\AbstractBaseService;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Templates\Helper\Settings\Parameters;
use App\Modules\Templates\Services\AclValidator;
use App\Modules\Templates\Repositories\TemplatesRepository;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Log\LoggerInterface;
use Throwable;

class TemplateService extends AbstractBaseService
{
	public function __construct(
		private readonly TemplatesRepository $templatesRepository,
		private readonly AclValidator $aclValidator,
		LoggerInterface $logger)
	{
		parent::__construct($logger);
	}

	public function updateSecure(array $postData): int
	{
		$playlistId = $postData['playlist_id'];
		$this->loadWithUserById($playlistId);
		$saveData = $this->collectDataForSettingsUpdate($postData);

		return $this->update($playlistId, $saveData);
	}

	/**
	 * @param array<string,mixed> $saveData
	 * @throws Exception
	 */
	public function update(int $templateId, array $saveData): int
	{
		return $this->templatesRepository->update($templateId, $saveData);
	}

	public function delete(int $templateId): int
	{
		try
		{
			$this->loadWithUserById($templateId);
			// Todo: Check if used
			return $this->templatesRepository->delete($templateId);
		}
		catch(Throwable $e)
		{
			$this->logger->error('Error delete template: '.$e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return 0;
		}

	}

	/**
	 * @return array{UID: int, company_id: int, template_id: int, ...}
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function loadWithUserById(int $templateId): array
	{
		/** @var array{UID: int, company_id: int, template_id: int, ...} $playlist */
		$template = $this->templatesRepository->findFirstWithUserName($templateId);
		if ($template === [])
			throw new ModuleException('playlists', 'No template with Id: '.$templateId.' found.');

		if (!$this->aclValidator->isTemplateEditable($this->UID, $template))
			throw new ModuleException('templates', 'Template is not editable.');

		return $template;
	}

	public function insert(array $saveData): int
	{
		return (int) $this->templatesRepository->insert($this->collectDataForSettingsInsert($saveData));
	}

	/**
	 * @param array<string,mixed> $postData
	 * @return array<string,mixed>
	 */
	private function collectDataForSettingsInsert(array $postData): array
	{
		if (array_key_exists(BaseParameters::PARAMETER_UID, $postData))
			$saveData[BaseParameters::PARAMETER_UID] = $postData[BaseParameters::PARAMETER_UID];
		else
			$saveData[BaseParameters::PARAMETER_UID] = $this->UID;

		$saveData[Parameters::PARAMETER_TYPE] = $postData[Parameters::PARAMETER_TYPE];

		return $this->collectCommonSettings($postData, $saveData);
	}

	/**
	 * @param array<string,mixed> $postData
	 * @return array<string,mixed>
	 */
	private function collectDataForSettingsUpdate(array $postData): array
	{
		$saveData[BaseParameters::PARAMETER_UID] = $postData[BaseParameters::PARAMETER_UID];
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