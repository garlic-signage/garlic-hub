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

import noUiSlider from "../../../../external/nouislider.min.mjs";

export class ConditionalPlaySlider
{
	// the elements
	#slider = null;
	#enabler = null;
	#rangeFrom = null;
	#rangeUntil = null;

	enable()
	{
		this.#slider.noUiSlider.enable();
		const positions = this.getHandlePositions();
		this.#rangeFrom.innerHTML = this.#convertMinutesToTime(Number(positions[0]) * 15)
		this.#rangeUntil.innerHTML = this.#convertMinutesToTime(Number(positions[1]) * 15)
	}

	disable()
	{
		this.#slider.noUiSlider.disable();
		this.#rangeFrom.innerHTML = ""
		this.#rangeUntil.innerHTML = ""
	}

	getHandlePositions()
	{
		return this.#slider.noUiSlider.get(true);
	}

	isEnabled()
	{
		return this.#enabler.checked;
	}

	create(weekdayId, start, end)
	{
		this.#slider = document.getElementById("slider_"+weekdayId);
		noUiSlider.create(this.#slider, {
			start: [start, end],
			connect: true,
			step: 1,
			margin: 1,
			range: {
				'min': [0],
				'max': [96]
			}
		});

		this.#rangeFrom  = document.getElementById("range_from_"+weekdayId );
		this.#rangeUntil = document.getElementById("range_until_"+weekdayId );
		this.#enabler    = document.getElementById("enable_weekday_"+weekdayId);
		this.#toggleEnabled(this.#enabler.checked);
		this.#eventListenToEnabler();

		const that = this;
		this.#slider.noUiSlider.on('update', function (values)
		{
			if (that.isEnabled()) // to prevent a set on init
			{
				that.#rangeFrom.innerHTML = that.#convertMinutesToTime(values[0] * 15);
				that.#rangeUntil.innerHTML = that.#convertMinutesToTime(values[1] * 15);
			}
		});
	}


	#eventListenToEnabler()
	{
		this.#enabler.addEventListener("click",  (e) =>{
			this.#toggleEnabled(e.target.checked);
		});
	}

	#toggleEnabled(checked)
	{
		if (checked)
			this.enable();
		else
			this.disable();
	}

	#convertMinutesToTime(minutes)
	{
		var hours = Math.floor(minutes / 60);
		minutes = minutes % 60;
		if (hours < 10)
			hours = "0" + hours;
		// Stryker disable next-line EqualityOperator: the slider moves in quarters of an hour, 10 minutes never come
		if (minutes < 10)
			minutes = "0" + minutes;
		if (hours === 24)
			hours = "00";

		return hours.toString() + ":" + minutes.toString();
	}

}
