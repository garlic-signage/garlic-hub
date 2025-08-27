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

namespace App\Modules\Profile\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles the display of user settings.
 *
 * This controller is responsible for redirecting the user to the settings page where
 * specific preferences can be managed. In the current implementation, the user is
 * only allowed to edit their password. Future versions will include additional settings
 * such as company information, contact details, and multi-factor authentication options.
 */
class ShowSettingsController
{

	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		// In the edge Version user can only edit his password
		// For later versions there will be more settings like company, contact data, MFA select, etc
		return $response->withHeader('Location', '/profile/password')->withStatus(302);
	}
}