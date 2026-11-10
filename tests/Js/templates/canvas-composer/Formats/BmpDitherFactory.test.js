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

import {assertInstanceOf} from "@std/assert";
import {BmpDitherFactory} from "@js/templates/canvas-composer/Formats/BmpDitherFactory.js";
import {Spectra6Dither}   from "@js/templates/canvas-composer/Formats/Spectra6Dither.js";

Deno.test('the factory gives the dither of the Spectra 6 displays', () =>
{
	assertInstanceOf(new BmpDitherFactory().create(), Spectra6Dither);
});
