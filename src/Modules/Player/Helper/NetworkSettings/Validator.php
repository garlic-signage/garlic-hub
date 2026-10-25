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

use App\Framework\Validation\BaseValidator;
use App\Framework\Security\CsrfToken;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;

/**
 * Class Validator
 *
 * Extends the functionality provided by BaseValidator. The Validator class is responsible
 * for validating user input while leveraging network parameters and CSRF token verification.
 */
class Validator extends BaseValidator
{
	private Parameters $networkParameters;

	public function __construct(Translator $translator, Parameters $settingsParameters, CsrfToken $csrfToken)
	{
		parent::__construct($translator, $csrfToken);
		$this->networkParameters = $settingsParameters;
	}

	/**
	 * @return string[]
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateUserInput(): array
	{
		$errors = $this->validateFormCsrfToken($this->networkParameters);

		$isIntranet  = $this->networkParameters->getValueOfParameter(Parameters::PARAMETER_IS_INTRANET);
		$apiEndpoint = $this->networkParameters->getValueOfParameter(Parameters::PARAMETER_API_ENDPOINT);

		if ($isIntranet === true && $apiEndpoint === '')
			$errors[] = $this->translator->translate('no_api_endpoint', 'player');

		if (is_string($apiEndpoint) && $apiEndpoint !== '' && !$this->isApiEndpoint($apiEndpoint))
			$errors[] = $this->translator->translate('invalid_api_endpoint', 'player');

		return $errors;
	}



	/**
	 * The address is the base of the calls of the server to the player ('/oauth2/token' and more are appended): an
	 * address of the web with a host, and nothing which would change the target of the appended part.
	 * Players are in the network of the customer, so a private address is fine.
	 */
	private function isApiEndpoint(string $url): bool
	{
		if (strlen($url) > 255 || preg_match('/[\x00-\x20\x7F]/', $url) === 1)
			return false;

		$parts = parse_url($url);
		if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true))
			return false;

		if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']))
			return false;

		$host = $parts['host'] ?? '';

		return $host !== '' && (filter_var($host, FILTER_VALIDATE_IP) !== false
			|| filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
			|| filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false);
	}
}
