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

use Dotenv\Dotenv;

require __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv::createImmutable(__DIR__ . '/../', '.env.test');
$dotenv->load();

$systemDir = realpath(__DIR__ . '/tests/');
$paths = [
	'systemDir' => $systemDir,
	'varDir' => $systemDir . '/var',
	'cacheDir' => $systemDir . '/var/cache',
	'logDir' => $systemDir . '/var/log',
	'configDir' => $systemDir . '/config'
];
define('_TestLibPath', $systemDir);
