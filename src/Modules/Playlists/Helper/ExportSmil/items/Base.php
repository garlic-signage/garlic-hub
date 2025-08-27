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

namespace App\Modules\Playlists\Helper\ExportSmil\items;

use App\Framework\Core\Config\Config;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Conditional;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Properties;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Trigger;

/**
 * Base abstract class implementing ItemInterface.
 *
 * Represents a foundational implementation for handling triggers,
 * conditions, and playlist-related functionalities. This class provides
 * mechanisms for determining specific behaviors and attributes within
 * a media system while adhering to XML requirements.
 */
abstract class Base implements ItemInterface
{
	public const string ID_PREFIX        = 'triggerMediaId-';
	public const string TABSTOPS_TAG = "\t\t\t\t\t\t\t";
	public const string TABSTOPS_PARAMETER = "\t\t\t\t\t\t\t\t";
	public const string TABSTOPS_PRIORITY  = "\t\t\t\t";
	/** @var array<string,mixed>  */
	protected array $item = [];
	protected bool $belongsToMasterPlaylist = false;
	protected readonly Config $config;
	protected readonly Trigger $begin;
	protected readonly Trigger $end;
	protected readonly Conditional $conditional;
	/** @var array<int,int>|array<empty,empty>  */
	protected array $touches = [];

	protected string $trigger = '';
	protected Properties $properties;

	/**
	 * @param array<string,mixed> $item
	 */
	public function __construct(Config $config, array $item, Properties $properties, Trigger $begin, Trigger $end, Conditional $conditional)
	{
		$this->config     = $config;
		$this->item       = $item;
		$this->properties = $properties;
		$this->begin      = $begin;
		$this->end        = $end;
		$this->conditional = $conditional;
	}

	/**
	 * @param array<int,int>|array<empty,empty> $touches
	 */
	public function setTouches(array $touches): void
	{
		$this->touches = $touches;
	}

	public function setBelongsToMasterPlaylist(bool $belongsToMasterPlaylist): void
	{
		$this->belongsToMasterPlaylist = $belongsToMasterPlaylist;
	}



	protected function determineBeginEndTrigger(): string
	{
		if (!$this->belongsToMasterPlaylist)
			return '';

		$ret = '';
		if ($this->begin->hasTriggers())
			$ret .= 'begin="'.$this->begin->determineTrigger().'" ';

		if ($this->end->hasTriggers())
			$ret .= 'end="'.$this->end->determineTrigger().'" ';

		return $ret;
	}

	public function getSmilElementTag(): string
	{
		if ($this->belongsToMasterPlaylist && $this->begin->hasTriggers())
			return '';

		return $this->createSmilTag();
	}

	public function getExclusive(): string
	{
		if (!$this->begin->hasTriggers() || !$this->belongsToMasterPlaylist)
			return '';

		$this->trigger = $this->determineBeginEndTrigger();
		$ret           = self::TABSTOPS_PRIORITY.'<priorityClass>'."\n";
		$ret          .= $this->createSmilTag();
		$ret           .= self::TABSTOPS_PRIORITY.'</priorityClass>'."\n";
		$this->trigger = '';

		return $ret;
	}

	protected function collectAttributes(): string
	{
		return $this->insertXmlId().
			$this->conditional->determineExprAttribute().
			$this->determineBeginEndTrigger().
			$this->encodeItemNameForTitleTag();
	}

	protected function insertXmlId(): string
	{
		// We need the ID set explicitly when there is a touch trigger for this item.
		// As playlists can be nested we create otherwise too much double ID
		if (!array_key_exists($this->item['item_id'], $this->touches) || !$this->belongsToMasterPlaylist)
			return '';

		return 'xml:id="'.self::ID_PREFIX.$this->item['item_id'].'" ';

	}

	/**
	 * Ampersand (&) in the title tag can cause an XML validation error
	 * Bug occurred in garlic-player Android Rewe 2021-09-17
	 */
	protected function encodeItemNameForTitleTag(): string
	{
		$title = htmlspecialchars($this->item['item_name'], ENT_XML1);
		return 'title="'.$title.'" ';
	}

	protected function determineDuration(): string
	{
		if ($this->item['item_duration'] > 0)
			return 'dur="'.$this->item['item_duration'].'s" ';

		return '';
	}
}