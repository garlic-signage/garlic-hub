#!/bin/bash
# garlic-hub.cdx.json: the composer packages, then the third party libraries in public/ from resources/sbom/frontend.json
# Made by .github/workflows/release.yml: attached to the release and copied into the Docker image.
set -e
cd "$(dirname "$0")/.."

composer CycloneDX:make-sbom --omit=dev --output-format=JSON --output-file=garlic-hub.cdx.json
php bin/sbom-frontend.php
