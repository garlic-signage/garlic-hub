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
export class PasswordToggler
{
	#passwordField = null;
	#togglePassword = null;

	constructor(passwordField, togglePassword)
	{
		this.#passwordField = passwordField;
		this.#togglePassword = togglePassword;
	}

	createEventListeners()
	{
		if (this.#passwordField === null ||this.#togglePassword === null)
			return;

		this.#togglePassword.addEventListener('click', () =>
		{
			const type = this.#passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
			this.#passwordField.setAttribute('type', type);

			if (type === 'password')
			{
				this.#togglePassword.classList.remove('bi-eye-slash-fill');
				this.#togglePassword.classList.add('bi-eye-fill');
			}
			else
			{
				this.#togglePassword.classList.remove('bi-eye-fill');
				this.#togglePassword.classList.add('bi-eye-slash-fill');
			}
		});


	}

}
