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


namespace App\Modules\Playlists\Helper\Templates;

use App\Framework\Core\Config\Config;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Services\ItemsService;
use App\Modules\Templates\Helper\Composer\BaseTemplateOrchestrator;
use App\Modules\Templates\Helper\Composer\ExportImage;
use App\Modules\Templates\Helper\Composer\TemplatePreparer;

class Orchestrator extends BaseTemplateOrchestrator
{
	private array $item = [];
	public function __construct(
		private readonly ItemsService    $itemsService,
		private readonly UserSession     $userSession,
		private readonly ExportImage         $exportImage,
		private readonly TemplatePreparer    $templatePreparer,
		Config $config
	)
	{
		parent::__construct($config);
		$this->itemsService->setUID($this->userSession->getUID());
	}

	public function checkRights(int $itemId): bool
	{
		try
		{
			$this->item = $this->itemsService->fetchItemById($itemId);
			return true;
		}
		catch (\Throwable $e)
		{
			return false;
		}
	}

	public function build(int $itemId): array
	{
		$replaced = $this->templatePreparer->replace($itemId, $this->item['playlist_id']);
		return $this->templatePreparer->prepare($this->item['playlist_name'].' / '.$this->item['item_name'], $replaced);
	}

	public function save(int $itemId, string $content, string $imageBase64): int
	{
		$content = $this->validate($content);
		if ($content === '')
			return 0;

		if ($this->exportImage->exportBase64($itemId, $imageBase64) === false)
			return 0;

		return $this->itemsService->updateField($itemId, 'content_data', $content);
	}

	public function getContent(): string
	{
		if ( $this->item['content_data'] === null)
			return '';

		$json = json_decode($this->item['content_data'], true);
		$this->restoreSrc($json['objects']);

		return json_encode($json);
	}


}