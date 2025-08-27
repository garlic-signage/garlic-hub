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

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * The TriggerPreparer class is responsible for preparing template data for triggers in the system.
 * It works with translations and organizes data associated with various trigger settings, ensuring
 * localization and modularity of the related information.
 */
class TriggerPreparer
{
	private string $moduleName = 'playlists';
	/** @var array<string,mixed>|array<empty,empty>  */
	private array $templateData = [];


	public function __construct(private readonly Translator $translator) {}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 */
	public function getTemplateData(): array
	{
		return $this->templateData;
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function generate(int $itemId): static
	{
		$this->templateData = [
			'TRIGGER_ITEM_ID' => $itemId,
			'LANG_WALLCLOCK' => $this->translator->translate('wallclock', $this->moduleName),
			'LANG_ADD_WALLCLOCK' => $this->translator->translate('add_wallclock', $this->moduleName),
			'LANG_REMOVE_WALLCLOCK' => $this->translator->translate('remove_wallclock', $this->moduleName),
			'wallclock_weekdays' => $this->generateWeekdays(),
			'LANG_WEEKDAY_AFTER' => $this->translator->translate('after', $this->moduleName),
			'LANG_WEEKDAY_BEFORE' => $this->translator->translate('before', $this->moduleName),
			'LANG_DATETIME' => $this->translator->translate('begin_datetime', $this->moduleName),
			'LANG_REPEATS' => $this->translator->translate('repeats', $this->moduleName),
			'LANG_NO_REPEATS' => $this->translator->translate('no_repeats', $this->moduleName),
			'LANG_INFINITE_REPEATS' => $this->translator->translate('infinite', $this->moduleName),
			'LANG_NUMBER_REPEATS' => $this->translator->translate('number_repeats', $this->moduleName),
			'LANG_EVERY' => $this->translator->translate('every', $this->moduleName),
			'LANG_REPEAT_MINUTES' => $this->translator->translate('minutes', 'main'),
			'LANG_REPEAT_HOURS' => $this->translator->translate('hours', 'main'),
			'LANG_REPEAT_DAYS' => $this->translator->translate('days', 'main'),
			'LANG_REPEAT_WEEKS' => $this->translator->translate('weeks', 'main'),
			'LANG_REPEAT_MONTHS' => $this->translator->translate('months', 'main'),
			'LANG_REPEAT_YEARS' => $this->translator->translate('years', 'main'),
			'LANG_ACCESSKEY' => $this->translator->translate('accesskey', $this->moduleName),
			'LANG_ADD_ACCESSKEY' => $this->translator->translate('add_accesskey', $this->moduleName),
			'LANG_REMOVE_ACCESSKEY' => $this->translator->translate('remove_accesskey', $this->moduleName),
			'LANG_TOUCH' => $this->translator->translate('touch', $this->moduleName),
			'LANG_ADD_TOUCH' => $this->translator->translate('add_touch', $this->moduleName),
			'LANG_REMOVE_TOUCH' => $this->translator->translate('remove_touch', $this->moduleName),
			'LANG_NOTIFY' => $this->translator->translate('notify', $this->moduleName),
			'LANG_ADD_NOTIFY' => $this->translator->translate('add_notify', $this->moduleName),
			'LANG_REMOVE_NOTIFY' => $this->translator->translate('remove_notify', $this->moduleName),
			'LANG_ONLY_ALPHANUMERIC' => $this->translator->translate('only_alphanumeric', $this->moduleName)
		];
		return $this;
	}

	/**
	 * @return list<array<string,string>>
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	private function generateWeekdays(): array
	{
		$langWeekdays = $this->translator->translateArrayForOptions('weekday_selects', 'main');

		$options = [];
		foreach ($langWeekdays as $key => $weekday)
		{
			$options[] = ['WEEKDAY_NUMBER' => $key, 'LANG_WEEKDAY_NAME' => $weekday];
		}

		return $options;
	}



}