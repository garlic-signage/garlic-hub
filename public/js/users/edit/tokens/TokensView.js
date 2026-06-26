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
export class TokensView
{
	#copyVerificationLink = document.getElementsByClassName("copy-verification-link");
	#deleteToken          = document.getElementsByClassName("delete-verification-link");
	#refreshToken         = document.getElementsByClassName("refresh-verification-link");
	#UID                  = document.getElementById("UID");

	get copyVerificationLink()
	{
		return this.#copyVerificationLink;
	}

	buildVerificationLink(id, token)
	{
		const el = document.getElementById(id);
		el.value = el.value.replace(/token=[^&]*/, 'token=' + token);
	}

	get deleteToken()
	{
		return this.#deleteToken;
	}

	get refreshToken()
	{
		return this.#refreshToken;
	}

	getUIDValue()
	{
		return this.#UID.value;
	}
}
