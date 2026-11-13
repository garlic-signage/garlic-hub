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

const SUN = 1;
const MON = 2;
const TUE = 4;
const WED = 8;
const THU = 16;
const FRI = 32;
const SAT = 64;

export class ConditionalPlayForm
{
	#enable_conditional_play = null;
	#edit_conditional_play   = null;
	enable_date_period      = null;
	edit_date_period        = null;
	#dateFrom               = null;
	#dateUntil              = null;
	#timeFrom               = null;
	#timeUntil              = null;
	enable_time_period      = null;
	edit_time_period        = null;
	#conditionalPlaySliderFactory = null;
	#weekdays = {};
	#conditional = {};


	constructor(conditionalPlaySliderFactory)
	{
		this.#conditionalPlaySliderFactory = conditionalPlaySliderFactory;
	}

	init(conditional)
	{
		this.#conditional = conditional;
		this.#enable_conditional_play = document.getElementById("enable_conditional_play");
		this.#edit_conditional_play   = document.getElementById("edit_conditional_play");

		this.initDatePeriod();
		this.#handleDatePeriodCheckBox()
		this.initTimePeriod();
		this.#handleTimePeriodCheckBox();
		this.initWeekDays();
		this.initConditionalPlay();
		this.handleConditionalPlayCheckBox()
	}

	initConditionalPlay()
	{
		this.#enable_conditional_play.onclick = () =>
		{
			this.handleConditionalPlayCheckBox();
		}
	}

	initDatePeriod()
	{
		this.enable_date_period = document.getElementById("enable_date_period");
		this.edit_date_period   = document.getElementById("edit_date_period");
		this.#dateFrom          = document.getElementById("date_from");
		this.#dateUntil         = document.getElementById("date_until");
		this.enable_date_period.onclick = () =>
		{
			this.#handleDatePeriodCheckBox();
		}
	}

	handleConditionalPlayCheckBox()
	{
		if (this.#enable_conditional_play.checked)
			this.#edit_conditional_play.style.display = "block";
		else
		{
			this.enable_date_period.checked = false;
			this.#handleDatePeriodCheckBox();
			this.enable_time_period.checked = false;
			this.#handleTimePeriodCheckBox();
			this.#edit_conditional_play.style.display = "none";
			let enable_weekdays = document.getElementsByClassName("enable_weekdays");
			for (let i = 0; i < enable_weekdays.length; i++)
			{
				enable_weekdays[i].checked = true;
				enable_weekdays[i].click();
			}
		}
	}

	#handleDatePeriodCheckBox()
	{
		if (this.enable_date_period.checked)
			this.edit_date_period.style.display = "flex";
		else
		{
			this.#dateFrom.value    = "";
			this.#dateUntil.value   = "";
			this.edit_date_period.style.display = "none";
		}
	}

	#handleTimePeriodCheckBox()
	{
		if (this.enable_time_period.checked)
			this.edit_time_period.style.display = "flex";
		else
		{
			this.#timeFrom.value    = "";
			this.#timeUntil.value   = "";
			this.edit_time_period.style.display = "none";
		}
	}
	initTimePeriod()
	{
		this.enable_time_period = document.getElementById("enable_time_period");
		this.edit_time_period   = document.getElementById("edit_time_period");
		this.#timeFrom    = document.getElementById("time_from");
		this.#timeUntil   = document.getElementById("time_until");
		this.enable_time_period.onclick = () =>
		{
			this.#handleTimePeriodCheckBox();
		}
	}

	initWeekDays()
	{

		this.#weekdays = {
			[SUN]: this.#createWeekdaySliderGroup(SUN),
			[MON]: this.#createWeekdaySliderGroup(MON),
			[TUE]: this.#createWeekdaySliderGroup(TUE),
			[WED]: this.#createWeekdaySliderGroup(WED),
			[THU]: this.#createWeekdaySliderGroup(THU),
			[FRI]: this.#createWeekdaySliderGroup(FRI),
			[SAT]: this.#createWeekdaySliderGroup(SAT)
		}
	}

	#createWeekdaySliderGroup(weekdayId)
	{
		let from = 0;
		let until = 96;
		if (this.#conditional.weekdays && this.#conditional.weekdays[weekdayId])
		{
			from = this.#conditional.weekdays[weekdayId].from;
			until = this.#conditional.weekdays[weekdayId].until;
		}

		return this.#conditionalPlaySliderFactory.create(weekdayId, from, until);
	}

	collectValues()
	{
		let ret = {
			"date": this.#getDate(),
			"time":	this.#getTime(),
			"weekdays": this.#getWeekdays()
		};

		// date, time and weekdays are empty only together, when conditional play is off: each part alone decides
		// Stryker disable next-line ConditionalExpression,LogicalOperator: see above
		if (Object.keys(ret["date"]).length === 0 && Object.keys(ret["time"]).length === 0 &&
			// Stryker disable next-line ConditionalExpression: see above
			Object.keys(ret["weekdays"]).length === 0)
			return {};

		return ret;
	}

	#getDate()
	{
		if (!this.#enable_conditional_play.checked)
			return {};

		let	date_from_val  = "";
		let	date_until_val = "";
		// Stryker disable next-line ConditionalExpression: unchecking the period empties its fields
		if (this.enable_date_period.checked)
		{
			// Stryker disable next-line ConditionalExpression,StringLiteral: an empty value gives the empty default
			if (this.#dateFrom.value !== "")
				date_from_val  = this.#dateFrom.value;
			// Stryker disable next-line ConditionalExpression,StringLiteral: an empty value gives the empty default
			if (this.#dateUntil.value !== "")
				date_until_val = this.#dateUntil.value;
		}

		return {"from": date_from_val, "until": date_until_val};
	}

	#getTime()
	{
		if (!this.#enable_conditional_play.checked)
			return {};

		let	time_from_val  = "";
		let	time_until_val = "";
		// Stryker disable next-line ConditionalExpression: unchecking the period empties its fields
		if (this.enable_time_period.checked)
		{
			time_from_val  = this.#timeFrom.value;
			time_until_val = this.#timeUntil.value;
		}

		return {"from": time_from_val, "until": time_until_val};
	}


	#getWeekdays()
	{
		if (!this.#enable_conditional_play.checked)
			return {};

		let weekdays = {};
		Object.entries(this.#weekdays).forEach(([weekday, sliderGroup]) => {
			if (sliderGroup.isEnabled())
			{
				const pos = sliderGroup.getHandlePositions()
				weekdays[weekday] = {"from":parseInt(pos[0], 10), "until": parseInt(pos[1])}
			}

		});

		return weekdays;
	}




}
