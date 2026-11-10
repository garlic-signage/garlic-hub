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
 * The badge of the mutation score (MSI) from the summary of Infection:
 *   php .github/scripts/msi-badge.php <summary.json> <badge.svg>
 * Same template and colors as the coverage badge, so both look alike in the README.
 */
if ($argc !== 3)
{
	fwrite(STDERR, "Usage: php msi-badge.php <summary.json> <badge.svg>\n");
	exit(1);
}

$summary = json_decode((string) file_get_contents($argv[1]), true);
$msi     = is_array($summary) ? ($summary['stats']['msi'] ?? null) : null;
if (!is_int($msi) && !is_float($msi))
{
	fwrite(STDERR, 'No MSI in ' . $argv[1] . "\n");
	exit(1);
}

$color = match (true)
{
	$msi < 40 => '#e05d44',  // red
	$msi < 60 => '#fe7d37',  // orange
	$msi < 75 => '#dfb317',  // yellow
	$msi < 95 => '#97CA00',  // green
	default   => '#4c1'      // bright green
};

$template = (string) file_get_contents(__DIR__ . '/../../vendor/codebtech/coveragebadge/template/badge.svg');
$badge    = str_replace(['{{ coverage }}', '{{ total }}', '{{ color }}'], ['mutation', (string) round($msi), $color], $template);

file_put_contents($argv[2], $badge);
echo 'MSI ' . $msi . "%\n";
