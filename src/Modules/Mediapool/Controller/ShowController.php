<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Mediapool\Controller;

use App\Framework\Core\Config\Config;
use App\Modules\Mediapool\Services\NodesService;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * ShowController is responsible for managing the layout and data for the mediapool interface.
 * It integrates with various services and utilizes translation for multi-language support.
 */
class ShowController
{

	private NodesService $nodesService;

	/**
	 * @param NodesService $nodesService
	 */
	public function __construct(NodesService $nodesService)
	{
		$this->nodesService = $nodesService;
	}

	/**
	 * @throws Exception|InvalidArgumentException
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$translator = $request->getAttribute('translator');

		$data = [
			'main_layout' => [
				'LANG_PAGE_TITLE' => $translator->translate('mediapool', 'menu'),
				'additional_css' => [
					'/css/external/bootstrap-icons.min.css',
					'/css/external/wunderbaum.css',
					'/css/external/glightbox.min.css',
					'/css/mediapool/overview.css',
					'/css/mediapool/media-list.css',
					'/css/mediapool/uploads.css'
				],
				'footer_scripts' => [
					'/js/external/glightbox.min.js',
					'/js/external/jszip.min.js'
				],
				'footer_modules' => ['/js/mediapool/init.js']
			],
			'this_layout' => [
				'template' => 'mediapool/overview', // Template-name
				'data' => [
					'LANG_DRAG_AND_DROP' => $translator->translate('drag_and_drop', 'mediapool'),
					'LANG_INSERT_FILES_HERE' => $translator->translate('insert_files_here', 'mediapool'),
					'LANG_START_UPLOAD' => $translator->translate('start_upload', 'mediapool'),
					'LANG_SAVE' => $translator->translate('save', 'main'),
					'LANG_CANCEL' => $translator->translate('cancel', 'main'),
					'LANG_CLOSE' => $translator->translate('close', 'main'),
					'LANG_FOLDER_NAME' => $translator->translate('name', 'main'),
					'LANG_IS_PUBLIC' => $translator->translate('is_public', 'main'),
					'LANG_EDIT' => $translator->translate('edit', 'main'),
					'LANG_CLONE' => $translator->translate('clone', 'main'),
					'LANG_DOWNLOAD' => $translator->translate('download', 'main'),
					'LANG_ADD_SUB_FOLDER' => $translator->translate('add_sub_folder', 'mediapool'),
					'LANG_EDIT_FOLDER' => $translator->translate('edit_folder', 'mediapool'),
					'LANG_REMOVE' => $translator->translate('remove', 'main'),
					'LANG_INFORMATION' => $translator->translate('information', 'main'),
					'LANG_OWNER' => $translator->translate('owner', 'main'),
					'LANG_UPLOADER' => $translator->translate('uploader', 'mediapool'),
					'LANG_FILENAME' => $translator->translate('filename', 'mediapool'),
					'LANG_DESCRIPTION' => $translator->translate('description', 'mediapool'),
					'LANG_MIMETYPE' => $translator->translate('mimetype', 'mediapool'),
					'LANG_FILESIZE' => $translator->translate('filesize', 'mediapool'),
					'LANG_DIMENSIONS' => $translator->translate('dimensions', 'mediapool'),
					'LANG_MEDIA_DURATION' => $translator->translate('media_duration', 'mediapool'),
					'LANG_ENTER_FILE_URL' => $translator->translate('enter_file_url', 'mediapool'),
					'LANG_DETECT_CAMERAS' => $translator->translate('detect_cameras', 'mediapool'),
					'LANG_RECORD_CAMERA' => $translator->translate('record_camera', 'mediapool'),
					'LANG_SELECT_CAMERA' => $translator->translate('select_camera', 'mediapool'),
					'LANG_ACTIVATE_CAMERA' => $translator->translate('activate_camera', 'mediapool'),
					'LANG_CAPTURE_PHOTO' => $translator->translate('capture_photo', 'mediapool'),
					'LANG_START_RECORD_VIDEO' => $translator->translate('start_record_video', 'mediapool'),
					'LANG_STOP_RECORD_VIDEO' => $translator->translate('stop_record_video', 'mediapool'),
					'LANG_RECORD_YOUR_SCREEN' => $translator->translate('record_your_screen', 'mediapool'),
					'LANG_ACTIVATE_SCREENCAST' => $translator->translate('activate_screencast', 'mediapool'),
					'LANG_SEARCH_STOCK_MEDIA' => $translator->translate('search_stock_media', 'mediapool'),
					'LANG_ENTER_SEARCH_TERM' => $translator->translate('enter_search_term', 'mediapool'),
					'LANG_ENTER_API_TOKEN' => $translator->translate('enter_api_token', 'mediapool'),
					'LANG_SEARCH' => $translator->translate('search', 'main'),
					'LANG_MARK_FOR_DOWNLOAD' => $translator->translate('mark_for_download', 'mediapool'),
					'LANG_DOWNLOAD_MARKED' => $translator->translate('download_marked', 'mediapool'),
					'LANG_UPLOAD_LOCAL_FILES' => $translator->translate('upload_local_files', 'mediapool'),
					'LANG_UPLOAD_EXTERNAL_FILES' => $translator->translate('upload_external_files', 'mediapool'),
					'LANG_UPLOAD_CAMERA' => $translator->translate('upload_camera', 'mediapool'),
					'LANG_UPLOAD_SCREENCAST' => $translator->translate('upload_screencast', 'mediapool'),
					'LANG_UPLOAD_STOCK_LIBRARIES' => $translator->translate('upload_stock_libraries', 'mediapool'),
					'LANG_UPLOADED_AT' => $translator->translate('uploaded_at', 'mediapool'),
					'LANG_MEDIA_ORIGIN' => $translator->translate('media_origin', 'mediapool'),
					'LANG_PAGE_URL' => $translator->translate('page_url', 'mediapool'),
					'LANG_STOCK_DESCRIPTION' => $translator->translate('stock_description', 'mediapool'),
					'LANG_STOCK_USERNAME' => $translator->translate('stock_username', 'mediapool'),
					'LANG_STOCK_REALNAME' => $translator->translate('stock_realname', 'mediapool'),
					'LANG_STOCK_USER_URL' => $translator->translate('stock_user_url', 'mediapool'),
					'LANG_CONFIGURATION' => $translator->translate('configuration', 'main'),
					'LANG_IMAGES_SEARCH' => $translator->translate('images_search', 'mediapool'),
					'LANG_VIDEOS_SEARCH' => $translator->translate('videos_search', 'mediapool'),
				]
			]
		];
		$session = $request->getAttribute('session');

		if ($this->nodesService->isModuleAdmin($session->get('user')['UID']))
		{
			$data['this_layout']['data']['add_root_folder'] = [
				'LANG_ADD_ROOT_FOLDER' => $translator->translate('add_root_folder', 'mediapool')
				];
			$data['this_layout']['data']['LANG_ADD_ROOT_FOLDER'] = $translator->translate('add_root_folder', 'mediapool');
		}
		/** @var Config $config */
		$config = $request->getAttribute('config');

		if ($config->getEdition() === Config::PLATFORM_EDITION_ENTERPRISE)
		{
			$data['this_layout']['this_layout'] = ['folder_dialog_visibility' =>
				['LANG_FOLDER_VISIBILITY' => $translator->translate('visibility', 'main'),
					'visibility_options' => $translator->translateArrayForOptions('visibility_selects', 'main')
				]
			];

		}

		$response->getBody()->write(serialize($data));
		return $response->withHeader('Content-Type', 'text/html');
	}
}