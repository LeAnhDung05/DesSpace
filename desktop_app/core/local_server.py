"""
DeSpace 3D Studio — Local HTTP Webhook Server.

Listens on 127.0.0.1:58300 to provide authenticated, zero-latency task reception
directly from the store manager's browser, as well as live connectivity health checks.
"""

import hmac
import json
import threading
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
from typing import Callable, Optional, Dict, Any
from urllib.parse import urlparse

from desktop_app.config import Config

MAX_BODY_BYTES = 1024 * 1024  # 1 MB cap to prevent OOM DoS


class _ReusableThreadingHTTPServer(ThreadingHTTPServer):
    allow_reuse_address = True
    daemon_threads = True


class LocalServerHandler(BaseHTTPRequestHandler):
    """
    Authenticated HTTP Request Handler with strict origin CORS allowlist.
    """

    def _allowed_origin(self) -> str:
        parsed = urlparse(Config.SITE_URL or "http://localhost:8090")
        if parsed.scheme and parsed.netloc:
            return f"{parsed.scheme}://{parsed.netloc}"
        return "http://localhost:8090"

    def _set_cors_headers(self, status_code: int = 200, content_type: str = "application/json"):
        allowed = self._allowed_origin()
        req_origin = (self.headers.get("Origin") or "").rstrip("/")
        self.send_response(status_code)
        self.send_header("Content-Type", content_type)
        if req_origin and req_origin.lower() == allowed.lower():
            self.send_header("Access-Control-Allow-Origin", req_origin)
            self.send_header("Vary", "Origin")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, X-DeSpace-Desktop-Key")
        self.end_headers()

    def _is_authorized(self) -> bool:
        expected = (Config.API_SECRET_KEY or "").strip()
        if not expected:
            return False
        provided = (self.headers.get("X-DeSpace-Desktop-Key") or "").strip()
        if not provided:
            return False
        return hmac.compare_digest(expected, provided)

    def do_OPTIONS(self):
        self._set_cors_headers(204)

    def do_GET(self):
        if self.path == "/api/health" or self.path == "/health":
            self._set_cors_headers(200)
            payload = {
                "status": "online",
                "app": "DeSpace 3D Studio Desktop",
                "version": "3.1.0",
                "port": getattr(self.server, "server_port", Config.LOCAL_PORT),
            }
            self.wfile.write(json.dumps(payload).encode("utf-8"))
        else:
            self._set_cors_headers(404)
            self.wfile.write(b'{"error": "Endpoint not found"}')

    def do_POST(self):
        if self.path == "/api/task" or self.path == "/task":
            if not self._is_authorized():
                self._set_cors_headers(401)
                self.wfile.write(b'{"success": false, "error": "Unauthorized: invalid or missing X-DeSpace-Desktop-Key"}')
                return

            try:
                content_length = int(self.headers.get("Content-Length", 0))
            except ValueError:
                self._set_cors_headers(400)
                self.wfile.write(b'{"success": false, "error": "Invalid Content-Length"}')
                return

            if content_length < 0 or content_length > MAX_BODY_BYTES:
                self._set_cors_headers(413)
                self.wfile.write(b'{"success": false, "error": "Payload exceeds 1MB limit"}')
                return

            body = self.rfile.read(content_length)
            try:
                task_data = json.loads(body.decode("utf-8"))
                if not isinstance(task_data, dict):
                    raise ValueError("Task payload must be a JSON object")

                callback = getattr(self.server, "task_callback", None)
                if callback:
                    threading.Thread(
                        target=callback,
                        args=(task_data,),
                        daemon=True,
                    ).start()

                self._set_cors_headers(200)
                resp = {
                    "success": True,
                    "message": "Task successfully received and queued in DeSpace 3D Studio Desktop",
                    "job_uuid": task_data.get("job_uuid"),
                }
                self.wfile.write(json.dumps(resp).encode("utf-8"))
            except Exception as e:
                self._set_cors_headers(400)
                err_resp = {"success": False, "error": str(e)}
                self.wfile.write(json.dumps(err_resp).encode("utf-8"))
        else:
            self._set_cors_headers(404)
            self.wfile.write(b'{"error": "Endpoint not found"}')

    def log_message(self, format, *args):
        pass


class DesktopLocalServer:
    """
    Manages the lifecycle of the local HTTP webhook listener.
    """

    def __init__(self, port: Optional[int] = None, task_callback: Optional[Callable[[Dict[str, Any]], None]] = None):
        self.port = port or Config.LOCAL_PORT
        self.task_callback = task_callback
        self.server: Optional[ThreadingHTTPServer] = None
        self.thread: Optional[threading.Thread] = None
        self.running = False

    def start(self):
        """
        Start the HTTP server on a background daemon thread.
        """
        if self.running:
            return

        try:
            self.server = _ReusableThreadingHTTPServer(("127.0.0.1", self.port), LocalServerHandler)
            self.server.task_callback = self.task_callback  # type: ignore[attr-defined]
            self.running = True
            self.thread = threading.Thread(target=self._run_server, daemon=True)
            self.thread.start()
            print(f"[LocalServer] DeSpace 3D Studio Desktop listener started on http://127.0.0.1:{self.port}")
        except Exception as e:
            self.running = False
            print(f"[LocalServer] Warning: Could not bind to port {self.port}: {e}")

    def _run_server(self):
        try:
            if self.server:
                self.server.serve_forever(poll_interval=0.25)
        except Exception as e:
            print(f"[LocalServer] Server loop exited with error: {e}")
        finally:
            self.running = False

    def stop(self):
        """
        Stop the HTTP server cleanly.
        """
        self.running = False
        srv = self.server
        self.server = None
        if srv:
            try:
                srv.shutdown()
            except Exception:
                pass
            try:
                srv.server_close()
            except Exception:
                pass
        print("[LocalServer] DeSpace 3D Studio Desktop listener stopped.")
