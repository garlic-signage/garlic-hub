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


namespace App\Modules\Templates\Helper\Settings;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Templates\Services\TemplatesService;
use Doctrine\DBAL\Exception;

/**
 * Handles orchestration of validation, fetching, and saving processes.
 */
class Orchestrator
{
	/** @var array<string,mixed> */
	private array $template = [];

	public function __construct(
		private readonly SettingsFormBuilder      $settingsFormBuilder,
		private readonly SettingsParametersPolicy $settingsParametersPolicy,
		private readonly TemplatesService         $templateService,
		private readonly FormInputHandler         $formInputHandler
	) {}

    /**
     * @return bool
     */
	public function checkCreateRights(): bool
	{
		return $this->templateService->checkCreateRights();
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
			$this->template = $this->templateService->loadWithUserById($templateId);
			return true;
		}
		catch (NotFoundException|AccessDeniedException)
		{
			// the callers answer with their own response, html redirect or json
			return false;
		}
	}

	/**
	 * @param array<string,mixed> $post
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildCreateForm(array $post = []): array
	{
		$this->settingsParametersPolicy->addCreateFormElements();
		return $this->settingsFormBuilder->buildForm($post);
	}

	/**
	 * @return array{hidden:list<array<string,string>>, visible: list<array<string,string>>}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function buildEditForm(): array
	{
		$this->settingsParametersPolicy->addEditFormElements();
		return $this->settingsFormBuilder->buildForm($this->template);
	}


	/**
	 * @param array<string,mixed> $post
	 * @return array{success: bool, errors?: string[]}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function storeCreateSettings(array $post): array
	{
		$this->settingsParametersPolicy->addCreateFormElements();
		$errors = $this->formInputHandler->validateCreate($post);

		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		$saveData = $this->formInputHandler->getParsed();

		if ($this->templateService->insert($saveData) === 0)
			return ['success' => false, 'errors' => ['No save possible']];

		return ['success' => true];
	}

	/**
	 * @param array<string,mixed> $post
	 * @return array{success: bool, errors?: string[]}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function storeEditSettings(int $templateId, array $post): array
	{
		$this->settingsParametersPolicy->addEditFormElements();
		$errors = $this->formInputHandler->validateEdit($post);
		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		$saveData = $this->formInputHandler->getParsed();
		if ($this->templateService->update($templateId, $saveData) === 0)
			return ['success' => false, 'errors' => ['No save possible']];

		return ['success' => true];
	}
}