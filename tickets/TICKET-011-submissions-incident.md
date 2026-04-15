# TICKET-011: User briefly saw another account's data

**Priority:** Critical
**Reported by:** Customer (username: `alice`) via Support, escalated to Security

> "I opened my submissions page and for a second I saw form entries that were definitely not mine — different form names, different data. I refreshed and it was back to normal. It freaked me out."

A second user reported something similar last week. Both incidents involved a single page load and did not persist after refresh. We have not been able to reproduce it reliably from the reports alone, but given the severity this needs an engineering investigation into how user data is scoped during request handling.
