# TICKET-012: Security review — advanced search endpoint

**Priority:** Critical
**Reported by:** Security team

> "During a code audit we identified that the advanced search API may not be adequately sanitizing user input before building database queries. The concern is around how the search field parameter is handled. Engineering should review the query construction and ensure all user-supplied values are properly validated."

This is a security-sensitive issue — investigate and remediate in a controlled environment only.
