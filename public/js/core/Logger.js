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
// Stryker disable all: not exported and not loaded by any page, no test can import it

class Logger
{
    constructor(env, serverUrl = null, level = "debug")
    {
        this.env          = env;
        this.serverUrl    = serverUrl;
        this.levels       = { debug: 0, info: 1, warn: 2, error: 3 };
        this.currentLevel = this.levels[level];
    }

    debug(msg, meta = {})
    {
        this.#log('debug', msg, meta);
    }

    info(msg, meta = {})
    {
        this.#log('info', msg, meta);
    }

    warn(msg, meta = {})
    {
        this.#log('warn', msg, meta);
    }

    error(msg, meta = {})
    {
        this.#log('error', msg, meta);
    }

    #log(level, message, meta = {})
    {
        if (this.levels[level] < this.currentLevel)
            return;

        const logEntry = {
            level,
            message,
            meta,
            timestamp: new Date().toISOString(),
        };

        if (this.env === 'dev')
            console[level](`[${level.toUpperCase()}] ${message}`, meta);

        this.#sendLogToServer(logEntry);
    }

    #sendLogToServer(logEntry)
    {
        fetch(this.serverUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(logEntry),
        }).catch((err) => {
            if (this.env === 'dev')
                console.error('Remote logging failed:', err);

        });
    }
}
