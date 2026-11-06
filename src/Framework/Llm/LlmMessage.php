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

namespace App\Framework\Llm;

/**
 * A message of the conversation: a text and the images to it as data urls (data:image/jpeg;base64,...).
 */
final readonly class LlmMessage
{
	public const string ROLE_USER      = 'user';
	public const string ROLE_ASSISTANT = 'assistant';

	/**
	 * @param list<string> $images
	 */
	public function __construct(
		public string $role,
		public string $text,
		public array $images = []
	) {}

	/**
	 * @param list<string> $images
	 */
	public static function user(string $text, array $images = []): self
	{
		return new self(self::ROLE_USER, $text, $images);
	}
}
