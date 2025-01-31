/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
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



    async fetchData(url, options = {})
    {
        const defaultOptions  = {method: 'GET', headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' } };
        const config          = { ...defaultOptions, ...options };
        const response        = await fetch(url, config);

        this.#checkResponse(response);

		// do not use const response.headers.get('Content-Type'); as
		// supposed alternative.
		// It is not trustable as server could send wrong content type.
        try
        {
            return await response.json()
		}
        catch (e)
        {
            return await response.text();
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

    #checkResponse(response)
    {
        if (response.status === 401)
            throw new Error('Unauthorized - Please log in again.');
        else if (response.status === 500)
            throw new Error('Server error - Try again later.');
        else if (!response.ok)
            throw new Error(`HTTP-Error: ${response.status}`);

    }
}
