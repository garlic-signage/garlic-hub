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

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The third party libraries in public/ are not known to composer, the SBOM gets them from resources/sbom/frontend.json.
 * This test keeps the list true: every file in the external folders is listed and its sha256 matches.
 * Whoever adds or updates a library has to update the list, version included.
 */
class FrontendSbomTest extends TestCase
{
	private const array EXTERNAL_DIRS = ['public/js/external', 'public/css/external'];

	private string $root;

	/** @var array<string,string> path => sha256 */
	private array $listed = [];

	protected function setUp(): void
	{
		$this->root = dirname(__DIR__, 2);
		$manifest   = json_decode((string) file_get_contents($this->root . '/resources/sbom/frontend.json'), true, 512, JSON_THROW_ON_ERROR);
		foreach ($manifest['components'] as $library)
		{
			foreach ($library['files'] as $path => $sha256)
				$this->listed[$path] = $sha256;
		}
	}

	public function testEveryExternalFileIsListed(): void
	{
		$missing = [];
		foreach (self::EXTERNAL_DIRS as $dir)
		{
			foreach ((array) glob($this->root . '/' . $dir . '/*') as $file)
			{
				$path = substr((string) $file, strlen($this->root) + 1);
				if (!isset($this->listed[$path]))
					$missing[] = $path;
			}
		}

		static::assertSame([], $missing, 'not in resources/sbom/frontend.json');
	}

	public function testListedFilesHaveTheirHash(): void
	{
		$differs = [];
		foreach ($this->listed as $path => $sha256)
		{
			$file = $this->root . '/' . $path;
			if (!is_file($file) || hash_file('sha256', $file) !== $sha256)
				$differs[] = $path;
		}

		static::assertSame([], $differs, 'missing or changed, update resources/sbom/frontend.json');
	}
}
