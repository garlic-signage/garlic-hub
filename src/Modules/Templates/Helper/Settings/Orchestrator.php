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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Templates\Services\TemplateService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Handles orchestration of validation, fetching, and saving processes.
 */
class Orchestrator
{
	/** @var array<string,string>  */
	private array $input;

	public function __construct(
		private readonly CreateFormBuilder $createFormBuilder,
		private readonly SettingsParametersPolicy $settingsParametersPolicy,
		private readonly CreateFormWriter  $createFormWriter,
		private readonly CreateFormInputHandler  $createFormInputHandler
	) {}

	public function checkCreateRights(): bool
	{
		return $this->settingsParametersPolicy->checkCreateRights();
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function buildCreateForm(array $post = []): array
	{
		$this->settingsParametersPolicy->addCreateFormElements();
		return $this->createFormBuilder->build($post);
	}

	/**.
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function storeCreateSettings(array $post): array
	{
		$this->settingsParametersPolicy->addCreateFormElements();
		$errors = $this->createFormInputHandler->validate($post);

		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		$saveData = $this->createFormInputHandler->getParsed();
		$errors = $this->createFormWriter->store($saveData);
		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		return ['success' => true];
	}

	public function storeEditSettings(): array
	{
		return ['success' => true];
	}




}