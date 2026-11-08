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

// Builds the bundled templates from templates.js in headless Chromium and writes
// <key>.json and <key>.jpg to resources/templates/fabric.
// Usage: node misc/template-generator/generate.mjs   (CHROME_BIN overrides the chromium binary)

import {execFileSync} from 'node:child_process';
import {mkdirSync, writeFileSync} from 'node:fs';
import {dirname, join} from 'node:path';
import {fileURLToPath} from 'node:url';

const generatorDir = dirname(fileURLToPath(import.meta.url));
const targetDir    = join(generatorDir, '../../resources/templates/fabric');
const chrome       = process.env.CHROME_BIN || 'chromium';

const dom = execFileSync(chrome, [
	'--headless=new', '--no-sandbox', '--disable-gpu', '--allow-file-access-from-files',
	'--virtual-time-budget=60000', '--dump-dom', 'file://' + join(generatorDir, 'generate.html')
], {encoding: 'utf8', maxBuffer: 256 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore']});

const match = dom.match(/<pre id="out">([\s\S]*)<\/pre>/);
const out   = (match ? match[1] : '').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
if (!out.startsWith('['))
{
	console.error(out || 'No output of the generator page.');
	process.exit(1);
}

// fabric writes coordinates like 1183.5899999999, two decimals are enough; a scale needs more, a picture of
// 1600 px scaled by 0.525 would be 8 px larger with 0.53
const round = (value, key = '') => Array.isArray(value) ? value.map(v => round(v))
	: value !== null && typeof value === 'object' ? Object.fromEntries(Object.entries(value).map(([k, v]) => [k, round(v, k)]))
	: typeof value === 'number' ? (key === 'scaleX' || key === 'scaleY' ? Math.round(value * 1e6) / 1e6 : Math.round(value * 100) / 100)
	: value;

mkdirSync(targetDir, {recursive: true});
for (const template of JSON.parse(out))
{
	const json = {name: template.name, description: template.description, content: round(template.content)};
	writeFileSync(join(targetDir, template.key + '.json'), JSON.stringify(json, null, '\t') + '\n');
	writeFileSync(join(targetDir, template.key + '.jpg'), Buffer.from(template.image.split(',', 2)[1], 'base64'));
	console.log(template.key);
}
