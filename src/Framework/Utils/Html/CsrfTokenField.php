<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Framework\Utils\Html;


use App\Framework\Core\CsrfToken;
use Exception;

/**
 * Represents a CSRF token input field that is integrated with a CSRF token object to ensure security.
 *
 * This class is typically used to automatically populate an input field with a CSRF token value
 * to prevent cross-site request forgery attacks.
 *
 * Extends functionality provided by AbstractInputField to include CSRF token integration.
 *
 * Implements methods to set and handle the CSRF token value.
 *
 * @throws Exception if there is an error during the initialization of the CSRF token field.
 */
class CsrfTokenField extends AbstractInputField
{
	/**
	 * @throws Exception
	 */
	public function __construct(array $attributes, CsrfToken $csrfToken)
	{
		parent::__construct($attributes);
		$this->setValue($csrfToken->getToken());
	}

}