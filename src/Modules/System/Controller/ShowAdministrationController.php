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

namespace App\Modules\System\Controller;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Auth\UserSession;
use App\Shared\Layout\AdministrationLinks;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The administration page: an entry per area whose rights the user has. The user menu has only this one link,
 * so it stays short when areas are added.
 */
readonly class ShowAdministrationController
{
	public function __construct(private AdministrationLinks $administrationLinks, private UserSession $userSession, private Translator $translator)
	{}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$links = $this->administrationLinks->build($this->userSession->getUID());
		if ($links === [])
			return $response->withHeader('Location', '/')->withStatus(302);

		$title = $this->translator->translate('administration', 'system');

		return new PageResponse([
			'main_layout' => ['LANG_PAGE_TITLE' => $title, 'additional_css' => ['/css/system/administration.css'], 'footer_modules' => []],
			'this_layout' => [
				'template' => 'system/administration',
				'data'     => ['LANG_PAGE_HEADER' => $title, 'areas' => $links]
			]
		]);
	}
}
