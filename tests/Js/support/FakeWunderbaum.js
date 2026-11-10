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
 * Takes the place of public/js/external/wunderbaum.esm.min.js (see deno.json): the real tree needs a window
 * with layout. The tests call the callbacks of the options with the events of Wunderbaum.
 */
export class Wunderbaum
{
	/** the tree created last */
	static last = null;
	/** the node of an element, as Wunderbaum.getNode() finds it */
	static nodes = new Map();

	options;
	calls = [];
	activeNode = null;
	keys = new Map();

	constructor(options)
	{
		this.options = options;
		Wunderbaum.last = this;
	}

	static getNode(element)
	{
		return Wunderbaum.nodes.get(element) ?? null;
	}

	findKey(key)
	{
		return this.keys.get(key) ?? null;
	}

	getActiveNode()
	{
		return this.activeNode;
	}

	addChildren(child)
	{
		this.calls.push(['addChildren', child]);
	}

	filterNodes(text, options)
	{
		this.calls.push(['filterNodes', text, options]);
	}

	setOption(name, value)
	{
		this.calls.push(['setOption', name]);
		this.options[name] = value;
	}
}
