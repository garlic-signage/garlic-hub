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
'use strict';

export class FlashMessageHandler
{
	constructor(containerSelector = 'body')
	{
		this.container = document.querySelector(containerSelector);
		this.template = document.getElementById("flashMessageTemplate");

		if (!this.template)
			throw new Error('Message template not found');

	}

	showSuccess(message)
	{
		this.#showMessage(message, 'success');
	}

	showInformation(message)
	{
		this.#showMessage(message, 'information');
	}

	showError(message)
	{
		this.#showMessage(message, 'error');
	}

	#showMessage(message, type)
	{
		const messageElement = this.#createMessageElement(message, type);
		this.#insertMessage(messageElement);
	}

	#createMessageElement(message, type)
	{
		const templateContent = this.template.content.cloneNode(true);
		const messageBar      = templateContent.querySelector('.message-bar');
		const span            = templateContent.querySelector('span');
		const closeButton     = templateContent.querySelector('.close-button');

		messageBar.classList.add(`message-bar--${type}`);

		span.textContent = message;

		// errors and information are closed by the user, successes fade out (theme-base.css)
		if (type === 'success' && closeButton)
			closeButton.remove();

		return messageBar;
	}

	#insertMessage(messageElement)
	{
		this.container.insertAdjacentElement('afterbegin', messageElement);
	}

	clearAllMessages()
	{
		const messages = this.container.querySelectorAll('.message-bar');
		messages.forEach(message => this.#removeMessage(message));
	}

	#removeMessage(messageElement)
	{
		if (messageElement && messageElement.parentNode) {
			messageElement.style.transition = 'opacity 0.3s ease-out';
			messageElement.style.opacity = '0';

			setTimeout(() => {
				if (messageElement.parentNode) {
					messageElement.parentNode.removeChild(messageElement);
				}
			}, 300);
		}
	}
}