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

/**
 * Takes the place of /js/external/pdf.min.mjs (see deno.json): PdfPreview imports it by the address of the web
 * server, which Deno cannot load. The documents are recorded, the pages have the size 600 x 800.
 */
export const GlobalWorkerOptions = {workerSrc: ''};

export const loaded   = [];
export const rendered = [];

export function getDocument(data)
{
	loaded.push(data);
	const page = {
		getViewport: ({scale}) => ({width: 600 * scale, height: 800 * scale}),
		render(renderContext)
		{
			rendered.push(renderContext);
			return {promise: Promise.resolve()};
		}
	};

	return {promise: Promise.resolve({getPage: async (number) => page})};
}
