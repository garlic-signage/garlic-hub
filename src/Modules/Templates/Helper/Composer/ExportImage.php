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

use App\Framework\Media\DataUrlDecoder;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;

class ExportImage
{
	private string $errorText = '';

	public function __construct(private readonly DataUrlDecoder $dataUrlDecoder, private MediaHandlerFactory $mediaHandlerFactory)
	{}

	public function exportBase64(int $id, string $base64Encoded): bool
	{
		try
		{
			$decoded = $this->dataUrlDecoder->decode($base64Encoded);

			$mediaHandler     = $this->mediaHandlerFactory->createTemplatesHandler($decoded->mimeType);
			$filePath         = $mediaHandler->getOriginalPath().'/'.$id.'.'.$decoded->extension;
			$mediaHandler->validateStoredFile($filePath);
			$absoluteFilePath = $mediaHandler->getAbsolutePath($filePath);

			$mediaHandler->writeBinaryString($absoluteFilePath, $decoded->binaryContent);
			$mediaHandler->createThumbnail($absoluteFilePath);
			return true;

		}
		catch (\Throwable $exception)
		{
			$this->errorText = $exception->getMessage();
			return false;
		}
	}

	public function getErrorText(): string
	{
		return $this->errorText;
	}
}