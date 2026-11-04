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

import {BaseTypes} from "./BaseTypes.js";

export class WallclockEdit extends BaseTypes
{
	#REPEATS_NAME = "repeats-select";
	#editDatetime = null;
	#editWeekday = null;
	#editWeekdayPrefix = null;

	#noRepeatSelect = null;
	#infiniteRepeatSelect = null;
	#numberRepeatsSelect = null;
	#numberRepeats = 0;
	#repeatsPeriods = null;
	#repeatMinutes = 0;
	#repeatHours = 0;
	#repeatDays = 0;
	#repeatWeeks = 0;
	#repeatMonths = 0;
	#repeatYears = 0;

	#has_save_errors = false;


	init(data)
	{
		this.cloneNode("wallclockTemplate");
		this.addRemoveListener();
		this.#editDatetime = this.node.querySelector(".edit-datetime");
		if (data.hasOwnProperty("iso_date"))
			this.#editDatetime.value = data.iso_date;

		this.#editWeekday  = this.node.querySelector(".edit-weekday");
		if (data.hasOwnProperty("weekday"))
			this.#editWeekday.value = data.weekday;
		else
			this.#editWeekday.value = "0";

		this.#editWeekdayPrefix = this.node.querySelector(".edit-weekday-prefix");
		if (data.hasOwnProperty("weekday_prefix"))
			this.#editWeekdayPrefix.value = data.weekday_prefix;

		if (this.#editWeekday.value === "0")
			this.#editWeekdayPrefix.style.visibility = "hidden";

		this.#noRepeatSelect  = this.node.querySelector(".no-repeats-select");
		this.#noRepeatSelect.name = this.#REPEATS_NAME +this.id;
		this.#infiniteRepeatSelect  = this.node.querySelector(".infinite-repeats-select");
		this.#infiniteRepeatSelect.name = this.#REPEATS_NAME +this.id;
		this.#numberRepeatsSelect  = this.node.querySelector(".number-repeats-select");
		this.#numberRepeatsSelect.name = this.#REPEATS_NAME +this.id;
		this.#numberRepeats  = this.node.querySelector(".number-repeats");
		this.#repeatsPeriods  = this.node.querySelector(".edit-repeat-periods");
		if (data.hasOwnProperty("repeat_counts"))
		{
			switch (data.repeat_counts)
			{
				case "-1":
					this.#noRepeatSelect.checked = true;
					this.#repeatsPeriods.style.visibility = "hidden";
					this.#numberRepeats.style.visibility = "hidden";
					break;
				case "0":
					this.#infiniteRepeatSelect.checked = true;
					this.#repeatsPeriods.style.visibility = "visible";
					this.#numberRepeats.style.visibility = "hidden";
					break;
				default:
					this.#numberRepeatsSelect.checked = true;
					this.#repeatsPeriods.style.visibility = "visible";
					this.#numberRepeats.style.visibility = "visible";
					if (data.hasOwnProperty("repeat_counts"))
						this.#numberRepeats.value =  data.repeat_counts;
					break;
			}
		}

		this.#repeatMinutes  = this.node.querySelector(".repeat-minutes");
		if (data.hasOwnProperty("repeat_minutes"))
			this.#repeatMinutes.value = parseInt(data.repeat_minutes);

		this.#repeatHours  = this.node.querySelector(".repeat-hours");
		if (data.hasOwnProperty("repeat_hours"))
			this.#repeatHours.value = data.repeat_hours;

		this.#repeatDays  = this.node.querySelector(".repeat-days");
		if (data.hasOwnProperty("repeat_days"))
			this.#repeatDays.value = data.repeat_days;

		this.#repeatWeeks  = this.node.querySelector(".repeat-weeks");
		if (data.hasOwnProperty("repeat_weeks"))
			this.#repeatWeeks.value = data.repeat_weeks;

		this.#repeatMonths  = this.node.querySelector(".repeat-months");
		if (data.hasOwnProperty("repeat_months"))
			this.#repeatMonths.value = data.repeat_months;

		this.#repeatYears  = this.node.querySelector(".repeat-years");
		if (data.hasOwnProperty("repeat_years"))
			this.#repeatYears.value = data.repeat_years;

		this.#initActions();

	}


	getValues()
	{
		let ar = {}

		let weekday             = this.#editWeekday.value;
		if (weekday === "0")
			ar.weekday = 0;
		else
		{
			let prefix       = this.#editWeekdayPrefix.value;
			if (prefix === "-")
				ar.weekday = "-" + weekday;
			else
				ar.weekday = "+" + weekday;
		}

		ar.iso_date  = this.#editDatetime.value;
		if (ar.iso_date === "")
		{
			this.#editDatetime.style.color = "red";
			this.#has_save_errors = true;
		}
		else
		{
			this.#editDatetime.style.color = "black";
		}

		// datetime-local did not commit seconds when 0. Let's add it here to get a valid ISO date.
		if (ar.iso_date !== "" && ar.iso_date.length < 17)
			ar.iso_date += ':00';

		let el = document.getElementsByName(this.#REPEATS_NAME + this.id);
		let repeat = "-1";
		for(let i = 0; i < el.length; i++)
		{
			if (el[i].checked)
				repeat = el[i].value
		}

		if (repeat > 0)
			ar.repeat_counts = this.#numberRepeats.value;
		else
			ar.repeat_counts = repeat;

		if (repeat === "-1")
		{
			ar.repeat_minutes = 0;
			ar.repeat_hours   = 0;
			ar.repeat_days    = 0;
			ar.repeat_weeks   = 0;
			ar.repeat_months  = 0;
			ar.repeat_years   = 0;
		}
		else
		{
			ar.repeat_minutes = this.#repeatMinutes.value;
			ar.repeat_hours   = this.#repeatHours.value;
			ar.repeat_days    = this.#repeatDays.value;
			ar.repeat_weeks   = this.#repeatWeeks.value;
			ar.repeat_months  = this.#repeatMonths.value;
			ar.repeat_years   = this.#repeatYears.value;
		}

		return ar;
	}

	#initActions()
	{
		this.#editWeekday.addEventListener("change", () =>
		{
			if (this.#editWeekday.value !== "0")
				this.#editWeekdayPrefix.style.visibility = "visible";
			else
				this.#editWeekdayPrefix.style.visibility = "hidden";
		});
		this.#noRepeatSelect.addEventListener("click", () => {
			this.#repeatsPeriods.style.visibility = "hidden";
			this.#numberRepeats.style.visibility = "hidden";
		});
		this.#infiniteRepeatSelect.addEventListener("click", () => {
			this.#repeatsPeriods.style.visibility = "visible";
			this.#numberRepeats.style.visibility = "hidden";
		});
		this.#numberRepeatsSelect.addEventListener("click", () => {
			this.#repeatsPeriods.style.visibility = "visible";
			this.#numberRepeats.style.visibility = "visible";
		});
	}

}
