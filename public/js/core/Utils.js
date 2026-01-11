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

export class Utils
{
	static formatSecondsToTime(seconds)
	{
		const hours = Math.floor(seconds / 3600);
		const minutes = Math.floor((seconds % 3600) / 60);
		const secs = seconds % 60;

		const pad = (num) => String(num).padStart(2, '0');

		return `${pad(hours)}:${pad(minutes)}:${pad(secs)}`;
	}

	static formatBytes(bytes)
	{
		const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];

		if (bytes === 0) return '0 Bytes';

		const i = Math.floor(Math.log(bytes) / Math.log(1024));
		const size = (bytes / Math.pow(1024, i)).toFixed(2);

		return `${size} ${sizes[i]}`;
	}

	static async confirmAction(message)
	{
		return confirm(message);
	}
}