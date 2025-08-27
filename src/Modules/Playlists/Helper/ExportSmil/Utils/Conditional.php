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

namespace App\Modules\Playlists\Helper\ExportSmil\Utils;

/**
 * The Conditional class handles conditions based on date, time, and weekdays,
 * allowing the generation of a specific expression attribute based on the provided input.
 */
class Conditional
{
	/** @var array<string,mixed>  */
	private array $conditional;

	/**
	 * @param array<string,mixed> $conditional
	 */
	public function __construct(array $conditional)
	{
		$this->conditional = $conditional;
	}

	public function hasConditional(): bool
	{
		return !empty($this->conditional);
	}

	public function determineExprAttribute(): string
	{
		if (!$this->hasConditional())
			return '';

		$expr = '';
		if (isset($this->conditional['date']['from']) && $this->conditional['date']['from'] != '')
		{
			$expr .= "adapi-compare(substring-before(adapi-date(), 'T'), '".$this->conditional['date']['from']."')&gt;=0";
		}
		if (isset($this->conditional['date']['until']) && $this->conditional['date']['until'] != '')
		{
			if ($expr != '')
				$expr .= ' and ';

			$expr .= "adapi-compare(substring-before(adapi-date(), 'T'), '".$this->conditional['date']['until']."')&lt;=0";
		}
		if (isset($this->conditional['time']['from']) && $this->conditional['time']['from'] != '')
		{
			if ($expr != '')
				$expr .= ' and ';

			$expr .= "adapi-compare(substring-after(adapi-date(), 'T'), '".$this->conditional['time']['from']."')&gt;=0";
		}

		if (isset($this->conditional['time']['until']) && $this->conditional['time']['until'] != '')
		{
			if ($expr != '')
				$expr .= ' and ';

			$time_until = $this->conditional['time']['until'];
			if ($time_until === '00:00:00')
				$time_until = '23:59:59';

			$expr .= "adapi-compare(substring-after(adapi-date(), 'T'), '".$time_until."')&lt;=0";
		}
		$weekdays = '';


		if ($this->conditional['weekdays'] !== [])
		{
			if ($expr != '')
				$expr .= ' and ';

			$j = 0;
			for ($i = 0; $j < 128; $j=pow(2, $i++))
			{
				if(isset($this->conditional['weekdays'][$j]))
				{
					if ($weekdays != '')
						$weekdays .= ' or ';

					$from =  gmdate('H:i:s',$this->conditional['weekdays'][$j]['from']*15*60);
					$until = gmdate('H:i:s',$this->conditional['weekdays'][$j]['until']*15*60);
					if ($until == '00:00:00')
						$until = '23:59:59';
					$weekdays .= '(' .($i-1)."=adapi-weekday() and adapi-compare(substring-after(adapi-date(), 'T'), '$from')&gt;=0 and adapi-compare(substring-after(adapi-date(), 'T'), '$until')&lt;=0)";
				}
			}
			if ($weekdays != '')
				$weekdays = '('.$weekdays.')';
		}
		$expr = $expr.$weekdays;

		if ($expr != '')
			$expr = 'expr="'.$expr.'" ';

		return $expr;
	}

}