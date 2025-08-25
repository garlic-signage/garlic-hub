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

use App\Framework\Core\BaseValidator;
use App\Framework\Core\CsrfToken;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class Validator
 *
 * Extends the functionality provided by BaseValidator. The Validator class is responsible
 * for validating user input while leveraging network parameters and CSRF token verification.
 */
class Validator extends BaseValidator
{
	private Parameters $networkParameters;

	public function __construct(Translator $translator, Parameters $networkParameters, CsrfToken $csrfToken)
	{
		parent::__construct($translator, $csrfToken);
		$this->networkParameters = $networkParameters;
	}

	/**
	 * @return string[]
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function validateUserInput(): array
	{
		$errors = $this->validateFormCsrfToken($this->networkParameters);

		$isIntranet  = $this->networkParameters->getValueOfParameter(Parameters::PARAMETER_IS_INTRANET);
		$apiEndpoint = $this->networkParameters->getValueOfParameter(Parameters::PARAMETER_API_ENDPOINT);

		if ($isIntranet === true && $apiEndpoint === '')
			$errors[] = $this->translator->translate('no_api_endpoint', 'player');

		return $errors;
	}


}