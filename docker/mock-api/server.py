"""
Mock external API that simulates a third-party service (e.g., payment processor).
Responds to requests after a configurable delay to mimic real-world latency.
"""

import http.server
import json
import os
import random
import secrets
import time

DEFAULT_DELAY_MS = int(os.environ.get("DEFAULT_DELAY_MS", "5000"))


class MockAPIHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path.startswith("/salt"):
            self._send_json(200, {"salt": secrets.token_hex(16)})
            return
        self._handle_request()

    def do_POST(self):
        if self.path.startswith("/charge"):
            self._handle_charge()
            return
        self._handle_request()

    def _read_json(self):
        length = int(self.headers.get("Content-Length", 0))
        raw = self.rfile.read(length) if length > 0 else b"{}"
        try:
            return json.loads(raw.decode("utf-8"))
        except json.JSONDecodeError:
            return {}

    def _auth_key(self):
        auth = self.headers.get("Authorization", "")
        if auth.startswith("Bearer "):
            return auth[7:].strip()
        return None

    def _handle_charge(self):
        delay_ms = random.randint(2000, 6000)
        time.sleep(delay_ms / 1000.0)
        data = self._read_json()
        api_key = self._auth_key()
        if not api_key:
            self._send_json(401, {"status": "declined", "reason": "missing_auth"})
            return
        h = data.get("hash")
        user_id = data.get("user_id")
        amount = data.get("amount")
        dt = data.get("datetime")
        if not h or user_id is None or amount is None or not dt:
            self._send_json(400, {"status": "declined", "reason": "invalid_payload"})
            return
        if float(amount) < 1.0:
            self._send_json(200, {"status": "declined", "reason": "insufficient_funds"})
            return
        self._send_json(
            200,
            {"transaction_id": f"txn_{secrets.token_hex(8)}", "status": "approved"},
        )

    def _handle_request(self):
        delay_ms = DEFAULT_DELAY_MS
        if self.path.startswith("/analytics"):
            delay_ms = random.randint(3000, 8000)
        elif self.path.startswith("/payments"):
            delay_ms = random.randint(2000, 6000)
        elif self.path.startswith("/fast"):
            delay_ms = 50

        time.sleep(delay_ms / 1000.0)

        response = {
            "status": "ok",
            "data": {
                "request_id": f"mock-{int(time.time()*1000)}",
                "processing_time_ms": delay_ms,
                "result": "success",
            },
        }
        self._send_json(200, response)

    def _send_json(self, code, obj):
        body = json.dumps(obj).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format, *args):
        pass


if __name__ == "__main__":
    port = 8888
    server = http.server.HTTPServer(("0.0.0.0", port), MockAPIHandler)
    print(f"Mock API listening on port {port}, default delay {DEFAULT_DELAY_MS}ms")
    server.serve_forever()
