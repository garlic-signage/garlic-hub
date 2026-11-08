/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

// The designs of the bundled templates in resources/templates/fabric. Each one is built in landscape and portrait.
// Only fonts of the composer may be used, they are listed here with their path below public/fonts.
// The designs keep the rules of the design check of the composer: a font of at least 1/30 of the shorter side (36 px),
// a contrast of 4.5:1, from 1/20 of the shorter side (54 px) 3:1. A text box is not wider than its text needs when
// something else lies behind it, because the check measures the whole box. All texts together can be read within the
// default duration of a playlist item (15 s): two seconds to look and three words a second, so at most 39 words.
const FONTS = {
	'BebasNeue-Regular':      'Bebas_Neue/BebasNeue-Regular.ttf',
	'DejaVuSans':             'Dejavu_Sans/DejaVuSans.ttf',
	'PlayfairDisplay':        'Playfair_Display/PlayfairDisplay.ttf',
	'PlayfairDisplay-Italic': 'Playfair_Display/PlayfairDisplay-Italic.ttf',
	'Poppins-Bold':           'Poppins/Poppins-Bold.ttf',
	'Poppins-Italic':         'Poppins/Poppins-Italic.ttf',
	'Poppins-Regular':        'Poppins/Poppins-Regular.ttf',
	'RobotoMono':             'Roboto_Mono/RobotoMono.ttf',
};

const LOCK = {lockMovementX: true, lockMovementY: true, lockRotation: true, lockScalingX: true, lockScalingY: true};

function bg(W, H, fill)
{
	return new fabric.Rect({left: 0, top: 0, width: W, height: H, fill, strokeWidth: 0, ...LOCK});
}

function rect(l, t, w, h, fill, o = {})
{
	return new fabric.Rect({left: l, top: t, width: w, height: h, fill, strokeWidth: 0, ...o});
}

function circ(cx, cy, r, fill, o = {})
{
	const sw = o.strokeWidth || 0;
	return new fabric.Circle({left: cx - r - sw / 2, top: cy - r - sw / 2, radius: r, fill, strokeWidth: 0, ...o});
}

// anchor: top (default), middle or bottom of the text block at y
function text(t, l, y, w, size, font, fill, o = {})
{
	const {anchor = 'top', align = 'left', lh = 1.16, cs = 0, ...rest} = o;
	const tb = new fabric.Textbox(t, {
		left: l, top: y, width: w, fontSize: size, fontFamily: font, fill,
		textAlign: align, lineHeight: lh, charSpacing: cs, ...rest
	});
	if (anchor === 'middle')
		tb.set('top', y - tb.height / 2);
	else if (anchor === 'bottom')
		tb.set('top', y - tb.height);
	return tb;
}

function below(obj, gap)
{
	return obj.top + obj.height + gap;
}

function grad(w, h, stops, dir = 'v')
{
	const coords = dir === 'v' ? {x1: 0, y1: 0, x2: 0, y2: h}
		: dir === 'h' ? {x1: 0, y1: 0, x2: w, y2: 0}
		: {x1: 0, y1: 0, x2: w, y2: h};
	return new fabric.Gradient({type: 'linear', gradientUnits: 'pixels', coords,
		colorStops: stops.map(([offset, color]) => ({offset, color}))});
}

// The placeholder of an image is a real image, marked as placeholder: it is replaced like every image and the design
// check of the composer reports it while it is there. Its picture is loaded by generate.html, the composer loads it
// from PLACEHOLDER_SRC (BaseTemplateOrchestrator::restoreSrc()).
const PLACEHOLDER_SRC = '/images/templates/placeholder.png';
let placeholderElement = null;

// fills the area centered, like the composer replaces an image
function imagePlaceholder(l, t, w, h, o = {})
{
	const nw = placeholderElement.naturalWidth, nh = placeholderElement.naturalHeight;
	const scale = Math.max(w / nw, h / nh), cw = w / scale, ch = h / scale;
	return [new fabric.FabricImage(placeholderElement, {
		left: l, top: t, width: cw, height: ch, cropX: (nw - cw) / 2, cropY: (nh - ch) / 2,
		scaleX: scale, scaleY: scale, placeholder: true, ...o
	})];
}

// a round placeholder, e.g. for a photo of a person
function roundImagePlaceholder(cx, cy, r)
{
	const [image] = imagePlaceholder(cx - r, cy - r, 2 * r, 2 * r);
	image.set('clipPath', new fabric.Circle({radius: r / image.scaleX, originX: 'center', originY: 'center'}));
	return [image];
}

const TEMPLATES = [
{
	key: 'announcement',
	name: 'Announcement',
	description: 'Headline with a short text for news and notices.',
	build(W, H)
	{
		const P = H > W, pad = P ? 100 : 140, tw = W - 2 * pad - (P ? 0 : 280);
		const o = [
			bg(W, H, grad(W, H, [[0, '#0B1F3A'], [1, '#1D4E89']], 'd')),
			circ(W - (P ? 60 : 180), P ? 260 : 120, P ? 420 : 460, 'rgba(255,255,255,0.05)'),
			circ(W - (P ? 160 : 300), P ? 420 : 300, P ? 200 : 220, 'rgba(76,201,240,0.10)'),
		];
		const label = text('ANNOUNCEMENT', pad, P ? 520 : 200, tw, 36, 'Poppins-Bold', '#4CC9F0', {cs: 300});
		const bar   = rect(pad, below(label, 30), 120, 8, '#4CC9F0');
		const h1    = text('Important Update\nfor All Employees', pad, below(bar, 50), tw, P ? 104 : 110, 'Poppins-Bold', '#FFFFFF', {lh: 1.1});
		const body  = text('Starting next Monday, the new opening hours apply. Please read the details on the intranet or ask your team lead.',
			pad, below(h1, 50), tw, P ? 46 : 46, 'Poppins-Regular', '#C9D6E8', {lh: 1.4});
		o.push(label, bar, h1, body,
			rect(pad, H - (P ? 220 : 160), W - 2 * pad, 2, 'rgba(255,255,255,0.25)'),
			text('Company Name', pad, H - (P ? 180 : 120), (W - 2 * pad) / 2, 36, 'Poppins-Bold', '#FFFFFF'),
			text('November 2026', W / 2, H - (P ? 180 : 120), (W - 2 * pad) / 2, 36, 'Poppins-Regular', '#C9D6E8', {align: 'right'}));
		return o;
	}
},
{
	key: 'welcome',
	name: 'Welcome',
	description: 'Welcome screen for the reception with logo and guest names.',
	build(W, H)
	{
		const P = H > W, pad = P ? 100 : 140, align = P ? 'center' : 'left', tw = P ? W - 2 * pad : 1100;
		const o = [
			bg(W, H, '#F6F1EA'),
			circ(P ? W - 40 : W - 160, P ? H - 300 : 380, P ? 380 : 460, '#E07A5F'),
			circ(P ? 120 : W - 520, P ? H - 140 : H - 40, P ? 160 : 170, '#81B29A'),
			circ(P ? W - 260 : W - 160, P ? H - 620 : 120, 60, '#F2CC8F'),
			...imagePlaceholder(P ? (W - 360) / 2 : pad, pad, 360, 140),
		];
		const h1   = text('Welcome', pad, P ? 520 : 330, tw, P ? 200 : 230, 'PlayfairDisplay', '#3D405B', {align, lh: 1});
		const sub  = text('to Company Name', pad, below(h1, 20), tw, 60, 'Poppins-Regular', '#3D405B', {align});
		const bar  = rect(P ? (W - 160) / 2 : pad, below(sub, 50), 160, 6, '#E07A5F');
		const body = text('Today we are delighted to welcome\nJane Doe and the team of Example Ltd.', pad, below(bar, 50), tw, 44,
			'Poppins-Regular', '#5C6070', {align, lh: 1.4});
		o.push(h1, sub, bar, body);
		return o;
	}
},
{
	key: 'menu',
	name: 'Menu',
	description: 'Menu or price list with five dishes and an image.',
	build(W, H)
	{
		const P = H > W, pad = P ? 100 : 120;
		const o = [
			bg(W, H, grad(W, H, [[0, '#1E2320'], [1, '#2D3631']])),
		];
		const listX = pad, listW = P ? W - 2 * pad : 960;
		const title = text("Today's Menu", listX, P ? 110 : 90, listW, P ? 120 : 110, 'PlayfairDisplay', '#F2E8CF', {align: P ? 'center' : 'left'});
		const sub   = text('FRESH FROM OUR KITCHEN', listX, below(title, 10), listW, 36, 'Poppins-Regular', '#C8B88A', {cs: 300, align: P ? 'center' : 'left'});
		o.push(title, sub);
		if (P)
			o.push(...imagePlaceholder(pad, below(sub, 60), W - 2 * pad, 520));
		else
			o.push(...imagePlaceholder(1200, 120, 600, 840));

		const dishes = [
			['Tomato Soup', 'with basil and croutons', '4.50'],
			['Chicken Curry', 'with basmati rice', '9.80'],
			['Vegetable Lasagna', 'spinach and ricotta', '8.90'],
			['Caesar Salad', 'with parmesan', '7.50'],
			['Apple Strudel', 'with vanilla sauce', '3.90'],
		];
		const start = P ? 1000 : 330, rowH = P ? 160 : 132;
		dishes.forEach(([name, desc, price], i) => {
			const y = start + i * rowH;
			o.push(
				text(name, listX, y, listW - 220, 46, 'Poppins-Bold', '#F2E8CF'),
				text(desc, listX, y + 60, listW - 220, 36, 'Poppins-Regular', '#A9B3A6'),
				text(price, listX + listW - 220, y, 220, 46, 'Poppins-Bold', '#E9C46A', {align: 'right'}),
			);
			if (i < dishes.length - 1)
				o.push(rect(listX, y + rowH - 22, listW, 2, 'rgba(242,232,207,0.15)'));
		});
		return o;
	}
},
{
	key: 'sale',
	name: 'Sale',
	description: 'Big discount with product image and validity.',
	build(W, H)
	{
		const P = H > W, pad = P ? 100 : 140, align = P ? 'center' : 'left', tw = P ? W - 2 * pad : 900;
		const o = [
			bg(W, H, grad(W, H, [[0, '#E63946'], [1, '#B5179E']], 'd')),
			circ(P ? 80 : 160, P ? 120 : H - 60, P ? 260 : 300, 'rgba(255,255,255,0.08)'),
		];
		const sale = text('SALE', pad, P ? 120 : 100, tw, P ? 180 : 190, 'BebasNeue-Regular', '#FFD60A', {cs: 150, align, lh: 1});
		const big  = text('-30%', pad, below(sale, -20), tw, P ? 420 : 400, 'BebasNeue-Regular', '#FFFFFF', {align, lh: 0.9});
		const sub  = text('on all summer items', pad, below(big, 10), tw, 56, 'Poppins-Regular', '#FFFFFF', {align});
		o.push(sale, big, sub);

		const tagW = 560, tagX = P ? (W - tagW) / 2 : pad, tagY = P ? H - 260 : below(sub, 50);
		if (P)
			o.push(...imagePlaceholder(pad, below(sub, 60), W - 2 * pad, tagY - below(sub, 60) - 70));
		else
			o.push(...imagePlaceholder(1100, 140, 680, 800));
		o.push(
			rect(tagX, tagY, tagW, 110, '#FFD60A', {rx: 55, ry: 55}),
			text('Only until Nov 30', tagX, tagY + 55, tagW, 44, 'Poppins-Bold', '#1D1D1D', {align: 'center', anchor: 'middle'}),
		);
		return o;
	}
},
{
	key: 'event',
	name: 'Event',
	description: 'Event with date, time and place.',
	build(W, H)
	{
		const P = H > W, pad = P ? 100 : 140;
		const o = [
			bg(W, H, grad(W, H, [[0, '#240046'], [1, '#5A189A']])),
			circ(W - 100, P ? 300 : 100, P ? 340 : 380, 'rgba(255,255,255,0.05)'),
			circ(P ? 60 : W - 420, H - 100, P ? 240 : 200, 'rgba(199,125,255,0.10)'),
		];
		const dx = P ? (W - 280) / 2 : pad, dy = P ? 200 : 150;
		o.push(
			rect(dx, dy, 280, 300, '#FFFFFF', {rx: 24, ry: 24}),
			rect(dx, dy, 280, 90, '#7B2CBF', {rx: 24, ry: 24}),
			rect(dx, dy + 60, 280, 30, '#7B2CBF'),
			text('NOV', dx, dy + 45, 280, 52, 'Poppins-Bold', '#FFFFFF', {align: 'center', anchor: 'middle', cs: 200}),
			text('24', dx, dy + 195, 280, 180, 'BebasNeue-Regular', '#240046', {align: 'center', anchor: 'middle', lh: 1}),
		);
		const tx = P ? pad : dx + 340, tw = P ? W - 2 * pad : W - tx - pad, align = P ? 'center' : 'left';
		const label = text('EVENT', tx, P ? dy + 380 : dy + 20, tw, 36, 'Poppins-Bold', '#C77DFF', {cs: 300, align});
		const title = text('Team Summer Party', tx, below(label, 20), tw, P ? 120 : 120, 'Poppins-Bold', '#FFFFFF', {align, lh: 1.05});
		o.push(label, title);

		const iy = P ? below(title, 120) : 620, ix = P ? pad + 60 : pad, lw = 260;
		o.push(
			text('Time\nPlace\nDress code', ix, iy, lw, 44, 'Poppins-Bold', '#C77DFF', {lh: 1.6}),
			text('6:00 pm – 11:00 pm\nRooftop terrace, 5th floor\nCasual', ix + lw, iy, W - ix - lw - pad, 44, 'Poppins-Regular', '#FFFFFF', {lh: 1.6}),
			rect(pad, H - (P ? 260 : 190), W - 2 * pad, 2, 'rgba(255,255,255,0.2)'),
			text('Please register at the reception by November 20.', pad, H - (P ? 220 : 150), W - 2 * pad, 38, 'Poppins-Regular', '#E0AAFF', {align}),
		);
		return o;
	}
},
{
	key: 'opening_hours',
	name: 'Opening Hours',
	description: 'Opening hours for the week with a note.',
	build(W, H)
	{
		const P = H > W, pad = P ? 100 : 140;
		const o = [bg(W, H, '#FFFFFF')];
		let tx, ty, tw;
		if (P)
		{
			o.push(rect(0, 0, W, 620, '#1F766C'), circ(W - 80, 80, 220, 'rgba(255,255,255,0.10)'));
			const h1 = text('Opening Hours', pad, 300, W - 2 * pad, 110, 'Poppins-Bold', '#FFFFFF', {anchor: 'middle', lh: 1.05});
			o.push(h1, text('We look forward to your visit', pad, below(h1, 30), W - 2 * pad, 44, 'Poppins-Regular', '#E3F6F2'));
			tx = pad; ty = 780; tw = W - 2 * pad;
		}
		else
		{
			o.push(rect(0, 0, 700, H, '#1F766C'), circ(80, H - 80, 260, 'rgba(255,255,255,0.10)'));
			const h1 = text('Opening\nHours', pad, H / 2 - 60, 700 - 2 * pad, 120, 'Poppins-Bold', '#FFFFFF', {anchor: 'middle', lh: 1.05});
			o.push(h1, text('We look forward to your visit', pad, below(h1, 30), 700 - 2 * pad, 40, 'Poppins-Regular', '#E3F6F2', {lh: 1.3}));
			tx = 840; ty = 150; tw = W - tx - pad;
		}
		const size = P ? 58 : 52, lh = P ? 1.95 : 1.6;
		const days = text('Monday\nTuesday\nWednesday\nThursday\nFriday\nSaturday\nSunday', tx, ty, tw / 2, size, 'Poppins-Regular', '#264653', {lh});
		const hours = text('08:00 – 18:00\n08:00 – 18:00\n08:00 – 18:00\n08:00 – 18:00\n08:00 – 18:00\n09:00 – 14:00\nClosed', tx + tw / 2, ty, tw / 2, size, 'Poppins-Bold', '#264653', {lh, align: 'right'});
		o.push(days, hours,
			rect(tx, below(days, 40), tw, 2, '#E0E6E8'),
			text('Closed on public holidays', tx, below(days, 80), tw, 38, 'Poppins-Italic', '#6C757D'));
		return o;
	}
},
{
	key: 'wifi',
	name: 'Wi-Fi Access',
	description: 'Guest Wi-Fi with network name, password and QR code.',
	build(W, H)
	{
		const P = H > W, pad = P ? 100 : 140;
		const o = [bg(W, H, grad(W, H, [[0, '#0F4C5C'], [1, '#0A9396']], 'd'))];
		const cx = P ? W / 2 : 440, cy = P ? 420 : 400;
		[230, 155, 80].forEach(r => o.push(new fabric.Circle({
			left: cx - r - 14, top: cy - r - 14, radius: r, startAngle: 225, endAngle: 315,
			fill: '', stroke: '#FFFFFF', strokeWidth: 28, strokeLineCap: 'round'
		})));
		o.push(circ(cx, cy - 4, 22, '#FFFFFF'));
		const tx = P ? pad : 100, tw = P ? W - 2 * pad : 680, align = 'center';
		const h1 = text('Free Wi-Fi', tx, cy + 80, tw, 110, 'Poppins-Bold', '#FFFFFF', {align});
		o.push(h1, text('Enjoy your stay with us', tx, below(h1, 10), tw, 42, 'Poppins-Regular', '#C7F0EC', {align}));

		const cX = P ? pad : 820, cY = P ? 900 : 160, cW = P ? W - 2 * pad : W - 820 - pad, cH = P ? 880 : 760;
		o.push(rect(cX, cY, cW, cH, '#FFFFFF', {rx: 32, ry: 32}));
		const ix = cX + 70, iw = P ? cW - 140 : cW - 140 - 380;
		const l1 = text('NETWORK', ix, cY + 90, iw, 36, 'Poppins-Bold', '#4A6A70', {cs: 200});
		const v1 = text('Guest-WiFi', ix, below(l1, 10), iw, P ? 64 : 56, 'RobotoMono', '#0F4C5C');
		const l2 = text('PASSWORD', ix, below(v1, 60), iw, 36, 'Poppins-Bold', '#4A6A70', {cs: 200});
		const v2 = text('welcome2026', ix, below(l2, 10), iw, P ? 64 : 56, 'RobotoMono', '#0F4C5C');
		o.push(l1, v1, l2, v2);
		if (P)
			o.push(...imagePlaceholder((W - 340) / 2, below(v2, 70), 340, 340));
		else
		{
			o.push(...imagePlaceholder(cX + cW - 70 - 340, cY + 90, 340, 340));
			o.push(text('Scan the code or enter the password manually.', ix, cY + cH - 140, cW - 140, 36, 'Poppins-Regular', '#4A6A70'));
		}
		return o;
	}
},
{
	key: 'hiring',
	name: "We're Hiring",
	description: 'Job advertisement with benefits and contact.',
	build(W, H)
	{
		const P = H > W, pad = P ? 100 : 140, tw = P ? W - 2 * pad : 880;
		const o = [bg(W, H, '#FFD60A'), circ(P ? W - 40 : 980, P ? 60 : H - 40, P ? 220 : 180, 'rgba(0,0,0,0.06)')];
		let y = P ? 120 : 110;
		if (P)
		{
			o.push(...imagePlaceholder(pad, y, W - 2 * pad, 720));
			y += 790;
		}
		else
			o.push(...imagePlaceholder(1140, 140, 640, 800));
		const h1   = text("WE'RE HIRING!", pad, y, tw, P ? 190 : 200, 'BebasNeue-Regular', '#111111', {lh: 1});
		const job  = text('Sales Assistant (m/f/d)', pad, below(h1, 10), tw, 64, 'Poppins-Bold', '#111111');
		const list = text('•  Full-time or part-time\n•  Friendly team\n•  Fair pay and training', pad, below(job, 40), tw, 46, 'Poppins-Regular', '#222222', {lh: 1.5});
		o.push(h1, job, list);
		const by = P ? H - 260 : H - 230, bw = P ? W - 2 * pad : 880;
		o.push(
			rect(pad, by, bw, 120, '#111111', {rx: 60, ry: 60}),
			text('Apply now: jobs@company.com', pad, by + 60, bw, 44, 'Poppins-Bold', '#FFD60A', {align: 'center', anchor: 'middle'}),
		);
		return o;
	}
},
{
	key: 'new_colleague',
	name: 'New Colleague',
	description: 'Introduces a new team member with photo, name and role.',
	build(W, H)
	{
		const P = H > W, pad = P ? 100 : 140;
		const o = [bg(W, H, '#EEF2FF'),
			P ? rect(0, 0, W, 760, '#3651D4') : rect(0, 0, 820, H, '#3651D4'),
			circ(P ? 60 : 80, P ? 80 : H - 60, 200, 'rgba(255,255,255,0.10)')];
		const cx = P ? W / 2 : 820, cy = P ? 760 : H / 2, r = P ? 280 : 300;
		o.push(circ(cx, cy, r, '#FFFFFF'),
			...roundImagePlaceholder(cx, cy, r - 20));
		const tx = P ? pad : 1200, tw = P ? W - 2 * pad : W - 1200 - pad, align = P ? 'center' : 'left';
		const label = text('PLEASE WELCOME', tx, P ? 1100 : 300, tw, 38, 'Poppins-Bold', '#3651D4', {cs: 300, align});
		const name  = text('Jane Doe', tx, below(label, 20), tw, 110, 'Poppins-Bold', '#1B1F3B', {align, lh: 1.05});
		const role  = text('Marketing Manager', tx, below(name, 10), tw, 52, 'Poppins-Regular', '#3651D4', {align});
		const bar   = rect(P ? (W - 120) / 2 : tx, below(role, 50), 120, 6, '#3651D4');
		const body  = text('Jane joins our marketing team on November 1. Say hello and give a warm welcome!', tx, below(bar, 50), tw, 42,
			'Poppins-Regular', '#4A4E69', {align, lh: 1.45});
		o.push(label, name, role, bar, body);
		return o;
	}
},
{
	key: 'quote',
	name: 'Quote',
	description: 'Quote of the day with author.',
	build(W, H)
	{
		const P = H > W, pad = P ? 110 : 240, tw = W - 2 * pad;
		const o = [bg(W, H, '#111827'),
			circ(W, 0, P ? 360 : 420, 'rgba(245,158,11,0.08)'),
			circ(0, H, P ? 300 : 340, 'rgba(245,158,11,0.06)')];
		const mark = text('“', pad, 0, tw, P ? 420 : 400, 'PlayfairDisplay', '#F59E0B', {align: 'center', lh: 1});
		const quote = text('Great things are never done by one person alone. They are done by a team.', pad, 0, tw, P ? 84 : 84,
			'PlayfairDisplay-Italic', '#FFFFFF', {align: 'center', lh: 1.3});
		const bar = rect((W - 120) / 2, 0, 120, 4, '#F59E0B');
		const author = text('Author Name', pad, 0, tw, 44, 'Poppins-Regular', '#F59E0B', {align: 'center', cs: 100});
		// the quote mark has much empty space below the glyph
		const markVisible = mark.height * 0.45, total = markVisible + quote.height + 60 + 4 + 50 + author.height;
		let y = (H - total) / 2 - (P ? 40 : 20);
		mark.set('top', y - mark.height * 0.12);
		y += markVisible;
		quote.set('top', y);
		bar.set('top', below(quote, 60));
		author.set('top', below(bar, 50));
		o.push(mark, quote, bar, author);
		return o;
	}
},
{
	key: 'directions',
	name: 'Directions',
	description: 'Wayfinding with four destinations and arrows.',
	build(W, H)
	{
		const P = H > W, pad = P ? 100 : 140;
		const o = [bg(W, H, '#1F2937')];
		const h1 = text('Directions', pad, P ? 140 : 90, W - 2 * pad, P ? 110 : 90, 'Poppins-Bold', '#FFFFFF');
		o.push(h1, rect(pad, below(h1, 20), 120, 6, '#10B981'));
		const rows = [
			['→', '#047857', 'Meeting Rooms', 'Floor 1 · Rooms 101–104'],
			['←', '#2563EB', 'Reception', 'Ground floor'],
			['↑', '#B45309', 'Cafeteria', 'Floor 2 · open 11:30 – 14:00'],
			['↓', '#DC2626', 'Parking', 'Level -1 · Visitors'],
		];
		const start = P ? 500 : 270, rowH = P ? 350 : 190, box = P ? 220 : 150;
		rows.forEach(([arrow, color, dest, info], i) => {
			const y = start + i * rowH, cyRow = y + box / 2;
			o.push(
				rect(pad, y, box, box, color, {rx: 24, ry: 24}),
				text(arrow, pad, cyRow, box, P ? 150 : 110, 'DejaVuSans', '#FFFFFF', {align: 'center', anchor: 'middle', lh: 1}),
				text(dest, pad + box + 50, cyRow - 6, W - 2 * pad - box - 50, P ? 70 : 60, 'Poppins-Bold', '#FFFFFF', {anchor: 'bottom'}),
				text(info, pad + box + 50, cyRow + 6, W - 2 * pad - box - 50, 40, 'Poppins-Regular', '#9CA3AF'),
			);
			if (i < rows.length - 1)
				o.push(rect(pad, y + box + (rowH - box) / 2, W - 2 * pad, 2, 'rgba(255,255,255,0.10)'));
		});
		return o;
	}
},
{
	key: 'safety',
	name: 'Safety Notice',
	description: 'Warning sign with symbol, title and instructions.',
	build(W, H)
	{
		const P = H > W, pad = P ? 100 : 140;
		const o = [bg(W, H, '#FFC300')];
		const band = 70;
		const stripes = (y) => {
			const parts = [rect(0, 0, W, band, '#111111')];
			for (let x = -band; x < W + band; x += 100)
				parts.push(new fabric.Polygon([{x: x, y: band}, {x: x + 50, y: band}, {x: x + 50 + band, y: 0}, {x: x + band, y: 0}],
					{fill: '#FFC300', strokeWidth: 0}));
			const group = new fabric.Group(parts);
			group.set({left: 0, top: y});
			return group;
		};
		o.push(stripes(0), stripes(H - band));

		const tri = P ? 520 : 480, tx = P ? (W - tri) / 2 : pad, ty = P ? 260 : (H - tri * 0.88) / 2;
		o.push(new fabric.Triangle({left: tx, top: ty, width: tri, height: tri * 0.88, fill: '#111111', strokeWidth: 0}),
			text('!', tx + tri * 0.35, ty + tri * 0.56, tri * 0.3, tri * 0.6, 'BebasNeue-Regular', '#FFC300', {align: 'center', anchor: 'middle', lh: 1}));
		const x = P ? pad : tx + tri + 100, w = P ? W - 2 * pad : W - x - pad, align = P ? 'center' : 'left';
		const label = text('SAFETY NOTICE', x, P ? ty + tri * 0.88 + 100 : 260, w, 40, 'Poppins-Bold', '#111111', {cs: 300, align});
		const h1    = text('Protective Equipment Required', x, below(label, 20), w, P ? 96 : 92, 'Poppins-Bold', '#111111', {align, lh: 1.08});
		const body  = text('Safety shoes, hard hats and high-visibility vests must be worn in this area at all times.', x, below(h1, 40), w, 46,
			'Poppins-Regular', '#222222', {align, lh: 1.4});
		o.push(label, h1, body);
		return o;
	}
},
];
