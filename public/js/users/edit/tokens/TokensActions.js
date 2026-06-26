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
export class TokensActions
{
	#tokenService;
	#flashMessageHandler;
	#tokenView;

	constructor(tokenView, flashMessageHandler, tokenService)
	{
		this.#tokenService = tokenService;
		this.#flashMessageHandler = flashMessageHandler;
		this.#tokenView = tokenView;
	}

	initActions()
	{
		this.#copyToClipBoardAction();
		this.#deleteAction();
		this.#refreshAction();
	}

	#copyToClipBoardAction()
	{
		for (let i = 0; i < this.#tokenView.copyVerificationLink.length; i++)
		{
			this.#tokenView.copyVerificationLink[i].addEventListener('click', () =>
			{
				const id = this.#tokenView.copyVerificationLink[i].dataset.id;
				const inputField = document.getElementById(id);
				navigator.clipboard.writeText(inputField.value)
					.then(() => {
						const saveColor = inputField.style.backgroundColor;
						inputField.style.backgroundColor = "lightgreen";
						setTimeout(() => {
							inputField.style.backgroundColor = saveColor;
						}, 100);
					})
					.catch(err => {
						this.#tokenView.copyVerificationLink[i].style.backgroundColor = "red";
					});
			});
		}
	}

	#deleteAction()
	{
		for (let i = 0; i < this.#tokenView.deleteToken.length; i++)
		{
			this.#tokenView.deleteToken[i].addEventListener('click', async () =>
			{
				const result = await this.#tokenService.delete(
					this.#tokenView.getUIDValue(),
					this.#tokenView.deleteToken[i].dataset.id
				);
				if (result.success)
					location.reload();
				else
					this.#flashMessageHandler.showError(result.error_message);

			});
		}
	}

	#refreshAction()
	{
		for (let i = 0; i < this.#tokenView.refreshToken.length; i++)
		{
			this.#tokenView.refreshToken[i].addEventListener('click', async () =>
			{
				const purpose = this.#tokenView.refreshToken[i].dataset.id;
				const result = await this.#tokenService.refresh(this.#tokenView.getUIDValue(), purpose);
				if (result.success)
					this.#tokenView.buildVerificationLink(purpose, result.token)
				else
					this.#flashMessageHandler.showError(result.error_message);
			});
		}
	}
}
