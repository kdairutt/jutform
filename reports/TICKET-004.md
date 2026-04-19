# Investigation Report — Duplicate confirmation emails

## Debugging Steps

1. Reviewed `EmailWorker::processBatch()` — two workers running concurrently both SELECT the same pending rows before either marks them as sent.
2. Confirmed race condition: Worker-1 and Worker-2 both pick up row id=5, both send the email, then both try to UPDATE — first wins, but email already sent twice.

## Root Cause

No atomic claim between SELECT and send. Two workers could read the same pending rows simultaneously and both send before either updated the status.

## Fix Description

Added `processing` status to `scheduled_emails`. Before sending, each worker atomically claims a row with `UPDATE ... WHERE status = 'pending'`. If `rowCount() !== 1`, another worker already claimed it — skip. MySQL's atomic UPDATE guarantees only one worker can claim each row.

## Response to Reporter

> Hi,

> We identified the issue — during busy periods two background processes were occasionally picking up the same email simultaneously. We've added an atomic locking mechanism so each email can only be claimed and sent by one process. The duplicate sends should no longer occur.