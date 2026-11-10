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


import {assertEquals}   from "@std/assert";
import {createDom}      from "@support/dom.js";
import {replaceGlobals} from "@support/globals.js";
import {AudioPreview}   from "@js/mediapool/uploads/Preview/AudioPreview.js";

/**
 * Records the read files; load() answers like the browser when the file was read.
 */
class FakeFileReader
{
	static last = null;
	onload = null;
	read   = [];

	constructor()
	{
		FakeFileReader.last = this;
	}

	readAsDataURL(file)
	{
		this.read.push(file);
	}

	load(result)
	{
		this.onload({target: {result}});
	}
}

function createPage()
{
	const dom     = createDom();
	const globals = replaceGlobals({FileReader: FakeFileReader});
	return {[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }};
}

Deno.test('the preview is an audio element, the file is read as data URL', () =>
{
	using _page = createPage();
	const file  = {name: 'lobby', size: 1, type: 'audio/x'};

	const element = new AudioPreview(file).createPreview();

	assertEquals(element.tagName.toLowerCase(), 'audio');
	assertEquals(FakeFileReader.last.read.length, 1);
	assertEquals(FakeFileReader.last.read[0] === file, true);
	assertEquals(element.hasAttribute('src'), false);
});

Deno.test('the read file is played in the audio element with controls', () =>
{
	using _page   = createPage();
	const element = new AudioPreview({name: 'lobby', size: 1, type: 'audio/x'}).createPreview();

	FakeFileReader.last.load('data:audio/x;base64,AAAA');

	assertEquals(element.src, 'data:audio/x;base64,AAAA');
	assertEquals(element.controls, true);
});
