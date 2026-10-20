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

namespace App\Modules\Playlists\Helper\Settings;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Forms\FormTemplatePreparer;

readonly class TemplatePreparer
{
	public function __construct(private Translator           $translator,
								private FormTemplatePreparer $formTemplatePreparer)
	{}

	/**
	 * @param array<string,mixed> $dataSections
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function prepare(array $dataSections, string $playlistMode): array
	{
		$playlistMode = strtolower($playlistMode);
		$modeName     = $this->translator->translateArrayForOptions('playlist_mode_selects', 'playlists')[$playlistMode] ?? $playlistMode;

		$dataSections['title']             = $this->translator->translate('settings', 'playlists') . ' - ' . $modeName;
		$dataSections['additional_css']    = ['/css/playlists/settings.css'];
		$dataSections['footer_modules']    = ['/js/playlists/settings/init.js'];
		$dataSections['template_name']     = 'playlists/edit';
		$dataSections['form_action']       = '/playlists/settings';
		$dataSections['save_button_label'] = $this->translator->translate('save', 'main');

		return $this->formTemplatePreparer->prepareUITemplate($dataSections);
	}
}
