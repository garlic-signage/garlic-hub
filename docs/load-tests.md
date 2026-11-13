# Load Tests

The load tests in `tests/Load` answer one question: can a Raspberry Pi serve the CMS of a typical installation?
They run with [k6](https://k6.io) in Docker on another computer in the same network, nothing has to be installed
on the Pi.

| File            | What it does                                                                                     |
|-----------------|--------------------------------------------------------------------------------------------------|
| `make-media.sh` | creates the test media: 10 pictures in 4K (about 4.5 MB) and 3 videos in Full HD (about 50 MB)   |
| `seed.js`       | fills a fresh installation through the API: 2000 media in 20 folders, 200 playlists, 200 players |
| `pi.js`         | 200 players and 10 users at the same time                                                        |

## Run them

On a fresh installation, with the admin created at `/create-initial`:

```bash
tests/Load/make-media.sh
docker run --rm -i --network host -v "$PWD/tests/Load:/load" \
  -e BASE_URL=http://<pi> -e USERNAME=<admin> -e PASSWORD=<password> grafana/k6 run /load/pi.js
```

`BASE_URL` must be the IP or the host name of the Pi. The header comments of `pi.js` and `seed.js` list the
options. For a run on realistic data, run `seed.js` first and then `pi.js` with the `PLAYLIST_ID` and `NODE_ID`
which `seed.js` prints at the end.

What `pi.js` simulates:

- **Players**: all of them ask for their SMIL index at the same time, like after an export, and download the media
  of their playlist. Then each one asks every 5 minutes (`POLL`), with `If-Modified-Since` and `If-None-Match` like
  a real player.
- **Users**: logged in, they click through playlists, the playlist editor, the mediapool and the players, with
  3 to 10 seconds between two clicks.

Watch the Pi during the run, e.g. with `vmstat 1` and `vcgencmd measure_temp`. Never open the SQLite database on
the Pi as another user than `www-data`, see [Raspberry Pi Image](raspberry-pi.md#backup).

## Results: Raspberry Pi 4 with 1 GB

Raspberry Pi 4 with 1 GB, 64 GB microSD card, passive case without fan, gigabit cable. The
[Raspberry Pi image](raspberry-pi.md) with its settings for 1 GB: 6 PHP workers, `memory_limit` 256M.

### 1. 200 players and 10 users, 15 minutes

All players have the same playlist with the 13 test media, 184 MB per player.

| Measured                     | Result                       |
|------------------------------|------------------------------|
| failed requests              | 1 of 5204 (0.01 %)           |
| clicks of the users, p95     | 89 ms                        |
| SMIL index, p95              | 4.2 s, in the burst at start |
| delivered                    | 38 GB, at most 2:41 min      |
| memory used, max             | 278 MB, no swap              |
| CPU idle, average            | 93 %                         |

The players got 38 GB at the speed of the network: the same 13 files stay in the page cache of the Pi. The index
of 200 players at once keeps the CPU busy for a few seconds, then the Pi is idle again.

### 2. Seed: 2000 media, 200 playlists, 200 players

| Measured                     | Result                           |
|------------------------------|----------------------------------|
| uploads                      | 2000 in 31 min, 13 GB            |
| upload with thumbnails, p95  | 3.1 s, at most 19 s              |
| failed                       | none                             |
| memory used, max             | 455 MB, swap 7 MB                |
| temperature, max             | 53 °C, never throttled           |
| card / database afterwards   | 17 of 58 GB used / 1.7 MB        |

### 3. 200 players and 10 users on the seeded data, 2 hours

Every player has its own playlist with 10 of the 2000 media, 19 GB in all.

| Measured                     | Result                                  |
|------------------------------|-----------------------------------------|
| failed requests              | 3 of 17756 (0.02 %), logins at start    |
| clicks of the users, p95     | 53 ms                                   |
| SMIL index, p95              | 87 ms                                   |
| delivered                    | 19 GB, at most 4:15 min                 |
| memory used                  | 229 to 234 MB for 2 hours, swap 6 MB    |
| temperature, max             | 48 °C, never throttled                  |
| CPU idle, average            | 94 %                                    |

The memory stayed flat for 2 hours: no leak. The first 6 minutes the Pi waited for the card (I/O wait about
90 %): 200 players asked for 2000 different files, which do not fit into the page cache, and a microSD card on a
Pi 4 reads about 50 MB/s, half the speed of the network. After the first download the players only got
304 Not Modified.

The 3 failed requests were logins at the very start, while 200 players asked for their index: the connection
was closed without an answer and k6 does not repeat a POST. The first run had one of these too. A browser
repeats such a request.

### Raw results

The output of k6 for each run without its progress lines: the messages during the run and the summary at the end.

1. [result-pi4-1gb.txt](../tests/Load/result-pi4-1gb.txt): 200 players and 10 users, 15 minutes
2. [seed-pi4-1gb.txt](../tests/Load/seed-pi4-1gb.txt): seed
3. [result-pi4-1gb-seeded-2h.txt](../tests/Load/result-pi4-1gb-seeded-2h.txt): on the seeded data, 2 hours

## What it means

- **A Raspberry Pi 4 with 1 GB is enough** for 200 players and 10 users at the same time. Memory and CPU have
  large reserves, a typical installation with far fewer displays is no challenge for it.
- **The limit is the storage, then the network**, not memory or CPU: when many players load different media at
  once, a microSD card delivers at half the speed of the network. With an SSD the network is the limit.
- **Peaks only delay**: after an export the players get their index and media a few seconds or minutes later,
  they keep playing meanwhile. A user who clicks during such a peak may wait a few seconds.
- A Raspberry Pi 3 has 100 Mbit and is not tested, its network limits it to far fewer players.
