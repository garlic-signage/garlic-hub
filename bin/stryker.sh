#!/bin/bash
# Mutation tests of public/js with StrykerJS, in Docker (Node + Deno), reports in var/stryker/.
#   bin/stryker.sh                    all groups one after another (takes hours)
#   bin/stryker.sh core uploads       only these groups
#   bin/stryker.sh -c <name> <tests below tests/Js> <globs below public/js>...   own run, e.g. one file:
#   bin/stryker.sh -c fetch core/FetchClient.test.js core/FetchClient.js
# Each mutant runs the deno tests of its group, so a group only mutates the code its tests cover.
# Equivalent mutants are marked in the code: // Stryker disable next-line <Mutator>: <reason>
set -e
cd "$(dirname "$0")/.."

declare -A TESTS=(
	[core]="core"
	[small]="account users player/datatable playlists/overview templates/datatable templates/selectors"
	[uploads]="mediapool/uploads"
	[mediapool]="mediapool/media mediapool/treeview mediapool/selector"
	[playlists-standard]="playlists/compose/standard"
	[playlists-multizone]="playlists/compose/multizone"
	[composer-services]="templates/canvas-composer/Services"
	[composer-controller]="templates/canvas-composer/Controller"
	[composer-rest]="templates/canvas-composer/Views templates/canvas-composer/Utils templates/canvas-composer/Formats templates/canvas-composer/Fonts"
)
declare -A MUTATE=(
	[core]="core/**/*.js"
	[small]="account/**/*.js users/**/*.js player/datatable/**/*.js playlists/overview/**/*.js templates/datatable/**/*.js templates/selectors/**/*.js"
	[uploads]="mediapool/uploads/**/*.js"
	[mediapool]="mediapool/media/**/*.js mediapool/treeview/**/*.js mediapool/selector/**/*.js mediapool/*.js"
	[playlists-standard]="playlists/compose/standard/**/*.js"
	[playlists-multizone]="playlists/compose/multizone/**/*.js playlists/compose/*.js"
	[composer-services]="templates/canvas-composer/Services/**/*.js"
	[composer-controller]="templates/canvas-composer/Controller/**/*.js"
	[composer-rest]="templates/canvas-composer/Views/**/*.js templates/canvas-composer/Utils/**/*.js templates/canvas-composer/Formats/**/*.js templates/canvas-composer/Fonts/**/*.js templates/canvas-composer/*.js"
)
GROUPS_IN_ORDER="core small uploads mediapool playlists-standard playlists-multizone composer-services composer-controller composer-rest"

# not tested: page starters, the garlic-player WASM preview, libraries and the font scripts
EXCLUDE='"!public/js/**/init.js","!public/js/player/preview/**","!public/js/external/**","!public/js/templates/canvas-composer/Fonts/{font_generate,fontfaceobserver,fonts_preview}.js"'

IMAGE=garlic-stryker
docker build -q -t "$IMAGE" - > /dev/null <<'DOCKERFILE'
FROM denoland/deno:bin AS deno
FROM node:22-bookworm-slim
COPY --from=deno /deno /usr/local/bin/deno
# procps: Stryker ends the test processes with ps
RUN apt-get update && apt-get install -y --no-install-recommends procps && rm -rf /var/lib/apt/lists/* \
 && npm install -g @stryker-mutator/core@9.6.1
DOCKERFILE

mkdir -p var/stryker
docker_run() { docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -e DENO_DIR=/app/var/stryker/deno -v "$PWD":/app -w /app "$IMAGE" "$@"; }

# run <name> <tests> <globs>
run()
{
	local name=$1 tests=$2 mutate="" glob
	set -f   # the globs are for Stryker, not the shell
	for glob in $3; do mutate="$mutate\"public/js/$glob\","; done
	set +f
	cat > "var/stryker/$name.config.json" <<CONFIG
{
  "testRunner": "command",
  "commandRunner": { "command": "cd tests/Js && deno test -A --quiet $tests" },
  "mutate": [$mutate$EXCLUDE],
  "ignorePatterns": ["/*", "!/public", "/public/*", "!/public/js", "!/tests", "/tests/*", "!/tests/Js"],
  "coverageAnalysis": "off",
  "concurrency": ${CONCURRENCY:-$(( $(nproc) * 3 / 4 ))},
  "timeoutMS": 20000,
  "tempDirName": "var/stryker/tmp-$name",
  "cleanTempDir": "always",
  "reporters": ["clear-text", "html", "json"],
  "htmlReporter": { "fileName": "var/stryker/$name.html" },
  "jsonReporter": { "fileName": "var/stryker/$name.json" },
  "clearTextReporter": { "allowColor": false, "logTests": false, "reportMutants": false }
}
CONFIG
	echo "== $name"
	docker_run stryker run "var/stryker/$name.config.json" > "var/stryker/$name.log" 2>&1 || { tail -20 "var/stryker/$name.log"; return 1; }
	grep -E "^All files" "var/stryker/$name.log" || true

	# the surviving mutants with line and replacement
	docker_run node -e '
		const report = require("/app/var/stryker/" + process.argv[1] + ".json");
		for (const [path, file] of Object.entries(report.files).sort())
		{
			const lines = file.source.split("\n");
			const open  = file.mutants.filter(m => m.status === "Survived" || m.status === "NoCoverage")
				.sort((a, b) => a.location.start.line - b.location.start.line);
			if (open.length === 0)
				continue;
			console.log("  " + path);
			for (const m of open)
			{
				const line = m.location.start.line;
				console.log(`    ${line}: [${m.mutatorName}] ${lines[line - 1].trim().slice(0, 100)}  =>  ${JSON.stringify((m.replacement ?? "").slice(0, 80))}`);
			}
		}' "$name"
}

if [ "$1" = "-c" ]; then
	name=$2; tests=$3; shift 3
	run "$name" "$tests" "$*"
	exit
fi

for group in ${*:-$GROUPS_IN_ORDER}; do
	if [ -z "${TESTS[$group]}" ]; then
		echo "unknown group $group, groups: $GROUPS_IN_ORDER" >&2
		exit 1
	fi
	run "$group" "${TESTS[$group]}" "${MUTATE[$group]}"
done
