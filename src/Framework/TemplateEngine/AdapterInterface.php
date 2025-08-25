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

namespace App\Framework\TemplateEngine;

/**
 * AdapterInterface provides a contract for rendering templates with specific data.
 *
 * This interface ensures a consistent implementation for rendering templates
 * by requiring a render method that accepts a template name and an optional
 * set of data.
 */
interface AdapterInterface
{
	/**
	 * @param array<string,mixed>|array<empty,empty> $data
	 */
	public function render(string $template, array $data = []): string;
}