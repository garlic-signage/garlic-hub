<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
 * Every route in config/route.php names a controller and a method. The router resolves them only when the route is
 * called, a method which does not exist (renamed, never written) is found by the first user. This test finds it.
 */
class RoutesTest extends TestCase
{
	/**
	 * Routes to methods which do not exist (yet). Remove the route from config/route.php and the entry from here.
	 */
	private const array KNOWN_MISSING = [
		'App\\Modules\\Playlists\\Controller\\ShowSettingsController::delete',
		'App\\Modules\\Mediapool\\Controller\\MediaController::add',
		'App\\Modules\\Playlists\\Controller\\TriggerController::fetchMedia',
	];

	/**
	 * @return list<array{string,string,int}> class, method and line of every route
	 */
	private function routes(): array
	{
		$lines = file(__DIR__ . '/../../config/route.php', FILE_IGNORE_NEW_LINES);
		static::assertIsArray($lines);
		$use = [];
		foreach ($lines as $line)
		{
			if (preg_match('/^use ([\\\\\w]+)(?: as (\w+))?;/', $line, $match))
				$use[$match[2] ?? basename(str_replace('\\', '/', $match[1]))] = $match[1];
		}

		$routes = [];
		foreach ($lines as $number => $line)
		{
			if (str_starts_with(ltrim($line), '//') || !preg_match('/resolve\(\[([\\\\\w]+)::class, \'(\w+)\'\]/', $line, $match))
				continue;

			$class    = $match[1];
			$routes[] = [str_starts_with($class, '\\') ? ltrim($class, '\\') : ($use[$class] ?? $class), $match[2], $number + 1];
		}

		return $routes;
	}

	public function testThereAreRoutes(): void
	{
		static::assertGreaterThan(50, count($this->routes()));
	}

	public function testEveryRouteHasItsControllerAndMethod(): void
	{
		$missing = [];
		foreach ($this->routes() as [$class, $method, $line])
		{
			if (in_array($class . '::' . $method, self::KNOWN_MISSING, true))
				continue;

			if (!class_exists($class))
				$missing[] = 'line ' . $line . ': the class ' . $class . ' does not exist';
			elseif (!method_exists($class, $method))
				$missing[] = 'line ' . $line . ': ' . $class . '::' . $method . ' does not exist';
		}

		static::assertSame([], $missing);
	}

	public function testTheKnownMissingMethodsAreStillMissing(): void
	{
		foreach (self::KNOWN_MISSING as $route)
			static::assertFalse(method_exists(...explode('::', $route)), $route . ' exists now: remove it from KNOWN_MISSING.');
	}
}
