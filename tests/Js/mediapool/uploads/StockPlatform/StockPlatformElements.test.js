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

import {assertEquals}                 from "@std/assert";
import {assertSameElement, createDom} from "@support/dom.js";
import {StockPlatformElements}        from "@js/mediapool/uploads/StockPlatform/StockPlatformElements.js";

// the tab of the stock platforms in templates/mediapool/overview.mustache
const TAB = `
	<select id="selectStockPlatform"><option value="-">-</option></select>
	<div id="radioSearchConfig" style="display:none;">
		<input type="radio" name="config_search" value="configPlatform" id="checkConfigPlatform">
		<input type="radio" name="config_search" value="searchPlatform" id="checkSearchPlatform" checked>
	</div>
	<div id="radioSelectMediatype" style="display:none;">
		<input type="radio" name="mediatype_search" value="images" id="searchMediatypeImages" checked>
		<input type="radio" name="mediatype_search" value="videos" id="searchMediatypeVideos">
	</div>
	<div id="searchInPlatform" style="display:none;">
		<input type="text" id="searchTerms">
		<button id="searchStockPlatform"></button>
		<div id="searchResultsArea"></div>
	</div>
	<div id="configPlatform" style="display:none;">
		<input type="text" id="platformApiToken">
		<button id="savePlatformApiToken"></button>
	</div>
	<div id="downloadStatus"></div>
	<button id="startPlatformsUpload"></button>`;

const GETTERS = {
	selectStockPlatform: 'selectStockPlatform', radioSearchConfig: 'radioSearchConfig',
	checkSearchPlatform: 'checkSearchPlatform', checkConfigPlatform: 'checkConfigPlatform',
	searchTerms: 'searchTerms', searchStockPlatform: 'searchStockPlatform', platformApiToken: 'platformApiToken',
	savePlatformApiToken: 'savePlatformApiToken', searchInPlatform: 'searchInPlatform', configPlatform: 'configPlatform',
	searchResultsArea: 'searchResultsArea', startPlatformsUpload: 'startPlatformsUpload',
	startFileUpload: 'startPlatformsUpload', downloadStatus: 'downloadStatus', radioSelectMediatype: 'radioSelectMediatype',
	searchMediatypeVideos: 'searchMediatypeVideos', searchMediatypeImages: 'searchMediatypeImages'
};

function display(document, id)
{
	return document.getElementById(id).style.display;
}

Deno.test('the elements of the tab are read from the page', () =>
{
	using dom      = createDom(TAB);
	const elements = new StockPlatformElements();

	for (const [getter, id] of Object.entries(GETTERS))
		assertSameElement(elements[getter], dom.document.getElementById(id), getter);
});

Deno.test('the start button of the BaseUploader is the download button', () =>
{
	using _dom     = createDom(TAB);
	const elements = new StockPlatformElements();

	assertSameElement(elements.startFileUpload, elements.startPlatformsUpload);
});

Deno.test('a page without the tab has no elements', () =>
{
	using _dom     = createDom('');
	const elements = new StockPlatformElements();

	for (const getter of Object.keys(GETTERS))
		assertSameElement(elements[getter], null, getter);
});

Deno.test('the search or the configuration of the platform is shown', () =>
{
	using dom      = createDom(TAB);
	const elements = new StockPlatformElements();

	elements.toggleSearchInPlatform(true);
	assertEquals([display(dom.document, 'searchInPlatform'), display(dom.document, 'configPlatform')], ['block', 'none']);

	elements.toggleSearchInPlatform(false);
	assertEquals([display(dom.document, 'searchInPlatform'), display(dom.document, 'configPlatform')], ['none', 'block']);
});

Deno.test('the choice between images and videos needs a token and a platform with videos', () =>
{
	using dom      = createDom(TAB);
	const elements = new StockPlatformElements();

	for (const [hasToken, hasVideo, shown] of [[true, true, 'block'], [true, false, 'none'], [false, true, 'none'], [false, false, 'none']])
	{
		elements.toogleHasVideo(hasToken, hasVideo);
		assertEquals(display(dom.document, 'radioSelectMediatype'), shown, `${hasToken} / ${hasVideo}`);
	}
});

Deno.test('the switch between search and configuration is shown or hidden', () =>
{
	using dom      = createDom(TAB);
	const elements = new StockPlatformElements();

	elements.toggleSearchConfig(true);
	assertEquals(display(dom.document, 'radioSearchConfig'), 'block');

	elements.toggleSearchConfig(false);
	assertEquals(display(dom.document, 'radioSearchConfig'), 'none');
});

Deno.test('without a platform everything of a platform is hidden', () =>
{
	using dom      = createDom(TAB);
	const elements = new StockPlatformElements();
	elements.toggleSearchConfig(true);
	elements.toggleSearchInPlatform(true);
	elements.toogleHasVideo(true, true);

	elements.hidePlatformOptions();

	for (const id of ['radioSearchConfig', 'radioSelectMediatype', 'searchInPlatform', 'configPlatform'])
		assertEquals(display(dom.document, id), 'none', id);
});

Deno.test('a platform is added to the choice', () =>
{
	using dom      = createDom(TAB);
	const elements = new StockPlatformElements();

	elements.addPlatform('Pexels');

	const options = [...dom.document.querySelectorAll('#selectStockPlatform option')];
	assertEquals(options.map(option => [option.getAttribute('value'), option.textContent]), [['-', '-'], ['Pexels', 'Pexels']]);
});
