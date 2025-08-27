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


namespace App\Modules\Playlists\Helper\ConditionalPlay;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\TemplateEngine\AdapterInterface;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class responsible for preparing template data for conditional play functionality.
 * This class handles template data transformation and rendering.
 */
class TemplatePreparer
{
	/** @var array<string,mixed>|array<empty,empty>  */
	private array $templateData = [];
	private bool $conditionalPlay = false;

	public function __construct(
		private readonly Translator $translator,
		private readonly AdapterInterface $template)
	{}

	/**
	 * @param array<string,mixed> $conditionalPlayData
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function prepare(int $itemId, array $conditionalPlayData): void
	{
		$dateFrom  = $conditionalPlayData['date']['from'] ?? '';
		$dateUntil = $conditionalPlayData['date']['until'] ?? '';
		$timeFrom  = $conditionalPlayData['time']['from'] ?? '';
		$timeUntil = $conditionalPlayData['time']['until'] ?? '';
		$weekdays  = $conditionalPlayData['weekdays'] ?? [];

		$this->templateData = [
			'LANG_CONDITIONAL_PLAY' => $this->translator->translate('conditional_play', 'playlists'),
			'LANG_DATE_PERIOD' => $this->translator->translate('validity_period', 'playlists'),
			'LANG_DAILY' => $this->translator->translate('daily', 'main'),
			'LANG_WEEKDAYS' => $this->translator->translate('weekdays', 'main'),
			'LANG_FROM'   => $this->translator->translate('from', 'main'),
			'LANG_UNTIL'  => $this->translator->translate('until', 'main'),
			'CONDITIONAL_PLAY_ITEM_ID' => $itemId,
			'DATE_FROM'  => $dateFrom,
			'DATE_UNTIL' => $dateUntil,
			'TIME_FROM'  => $timeFrom,
			'TIME_UNTIL' => $timeUntil
		];
		if ($dateFrom !== '' || $dateUntil != '')
		{
			$this->templateData['DATE_PERIOD_CHECKED'] = 'checked';
			$this->conditionalPlay = true;
		}

		if ($timeFrom != '' || $timeUntil != '')
		{
			$this->templateData['TIME_PERIOD_CHECKED'] = 'checked';
			$this->conditionalPlay = true;
		}

		$this->templateData['list_weekdays'] = $this->determineWeekdays($weekdays);

		if ($this->conditionalPlay)
			$this->templateData['CONDITIONAL_PLAY_CHECKED'] = 'checked';
	}

	public function render(): string
	{
		return $this->template->render('playlists/conditional-play', $this->templateData);
	}

	/**
	 * @param array<string,mixed> $weekdays
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	private function determineWeekdays(array $weekdays): array
	{
		$listWeekdays = [];
		foreach($this->translator->translateArrayForOptions('weekday_selects', 'main') as $key => $inner_value)
		{
			$bin = pow(2, (int)$key - 1);
			$weekday = [
				'WEEKDAY_NUMBER' => $bin,
				'WEEKDAY' => $inner_value,
			];

			if (array_key_exists($bin, $weekdays))
			{
				$weekday['WEEK_DAY_CHECKED'] = 'checked';
				$this->conditionalPlay = true;
			}
			$listWeekdays[] = $weekday;
		}

		return $listWeekdays;
	}


}