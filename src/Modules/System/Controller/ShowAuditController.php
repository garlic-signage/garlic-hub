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

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Modules\Auth\UserSession;
use App\Modules\System\Helper\Audit\Orchestrator;
use App\Modules\System\Services\AclValidator;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * The audit log for the module admin of system. It stays readable while the log is off:
 * the old entries are there until the scheduler deletes them.
 */
readonly class ShowAuditController
{
	private const string MODULE_NAME = 'system';

	public function __construct(
		private Orchestrator $orchestrator,
		private DatatableTemplatePreparer $templatePreparer,
		private AclValidator $aclValidator,
		private UserSession $userSession,
		private Config $config,
		private Translator $translator,
		private Messages $flash
	) {}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		if (!$this->aclValidator->isModuleAdmin($this->userSession->getUID()))
		{
			$this->flash->addMessage('error', $this->translator->translate('audit_not_allowed', self::MODULE_NAME));
			return $response->withHeader('Location', '/')->withStatus(302);
		}

		$this->orchestrator->configure();
		$this->orchestrator->processSubmittedUserInput($request->getQueryParams());
		$this->orchestrator->prepareDataGrid();

		$templateData = $this->templatePreparer->preparerUITemplate($this->orchestrator->prepareUITemplate());
		$templateData['this_layout']['data']['FORM_ACTION']    = '/system/audit';
		$templateData['this_layout']['data']['audit_disabled'] = $this->isEnabled() ? [] : [['LANG_AUDIT_DISABLED' => $this->translator->translate('audit_disabled', self::MODULE_NAME)]];

		return new PageResponse($templateData);
	}

	private function isEnabled(): bool
	{
		return $this->config->isSwitchOn('enabled', self::MODULE_NAME, 'Scheduler') && $this->config->isSwitchOn('enabled', self::MODULE_NAME, 'Audit');
	}
}
