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

use Mustache\Engine;
use Mustache\Loader\FilesystemLoader;

/**
 * Builds the Mustache engine of the application: templates are loaded by name from the template directory,
 * partials from its sub directory "generic".
 *
 * The options are in one place, so a test can use exactly the engine which renders the pages.
 */
class MustacheEngineFactory
{
	/**
	 * @param string      $templateDir directory with the templates, "generic" below it holds the partials
	 * @param string|null $cacheDir    compiled templates are cached as PHP classes there (the key contains a hash of
	 *                                 the source, so changes invalidate it). Without a directory nothing is cached.
	 */
	public function create(string $templateDir, ?string $cacheDir = null): Engine
	{
		$templateDir = rtrim($templateDir, '/');
		$options     = [
			'loader'          => new FilesystemLoader($templateDir),
			'partials_loader' => new FilesystemLoader($templateDir . '/generic'),
			// escape single quotes too, so {{value}} is safe in single quoted attributes (Mustache default is ENT_COMPAT)
			'entity_flags'    => ENT_QUOTES | ENT_SUBSTITUTE,
		];

		if ($cacheDir !== null && $cacheDir !== '')
			$options['cache'] = $cacheDir;

		return new Engine($options);
	}
}
