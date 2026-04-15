# TICKET-006: Form search is extremely slow

**Priority:** High
**Reported by:** Multiple customers and internal QA (e.g. username: `poweruser`)

> "Searching for a form from my dashboard takes forever. I type something in the search box and just stare at a spinner for 10–15 seconds. Half the time I give up and refresh, which makes it even worse."

QA can reproduce the slowness on any account with a reasonable amount of data. A single search already feels sluggish; firing a handful of searches at once (e.g. a couple of browser tabs, or hitting the endpoint in parallel) makes the tail latency noticeably worse, which lines up with the "refresh makes it worse" report. The problem seems to have gotten worse over time as more data has accumulated.
