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

namespace App\Modules\Player\Helper\NetworkSettings;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * The Builder class is responsible for constructing and validating forms and handling user input.
 */
readonly class Builder
{
	private FormElementsCreator $formElementsCreator;
	private Validator $validator;
	private Parameters $parameters;
	public function __construct(Parameters $parameters, Validator $validator, FormElementsCreator $formElementsCreator)
	{
		$this->parameters           = $parameters;
		$this->validator            = $validator;
		$this->formElementsCreator  = $formElementsCreator;
	}

	/**
	 * @param array<string,mixed> $networkData
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function buildForm(array $networkData): array
	{
		$form       = [];
		$form[Parameters::PARAMETER_IS_INTRANET] = $this->formElementsCreator->createIsIntranet(
			(bool)($networkData[Parameters::PARAMETER_IS_INTRANET] ?? false),
		);

		$form[Parameters::PARAMETER_API_ENDPOINT] = $this->formElementsCreator->createApiEndpointField(
			$networkData[Parameters::PARAMETER_API_ENDPOINT] ?? ''
		);

		$form['player_id'] = $this->formElementsCreator->createHiddenPlayerIdField((int) $networkData['player_id']);
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