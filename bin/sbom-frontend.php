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

/*
 * Adds the third party libraries in public/ (resources/sbom/frontend.json) to the sbom.json of composer.
 * Each library becomes a component with its files as sub components and their sha256.
 * Called by bin/create-sbom.sh after composer CycloneDX:make-sbom.
 */

$root     = dirname(__DIR__);
$sbomFile = $root . '/sbom.json';
$sbom     = json_decode((string) file_get_contents($sbomFile), true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode((string) file_get_contents($root . '/resources/sbom/frontend.json'), true, 512, JSON_THROW_ON_ERROR);

$rootRef    = $sbom['metadata']['component']['bom-ref'];
$components = [];
$refs       = [];
foreach ($manifest['components'] as $library)
{
	$ref   = 'frontend/' . $library['name'] . ($library['version'] !== '' ? '@' . $library['version'] : '');
	$files = [];
	foreach ($library['files'] as $path => $sha256)
	{
		$actual = hash_file('sha256', $root . '/' . $path);
		if ($actual !== $sha256)
		{
			fwrite(STDERR, "$path: sha256 differs from resources/sbom/frontend.json\n");
			exit(1);
		}
		$files[] = [
			'type'   => 'file',
			'name'   => $path,
			'hashes' => [['alg' => 'SHA-256', 'content' => $sha256]],
		];
	}

	$component = ['bom-ref' => $ref, 'type' => 'library', 'name' => $library['name']];
	if ($library['version'] !== '')
		$component['version'] = $library['version'];
	if ($library['license'] !== '')
		$component['licenses'] = str_contains($library['license'], ' OR ')
			? [['expression' => $library['license']]]
			: [['license' => ['id' => $library['license']]]];
	if ($library['purl'] !== '')
		$component['purl'] = $library['purl'];
	if ($library['source'] !== '')
		$component['externalReferences'] = [['type' => 'distribution', 'url' => $library['source']]];
	if (isset($library['note']))
		$component['pedigree'] = ['notes' => $library['note']];
	$component['components'] = $files;

	$components[] = $component;
	$refs[]       = $ref;
}

// run twice without composer in between: replace instead of adding again
$sbom['components'] = array_merge(
	array_values(array_filter($sbom['components'], fn(array $c) => !str_starts_with($c['bom-ref'], 'frontend/'))),
	$components
);
foreach ($sbom['dependencies'] as &$dependency)
{
	if ($dependency['ref'] !== $rootRef)
		continue;

	$own = array_filter($dependency['dependsOn'] ?? [], fn(string $r) => !str_starts_with($r, 'frontend/'));
	$dependency['dependsOn'] = array_merge(array_values($own), $refs);
}
unset($dependency);
foreach ($refs as $ref)
	$sbom['dependencies'][] = ['ref' => $ref];
$sbom['dependencies'] = array_values(array_unique($sbom['dependencies'], SORT_REGULAR));

file_put_contents($sbomFile, json_encode($sbom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo count($components) . " frontend libraries added to sbom.json\n";
