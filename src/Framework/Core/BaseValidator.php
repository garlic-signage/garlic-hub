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

namespace App\Framework\Core;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * BaseValidator provides utility methods for common validation tasks such as
 * validating emails, JSON strings, passwords, and CSRF tokens.
 */
class BaseValidator
{
	public function __construct(
		protected readonly Translator $translator,
		protected readonly CsrfToken $csrfToken
	)
	{}

	public function isEmail(string $value): bool
	{
		return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
	}

	public function isJson(string $value): bool
	{
		// Just decode, don't need the result for validation
		return json_validate($value);
	}

	public function validatePassword(string $password, string $pattern): bool
	{
		$pattern = '/^'.$pattern.'$/';

		if (preg_match($pattern, $password))
			return true;

		return false;
	}

	public function validateCsrfToken(string $receivedToken): bool
	{
		return $this->csrfToken->validateToken($receivedToken);
	}

	/**
	 * @return string[]
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function validateFormCsrfToken(BaseEditParameters $baseEditParameters): array
	{
		$receivedToken = $baseEditParameters->getCsrfToken();

		$error = [];
		if ($receivedToken === '' || !$this->validateCsrfToken($receivedToken))
			$error[] = $this->translator->translate('csrf_token_mismatch', 'security');

		return $error;
	}

}
