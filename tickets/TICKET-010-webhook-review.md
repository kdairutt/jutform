# TICKET-010: Security review — webhook URL handling

**Priority:** Critical
**Reported by:** Security team

> "During a routine security review we flagged concerns around how user-supplied webhook URLs are processed. We believe the current validation may not be sufficient to prevent requests to internal infrastructure. We also noticed that some internal endpoints appear to trust the source of the request rather than requiring proper authentication. Engineering should review the webhook URL validation and internal endpoint access controls."

This is a security-sensitive issue — investigate and remediate in a controlled environment only.
