/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

export class FetchClient
{
    #xhr = null;
	#timeout = 6000;

	/**
	 * Milliseconds for a request to the AI: the server waits up to 60 seconds for the answer of the model.
	 */
	static LLM_TIMEOUT = 65000;

	/**
	 * @param options the options of fetch(), and timeout in milliseconds when a request may take longer than usual
	 */
    async fetchData(url, options = {})
    {
		const {timeout, ...fetchOptions} = options;
		const controller = new AbortController();
		const timeoutId = setTimeout(() => controller.abort(), timeout ?? this.#timeout);

		const defaultHeaders = { 'Accept': 'application/json', 'Cache-Control': 'no-cache' };
		const config         = { method: 'GET', ...fetchOptions, headers: { ...defaultHeaders, ...fetchOptions.headers }, signal: controller.signal };

		try
		{
			const response = await this.#fetch(url, config);

			// structured errors like {success: false, error_message: "..."} are
			// returned to the caller, so it can show the message
			const errorBody = await this.#detectErrorBody(response);
			if (errorBody !== null)
				return errorBody;

			this.#checkResponse(response);

			// do not use const response.headers.get('Content-Type'); as
			// supposed alternative.
			// It is not trustable as the server could send the wrong content type.
			// The body can be read once only: as text, then parsed.
			const text = await response.text();
			try
			{
				return JSON.parse(text);
			}
			catch
			{
				return text;
			}
		}
		finally
		{
			clearTimeout(timeoutId);
		}
    }

	/**
	 * Only the errors of the request itself are network errors, not the HTTP errors of the answer.
	 */
	async #fetch(url, config)
	{
		try
		{
			return await fetch(url, config);
		}
		catch (error)
		{
			if (error.name === 'AbortError')
				throw new Error('Timeout: Server did not respond.');

			throw new Error('Network error: ' + error.message);
		}
	}

    initUploadWithProgress()
    {
        this.#xhr = new XMLHttpRequest();
    }

    destroyUploadWithProgress()
    {
        this.#xhr = null;
    }

    getUploadProgressHandle()
    {
        return this.#xhr;
    }

    async uploadWithProgress(url, options = {}, onProgress)
    {
        if (this.#xhr === null)
            throw new Error('Call initUploadWithProgress() first.');

        return new Promise((resolve, reject) => {
            this.#xhr.open(options.method, url, true);

            // Track upload progress
            this.#xhr.upload.onprogress = (event) => {
                if (event.lengthComputable && onProgress)
                {
                    const percentComplete = (event.loaded / event.total) * 100;
                    onProgress(percentComplete);
                }
            };

            // Handle response
            this.#xhr.onload = () => {
                const responseText = this.#xhr.responseText;

                const response = {
                    status: this.#xhr.status,
                    statusText: this.#xhr.statusText,
                    text: () => Promise.resolve(responseText),
                    json: () => new Promise((resolve, reject) => {
                        try
                        {
                            resolve(JSON.parse(responseText));
                        }
                        catch (error)
                        {
                            reject(new Error(`JSON Parse Error: ${error.message}`));
                        }
                    })
                };

                if (this.#xhr.status >= 200 && this.#xhr.status < 300)
                {
                    if (this.#isJSON(responseText))
                        resolve(response.json());
                     else
                        reject(new Error(responseText));
                }
                else if (this.#isStructuredError(this.#xhr.status, responseText))
                    resolve(response.json()); // same as fetchData(): the caller shows the error_message
                else
                    reject(new Error("HTTP-Error: " + this.#xhr.status));
            };

            // Handle errors
            this.#xhr.onerror = () => reject(new Error('Network error occurred.'));
            this.#xhr.onabort = () => reject(new Error('Upload aborted.'));

            this.#xhr.send(options.body);
        });
    }

    #isJSON(str)
    {
        try
        {
            JSON.parse(str);
            return true;
        }
        catch
        {
            return false;
        }
    }

    #isStructuredError(status, responseText)
    {
        if (status < 400 || status >= 500 || status === 401 || !this.#isJSON(responseText))
            return false;

        const body = JSON.parse(responseText);
        return body !== null && typeof body === 'object' && body.success === false;
    }

    async #detectErrorBody(response)
    {
        // 401 keeps its own handling, 5xx are no structured errors
        if (response.status < 400 || response.status >= 500 || response.status === 401)
            return null;

        try
        {
            const body = await response.json();
            if (body !== null && typeof body === 'object' && body.success === false)
                return body;
        }
        catch
        {
            // no JSON body, #checkResponse() throws the HTTP error
        }

        return null;
    }

    #checkResponse(response)
    {
        if (response.status === 401)
            throw new Error('Unauthorized - Please log in again.');
        else if (response.status === 500)
            throw new Error('Server error - Try again later.');
        else if (!response.ok)
            throw new Error(`HTTP-Error: ${response.status}.`);

    }
}
