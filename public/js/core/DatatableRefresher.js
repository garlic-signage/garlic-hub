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
'use strict';

/**
 * Loads the current page of a datatable again after a change, e.g. a delete, and replaces the rows, the count and
 * the pager. The server renders only the content for the header X-Page-Fragment, without the layout. The filters
 * come from the URL or the session, like with a reload. The rest of the page and its listeners stay.
 */
export class DatatableRefresher
{
	static FRAGMENT_HEADER = "X-Page-Fragment";
	static PARTS           = [".results-listing", "#form_elements_search .results-count", "#form_elements_search .results-pager"];

	/**
	 * @return {Promise<boolean>} false when the table could not be loaded
	 */
	static async refresh()
	{
		const url = new URL(window.location.href);
		let page  = await DatatableRefresher.#load(url);
		if (page === null)
			return false;

		// the last row of the last page is gone: the page before
		const current = Number(page.getElementById("elements_page")?.value ?? 1);
		if (page.querySelector(".results-body") === null && current > 1)
		{
			url.searchParams.set("elements_page", String(current - 1));
			page = await DatatableRefresher.#load(url);
			if (page === null)
				return false;
			window.history.replaceState(null, "", url);
		}

		for (const selector of DatatableRefresher.PARTS)
		{
			const part = page.querySelector(selector);
			if (part !== null)
				document.querySelector(selector)?.replaceWith(document.importNode(part, true));
		}
		const pageInput = document.getElementById("elements_page");
		if (pageInput !== null)
			pageInput.value = page.getElementById("elements_page")?.value ?? pageInput.value;

		return true;
	}

	/**
	 * @return {Promise<Document|null>}
	 */
	static async #load(url)
	{
		try
		{
			const response = await fetch(url, {headers: {[DatatableRefresher.FRAGMENT_HEADER]: "content"}, credentials: "same-origin"});
			if (!response.ok || response.redirected)
				return null;

			const page = new DOMParser().parseFromString(await response.text(), "text/html");
			return page.querySelector(".results-listing") !== null ? page : null;
		}
		catch
		{
			return null;
		}
	}
}
