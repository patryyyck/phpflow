# Security Policy

## Supported versions

Security fixes are applied to the latest released PHPFlow version. Users should reproduce a
reported issue against the latest release when possible.

## Reporting a vulnerability

Please do not publish exploit details, credentials, tokens or sensitive target-project data in a
public issue.

If GitHub private vulnerability reporting is available for this repository, use the repository's
**Security** tab and **Report a vulnerability** flow.

If private vulnerability reporting is not available, open a minimal public issue requesting a
private reporting channel **without including vulnerability details**.

For ordinary analyzer bugs, unsupported static patterns and false positives/negatives, use the
regular issue templates instead.

## Scope

PHPFlow is designed to analyze source code statically and must not execute the target application.
A behavior that unexpectedly causes target-project code to run should be treated as
security-sensitive.
