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


namespace App\Modules\Templates\Helper\Composer;

use App\Framework\Core\Translate\Translator;
use App\Framework\Utils\Forms\FormTemplatePreparer;

class TemplatePreparer
{

	public function __construct(private readonly Translator $translator) {}

	public function replace(int $id, $playlistId = 0): array
	{
		$templateComposer = [
			'LANG_MOVE_BACKGROUND' => $this->translator->translate('move_background', 'templates'),
			'LANG_MOVE_BACK' => $this->translator->translate('move_back', 'templates'),
			'LANG_MOVE_FRONT' => $this->translator->translate('move_front', 'templates'),
			'LANG_MOVE_FOREGROUND' => $this->translator->translate('move_foreground', 'templates'),
			'LANG_INSERT_IMAGE' => $this->translator->translate('insert_image', 'templates'),
			'LANG_INSERT_TEXT' => $this->translator->translate('insert_textbox', 'templates'),
			'LANG_INSERT_SHAPE' => $this->translator->translate('insert_shape', 'templates'),
			'LANG_INSERT_CIRCLE' => $this->translator->translate('insert_circle', 'templates'),
			'LANG_INSERT_TRIANGLE' => $this->translator->translate('insert_triangle', 'templates'),
			'LANG_INSERT_RECTANGLE' => $this->translator->translate('insert_rectangle', 'templates'),
			'LANG_INSERT_PENTAGON' => $this->translator->translate('insert_pentagon', 'templates'),
			'LANG_INSERT_HEXAGON' => $this->translator->translate('insert_hexagon', 'templates'),
			'LANG_INSERT_OCTAGON' => $this->translator->translate('insert_octagon', 'templates'),
			'LANG_UNDO' => $this->translator->translate('undo', 'main'),
			'LANG_REDO' => $this->translator->translate('redo', 'main'),
			'LANG_FONT_FAMILY' => $this->translator->translate('font_family', 'templates'),
			'LANG_UPLOAD_FONT' => $this->translator->translate('upload_font', 'templates'),
			'LANG_SELECT_COLOR' => $this->translator->translate('select_color', 'templates'),
			'LANG_ALIGN_TEXT' => $this->translator->translate('align_text', 'templates'),
			'LANG_BOLD' => $this->translator->translate('bold', 'templates'),
			'LANG_ITALIC' => $this->translator->translate('italic', 'templates'),
			'LANG_UNDERLINE' => $this->translator->translate('underline', 'templates'),
			'LANG_GROUP_UNGROUP' => $this->translator->translate('group_ungroup', 'templates'),
			'LANG_OPAC	ITY' => $this->translator->translate('opacity', 'templates'),
			'LANG_OUTLINE_COLOR' => $this->translator->translate('outline_color', 'templates'),
			'LANG_OUTLINE_STRENGTH' => $this->translator->translate('outline_strength', 'templates'),
			'LANG_ALIGN_OBJECT' => $this->translator->translate('align_object', 'templates'),
			'LANG_ALIGN_LEFT' => $this->translator->translate('align_left', 'templates'),
			'LANG_ALIGN_CENTER' => $this->translator->translate('align_center', 'templates'),
			'LANG_ALIGN_RIGHT' => $this->translator->translate('align_right', 'templates'),
			'LANG_ALIGN_TOP' => $this->translator->translate('align_top', 'templates'),
			'LANG_ALIGN_MIDDLE' => $this->translator->translate('align_middle', 'templates'),
			'LANG_ALIGN_BOTTOM' => $this->translator->translate('align_bottom', 'templates'),
			'LANG_RESOLUTION' => $this->translator->translate('resolution', 'templates'),
			'LANG_ZOOM' => $this->translator->translate('zoom', 'main'),
			'LANG_DUBLICATE' => $this->translator->translate('duplicate', 'templates'),
			'LANG_DELETE' => $this->translator->translate('delete', 'main'),
			'LANG_REPLACE_IMAGE' => $this->translator->translate('replace_image', 'templates'),
			'LANG_LOCK' => $this->translator->translate('lock', 'templates'),
			'LANG_UNLOCK' => $this->translator->translate('unlock', 'templates'),
			'LANG_INSERT' => $this->translator->translate('insert', 'main'),
			'LANG_SAVE' => $this->translator->translate('save', 'main'),
			'LANG_CLOSE' => $this->translator->translate('close', 'main'),
			'LANG_CONFIRM_CLOSE_EDITOR' => $this->translator->translate('confirm_close_composer', 'templates'),
			'LANG_ADD_MEDIA' => $this->translator->translate('add', 'main'),
			'LANG_APPLY_MEDIA' => $this->translator->translate('apply', 'main'),
			'LANG_CANCEL' => $this->translator->translate('cancel', 'main'),
			'LANG_TRANSFER' => $this->translator->translate('transfer', 'main'),
		];

		if ($playlistId > 0)
		{
			$templateComposer['reset'] = [
				'LANG_RESET' => $this->translator->translate('reset', 'templates')
			];
			$templateComposer['is_playlist_item'] = ['ITEM_ID' => $id, 'PLAYLIST_ID' => $playlistId];
		}
		else
		{
			$templateComposer['is_admin'] = ['TEMPLATE_ID' => $id];
		}

		return $templateComposer;
	}

	public function prepare(String $name, array $dataSections): array
	{
		$title = $this->translator->translate('composer', 'templates').': '.$name;
		$dataSections['LANG_PAGE_HEADER'] = $title;

		return [
			'main_layout' => [
				'LANG_PAGE_TITLE' => $title,
				'additional_css'  => [
					'/css/templates/canvas.css',
					'/css/external/wunderbaum.css',
					'/css/mediapool/selector.css'
				],
				'footer_scripts' => [
					'/js/external/fabric.min.js',
					'/js/templates/canvas-composer/fonts_preview.js',
					'/js/templates/canvas-composer/fontfaceobserver.js',
					'/js/templates/canvas-composer/UndoRedo.js'
				],
				'footer_modules'   => ['/js/templates/canvas-composer/init.js']
			],
			'this_layout' => [
				'template' => 'templates/canvas',
				'data' => $dataSections
			]
		];
	}
}