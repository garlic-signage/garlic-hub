/*
 Can a Raspberry Pi serve 10 users and 200 players at the same time?

 Runs against a fresh installation (edge edition, own .env and database): the setup uploads the media of
 make-media.sh, builds a playlist, registers the players, assigns the playlist and exports it. Then all
 players ask for their index at once, like after an export, and download the media. Afterwards they ask
 every 5 minutes, while the users work in the UI.

   tests/Load/make-media.sh
   docker run --rm -i --network host -v "$PWD/tests/Load:/load" \
     -e BASE_URL=http://<pi> -e USERNAME=<admin> -e PASSWORD=<password> grafana/k6 run /load/pi.js

 BASE_URL must be an IP or a host name of the Pi, localhost or *.ddev.site are taken for the local player.
 Optional: PLAYERS (200), USERS (10), DURATION (15m).
*/
import http from 'k6/http';
import { check, fail, sleep } from 'k6';

const BASE_URL = (__ENV.BASE_URL || '').replace(/\/$/, '');
const PLAYERS  = parseInt(__ENV.PLAYERS || '200', 10);
const USERS    = parseInt(__ENV.USERS || '10', 10);
const DURATION = __ENV.DURATION || '15m';
const POLL     = 300;

const MEDIA = [
	...Array.from({ length: 10 }, (_, i) => ({ name: `picture-${i + 1}.jpg`, mime: 'image/jpeg' })),
	...Array.from({ length: 3 }, (_, i) => ({ name: `video-${i + 1}.mp4`, mime: 'video/mp4' })),
];

// only the setup uploads: VU 0 runs it, the other VUs must not hold 170 MB each
const files = __VU === 0 ? MEDIA.map((m) => ({ ...m, bytes: open(`./media/${m.name}`, 'b') })) : [];

export const options = {
	setupTimeout: '15m',
	scenarios: {
		players: { executor: 'constant-vus', exec: 'player', vus: PLAYERS, duration: DURATION, gracefulStop: '5m' },
		users:   { executor: 'constant-vus', exec: 'user', vus: USERS, duration: DURATION },
	},
	thresholds: {
		http_req_failed: ['rate<0.01'],
		checks: ['rate>0.99'],
		'http_req_duration{scenario:users}': ['p(95)<1000'],
		'http_req_duration{name:smil-index}': ['p(95)<2000'],
	},
};

function uuidOf(number) {
	return `00000000-0000-4000-8000-${String(number).padStart(12, '0')}`;
}

function agentOf(number) {
	return `GAPI/1.0 (UUID:${uuidOf(number)}; NAME:loadtest-${number}) garlic-linux/v0.6.0.679 (MODEL:Garlic)`;
}

function login() {
	const res = http.post(`${BASE_URL}/login`, {
		username: __ENV.USERNAME,
		password: __ENV.PASSWORD,
		csrf_token: csrfToken('/login'),
	}, { tags: { name: 'login' } });
	if (!check(res, { 'logged in': (r) => !r.url.endsWith('/login') }))
		fail('login failed');
}

function csrfToken(page) {
	const res = http.get(`${BASE_URL}${page}`, { tags: { name: page } });
	const match = res.body.match(/<meta name="csrf-token" content="([^"]+)"/) || res.body.match(/name="csrf_token"[^>]*value="([^"]+)"/);
	if (match === null)
		fail(`no csrf token on ${page}`);

	return match[1];
}

function json(res, what) {
	const body = res.status === 200 ? res.json() : null;
	if (body === null || (body.success !== undefined && body.success !== true))
		fail(`${what}: ${res.status} ${res.body}`);

	return body;
}

export function setup() {
	if (BASE_URL === '' || !__ENV.USERNAME || !__ENV.PASSWORD)
		fail('BASE_URL, USERNAME and PASSWORD are needed');

	login();
	const token = csrfToken('/mediapool');
	const name  = `loadtest-${Date.now()}`;

	const nodeId = json(http.post(`${BASE_URL}/async/mediapool/node`, { name, csrf_token: token }), 'node').data.id;
	for (const file of files)
	{
		const upload = { node_id: String(nodeId), csrf_token: token, file: http.file(file.bytes, file.name, file.mime) };
		json(http.post(`${BASE_URL}/async/mediapool/uploadLocalFile`, upload, { timeout: '5m' }), `upload ${file.name}`);
	}
	const media = json(http.get(`${BASE_URL}/async/mediapool/media/list/${nodeId}`), 'media list').media_list;

	http.post(`${BASE_URL}/playlists/settings`, { playlist_name: name, playlist_mode: 'master', csrf_token: token });
	const playlist = json(http.get(`${BASE_URL}/async/playlists/find/master/${name}`), 'playlist').find((p) => p.name === name);
	if (playlist === undefined)
		fail('playlist was not created');

	media.forEach((m, i) => json(http.post(`${BASE_URL}/async/playlists/items/insert`, {
		playlist_id: String(playlist.id), id: m.media_id, source: 'mediapool', position: String(i + 1), csrf_token: token,
	}), `insert ${m.filename}`));

	// a new player is registered by its first request, the edge edition releases it
	const uuids = new Set();
	for (let number = 1; number <= PLAYERS; number++)
	{
		http.get(`${BASE_URL}/smil-index`, { headers: { 'User-Agent': agentOf(number) } });
		uuids.add(uuidOf(number));
	}
	// the ids of the players are not known: the local player and others may be there already
	let assigned = 0;
	for (let playerId = 1; assigned < PLAYERS && playerId <= PLAYERS + 100; playerId++)
	{
		const res = http.get(`${BASE_URL}/async/player/${playerId}`, { tags: { name: '/async/player/id' }, responseCallback: http.expectedStatuses(200, 404) });
		if (res.status !== 200 || !uuids.has(res.json().uuid))
			continue;

		json(http.patch(`${BASE_URL}/async/player/playlist`, { player_id: String(playerId), playlist_id: String(playlist.id), csrf_token: token }), 'assign');
		assigned++;
	}
	if (assigned !== PLAYERS)
		fail(`only ${assigned} of ${PLAYERS} players got the playlist`);

	json(http.put(`${BASE_URL}/async/playlists`, { playlist_id: String(playlist.id), csrf_token: token }), 'export');

	return { playlistId: playlist.id, nodeId };
}

// state of the player of this VU between its polls
let lastModified = '';
const etags = {};

export function player() {
	const headers = { 'User-Agent': agentOf(__VU) };
	if (lastModified !== '')
		headers['If-Modified-Since'] = lastModified;

	const index = http.get(`${BASE_URL}/smil-index`, { headers, tags: { name: 'smil-index' } });
	check(index, { 'index 200 or 304': (r) => r.status === 200 || r.status === 304 });
	if (index.status === 200)
	{
		lastModified = index.headers['Last-Modified'] || '';
		// the SMIL has the urls of the configured server, the test asks the Pi under BASE_URL
		const paths = new Set([...index.body.matchAll(/src="[^"]*?(\/delivery\.php\/[^"]+)"/g)].map((m) => m[1]));
		check(paths, { 'index has media': (p) => p.size > 0 });
		for (const path of paths)
			download(path);
	}

	sleep(POLL);
}

function download(path) {
	const headers = { 'User-Agent': agentOf(__VU) };
	if (etags[path] !== undefined)
		headers['If-None-Match'] = etags[path];

	// the player stores the file, the test needs no body: 200 VUs with 170 MB each would not fit in the memory
	const res = http.get(`${BASE_URL}${path}`, { headers, responseType: 'none', timeout: '10m', tags: { name: 'delivery' } });
	check(res, { 'media 200 or 304': (r) => r.status === 200 || r.status === 304 });
	if (res.headers.Etag !== undefined)
		etags[path] = res.headers.Etag;
}

let loggedIn = false;

export function user(data) {
	if (!loggedIn)
	{
		login();
		loggedIn = true;
	}

	for (const [path, name] of [
		['/playlists', '/playlists'],
		[`/playlists/compose/${data.playlistId}`, '/playlists/compose/id'],
		[`/async/playlists/items/load/${data.playlistId}`, '/async/playlists/items/load/id'],
		['/mediapool', '/mediapool'],
		[`/async/mediapool/media/list/${data.nodeId}`, '/async/mediapool/media/list/id'],
		['/player', '/player'],
	])
	{
		const res = http.get(`${BASE_URL}${path}`, { tags: { name } });
		check(res, { 'page 200': (r) => r.status === 200 && !r.url.endsWith('/login') });
		// a user reads before the next click
		sleep(3 + Math.random() * 7);
	}
}
