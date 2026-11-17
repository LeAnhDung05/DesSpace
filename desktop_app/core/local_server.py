"""
DeSpace 3D Studio — Local HTTP Webhook Server.

Listens on 127.0.0.1:58300 to provide zero-latency task reception directly
from the store manager's browser, as well as live connectivity health checks.
"""

import json
import threading
from http.server import HTTPServer, BaseHTTPRequestHandler
from typing import Callable, Optional, Dict, Any

from desktop_app.config import Config


class LocalServerHandler(BaseHTTPRequestHandler):
    """
    HTTP Request Handler with CORS support.
    """

    task_callback: Optional[Callable[[Dict[str, Any]], None]] = None

    def _set_cors_headers(self, status_code: int = 200, content_type: str = "application/json"):
        self.send_response(status_code)
        self.send_header("Content-Type", content_type)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, X-DeSpace-Desktop-Key")
        self.end_headers()

    def do_OPTIONS(self):
        self._set_cors_headers(200)

    def do_GET(self):
        if self.path == "/api/health" or self.path == "/health":
            self._set_cors_headers(200)
            payload = {
                "status": "online",
                "app": "DeSpace 3D Studio Desktop",
                "version": "2.5.0",
                "port": getattr(self.server, "server_port", Config.LOCAL_PORT),
            }
            self.wfile.write(json.dumps(payload).encode("utf-8"))
        else:
            self._set_cors_headers(404)
            self.wfile.write(b'{"error": "Endpoint not found"}')

    def do_POST(self):
        if self.path == "/api/task" or self.path == "/task":
            content_length = int(self.headers.get("Content-Length", 0))
            body = self.rfile.read(content_length)
            try:
                task_data = json.loads(body.decode("utf-8"))
                if LocalServerHandler.task_callback:
                    # Execute callback in a separate thread so HTTP response is returned immediately
                    threading.Thread(
                        target=LocalServerHandler.task_callback,
                        args=(task_data,),
                        daemon=True
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
        # Suppress noisy standard HTTP access logs
        pass


class DesktopLocalServer:
    """
    Manages the lifecycle of the local HTTP webhook listener.
    """

    def __init__(self, port: Optional[int] = None, task_callback: Optional[Callable] = None):
        self.port = port or Config.LOCAL_PORT
        self.task_callback = task_callback
        LocalServerHandler.task_callback = task_callback
        self.server: Optional[HTTPServer] = None
        self.thread: Optional[threading.Thread] = None
        self.running = False

    def start(self):
        """
        Start the HTTP server on a background daemon thread.
        """
        if self.running:
            return

        try:
            self.server = HTTPServer(("127.0.0.1", self.port), LocalServerHandler)
            self.running = True
            self.thread = threading.Thread(target=self._run_server, daemon=True)
            self.thread.start()
            print(f"[LocalServer] DeSpace 3D Studio Desktop listener started on http://127.0.0.1:{self.port}")
        except Exception as e:
            print(f"[LocalServer] Warning: Could not bind to port {self.port}: {e}")

    def _run_server(self):
        while self.running and self.server:
            try:
                self.server.handle_request()
            except Exception:
                break

    def stop(self):
        """
        Stop the HTTP server.
        """
        self.running = False
        if self.server:
            try:
                self.server.server_close()
            except Exception:
                pass
            self.server = None
        print("[LocalServer] DeSpace 3D Studio Desktop listener stopped.")
