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


namespace App\Modules\Playlists\Helper\Trigger;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\TemplateEngine\AdapterInterface;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * This class is responsible for preparing and rendering templates by utilizing a provided AdapterInterface and TriggerPreparer.
 */
readonly class TemplatePreparer
{
	public function __construct(private AdapterInterface $template, private TriggerPreparer $triggerPreparer)
	{
	}

	/**
	 * @param int $itemId
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function prepare(int $itemId): void
	{
		$this->triggerPreparer->generate($itemId);
	}

	public function render(): string
	{
		return $this->template->render('playlists/trigger', $this->triggerPreparer->getTemplateData());
	}



}