/*
 Fills a fresh installation with the data of the largest one which a Raspberry Pi 4 with a 32 GB card should serve:
 2000 media in 20 folders, 200 playlists with 10 media each and 200 players, each with a playlist of its own.

 Everything goes through the API like in the UI, so thumbnails, metadata and exports are the real ones, and the
 time of the uploads on the Pi is part of the result. The media are those of make-media.sh: every upload gets
 a few bytes of its own at the end, so each one is a new file with its own checksum. With 5 % videos the media
 take about 10 GB.

   tests/Load/make-media.sh
   docker run --rm -i --network host -v "$PWD/tests/Load:/load" \
     -e BASE_URL=http://<pi> -e USERNAME=<admin> -e PASSWORD=<password> grafana/k6 run /load/seed.js

 At the end it prints PLAYLIST_ID and NODE_ID: pi.js runs with them on the seeded data instead of its own.
 Optional: MEDIA (2000), FOLDERS (20), PLAYLISTS (200), ITEMS (10), PLAYERS (200), VIDEOS (5 = percent),
 PARALLEL (4 uploads at the same time).
*/
import http from 'k6/http';
import { check, fail } from 'k6';

const BASE_URL  = (__ENV.BASE_URL || '').replace(/\/$/, '');
const MEDIA     = parseInt(__ENV.MEDIA || '2000', 10);
const FOLDERS   = parseInt(__ENV.FOLDERS || '20', 10);
const PLAYLISTS = parseInt(__ENV.PLAYLISTS || '200', 10);
const ITEMS     = parseInt(__ENV.ITEMS || '10', 10);
const PLAYERS   = parseInt(__ENV.PLAYERS || '200', 10);
const VIDEOS    = parseInt(__ENV.VIDEOS || '5', 10);
const PARALLEL  = parseInt(__ENV.PARALLEL || '4', 10);

const PICTURES = Array.from({ length: 10 }, (_, i) => open(`./media/picture-${i + 1}.jpg`, 'b'));
const MOVIES   = Array.from({ length: 3 }, (_, i) => open(`./media/video-${i + 1}.mp4`, 'b'));

export const options = {
	setupTimeout: '6h',
	vus: 1,
	iterations: 1,
	thresholds: { checks: ['rate>0.99'] },
};

function uuidOf(number) {
	return `00000000-0000-4000-8000-${String(number).padStart(12, '0')}`;
}

function agentOf(number) {
	return `GAPI/1.0 (UUID:${uuidOf(number)}; NAME:loadtest-${number}) garlic-linux/v0.6.0.679 (MODEL:Garlic)`;
}

function csrfToken(page) {
	const res   = http.get(`${BASE_URL}${page}`);
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

/**
 * The bytes of a file of make-media.sh with the number at the end: a new file for the mediapool.
 * JPEG and MP4 ignore what follows their end.
 */
function uniqueFile(number) {
	const isVideo = number % 100 < VIDEOS;
	const base    = new Uint8Array(isVideo ? MOVIES[number % MOVIES.length] : PICTURES[number % PICTURES.length]);
	const suffix  = Uint8Array.from(`seed-${number}-${Date.now()}`.split(''), (c) => c.charCodeAt(0));
	const bytes   = new Uint8Array(base.length + suffix.length);
	bytes.set(base);
	bytes.set(suffix, base.length);

	return isVideo
		? http.file(bytes.buffer, `seed-video-${number}.mp4`, 'video/mp4')
		: http.file(bytes.buffer, `seed-picture-${number}.jpg`, 'image/jpeg');
}

function login() {
	const res = http.post(`${BASE_URL}/login`, { username: __ENV.USERNAME, password: __ENV.PASSWORD, csrf_token: csrfToken('/login') });
	if (!check(res, { 'logged in': (r) => !r.url.endsWith('/login') }))
		fail('login failed');
}

/**
 * Half of the folders are roots, each of the others is a sub folder of one of them.
 */
function createFolders(token, seed) {
	const roots   = Math.ceil(FOLDERS / 2);
	const folders = [];
	for (let i = 1; i <= FOLDERS; i++)
	{
		const body = { name: `${seed}-folder-${i}`, csrf_token: token };
		if (i > roots)
			body.node_id = String(folders[(i - roots - 1) % roots]);
		folders.push(json(http.post(`${BASE_URL}/async/mediapool/node`, body), `folder ${i}`).data.id);
	}

	return folders;
}

function uploadMedia(token, folders) {
	const started = Date.now();
	for (let first = 1; first <= MEDIA; first += PARALLEL)
	{
		const batch = [];
		for (let number = first; number < first + PARALLEL && number <= MEDIA; number++)
		{
			batch.push(['POST', `${BASE_URL}/async/mediapool/uploadLocalFile`, {
				node_id: String(folders[number % folders.length]), csrf_token: token, file: uniqueFile(number),
			}, { timeout: '10m', tags: { name: 'upload' } }]);
		}
		http.batch(batch).forEach((res) => check(res, { 'uploaded': (r) => r.status === 200 && r.json().success === true }));

		if (first % 100 < PARALLEL)
			console.log(`${Math.min(first + PARALLEL - 1, MEDIA)} of ${MEDIA} media, ${Math.round((Date.now() - started) / 1000)} s`);
	}
}

function mediaIdsOf(folders) {
	const ids = [];
	for (const folder of folders)
		ids.push(...json(http.get(`${BASE_URL}/async/mediapool/media/list/${folder}`), `media of ${folder}`).media_list.map((m) => m.media_id));

	if (ids.length < MEDIA)
		fail(`only ${ids.length} of ${MEDIA} media are in the folders`);

	return ids;
}

function createPlaylists(token, seed, mediaIds) {
	const playlists = [];
	for (let i = 1; i <= PLAYLISTS; i++)
	{
		const name = `${seed}-playlist-${i}`;
		http.post(`${BASE_URL}/playlists/settings`, { playlist_name: name, playlist_mode: 'master', csrf_token: token });
		const playlist = json(http.get(`${BASE_URL}/async/playlists/find/master/${name}`), 'playlist').find((p) => p.name === name);
		if (playlist === undefined)
			fail(`playlist ${name} was not created`);

		// the playlists share their media like in real life: neighbours overlap
		for (let position = 1; position <= ITEMS; position++)
		{
			json(http.post(`${BASE_URL}/async/playlists/items/insert`, {
				playlist_id: String(playlist.id), id: mediaIds[(Math.floor((i - 1) * ITEMS / 2) + position) % mediaIds.length],
				source: 'mediapool', position: String(position), csrf_token: token,
			}), `item ${position} of ${name}`);
		}
		playlists.push(playlist.id);
	}

	return playlists;
}

/**
 * A new player is registered by its first request, the core edition releases it. Player n gets playlist n.
 */
function assignPlayers(token, playlists) {
	const numbers = new Map();
	for (let number = 1; number <= PLAYERS; number++)
	{
		http.get(`${BASE_URL}/smil-index`, { headers: { 'User-Agent': agentOf(number) } });
		numbers.set(uuidOf(number), number);
	}

	// the ids of the players are not known: the local player and others may be there already
	let assigned = 0;
	for (let playerId = 1; assigned < PLAYERS && playerId <= PLAYERS + 100; playerId++)
	{
		const res = http.get(`${BASE_URL}/async/player/${playerId}`, { responseCallback: http.expectedStatuses(200, 404) });
		if (res.status !== 200 || !numbers.has(res.json().uuid))
			continue;

		const playlistId = playlists[(numbers.get(res.json().uuid) - 1) % playlists.length];
		json(http.patch(`${BASE_URL}/async/player/playlist`, { player_id: String(playerId), playlist_id: String(playlistId), csrf_token: token }), 'assign');
		assigned++;
	}
	if (assigned !== PLAYERS)
		fail(`only ${assigned} of ${PLAYERS} players got a playlist`);
}

export function setup() {
	if (BASE_URL === '' || !__ENV.USERNAME || !__ENV.PASSWORD)
		fail('BASE_URL, USERNAME and PASSWORD are needed');

	login();
	const token = csrfToken('/mediapool');
	const seed  = `seed-${Date.now()}`;

	const folders = createFolders(token, seed);
	uploadMedia(token, folders);
	const playlists = createPlaylists(token, seed, mediaIdsOf(folders));
	assignPlayers(token, playlists);
	for (const playlistId of playlists)
		json(http.put(`${BASE_URL}/async/playlists`, { playlist_id: String(playlistId), csrf_token: token }), `export ${playlistId}`);

	console.log(`seeded: PLAYLIST_ID=${playlists[0]} NODE_ID=${folders[0]}`);
}

export default function () {
	// everything happens in the setup
}
