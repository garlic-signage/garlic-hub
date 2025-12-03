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
export class WidgetForm
{
	html_form = "";
	values = {};
	preferences = {};
	namespace = null;
	constructor()
	{
	}

	parsePreferences(prefs, vals, namespace = null)
	{
		this.values       = vals;
		this.preferences   = prefs;
		this.namespace     = namespace;
		let size = Object.keys(this.preferences).length;
		if (size === 0)
			return;

		var value = "";
		this.html_form = '<ul class="form_edit"';
		for (const [key, props] of Object.entries(this.preferences))
		{
			this.html_form += "<li><label>" + key + "<br />";
			if (this.values[key] !== undefined)
				value = this.values[key]
			else
				value = "";
			switch(props.types)
			{
				default:
				case "text":
					this.html_form += this.generateEdit(key, props, value);
					break;
				case "radio":
					this.html_form += this.generateRadio(key, props, value);
					break;
				case "colorOpacity":
					this.html_form += this.generateNumber(key, props, value, 0, 100);
					break;
				case "integer":
					this.html_form += this.generateNumber(key, props, value, 0, undefined);
					break;
				case "color":
					this.html_form += this.generateColor(key, props, value);
					break;
				case "list":
				case "combo":
					this.html_form += this.generateDropDown(key, props, value);
					break;
			}

			this.html_form += "</label></li>";
		}

		this.html_form += '</ul>'

		this.html_form += "";
	}

	collectValues()
	{
		let result = {};
		for (const [key, props] of Object.entries(this.preferences))
		{
			switch (props.types)
			{
				default:
				case "text":
					result[key] = document.getElementById(this._elementId(key)).value;
					break;
				case "radio":
					for (const [options_key, options_value] of Object.entries(props.options))
					{
						if (document.getElementById(this._elementId(key + "_" + options_key)).checked)
						{
							result[key] = document.getElementById(this._elementId(key + "_" + options_key)).value;
							break;
						}
					}
					break;
				case "colorOpacity":
					result[key] = document.getElementById(this._elementId(key)).value;
					break;
				case "integer":
					result[key] = document.getElementById(this._elementId(key)).value;
					break;
				case "color":
					result[key] = document.getElementById(this._elementId(key)).value;
					break;
				case "list":
				case "combo":
					const el = document.getElementById(this._elementId(key));
					result[key] = el[el.selectedIndex].value;
					break;
			}

		}
		return result;
	}


	getForm()
	{
		return this.html_form;
	}

	generateEdit(key, props, value)
	{
		let the_value = this.checkForDefaultValue(props, value);
		return '<input type="text"' + ' id="' + this._elementId(key) + '"' + ' name="' + key + '"' + this.checkToolTip(props) + this.checkMandatory(props) + the_value + '/>';
	}

	generateRadio(key, props, value)
	{
		let html = "";
		for (const [options_key, options_value] of Object.entries(props.options))
		{
			let checked = "";
			if (value === "" && props.hasOwnProperty("default") &&
				(props.default === options_value || props.default === options_value))
				checked = ' checked="checked"';
			else
			{
				if (value === options_value)
					checked = ' checked="checked"';
			}
			html += '<input type="radio"' + ' id="' + this._elementId(key + "_" + options_key) + '"' + ' name="' + key + '"' + ' value="' + options_value +'"' + checked + '/>'  + options_key;
		}
		return html;
	}

	generateNumber(key, props, value, min, max)
	{
		let the_value = this.checkForDefaultValue(props, value);
		let min_max = "";
		if (min !== undefined)
			min_max += ' min="'+min+'"';
		if (max !== undefined)
			min_max += ' max="'+max+'"';
		return '<input type="number"' + ' id="' + this._elementId(key) + '"' + ' name="' + key + '"' + min_max + this.checkToolTip(props) + this.checkMandatory(props) + the_value + '/>';
	}

	generateColor(key, props, value)
	{
		let the_value = this.checkForDefaultColorValue(props, value);
		return '<input type="color"' + ' id="' + this._elementId(key) + '"' + ' name="' + key + '"' + this.checkToolTip(props) + this.checkMandatory(props) +  the_value + '/>';
	}

	generateDropDown(key, props, value)
	{
		let html = '<select id="' + this._elementId(key) + '" name="' + key + '" />';

		for (const [options_key, options_value] of Object.entries(props.options))
		{
			let selected = "";
			if (value === "" && props.hasOwnProperty("default") &&
				(props.default === options_value || props.default === options_value))
				selected = ' selected="selected"';
			else
			{
				if (value === options_value)
					selected = ' selected="selected"';
			}
			html += '<option value="'+ options_key +'"' + selected + '>'  + options_value + '</option>';
		}
		html += "</select>";
		return html;
	}

	checkForDefaultColorValue(props, value)
	{
		let the_value;
		if (value === "" && props.hasOwnProperty("default"))
			the_value = ' value="' + this.standardizeColor(props.default) + '"';
		else
			the_value = ' value="' + value + '"';

		if (the_value.charAt(0) !== '')
			the_value = '' + the_value;

		return the_value;
	}

	standardizeColor(str)
	{
		let ctx = document.createElement("canvas").getContext("2d");
		ctx.fillStyle = str;
		return ctx.fillStyle;
	}

	checkForDefaultValue(props, value)
	{
		let the_value;
		if (value === "" && props.hasOwnProperty("default"))
			the_value = ' value="' + props.default + '"';
		else
			the_value = ' value="' + value + '"';

		return the_value;
	}

	checkToolTip(props)
	{
		if(!props.hasOwnProperty("tooltip"))
			return ""

		return ' title="' + props.tooltip + '"';
	}

	checkMandatory(props)
	{
		if(!props.hasOwnProperty("mandatory"))
			return ""

		if (props.mandatory !== "true")
			return "";

		return " required";
	}

	_elementId(key)
	{
		if (this.namespace === null)
			return key;

		return this.namespace + '_' + key;
	}
}
