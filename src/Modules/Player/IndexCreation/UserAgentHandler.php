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

namespace App\Modules\Player\IndexCreation;

use App\Modules\Player\Enums\PlayerModel;

/**
 * Handles the parsing and extraction of user agent information for identifying devices and their properties.
 */
class UserAgentHandler
{
	private readonly PlayerDetector $playerDetector;
	private string $uuid;
	private string $firmware;
	private string $name;
	private PlayerModel $model;

	public function __construct(PlayerDetector $playerDetector)
	{
		$this->playerDetector = $playerDetector;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getInfo(): array
	{
		return [
			'uuid'     => $this->getUuid(),
			'firmware' => $this->getFirmware(),
			'name'     => $this->getName(),
			'model'    => $this->getModel(),
		];
	}

	public function getUuid(): string
	{
		return $this->uuid;
	}

	public function getFirmware(): string
	{
		return $this->firmware;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getModel(): PlayerModel
	{
		return $this->model;
	}

	public function parseUserAgent(string $userAgent): static
	{
		// ADAPI/1.0 (UUID:a8294bat-c28f-50af-f94o-800869af5854; NAME:Player with spaces in name) SK8855-ADAPI/2.0.5 (MODEL:XMP-330)
		if (preg_match('/([^ ]+) \(UUID:(.*?); NAME:(.*?)\) (.*?) \(MODEL:(.*?)\)/', $userAgent, $matches))
		{
			$this->uuid     =  $matches[2];
			$this->firmware = $matches[4];
			$this->name     = urldecode($matches[3]);
			$this->model    = $this->playerDetector->detectModelId($matches[5])->getModelId();
		}
		// SmartAPI/1.0 (UUID:cc009f47-5a8d-42b4-af5a-1865710c05ba; NAME:05B200T100223; VERSION:v1.0.16; MODEL:TD-1050)
		elseif (preg_match('/([^ ]+) \(UUID:(.*?); NAME:(.*?); VERSION:(.*?); MODEL:(.*?)\)/', $userAgent, $matches))
		{
			$this->uuid     =  $matches[2];
			$this->firmware = $matches[4];
			$this->name     = urldecode($matches[3]);
			$this->model    = $this->playerDetector->detectModelId($matches[5])->getModelId();
		}
		elseif (preg_match('/([^ ]+) \(UUID:(.*?)\) (.*?)-(.*?) \(MODEL:(.*?)\)/', $userAgent, $matches))
		{
			$this->uuid     =  $matches[2];
			$this->firmware = $matches[4];
			$this->name     = urldecode($matches[3]);
			$this->model    = $this->playerDetector->detectModelId($matches[5])->getModelId();
		}
		else
		{
			$this->uuid     =  '';
			$this->firmware = '';
			$this->name     = '';
			$this->model    = PlayerModel::UNKNOWN;
		}
		return $this;
	}

}