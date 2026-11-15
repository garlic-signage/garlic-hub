#!/bin/bash
# sbom.json: the composer packages, then the third party libraries in public/ from resources/sbom/frontend.json
set -e
cd "$(dirname "$0")/.."

composer CycloneDX:make-sbom --omit=dev --output-format=JSON --output-file=sbom.json
php bin/sbom-frontend.php
