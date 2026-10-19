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

namespace App\Modules\Mediapool\Helper\Uploads;

use App\Framework\Controller\BaseResponseBuilder;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds the JSON responses for the mediapool upload endpoints.
 */
class ResponseBuilder extends BaseResponseBuilder
{
	public function hostNotAllowed(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'Host not allowed.');
	}

	public function noFilesToUpload(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'No files to upload.');
	}

	public function missingNode(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'node is missing');
	}

	public function noFiles(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'no files');
	}

	public function missingExternalLink(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'No external link submitted.');
	}

	/**
	 * The UploadService returns ['success' => true, 'message' => ...] or
	 * ['success' => false, 'error_message' => ...].
	 *
	 * @param array<string,mixed> $result
	 */
	public function uploadResult(ResponseInterface $response, array $result): ResponseInterface
	{
		if (($result['success'] ?? false) === true)
			return $this->jsonResponseHandler->jsonResponse($response, $result);

		return $this->jsonResponseHandler->jsonError($response, (string) ($result['error_message'] ?? 'Upload failed.'));
	}
}
