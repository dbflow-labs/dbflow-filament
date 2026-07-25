# Security Policy

## Supported Versions

| Version | Supported |
| --- | --- |
| 1.1.x | Yes |
| 1.0.x | Critical security fixes only |
| Below 1.0 | No |

## Reporting a Vulnerability

**Do not report security vulnerabilities through public GitHub Issues.**

Public issue trackers are not monitored for confidential security reports. Posting vulnerability details publicly may put other users at risk.

### Preferred reporting channel

When enabled for this repository, use [GitHub Private Vulnerability Reporting](https://github.com/dbflow-labs/dbflow-filament/security/advisories/new) to submit a confidential report.

### Fallback reporting channel

If private vulnerability reporting is unavailable, email:

**hello@dbflow.dev**

Suggested subject line:

```text
Security vulnerability in dbflowlabs/filament
```

## Information to Include

Please include as much of the following as possible:

- Affected DBFlow Filament version
- Affected DBFlow Core version
- Laravel version
- Filament version
- PHP version
- Database platform and version
- Vulnerability description
- Reproduction steps
- Minimal reproduction repository when possible
- Potential impact
- Suggested mitigation when available

## Do Not Include Sensitive Data

Do not include the following in your report:

- Passwords
- API keys
- Tokens
- Composer credentials
- License keys
- Personal data
- Customer data
- Production database exports
- Private source code unrelated to reproduction

Remove secrets and production data from reproduction steps, logs, screenshots, and sample payloads.

## Security-Sensitive Areas

Reports related to the following areas are especially important:

- Workflow task authorization
- Approval permissions
- Rejection permissions
- Cancellation permissions
- Reassignment permissions
- Delegation visibility
- Workflow instance visibility
- Audit-log visibility
- Assignee resolution
- Action execution payload visibility
- Credential exposure
- Configuration exposure
- Unauthorized cross-user access
- Unauthorized cross-tenant access

## Responsible Disclosure

We ask reporters to allow reasonable time to investigate and address confirmed issues before public disclosure. Coordinated disclosure helps protect DBFlow users.

We do not guarantee:

- A response within a fixed number of hours
- A fix within a guaranteed number of days
- Indefinite maintenance
- A financial bounty
- Public recognition
- Support beyond current operational capacity

## Non-Security Requests

Ordinary bugs, feature requests, installation questions, and support requests should use the normal GitHub Issues or support channels for this repository.
