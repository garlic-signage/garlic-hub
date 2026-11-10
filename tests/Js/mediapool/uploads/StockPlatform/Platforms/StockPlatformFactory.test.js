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

import {assertEquals, assertInstanceOf, assertStrictEquals, assertThrows} from "@std/assert";
import {FakeFetchClient}                                                  from "@support/FakeFetchClient.js";
import {StockPlatformFactory}                                             from "@js/mediapool/uploads/StockPlatform/Platforms/StockPlatformFactory.js";
import {PexelsPlatform}                                                   from "@js/mediapool/uploads/StockPlatform/Platforms/PexelsPlatform.js";
import {PixabayPlatform}                                                  from "@js/mediapool/uploads/StockPlatform/Platforms/PixabayPlatform.js";
import {UnsplashPlatform}                                                 from "@js/mediapool/uploads/StockPlatform/Platforms/UnsplashPlatform.js";

Deno.test('Unsplash, Pixabay and Pexels are registered with the fetch client', () =>
{
	const client  = new FakeFetchClient({});
	const factory = new StockPlatformFactory(client);

	assertEquals(Object.keys(factory.platforms), ['Unsplash', 'Pixabay', 'Pexels']);
	assertInstanceOf(factory.selectPlatform('Unsplash'), UnsplashPlatform);
	assertInstanceOf(factory.selectPlatform('Pixabay'), PixabayPlatform);
	assertInstanceOf(factory.selectPlatform('Pexels'), PexelsPlatform);
	for (const platform of Object.values(factory.platforms))
		assertStrictEquals(platform.fetchClient, client);
});

Deno.test('a platform is the same object every time it is selected', () =>
{
	const factory = new StockPlatformFactory(new FakeFetchClient({}));

	assertStrictEquals(factory.selectPlatform('Pexels'), factory.selectPlatform('Pexels'));
});

Deno.test('another platform can be registered', () =>
{
	const factory  = new StockPlatformFactory(new FakeFetchClient({}));
	const platform = {name: 'Other'};

	factory.registerPlatform('Other', platform);

	assertStrictEquals(factory.selectPlatform('Other'), platform);
});

Deno.test('an unknown platform cannot be selected', () =>
{
	const factory = new StockPlatformFactory(new FakeFetchClient({}));

	assertThrows(() => factory.selectPlatform('-'), Error, 'Platform - not registered.');
});
