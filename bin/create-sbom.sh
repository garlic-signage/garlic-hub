#!/bin/bash

composer CycloneDX:make-sbom --omit=dev --output-format=JSON --output-file=sbom.json