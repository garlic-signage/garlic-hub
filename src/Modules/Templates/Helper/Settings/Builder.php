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

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Auth\UserSession;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * The Builder class is responsible for constructing and validating forms and handling user input.
 */
readonly class Builder
{

	public function __construct(private readonly Parameters $parameters,
								private readonly UserSession $userSession,
								private readonly Validator $validator,
								private readonly FormElementsCreator $formElementsCreator)
	{}

	/**
	 * @param array<string,mixed> $settingsData
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function buildForm(array $settingsData): array
	{
		$form       = [];
		$form[Parameters::PARAMETER_NAME] = $this->formElementsCreator->createNameField(
			($settingsData[Parameters::PARAMETER_NAME] ?? ''),
		);

		if ($this->parameters->hasParameter(BaseParameters::PARAMETER_UID))
		{
			$form['UID'] = $this->formElementsCreator->createUIDField(
				$settingsData[BaseParameters::PARAMETER_UID] ?? $this->userSession->getUID(),
					$settingsData['username'] ?? $this->userSession->getUsername(),
				$this->userSession->getUID()
			);
		}

		if ($settingsData !== [])
			$form['template_id'] = $this->formElementsCreator->createHiddenTemplateIdField((int) $settingsData['template_id']);

		$form[BaseEditParameters::PARAMETER_CSRF_TOKEN] = $this->formElementsCreator->createCSRFTokenField();

		return $this->formElementsCreator->prepareForm($form);
	}


	/**
	 * @param array{player_id:int, player_name:string, is_intranet:int, api_endpoint:string} $post
	 * @return string[]
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function handleUserInput(array $post): array
	{
		$this->parameters->setUserInputs($post)
			->parseInputAllParameters();

		return $this->validator->validateUserInput();
	}

}