"""
DeSpace 3D Studio — WordPress REST API Synchronization Client.

Handles bidirectional communication between the Desktop Application
and the DeSpace store: polling jobs, reporting progress, uploading models,
and syncing batch Excel data.
"""

import os
import base64
import requests
from typing import Dict, Any, List, Optional
from pathlib import Path

from desktop_app.config import Config


class DeSpaceSyncClient:
    """
    HTTP REST API Client for DeSpace Desktop 3D Sync.
    """

    def __init__(self, site_url: Optional[str] = None, secret_key: Optional[str] = None):
        self.site_url = (site_url or Config.SITE_URL).rstrip("/")
        self.secret_key = secret_key or Config.API_SECRET_KEY
        self.api_base = f"{self.site_url}/wp-json/despace/v1/desktop-3d"

    def _get_headers(self) -> Dict[str, str]:
        return {
            "User-Agent": "DeSpace-3D-Studio-Desktop/2.5",
            "X-DeSpace-Desktop-Key": self.secret_key,
        }

    def health_check(self) -> Dict[str, Any]:
        """
        Verify connection to WordPress site.
        """
        url = f"{self.api_base}/health"
        try:
            resp = requests.get(url, headers=self._get_headers(), timeout=5)
            if resp.status_code == 200:
                data = resp.json()
                return {"online": True, "data": data, "message": "Kết nối thành công"}
            return {
                "online": False,
                "status_code": resp.status_code,
                "message": f"Máy chủ trả về HTTP {resp.status_code}"
            }
        except Exception as e:
            return {"online": False, "error": str(e), "message": f"Không thể kết nối: {e}"}

    def fetch_pending_jobs(self, limit: int = 20) -> List[Dict[str, Any]]:
        """
        Retrieve dispatched jobs waiting for 3D reconstruction.
        """
        url = f"{self.api_base}/jobs"
        params = {"status": "dispatched", "limit": limit}
        try:
            resp = requests.get(url, headers=self._get_headers(), params=params, timeout=10)
            if resp.status_code == 200:
                data = resp.json()
                return data.get("jobs", [])
            return []
        except Exception as e:
            print(f"Error fetching pending jobs: {e}")
            return []

    def get_job(self, job_uuid: str) -> Optional[Dict[str, Any]]:
        """
        Get single job details.
        """
        url = f"{self.api_base}/jobs/{job_uuid}"
        try:
            resp = requests.get(url, headers=self._get_headers(), timeout=10)
            if resp.status_code == 200:
                return resp.json()
            return None
        except Exception as e:
            print(f"Error getting job {job_uuid}: {e}")
            return None

    def update_progress(
        self, job_uuid: str, progress: int, message: str, status: str = "processing"
    ) -> bool:
        """
        Send reconstruction progress step to website.
        """
        url = f"{self.api_base}/jobs/{job_uuid}/progress"
        payload = {
            "progress": progress,
            "message": message,
            "status": status,
        }
        try:
            resp = requests.post(url, headers=self._get_headers(), json=payload, timeout=8)
            return resp.status_code == 200
        except Exception as e:
            print(f"Error updating progress for {job_uuid}: {e}")
            return False

    def upload_completed_model(
        self, job_uuid: str, glb_path: Path, metadata: Dict[str, Any]
    ) -> Dict[str, Any]:
        """
        Upload the completed GLB model and technical metadata to WordPress.
        """
        url = f"{self.api_base}/jobs/{job_uuid}/upload"
        glb_path = Path(glb_path)

        if not glb_path.exists():
            return {"success": False, "message": f"File GLB không tồn tại: {glb_path}"}

        try:
            # Send via base64 in JSON payload for maximum compatibility across environments
            with open(glb_path, "rb") as f:
                glb_base64 = base64.b64encode(f.read()).decode("utf-8")

            payload = {
                "glb_base64": glb_base64,
                "vertex_count": metadata.get("vertex_count", 0),
                "face_count": metadata.get("face_count", 0),
                "file_size": metadata.get("file_size_bytes", os.path.getsize(glb_path)),
                "usdz_url": metadata.get("usdz_url", ""),
                "thumbnail_url": metadata.get("thumbnail_url", ""),
            }

            resp = requests.post(url, headers=self._get_headers(), json=payload, timeout=30)
            if resp.status_code == 200:
                return resp.json()
            else:
                return {
                    "success": False,
                    "status_code": resp.status_code,
                    "message": resp.text
                }
        except Exception as e:
            return {"success": False, "error": str(e), "message": f"Lỗi tải lên: {e}"}

    def batch_sync_excel(self, items: List[Dict[str, Any]]) -> Dict[str, Any]:
        """
        Send parsed Excel items to WordPress for batch queueing.
        """
        url = f"{self.api_base}/batch-sync"
        try:
            resp = requests.post(url, headers=self._get_headers(), json={"items": items}, timeout=20)
            if resp.status_code == 200:
                return resp.json()
            return {"success": False, "status_code": resp.status_code, "message": resp.text}
        except Exception as e:
            return {"success": False, "error": str(e), "message": f"Lỗi batch sync: {e}"}
