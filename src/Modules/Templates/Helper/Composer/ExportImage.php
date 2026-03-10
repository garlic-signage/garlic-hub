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
use App\Framework\Media\DecodedDataUrlFile;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;
use Imagick;

class ExportImage
{
	private string $errorText = '';
	private DecodedDataUrlFile $decodedDataUrlFile;

	public function __construct(private readonly DataUrlDecoder $dataUrlDecoder, private MediaHandlerFactory $mediaHandlerFactory)
	{}

	public function decode(string $base64Encoded): static
	{
		$this->decodedDataUrlFile = $this->dataUrlDecoder->decode($base64Encoded);
		return $this;
	}

	public function exportBase64(int $id): bool
	{
		try
		{

			$mediaHandler     = $this->mediaHandlerFactory->createTemplatesHandler($this->decodedDataUrlFile->mimeType);
			$filePath         = '/'.$mediaHandler->getOriginalPath().'/'.$id.'.'.$this->decodedDataUrlFile->extension;
			$absoluteFilePath = $mediaHandler->getAbsolutePath($filePath);
			$mediaHandler->writeBinaryString($filePath, $this->decodedDataUrlFile->binaryContent);
			$mediaHandler->validateStoredFile($filePath);
			$mediaHandler->createThumbnail($absoluteFilePath);
			return true;
		}
		catch (\Throwable $exception)
		{
			$this->errorText = $exception->getMessage();
			return false;
		}
	}

	public function exportPlaylistItem(int $id): bool
	{
		try
		{

			$mediaHandler     = $this->mediaHandlerFactory->createPlaylistsHandler($this->decodedDataUrlFile->mimeType);
			$filePath         = '/'.$mediaHandler->getOriginalPath().'/'.$id.'.'.$this->decodedDataUrlFile->extension;
			$absoluteFilePath = $mediaHandler->getAbsolutePath($filePath);
			$mediaHandler->writeBinaryString($filePath, $this->decodedDataUrlFile->binaryContent);
			$mediaHandler->validateStoredFile($filePath);
			$mediaHandler->createThumbnail($absoluteFilePath);
			return true;
		}
		catch (\Throwable $exception)
		{
			$this->errorText = $exception->getMessage();
			return false;
		}
	}

	public function getMimeType(): string
	{
		return $this->decodedDataUrlFile->mimeType;
	}

	public function getErrorText(): string
	{
		return $this->errorText;
	}
}