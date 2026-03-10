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

namespace App\Framework\Media;


use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;

class DataUrlDecoder
{
	public function __construct(
		private readonly MimeTypeService $mimeTypeService,
	) {}

	/**
	 * @throws ModuleException
	 * @throws FrameworkException
	 */
	public function decode(string $dataUrl): DecodedDataUrlFile
    {
        if (!preg_match('#^data:([\w.+-]+/[\w.+-]+);base64,(.*)$#s', $dataUrl, $matches))
            throw new FrameworkException('Data-URL not valid.');

        $base64   = $matches[2];
		if ($base64 === '')
			throw new FrameworkException('Base64-Data are empty.');

		$binary = base64_decode($base64, true);
        if ($binary === false)
            throw new FrameworkException('Base64-Data are not valid.');

		$mimeType  = $this->mimeTypeService->detectFromStringContent($binary);
		$extension = $this->mimeTypeService->determineExtensionByMimeType($mimeType);

        return new DecodedDataUrlFile(
            mimeType: $mimeType,
            extension: $extension,
            binaryContent: $binary,
            size: strlen($binary)
        );
    }
}
