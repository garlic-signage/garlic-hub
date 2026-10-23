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

use App\Framework\Helper\Json;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Auth\UserSession;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Config\Config;
use App\Modules\Mediapool\Services\NodesService;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ShowController is responsible for managing the layout and data for the mediapool interface.
 * It integrates with various services and utilizes translation for multi-language support.
 */
class ShowController
{

	private NodesService $nodesService;
	private UserSession $userSession;
	private Translator $translator;
	private Config $config;

	public function __construct(NodesService $nodesService, UserSession $userSession, Translator $translator, Config $config)
	{
		$this->nodesService = $nodesService;
		$this->userSession  = $userSession;
		$this->translator   = $translator;
		$this->config       = $config;
	}

	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$data = [
			'main_layout' => [
				'LANG_PAGE_TITLE' => $this->translator->translate('mediapool', 'menu'),
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
					'LANG_DRAG_AND_DROP' => $this->translator->translate('drag_and_drop', 'mediapool'),
					'LANG_INSERT_FILES_HERE' => $this->translator->translate('insert_files_here', 'mediapool'),
					'LANG_START_UPLOAD' => $this->translator->translate('start_upload', 'mediapool'),
					'LANG_SAVE' => $this->translator->translate('save', 'main'),
					'LANG_CANCEL' => $this->translator->translate('cancel', 'main'),
					'LANG_CLOSE' => $this->translator->translate('close', 'main'),
					'LANG_FOLDER_NAME' => $this->translator->translate('name', 'main'),
					'LANG_IS_PUBLIC' => $this->translator->translate('is_public', 'main'),
					'LANG_EDIT' => $this->translator->translate('edit', 'main'),
					'LANG_CLONE' => $this->translator->translate('clone', 'main'),
					'LANG_DOWNLOAD' => $this->translator->translate('download', 'main'),
					'LANG_ADD_SUB_FOLDER' => $this->translator->translate('add_sub_folder', 'mediapool'),
					'LANG_EDIT_FOLDER' => $this->translator->translate('edit_folder', 'mediapool'),
					'LANG_REMOVE' => $this->translator->translate('remove', 'main'),
					'LANG_INFORMATION' => $this->translator->translate('information', 'main'),
					'LANG_OWNER' => $this->translator->translate('owner', 'main'),
					'LANG_UPLOADER' => $this->translator->translate('uploader', 'mediapool'),
					'LANG_FILENAME' => $this->translator->translate('filename', 'mediapool'),
					'LANG_DESCRIPTION' => $this->translator->translate('description', 'mediapool'),
					'LANG_MIMETYPE' => $this->translator->translate('mimetype', 'mediapool'),
					'LANG_FILESIZE' => $this->translator->translate('filesize', 'mediapool'),
					'LANG_DIMENSIONS' => $this->translator->translate('dimensions', 'mediapool'),
					'LANG_MEDIA_DURATION' => $this->translator->translate('media_duration', 'mediapool'),
					'LANG_ENTER_FILE_URL' => $this->translator->translate('enter_file_url', 'mediapool'),
					'LANG_DETECT_CAMERAS' => $this->translator->translate('detect_cameras', 'mediapool'),
					'LANG_RECORD_CAMERA' => $this->translator->translate('record_camera', 'mediapool'),
					'LANG_SELECT_CAMERA' => $this->translator->translate('select_camera', 'mediapool'),
					'LANG_ACTIVATE_CAMERA' => $this->translator->translate('activate_camera', 'mediapool'),
					'LANG_CAPTURE_PHOTO' => $this->translator->translate('capture_photo', 'mediapool'),
					'LANG_START_RECORD_VIDEO' => $this->translator->translate('start_record_video', 'mediapool'),
					'LANG_STOP_RECORD_VIDEO' => $this->translator->translate('stop_record_video', 'mediapool'),
					'LANG_RECORD_YOUR_SCREEN' => $this->translator->translate('record_your_screen', 'mediapool'),
					'LANG_ACTIVATE_SCREENCAST' => $this->translator->translate('activate_screencast', 'mediapool'),
					'LANG_SEARCH_STOCK_MEDIA' => $this->translator->translate('search_stock_media', 'mediapool'),
					'LANG_ENTER_SEARCH_TERM' => $this->translator->translate('enter_search_term', 'mediapool'),
					'LANG_ENTER_API_TOKEN' => $this->translator->translate('enter_api_token', 'mediapool'),
					'LANG_SEARCH' => $this->translator->translate('search', 'main'),
					'LANG_MARK_FOR_DOWNLOAD' => $this->translator->translate('mark_for_download', 'mediapool'),
					'LANG_DOWNLOAD_MARKED' => $this->translator->translate('download_marked', 'mediapool'),
					'LANG_UPLOAD_LOCAL_FILES' => $this->translator->translate('upload_local_files', 'mediapool'),
					'LANG_UPLOAD_EXTERNAL_FILES' => $this->translator->translate('upload_external_files', 'mediapool'),
					'LANG_UPLOAD_CAMERA' => $this->translator->translate('upload_camera', 'mediapool'),
					'LANG_UPLOAD_SCREENCAST' => $this->translator->translate('upload_screencast', 'mediapool'),
					'LANG_UPLOAD_STOCK_LIBRARIES' => $this->translator->translate('upload_stock_libraries', 'mediapool'),
					'LANG_UPLOADED_AT' => $this->translator->translate('uploaded_at', 'mediapool'),
					'LANG_MEDIA_ORIGIN' => $this->translator->translate('media_origin', 'mediapool'),
					'LANG_PAGE_URL' => $this->translator->translate('page_url', 'mediapool'),
					'LANG_STOCK_DESCRIPTION' => $this->translator->translate('stock_description', 'mediapool'),
					'LANG_STOCK_USERNAME' => $this->translator->translate('stock_username', 'mediapool'),
					'LANG_STOCK_REALNAME' => $this->translator->translate('stock_realname', 'mediapool'),
					'LANG_STOCK_USER_URL' => $this->translator->translate('stock_user_url', 'mediapool'),
					'LANG_CONFIGURATION' => $this->translator->translate('configuration', 'main'),
					'LANG_IMAGES_SEARCH' => $this->translator->translate('images_search', 'mediapool'),
					'LANG_VIDEOS_SEARCH' => $this->translator->translate('videos_search', 'mediapool'),
				]
			]
		];
		if ($this->nodesService->isModuleAdmin($this->userSession->getUID()))
		{
			$data['this_layout']['data']['add_root_folder'] = [
				'LANG_ADD_ROOT_FOLDER' => $this->translator->translate('add_root_folder', 'mediapool')
				];
			$data['this_layout']['data']['LANG_ADD_ROOT_FOLDER'] = $this->translator->translate('add_root_folder', 'mediapool');
		}
		$data['this_layout']['data']['LANG_JS'] = Json::encodeForScript([
			'add_root_folder'    => $this->translator->translate('add_root_folder', 'mediapool'),
			'add_sub_folder'     => $this->translator->translate('add_sub_folder', 'mediapool'),
			'edit_folder'        => $this->translator->translate('edit_folder', 'mediapool'),
			'start_record_video' => $this->translator->translate('start_record_video', 'mediapool'),
			'stop_record_video'  => $this->translator->translate('stop_record_video', 'mediapool')
		]);
		if ($this->config->getEdition() === Config::PLATFORM_EDITION_ENTERPRISE)
		{
			$data['this_layout']['data']['folder_dialog_visibility'] = [
				'LANG_FOLDER_VISIBILITY' => $this->translator->translate('visibility', 'main'),
				'visibility_options'     => $this->translator->translateArrayForOptions('visibility_selects', 'main')
			];

		}

		return new PageResponse($data);
	}
}