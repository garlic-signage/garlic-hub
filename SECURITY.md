# Security Policy

Reporting process and GPG key: see the
[organization security policy](https://github.com/garlic-signage/.github/blob/main/SECURITY.md).

## Supported Versions

Only the latest release receives security fixes. Please update to it.

## Security Advisories

Fixed vulnerabilities are published as
[GitHub Security Advisories](https://github.com/garlic-signage/garlic-hub/security/advisories),
with a CVE identifier where one is assigned. Dependabot and other scanners pick them up
from there. Ordinary bugs are tracked in the issues and the release notes.

## Before Reporting

Please read the [Threat Model](docs/threat-model.md). It documents which
properties garlic-hub is designed to protect and which behaviours are
deliberate design decisions arising from the constraints of unattended signage
hardware. Reports describing documented design decisions as vulnerabilities
will be closed with a reference to that document.

Reports must include a proof of concept demonstrating the claimed impact.
Please agree on a severity assessment before requesting a CVE identifier or
contacting a CVE Numbering Authority.