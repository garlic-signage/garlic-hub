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

namespace App\Modules\Profile\Helper\Password;

use App\Framework\Core\Config\Config;
use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Services\ProfileService;
use App\Modules\Profile\Services\UserTokenService;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * A facade class that encapsulates interactions between multiple services and components
 * to provide a simplified API for managing user profiles, passwords, and related parameters.
 */
class Facade
{
	private readonly Builder $passwordFormBuilder;
	private readonly ProfileService $profileService;
	private readonly UserTokenService $userTokensService;
	private readonly Parameters $passwordParameters;
	private Translator $translator;
	private Config $config;
	/** @var array{"UID":int, "company_id":int, "username":string, "status":int, "purpose":string}|array{}  */
	private array $user = [];
	private int $myUID;
	public function __construct(Builder $settingsFormBuilder, ProfileService $profileService, UserTokenService $userTokensService, Translator $translator, Parameters $passwordParameters, Config $config)
	{
		$this->passwordFormBuilder = $settingsFormBuilder;
		$this->profileService      = $profileService;
		$this->translator          = $translator;
		$this->userTokensService   = $userTokensService;
		$this->passwordParameters  = $passwordParameters;
		$this->config = $config;
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	public function determineUIDByToken(string $passwordToken): int
	{
		$user = $this->userTokensService->findByToken($passwordToken);
		if ($user === null)
			return 0;

		$this->user = $user;

		if ($this->user['purpose'] !== TokenPurposes::PASSWORD_RESET->value &&
			$this->user['purpose'] !== TokenPurposes::INITIAL_PASSWORD->value)
			return 0;
		$this->profileService->setUID($this->user['UID']);

		return (int) $this->user['UID'];
	}

	public function init(Session $session): void
	{
		/** @var array{UID: int} $user */
		$user = $session->get('user');
		$this->myUID = $user['UID'];
		$this->profileService->setUID($this->myUID);
	}

	/**
	 * @param array<string,mixed> $post
	 * @return array<string,mixed>
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function configureUserFormParameter(array $post): array
	{
		$passwordPattern = $this->config->getConfigValue('password_pattern', 'main');

		return $this->passwordFormBuilder->handleUserInput($post, $passwordPattern);
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 */
	public function storePassword(): int
	{
		$password = $this->passwordParameters->getValueOfParameter(Parameters::PARAMETER_PASSWORD);

		return $this->profileService->updatePassword($this->myUID, $password);
	}

	/**
	 * @return string[]
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function getUserServiceErrors(): array
	{
		$errors     = $this->profileService->getErrorMessages();
		$translated =[];
		foreach ($errors as $error)
		{
			$translated[] = $this->translator->translate($error, 'profile');
		}
		return $translated;
	}

	/**
	 * @return array<string,mixed>
	 *
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function prepareUITemplate(string $passwordToken): array
	{
		if (!empty($passwordToken) && $this->user !== [])
		{
			$this->passwordParameters->addToken();
			$formAction = '/force-password';
			$title = $this->determineTitleWhenForced();
		}
		else
			$title = $this->translator->translate('edit_password', 'profile');

		$passwordPattern = $this->config->getConfigValue('password_pattern', 'main');
		$dataSections                      = $this->passwordFormBuilder->buildForm($passwordPattern, $passwordToken);
		$dataSections['title']             = $title;
		$dataSections['explanations']      = $this->translator->translate('password_explanation', 'profile');
		$dataSections['additional_css']    = ['/css/profile/password.css'];
		$dataSections['footer_modules']    = ['/js/profile/password/init.js'];
		$dataSections['template_name']     = 'profile/edit';
		$dataSections['form_action']       = $formAction ?? '/profile/password';
		$dataSections['save_button_label'] = $this->translator->translate('save', 'main');

		return $dataSections;
	}

	/**
	 * @throws ModuleException|Exception
	 */
	public function storeForcedPassword(int $UID, string $passwordToken): int
	{
		$password = $this->passwordParameters->getValueOfParameter(Parameters::PARAMETER_PASSWORD);

		return $this->profileService->storeNewForcedPassword($UID, $passwordToken, $password);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	private function determineTitleWhenForced(): string
	{
		// @phpstan-ignore-next-line // $this->>user at this point cannot be empty as checked by method @see prepareUITemplate
		if ($this->user['purpose'] === TokenPurposes::INITIAL_PASSWORD->value)
			$title = sprintf($this->translator->translate('initial_password_for', 'profile'), $this->user['username']);
		else
			$title = sprintf($this->translator->translate('edit_password_for', 'profile'), $this->user['username']);

		return $title;
	}

}