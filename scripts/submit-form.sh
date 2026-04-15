#!/bin/bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage: ./scripts/submit-form.sh <form_id> [options]

Submit synthetic POSTs to /api/forms/<form_id>/submissions. Useful for
populating test data, generating activity, and exercising code paths that
depend on a form having many submissions (list views, search, analytics,
workers, etc.).

Options:
  -n, --count N         number of submissions to send (default: 100)
  -c, --concurrency M   number of requests in flight at once (default: 10)
  -H, --host URL        base URL of the app (default: http://localhost:8080)
  -d, --data JSON       JSON object used as the "data" field. The token
                        "{i}" in the JSON is replaced with the submission
                        index (1..N). Defaults to a name/email payload.
  -q, --quiet           print only the final status-code summary
  -h, --help            show this help

Examples:
  ./scripts/submit-form.sh 1
  ./scripts/submit-form.sh 1 -n 500 -c 25
  ./scripts/submit-form.sh 3 -n 50 -d '{"name":"Tester {i}","email":"t{i}@x.test"}'
EOF
}

form_id=""
count=100
concurrency=10
host="http://localhost:8080"
data_tpl='{"name":"Load User {i}","email":"load{i}@example.test"}'
quiet=0

while [ $# -gt 0 ]; do
    case "$1" in
        -n|--count)       count="$2"; shift 2 ;;
        -c|--concurrency) concurrency="$2"; shift 2 ;;
        -H|--host)        host="$2"; shift 2 ;;
        -d|--data)        data_tpl="$2"; shift 2 ;;
        -q|--quiet)       quiet=1; shift ;;
        -h|--help)        usage; exit 0 ;;
        --)               shift; break ;;
        -*)
            echo "Unknown option: $1" >&2
            usage
            exit 1
            ;;
        *)
            if [ -z "$form_id" ]; then
                form_id="$1"
            else
                echo "Unexpected argument: $1" >&2
                usage
                exit 1
            fi
            shift
            ;;
    esac
done

if [ -z "$form_id" ]; then
    echo "Error: form id is required." >&2
    echo >&2
    usage
    exit 1
fi

if ! [[ "$count" =~ ^[0-9]+$ ]] || [ "$count" -lt 1 ]; then
    echo "Error: --count must be a positive integer (got: $count)." >&2
    exit 1
fi

if ! [[ "$concurrency" =~ ^[0-9]+$ ]] || [ "$concurrency" -lt 1 ]; then
    echo "Error: --concurrency must be a positive integer (got: $concurrency)." >&2
    exit 1
fi

url="$host/api/forms/$form_id/submissions"

if [ "$quiet" -eq 0 ]; then
    echo "Posting $count submissions to $url (concurrency: $concurrency)..."
fi

export SUBMIT_URL="$url"
export SUBMIT_BODY_TPL="$data_tpl"

start_ts=$(date +%s)

status_summary=$(
    seq 1 "$count" | xargs -P "$concurrency" -I{} sh -c '
        i="$1"
        body=$(printf "%s" "$SUBMIT_BODY_TPL" | sed "s/{i}/$i/g")
        curl -s -o /dev/null -w "%{http_code}\n" \
            -X POST "$SUBMIT_URL" \
            -H "Content-Type: application/json" \
            -d "{\"data\":$body}"
    ' _ {} | sort | uniq -c | awk '{printf "  %s : %d\n", $2, $1}'
)

end_ts=$(date +%s)
elapsed=$((end_ts - start_ts))

echo "Status codes:"
echo "$status_summary"

if [ "$quiet" -eq 0 ]; then
    if [ "$elapsed" -gt 0 ]; then
        rps=$((count / elapsed))
        echo "Finished $count requests in ${elapsed}s (~${rps} req/s)."
    else
        echo "Finished $count requests in <1s."
    fi
fi
