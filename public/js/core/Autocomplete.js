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

/**
 * The Autocomplete class fetches search suggestions from an API.
 * The suggestions show up in a list.
 *
 * After a keydown it waits 300 ms
 * before getting the suggestions.
 * This stops too many requests
 * while the user is typing.
 */
export class Autocomplete
{
	/**
	 * Debounce delay in milliseconds to prevent frequent API calls.
	 * @type {number}
	 * @static
	 * @default 300
	 */
	static DEBOUNCE_DELAY = 300;

	#autocompleteView  = null;
	#apiEndpoint       = null;
	#debounce_timeout  = null;
	#fieldName         = ""
	#ignoreBlur         = false; // datalist do not fire events sometimes. @see #handleNotSelected()


	/**
	 * @param autocompleteView
	 * @param {string} fieldName - The name of the field used or referenced.
	 * @param {string} apiEndpoint - The API endpoint associated with the instance.
	 * @return {Object} A new instance of the class with the provided field name and API endpoint set.
	 */
	constructor(autocompleteView, fieldName, apiEndpoint)
	{
		this.#autocompleteView = autocompleteView;
		this.#apiEndpoint      = apiEndpoint;
		this.#fieldName        = fieldName;
	}

	initWithExistingFields()
	{
		this.#autocompleteView.initExisting(this.#fieldName);
		if (this.#autocompleteView.exists)
			this.#addListener();
	}

	initWithCreateFields(parent, wrapClassName)
	{
		this.#autocompleteView.initCreate(parent, this.#fieldName, wrapClassName);
		this.#addListener();
		this.#fetchSuggestions();
	}

	getHiddenIdElement()
	{
		return this.#autocompleteView.hiddenElement;
	}

	getEditFieldElement()
	{
		return this.#autocompleteView.inputElement;
	}

	restore(newName)
	{
		return this.#autocompleteView.restore(newName);
	}

	/**
	 * Set the hidden ID, and the text field with values.
	 */
	setInputFields(id, text)
	{
		this.#autocompleteView.hiddenElement.value = id;
		this.#autocompleteView.inputElement.dataset.id = id;
		this.#autocompleteView.inputElement.value = text;
	}

	/**
	 * Clears the values of all input fields.
	 */
	clearAll()
	{
		this.#autocompleteView.datalistElement.innerHTML = '';
		this.#autocompleteView.inputElement.value = 0;
		this.#clearSelection();
	}


	/**
	 * Gets results from the database through the API.
	 * This method is triggered after a debounced delay to reduce the amount of API requests.
	 *
	 * @async
	 * @returns {Promise<void>}
	 */
	async #fetchSuggestions()
	{
/*		if (this.#autocompleteView.inputElement.value.length < 1) // Only fetch if input is not empty
		{
			this.#clearSelection();  // Clear the hidden field when input is cleared
			return;
		}
*/
		try
		{
			const url = this.#apiEndpoint + this.#autocompleteView.inputElement.value;
			const response    = await fetch(url);
			const suggestions = await response.json();
			this.#updateDataList(suggestions);
			this.#autocompleteView.inputElement.dispatchEvent(new Event('focus'));
		}
		catch (error)
		{
			console.error('Error fetching suggestions:', error);
		}
	}

	/**
	 * Update the datalist element with the provided suggestions.
	 * Each suggestion is added as an option element to the datalist.
	 *
	 * @param {Array<Object>} suggestions - An array of suggestion objects containing both display text and a value.
	 */
	#updateDataList(suggestions)
	{
		this.#autocompleteView.datalistElement.innerHTML = ''; // Clear existing options

		suggestions.forEach(suggestion => {
			const option = document.createElement('option');
			option.value = suggestion.name;

			option.setAttribute('data-value', suggestion.id);
			this.#autocompleteView.datalistElement.appendChild(option);
		});
	}

	#addListener()
	{
		this.#autocompleteView.inputElement.addEventListener('input', () =>
			this.#debounce(() => this.#fetchSuggestions(), Autocomplete.DEBOUNCE_DELAY)
		);
		// Add a 'change' listener to detect when a user selects from the datalist.
		this.#autocompleteView.inputElement.addEventListener('change', () => this.#handleSelection());
		this.#autocompleteView.inputElement.addEventListener('blur', () => this.#handleNotSelected());
	}

	/**
	 * Handles the selection of an option from the datalist.
	 * This method checks if the user selected a value that matches an option in the datalist
	 * and then stores the associated `data-value` in the hidden input field.
	 */
	#handleSelection()
	{
		this.#ignoreBlur = true;
		this.#doSelectionChange();
//		this.#autocompleteView.inputElement.blur();
	}


	#handleNotSelected()
	{
		// Crappy datalist input will not trigger an event wenn search term in input is equal to datalist suggestion value
		// we need to secure the value will be selected. The not so elegant solution is to wait for a blur event.
		// But because selection triggers a blur we want to prevent a repeatedly db update
		// For this idiocy we need to set ignoreBlur and check it here
		if (this.#ignoreBlur === true)
		{
			this.#ignoreBlur = false;
			return;
		}

		this.#doSelectionChange();
	}

	/**
	 * Clears the value of the selected hidden field.
	 */
	#clearSelection()
	{
		this.#autocompleteView.hiddenElement.value = '';
	}

	#doSelectionChange()
	{
		const value = this.#autocompleteView.inputElement.value;
		this.#ignoreBlur = true;

		const option = this.#autocompleteView.datalistElement
			.querySelector(`option[value="${CSS.escape(value)}"]`);

		if (!option)
			return;

		const dataValue = option.getAttribute('data-value');
		this.#autocompleteView.hiddenElement.value = dataValue;
		this.#autocompleteView.hiddenElement.dispatchEvent(new Event('selectedFromDataList'));
		this.#autocompleteView.inputElement.dataset.id = dataValue;
	}

	/**
	 * Debounce the provided function, delaying its execution by the specified delay time.
	 * Ensures that the function is only called after the user stops typing for the delay period.
	 *
	 * @private
	 * @param {Function} func - The function to debounce.
	 * @param {number} delay - The delay in milliseconds.
	 */
	#debounce(func, delay)
	{
		clearTimeout(this.#debounce_timeout);  // Clear any existing timeout
		this.#debounce_timeout = setTimeout(func, delay);  // Set a new timeout
	}
}
