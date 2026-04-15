#!/bin/bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage: ./scripts/load-gen.sh <command> [options]

Concurrent-burst HTTP load generator. Fires configurable bursts of parallel
GET requests at a single URL, either once or in a background loop, using
cookies from a jar file. Useful for exercising hot paths, measuring latency
under contention, and reproducing load-dependent behavior.

Commands:
  start                  start a background burst loop
  stop                   stop the background loop
  status                 show whether it's running, plus a single latency probe
  once                   fire one burst in the foreground and exit

Options:
  -u, --url URL            target URL to hit on every request (required for
                           start/once/status)
  -c, --concurrency N      parallel requests per burst (default: 10)
  -i, --interval S         seconds between bursts in loop mode (default: 12)
  -t, --timeout S          per-request curl --max-time (default: 30)
  -j, --cookie-jar PATH    curl cookie jar file (default: <state-dir>/cookies)
  -H, --cookie-header STR  raw "Cookie: ..." value; used instead of the jar
                           when set
      --login-url URL      if set, POST to this URL before bursts to populate
                           the cookie jar
      --login-body JSON    JSON body for --login-url (required if login used)
      --login-content-type CT
                           Content-Type header for login POST
                           (default: application/json)
  -p, --probe-url URL      URL used by `status` for the latency probe
                           (default: same as --url)
  -s, --state-dir DIR      where pid/log/cookie files live
                           (default: /tmp/load-gen)
  -h, --help               show this help

Examples:
  ./scripts/load-gen.sh start -u http://localhost:8080/api/forms
  ./scripts/load-gen.sh start -u http://localhost:8080/api/forms/analytics \
      --login-url http://localhost:8080/api/auth/login \
      --login-body '{"username":"admin","password":"password"}'
  ./scripts/load-gen.sh once -u http://localhost:8080/api/forms -c 20
  ./scripts/load-gen.sh stop
EOF
}

cmd="${1:-}"
if [ -z "$cmd" ] || [ "$cmd" = "-h" ] || [ "$cmd" = "--help" ]; then
    usage
    [ -z "$cmd" ] && exit 2 || exit 0
fi
shift || true

target_url=""
concurrency=10
interval=12
timeout=30
cookie_jar=""
cookie_header=""
login_url=""
login_body=""
login_content_type="application/json"
probe_url=""
state_dir="/tmp/load-gen"

while [ $# -gt 0 ]; do
    case "$1" in
        -u|--url)                target_url="$2"; shift 2 ;;
        -c|--concurrency)        concurrency="$2"; shift 2 ;;
        -i|--interval)           interval="$2"; shift 2 ;;
        -t|--timeout)            timeout="$2"; shift 2 ;;
        -j|--cookie-jar)         cookie_jar="$2"; shift 2 ;;
        -H|--cookie-header)      cookie_header="$2"; shift 2 ;;
        --login-url)             login_url="$2"; shift 2 ;;
        --login-body)            login_body="$2"; shift 2 ;;
        --login-content-type)    login_content_type="$2"; shift 2 ;;
        -p|--probe-url)          probe_url="$2"; shift 2 ;;
        -s|--state-dir)          state_dir="$2"; shift 2 ;;
        -h|--help)               usage; exit 0 ;;
        --)                      shift; break ;;
        -*)
            echo "Unknown option: $1" >&2
            usage
            exit 1
            ;;
        *)
            echo "Unexpected argument: $1" >&2
            usage
            exit 1
            ;;
    esac
done

if ! [[ "$concurrency" =~ ^[0-9]+$ ]] || [ "$concurrency" -lt 1 ]; then
    echo "Error: --concurrency must be a positive integer (got: $concurrency)." >&2
    exit 1
fi
if ! [[ "$interval" =~ ^[0-9]+$ ]] || [ "$interval" -lt 1 ]; then
    echo "Error: --interval must be a positive integer (got: $interval)." >&2
    exit 1
fi
if ! [[ "$timeout" =~ ^[0-9]+$ ]] || [ "$timeout" -lt 1 ]; then
    echo "Error: --timeout must be a positive integer (got: $timeout)." >&2
    exit 1
fi

mkdir -p "$state_dir"
[ -z "$cookie_jar" ] && cookie_jar="$state_dir/cookies"
[ -z "$probe_url" ] && probe_url="$target_url"
pid_file="$state_dir/pid"
log_file="$state_dir/load.log"

require_url() {
    if [ -z "$target_url" ]; then
        echo "Error: --url is required." >&2
        exit 1
    fi
}

do_login() {
    [ -z "$login_url" ] && return 0
    if [ -z "$login_body" ]; then
        echo "Error: --login-url requires --login-body." >&2
        exit 1
    fi
    curl -sS -f -c "$cookie_jar" \
        -H "Content-Type: $login_content_type" \
        -X POST "$login_url" \
        -d "$login_body" >/dev/null
}

build_auth_args() {
    if [ -n "$cookie_header" ]; then
        auth_args_arr=(-H "Cookie: $cookie_header")
    else
        auth_args_arr=(-b "$cookie_jar")
    fi
}

fire_burst() {
    local url="$1"
    build_auth_args
    for i in $(seq 1 "$concurrency"); do
        curl -sS "${auth_args_arr[@]}" -o /dev/null \
            -w "  [req-$i] %{http_code} in %{time_total}s\n" \
            --max-time "$timeout" \
            "$url" &
    done
    wait
}

burst_loop() {
    do_login
    echo "[load-gen] target=$target_url concurrency=$concurrency interval=${interval}s" >>"$log_file"
    while :; do
        echo "[load-gen] $(date '+%Y-%m-%dT%H:%M:%S%z') firing burst of $concurrency" >>"$log_file"
        fire_burst "$target_url" >>"$log_file" 2>&1 || true
        sleep "$interval"
    done
}

probe() {
    build_auth_args
    curl -sS "${auth_args_arr[@]}" -o /dev/null \
        -w "  %{url_effective} -> %{http_code} in %{time_total}s\n" \
        --max-time "$timeout" "$probe_url" || true
}

case "$cmd" in
    start)
        require_url
        if [ -f "$pid_file" ] && kill -0 "$(cat "$pid_file")" 2>/dev/null; then
            echo "already running, pid=$(cat "$pid_file")"
            exit 0
        fi
        : >"$log_file"
        do_login
        script_path="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/$(basename "${BASH_SOURCE[0]}")"
        nohup bash -c "
            set -euo pipefail
            target_url='$target_url'
            concurrency='$concurrency'
            interval='$interval'
            timeout='$timeout'
            cookie_jar='$cookie_jar'
            cookie_header='$cookie_header'
            login_url='$login_url'
            login_body='$login_body'
            login_content_type='$login_content_type'
            log_file='$log_file'
            $(declare -f do_login build_auth_args fire_burst burst_loop)
            burst_loop
        " >/dev/null 2>&1 &
        echo $! >"$pid_file"
        disown || true
        echo "load-gen started, pid=$(cat "$pid_file")"
        echo "tail log:  tail -f $log_file"
        echo "stop:      $script_path stop"
        ;;
    stop)
        if [ -f "$pid_file" ]; then
            pid="$(cat "$pid_file")"
            if kill -0 "$pid" 2>/dev/null; then
                pkill -P "$pid" 2>/dev/null || true
                kill "$pid" 2>/dev/null || true
                echo "stopped pid=$pid"
            else
                echo "pid=$pid no longer running"
            fi
            rm -f "$pid_file"
        else
            echo "no pid file, nothing to stop"
        fi
        ;;
    status)
        require_url
        if [ -f "$pid_file" ] && kill -0 "$(cat "$pid_file")" 2>/dev/null; then
            echo "load-gen running, pid=$(cat "$pid_file")"
        else
            echo "load-gen not running"
        fi
        echo "latency probe:"
        probe
        ;;
    once)
        require_url
        do_login
        echo "Firing a single burst of $concurrency concurrent requests against $target_url..."
        fire_burst "$target_url"
        ;;
    *)
        echo "Unknown command: $cmd" >&2
        usage
        exit 1
        ;;
esac
