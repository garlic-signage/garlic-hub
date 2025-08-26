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

namespace App\Modules\Player\IndexCreation\Builder;

use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\Enums\IndexSections;
use App\Modules\Player\Enums\TemplateIndexFiles;
use App\Modules\Player\IndexCreation\Builder\Preparers\PlaylistPreparer;
use App\Modules\Player\IndexCreation\Builder\Preparers\PreparerFactory;
use App\Modules\Playlists\Collector\Contracts\PlaylistStructureInterface;
use Exception;

/**
 * Handles the preparation of template data for various types of media players
 * and playlists by utilizing specific preparer instances.
 */
class TemplatePreparer
{
	private PlayerEntity $playerEntity;
	private PlaylistStructureInterface $playlistStructure;
	private readonly PreparerFactory $preparerFactory;

	/** @var list<array<string,mixed>> */
	private array $meta = [];
	/** @var list<array<string,mixed>> */
	private array $subscriptions = [];
	/** @var list<array<string,mixed>> */
	private array $layout = [];
	/** @var list<array<string,mixed>> */
	private array $standby_times = [];
	/** @var list<array<string,mixed>> */
	private array $playlist = [];
	/** @var array<string, mixed>  */
	private array $templateData;

	public function __construct(PreparerFactory $preparerFactory)
	{
		$this->preparerFactory = $preparerFactory;
	}

	public function setPlayerEntity(PlayerEntity $playerEntity): static
	{
		$this->playerEntity = $playerEntity;
		return $this;
	}

	public function setPlaylistStructure(PlaylistStructureInterface $playlistStructure): static
	{
		$this->playlistStructure = $playlistStructure;
		return $this;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getTemplateData(): array
	{
		return $this->templateData;
	}

	/**
	 * @throws Exception
	 */
	public function prepare(TemplateIndexFiles $indexFile): static
	{
		switch ($indexFile)
		{
			case TemplateIndexFiles::GARLIC:
				$this->prepareForGarlic();
				break;
			case TemplateIndexFiles::XMP2XXX:
				$this->prepareForIAdea();
				break;
			case TemplateIndexFiles::SIMPLE:
				$this->prepareForSimple();
				break;
		}
		return $this;
	}

	/**
	 * @throws Exception
	 */
	private function prepareForGarlic(): void
	{
		$this->prepareStandards();
		$this->subscriptions = $this->preparerFactory->create(IndexSections::SUBSCRIPTIONS, $this->playerEntity)->prepare();
		$this->standby_times = $this->preparerFactory->create(IndexSections::STANDBY_TIMES, $this->playerEntity)->prepare();
		$playlist            = $this->preparerFactory->create(IndexSections::PLAYLIST, $this->playerEntity);

		if ($playlist instanceof PlaylistPreparer)
			$playlist->setPlaylistStructure($this->playlistStructure)->setIsSimple(false);

		$this->playlist = $playlist->prepare();
		$this->setTemplateData();
	}

	/**
	 * @throws Exception
	 */
	private function prepareForIAdea(): void
	{
		$this->prepareForGarlic();
	}

	/**
	 * @throws Exception
	 */
	private function prepareForSimple(): void
	{
		$this->prepareStandards();

		$playlist =	$this->preparerFactory->create(IndexSections::PLAYLIST, $this->playerEntity);
		if ($playlist instanceof PlaylistPreparer)
			$playlist->setPlaylistStructure($this->playlistStructure)->setIsSimple(true);

		$this->playlist = $playlist->prepare();
		$this->setTemplateDataSimple();
	}

	/**
	 * @throws Exception
	 */
	private function prepareStandards(): void
	{
		$this->meta   = $this->preparerFactory->create(IndexSections::META, $this->playerEntity)->prepare();
		$this->layout = $this->preparerFactory->create(IndexSections::LAYOUT, $this->playerEntity)->prepare();
	}

	private function setTemplateDataSimple(): void
	{
		$this->templateData = [
			'meta' => $this->meta,
			'layout' => $this->layout,
			'playlist' => $this->playlist,
		];
	}

	private function setTemplateData(): void
	{
		$this->templateData = [
			'meta' => $this->meta,
			'subscriptions' => $this->subscriptions,
			'layout' => $this->layout,
			'standby_times' => $this->standby_times,
			'playlist' => $this->playlist,
		];
	}
}