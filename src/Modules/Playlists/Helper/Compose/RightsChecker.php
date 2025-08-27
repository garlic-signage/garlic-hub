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

namespace App\Modules\Playlists\Helper\Compose;

use App\Framework\Core\Config\Config;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Playlists\Services\AclValidator;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class RightsChecker
 *
 * Provides methods to check rights and permissions based on the system's edition and specific business rules.
 * Handles translation and validation of various actions, such as inserting media, playlists, templates, and channels.
 * Utilizes dependencies for access control and translations.
 */
class RightsChecker
{
	CONST string MODULE_NAME = 'playlists';

	private string $edition;
	private readonly Translator $translator;
	private readonly AclValidator $aclValidator;

	/**
	 * @param Translator $translator
	 * @param AclValidator $aclValidator
	 */
	public function __construct(Translator $translator, AclValidator $aclValidator)
	{
		$this->translator   = $translator;
		$this->aclValidator = $aclValidator;
		$this->edition      = $this->aclValidator->getConfig()->getEdition();
	}

	public function getEdition(): string
	{
		return $this->edition;
	}

	/**
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function checkInsertExternalMedia(): array
	{
		if ($this->getEdition() === Config::PLATFORM_EDITION_EDGE)
			return [];

		return [
			'LANG_INSERT_EXTERNAL_MEDIA' => $this->translator->translate('insert_external_media', self::MODULE_NAME),
		];
	}

	/**
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function checkInsertPlaylist(int $timeLimit): array
	{
		if ($timeLimit > 0)
			return [];

		return [
			'LANG_INSERT_PLAYLISTS' => $this->translator->translate('insert_playlists', self::MODULE_NAME),
		];
	}

	/**
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function checkInsertExternalPlaylist(int $timeLimit): array
	{
		if ($this->getEdition() === Config::PLATFORM_EDITION_EDGE || $timeLimit > 0)
			return [];

		return [
			'LANG_INSERT_EXTERNAL_PLAYLISTS' => $this->translator->translate('insert_external_playlists', self::MODULE_NAME)
		];
	}

	/**
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function checkInsertTemplates(): array
	{
		if ($this->getEdition() === Config::PLATFORM_EDITION_EDGE)
			return [];

		return [
			'LANG_INSERT_TEMPLATES' => $this->translator->translate('insert_templates', self::MODULE_NAME),
		];

	}

	/**
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function checkInsertChannels():array
	{
		if ($this->getEdition() === Config::PLATFORM_EDITION_EDGE)
			return [];

		return [
			'LANG_INSERT_CHANNELS' => $this->translator->translate('insert_channels', self::MODULE_NAME),
		];

	}

	/**
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function checkTimeLimit(int $timeLimit): array
	{
		if ($timeLimit === 0)
			return [];

		return [
			'LANG_PLAYLIST_REMAIN_DURATION' => $this->translator->translate('remain_duration', self::MODULE_NAME),
		];
	}

}