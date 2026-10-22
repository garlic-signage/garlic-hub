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

namespace App\Shared\Layout;

use Slim\Flash\Messages;

/**
 * Flash messages of the previous request. Errors get a close button,
 * successes disappear after 5 s (set in CSS).
 */
readonly class FlashMessagesBuilder
{
	private const array TYPES = ['error' => true, 'success' => false];

	public function __construct(private Messages $flash)
	{
	}

	/**
	 * @return list<array{MESSAGE_TYPE: string, has_close_button: bool, MESSAGE_TEXT: mixed}>
	 */
	public function build(): array
	{
		$messages = [];
		foreach (self::TYPES as $type => $hasCloseButton)
		{
			if (!$this->flash->hasMessage($type))
				continue;

			foreach ($this->flash->getMessage($type) as $message)
			{
				$messages[] = ['MESSAGE_TYPE' => $type, 'has_close_button' => $hasCloseButton, 'MESSAGE_TEXT' => $message];
			}
		}

		return $messages;
	}
}
