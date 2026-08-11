# Security Policy

## Supported Version

| Version | Security Support |
|---|---|
| 1.1.x | Supported |
| 1.0.x | Limited / upgrade recommended |

Users should deploy the latest available release.

## Security Model

WP Blogger is an activity logging and integrity-monitoring component for WordPress.

It is intended to provide visibility into:

- authentication activity;
- user and role changes;
- plugin and theme changes;
- WordPress updates;
- selected content and media events;
- configuration changes;
- filesystem additions, modifications and deletions;
- suspicious executable files;
- selected XML-RPC and REST activity;
- repeated failed authentication.

WP Blogger is not a complete endpoint protection, malware prevention or intrusion prevention product.

## Trust Assumptions

The security model assumes:

1. the operating system is not fully compromised;
2. the PHP process has limited privileges;
3. WP Blogger code is not writable by untrusted WordPress users;
4. the primary log directory is protected from public web access;
5. the initial integrity baseline is created from a trusted installation.

An attacker with root or equivalent server privileges may be able to modify or remove local logs and plugin code.

## Recommended Deployment

Use:

```text
/wp-content/mu-plugins/wp-blogger.php
/wp-content/wp-blogger/
/var/log/wp-blogger/
```

Prefer `/var/log/wp-blogger/` over a web-accessible path.

Where possible:

- make WP Blogger code root-owned;
- prevent Apache/PHP from modifying plugin code;
- allow the PHP process to write only where required;
- restrict SSH access;
- use SSH keys;
- restrict WordPress administrator access;
- deploy MFA where practical;
- forward high-value audit events to an external system.

## Sensitive Data

WP Blogger should not intentionally record:

- passwords;
- authentication cookies;
- private keys;
- API secrets;
- database passwords;
- session tokens;
- full sensitive POST bodies.

Security contributions should preserve this principle.

## Integrity Baseline Warning

The filesystem baseline should only be created after the WordPress installation has been reviewed.

If malware or a web shell exists before baseline creation, its hash may become part of the trusted state.

A baseline is a change reference, not a malware verdict.

## Security Events Are Indicators

Events such as:

```text
file_modified
file_added
file_deleted
suspicious_file
php_in_uploads
```

must be investigated in context.

Legitimate WordPress updates may cause filesystem changes.

Likewise, malware can use benign-looking filenames and may evade simple filename detection.

## Reporting a Vulnerability

Do not publicly disclose an exploitable vulnerability in an open GitHub issue before maintainers have had a reasonable opportunity to assess it.

A vulnerability report should include:

- affected version;
- affected component or file;
- technical description;
- reproduction steps;
- security impact;
- prerequisites;
- proof-of-concept details where appropriate;
- proposed mitigation if known.

Do not include:

- real production credentials;
- private keys;
- authentication cookies;
- personal data;
- live exploit payloads targeting third-party systems.

If the repository has a private security advisory feature enabled, use it for vulnerability reports.

Otherwise, contact the project maintainer through the private contact method listed by the repository owner.

## Security Review Priorities

Changes to the following areas require additional review:

- log path handling;
- filesystem scanning;
- file read/write operations;
- WordPress capability checks;
- nonces;
- REST endpoints;
- admin actions;
- user-supplied paths;
- log viewer output escaping;
- JSON parsing;
- baseline replacement;
- export functions;
- external integrations.

## WordPress Administration

Administrative WP Blogger actions must use appropriate WordPress capability checks.

Security-sensitive actions should also use WordPress nonce validation where applicable.

Do not rely on menu visibility alone as access control.

## Output Handling

All data displayed in the WordPress administration interface should be escaped appropriately before HTML output.

Log entries may contain attacker-controlled values such as:

- usernames;
- request URIs;
- user agents;
- filenames;
- IP-derived text fields.

These values must never be treated as trusted HTML.

## Filesystem Scanning

Filesystem monitoring must avoid:

- arbitrary user-controlled filesystem traversal;
- following unsafe symbolic links without review;
- exposing file contents unnecessarily;
- storing secrets in logs;
- unbounded scans that can exhaust server resources.

Future development should include configurable exclusions and resource limits.

## Log Integrity

Local logs are useful but are not immutable.

For higher-assurance environments, forward logs to:

- remote syslog;
- Wazuh;
- Elastic Stack;
- Splunk;
- Graylog;
- another security logging service.

External copies provide stronger resistance against local evidence destruction.

## Incident Response

If WP Blogger detects suspicious activity:

1. preserve relevant logs;
2. preserve Apache/PHP/system logs;
3. identify affected files;
4. calculate hashes;
5. review file ownership and timestamps;
6. compare WordPress Core against an official release;
7. review plugins and themes;
8. review user and administrator accounts;
9. rotate compromised credentials;
10. restore from a verified source where necessary.

Avoid deleting evidence before collection when an investigation is required.

## Hardening Recommendations

WP Blogger should be combined with:

- current WordPress Core;
- current plugins and themes;
- removal of unused extensions;
- restricted filesystem permissions;
- restricted PHP execution in uploads;
- SSH key authentication;
- OS patching;
- database least privilege;
- backups;
- WAF/firewall controls;
- security monitoring.

## Security Disclaimer

WP Blogger provides monitoring and audit support.

It does not guarantee prevention or detection of every compromise, malicious file, web shell, credential attack or persistence mechanism.
