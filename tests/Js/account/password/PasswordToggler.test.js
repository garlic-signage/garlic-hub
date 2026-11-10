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
import {assertEquals}    from "@std/assert";
import {createDom}       from "@support/dom.js";
import {PasswordToggler} from "@js/account/password/PasswordToggler.js";

// the field of PasswordRenderer
const PAGE = '<div class="password-container"><input type="password" id="password"><span class="toggle-password bi bi-eye-fill" id="toggle_password"></span></div>';

function state(document)
{
	return [document.getElementById('password').getAttribute('type'), document.getElementById('toggle_password').className];
}

Deno.test('a click shows the password, the next hides it again', () =>
{
	using dom = createDom(PAGE);
	new PasswordToggler(dom.document.getElementById('password'), dom.document.getElementById('toggle_password')).createEventListeners();
	const toggle = dom.document.getElementById('toggle_password');

	toggle.dispatchEvent(new Event('click'));
	assertEquals(state(dom.document), ['text', 'toggle-password bi bi-eye-slash-fill']);

	toggle.dispatchEvent(new Event('click'));
	assertEquals(state(dom.document), ['password', 'toggle-password bi bi-eye-fill']);
});

Deno.test('without the field or the toggle nothing happens', () =>
{
	using dom = createDom(PAGE);
	const field  = dom.document.getElementById('password');
	const toggle = dom.document.getElementById('toggle_password');

	new PasswordToggler(null, toggle).createEventListeners();
	new PasswordToggler(field, null).createEventListeners();
	toggle.dispatchEvent(new Event('click'));

	assertEquals(state(dom.document), ['password', 'toggle-password bi bi-eye-fill']);
});
