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

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\DataUrlDecoder;
use App\Framework\Media\DecodedDataUrlFile;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;

class ExportImage
{
	private string $errorText = '';
	private DecodedDataUrlFile $decodedDataUrlFile;

	public function __construct(private readonly DataUrlDecoder $dataUrlDecoder, private readonly MediaHandlerFactory $mediaHandlerFactory)
	{}

    /**
	 * An image which is no valid data url is no error of the server: nothing is decoded, getErrorText() tells why
	 * and the export methods return false.
	 */
    public function decode(string $base64Encoded): static
	{
		// a failed decode must not export the image of an earlier one
		unset($this->decodedDataUrlFile);
		$this->errorText = '';
		try
		{
			$this->decodedDataUrlFile = $this->dataUrlDecoder->decode($base64Encoded);
		}
		catch (FrameworkException|ModuleException $exception)
		{
			$this->errorText = $exception->getMessage();
		}

		return $this;
	}

	public function exportBase64(int $id): bool
	{
		if (!$this->hasDecodedImage())
			return false;

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
		if (!$this->hasDecodedImage())
			return false;

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

	/**
	 * Without a decoded image the reason stays the one of decode(), or tells that decode() was not called.
	 */
	private function hasDecodedImage(): bool
	{
		if (isset($this->decodedDataUrlFile))
			return true;

		if ($this->errorText === '')
			$this->errorText = 'No image decoded.';

		return false;
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