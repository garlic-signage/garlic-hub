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
use App\Framework\Security\CsrfToken;
use App\Framework\System\AppVersion;
use App\Framework\System\SelfUpdate;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Auth\UserSession;
use App\Modules\System\Services\AclValidator;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Exception as PhpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * The update of garlic-hub for the module admin of system, only where garlic-hub-update is installed
 * (Raspberry Pi image). The button only requests the update, see SelfUpdate.
 */
readonly class ShowUpdateController
{
	private const string MODULE_NAME = 'system';

	public function __construct(
		private SelfUpdate $selfUpdate,
		private AppVersion $appVersion,
		private AclValidator $aclValidator,
		private UserSession $userSession,
		private Translator $translator,
		private CsrfToken $csrfToken,
		private Messages $flash
	) {}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		if (!$this->isAllowed())
			return $this->leave($response);

		$status = $this->selfUpdate->readStatus();
		$busy   = $this->selfUpdate->isRequested() || ($status !== null && $status['state'] === 'running');
		$title  = $this->translator->translate('update', self::MODULE_NAME);

		return new PageResponse([
			'main_layout' => ['LANG_PAGE_TITLE' => $title, 'additional_css' => [], 'footer_modules' => []],
			'this_layout' => [
				'template' => 'system/update',
				'data'     => [
					'LANG_PAGE_HEADER'      => $title,
					'CSRF_TOKEN'            => $this->csrfToken->getToken(),
					'LANG_INSTALLED'        => $this->translator->translate('update_installed', self::MODULE_NAME, ['{version}' => $this->appVersion->getVersion()]),
					'LANG_STATUS'           => $this->describe($status),
					'LANG_EXPLANATION'      => $this->translator->translate('update_explanation', self::MODULE_NAME),
					'LANG_BUTTON'           => $this->translator->translate('update_start', self::MODULE_NAME),
					'BUTTON_DISABLED'       => $busy ? 'disabled' : '',
					'is_busy'               => $busy ? [['LANG_RELOAD' => $this->translator->translate('update_reload', self::MODULE_NAME)]] : []
				]
			]
		]);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function request(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		if (!$this->isAllowed())
			return $this->leave($response);

		$status = $this->selfUpdate->readStatus();
		if ($this->selfUpdate->isRequested() || ($status !== null && $status['state'] === 'running'))
		{
			$this->flash->addMessage('error', $this->translator->translate('update_busy', self::MODULE_NAME));
			return $response->withHeader('Location', '/system/update')->withStatus(302);
		}

		try
		{
			$this->selfUpdate->request();
			$this->flash->addMessage('success', $this->translator->translate('update_requested', self::MODULE_NAME));
		}
		catch (CoreException $e)
		{
			$this->flash->addMessage('error', $this->translator->translate('update_not_requested', self::MODULE_NAME, ['{reason}' => $e->getMessage()]));
		}

		return $response->withHeader('Location', '/system/update')->withStatus(302);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	private function isAllowed(): bool
	{
		return $this->selfUpdate->isAvailable() && $this->aclValidator->isModuleAdmin($this->userSession->getUID());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function leave(ResponseInterface $response): ResponseInterface
	{
		$this->flash->addMessage('error', $this->translator->translate('update_not_allowed', self::MODULE_NAME));
		return $response->withHeader('Location', '/')->withStatus(302);
	}

	/**
	 * @param array{state:string, step:string, from:string, to:string, time:string}|null $status
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function describe(?array $status): string
	{
		if ($this->selfUpdate->isRequested())
			return $this->translator->translate('update_state_requested', self::MODULE_NAME);

		if ($status === null)
			return $this->translator->translate('update_state_none', self::MODULE_NAME);

		$step = $status['step'] !== '' ? $this->translator->translate('update_step_' . $status['step'], self::MODULE_NAME) : '';

		return $this->translator->translate('update_state_' . $status['state'], self::MODULE_NAME, [
			'{from}' => $status['from'],
			'{to}'   => $status['to'],
			'{step}' => $step,
			'{time}' => $this->formatTime($status['time'])
		]);
	}

	private function formatTime(string $time): string
	{
		try
		{
			return new DateTimeImmutable($time)->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i');
		}
		catch (PhpException)
		{
			return $time;
		}
	}
}
