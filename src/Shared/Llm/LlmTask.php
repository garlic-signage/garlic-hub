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

namespace App\Shared\Llm;

/**
 * The tasks the AI helps with. Each has its own model, e.g. a small local one for the texts and another one which
 * sees images. A task without a model is off, its helper does not appear.
 */
final class LlmTask
{
	public const string TEXT             = 'text';
	public const string IMAGE_TAGGING    = 'image_tagging';
	public const string IMAGE_GENERATION = 'image_generation';
	public const array TASKS             = [self::TEXT, self::IMAGE_TAGGING, self::IMAGE_GENERATION];
}
