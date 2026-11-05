/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
import { LabeledZone } from './LabeledZone.js';
export class LabeledZoneFactory
{
	constructor(defaultOptions = {})
	{
		this.defaultOptions = {
			left: 0,
			top: 0,
			width: 200,
			height: 100,
			fill: "#222222",
			fontSize: 20,
			...defaultOptions,  // Ermöglicht das Überschreiben von Standardwerten bei der Erstellung der Factory
		};
	}

	create(options = {})
	{
		const finalOptions = { ...this.defaultOptions, ...options };

		return new LabeledZone(finalOptions);
	}
}
