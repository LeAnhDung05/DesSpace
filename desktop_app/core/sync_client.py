"""
DeSpace 3D Studio — WordPress REST API Synchronization Client v3.0.

Handles bidirectional communication between the Desktop Application
and the DeSpace store:
- One-click product creation & publishing with auto-generated .GLB + .USDZ 3D/AR models
- Live store category fetching
- Job polling, progress streaming, and completed model upload
- Batch Excel product + 3D publishing
"""

import os
import base64
import requests
from typing import Dict, Any, List, Optional
from pathlib import Path

from desktop_app.config import Config


class DeSpaceSyncClient:
    """
    HTTP REST API Client for DeSpace Desktop 3D Studio v3.0.
    """

    def __init__(self, site_url: Optional[str] = None, secret_key: Optional[str] = None):
        self.site_url = (site_url or Config.SITE_URL).rstrip("/")
        self.secret_key = secret_key or Config.API_SECRET_KEY
        self.api_base = f"{self.site_url}/wp-json/despace/v1/desktop-3d"
        self.session = requests.Session()

    def close(self):
        try:
            self.session.close()
        except Exception:
            pass

    def _get_headers(self) -> Dict[str, str]:
        return {
            "User-Agent": "DeSpace-3D-Studio-Desktop/3.1",
            "X-DeSpace-Desktop-Key": self.secret_key,
        }

    def health_check(self) -> Dict[str, Any]:
        """
        Verify connection to WordPress site.
        """
        url = f"{self.api_base}/health"
        try:
            resp = self.session.get(url, headers=self._get_headers(), timeout=5)
            if resp.status_code == 200:
                data = resp.json()
                return {"online": True, "data": data, "message": "Kết nối thành công"}
            return {
                "online": False,
                "status_code": resp.status_code,
                "message": f"Máy chủ trả về HTTP {resp.status_code}",
            }
        except Exception as e:
            return {"online": False, "error": str(e), "message": f"Không thể kết nối: {e}"}

    def fetch_categories(self) -> List[Dict[str, Any]]:
        """
        Fetch live WooCommerce product categories from the store.
        """
        url = f"{self.api_base}/categories"
        try:
            resp = self.session.get(url, headers=self._get_headers(), timeout=8)
            if resp.status_code == 200:
                data = resp.json()
                cats = data.get("categories", [])
                if isinstance(cats, list) and cats:
                    return cats
        except Exception as e:
            print(f"Warning fetching store categories: {e}")

        # Fallback standard DeSpace categories if offline
        return [
            {"id": 0, "name": "Tủ giày & Lưu trữ", "slug": "tu-giay-luu-tru"},
            {"id": 0, "name": "Tủ quần áo", "slug": "tu-quan-ao"},
            {"id": 0, "name": "Kệ sách & Tủ trưng bày", "slug": "ke-sach-tu-trung-bay"},
            {"id": 0, "name": "Tủ bếp", "slug": "tu-bep"},
            {"id": 0, "name": "Ghế & Đôn", "slug": "ghe-don"},
            {"id": 0, "name": "Sofa", "slug": "sofa"},
            {"id": 0, "name": "Bàn ăn & Bàn trà", "slug": "table"},
            {"id": 0, "name": "Giường ngủ", "slug": "bed"},
            {"id": 0, "name": "Đèn trang trí", "slug": "lamp"},
        ]

    def fetch_pending_jobs(self, limit: int = 20) -> List[Dict[str, Any]]:
        """
        Retrieve dispatched jobs waiting for 3D reconstruction.
        """
        url = f"{self.api_base}/jobs"
        params = {"status": "dispatched", "limit": limit}
        try:
            resp = self.session.get(url, headers=self._get_headers(), params=params, timeout=10)
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
            resp = self.session.get(url, headers=self._get_headers(), timeout=10)
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
            resp = self.session.post(url, headers=self._get_headers(), json=payload, timeout=8)
            return resp.status_code == 200
        except Exception as e:
            print(f"Error updating progress for {job_uuid}: {e}")
            return False

    def upload_completed_model(
        self, job_uuid: str, glb_path: Path, metadata: Dict[str, Any]
    ) -> Dict[str, Any]:
        """
        Upload the completed GLB + USDZ + Thumbnail and technical metadata to WordPress.
        """
        url = f"{self.api_base}/jobs/{job_uuid}/upload"
        glb_path = Path(glb_path)

        if not glb_path.exists():
            return {"success": False, "message": f"File GLB không tồn tại: {glb_path}"}

        try:
            with open(glb_path, "rb") as f:
                glb_base64 = base64.b64encode(f.read()).decode("utf-8")

            usdz_base64 = ""
            usdz_path_str = metadata.get("usdz_path", "")
            if usdz_path_str and Path(usdz_path_str).exists():
                with open(usdz_path_str, "rb") as uf:
                    usdz_base64 = base64.b64encode(uf.read()).decode("utf-8")

            thumb_base64 = ""
            thumb_path_str = metadata.get("thumbnail_path", "")
            if thumb_path_str and Path(thumb_path_str).exists():
                with open(thumb_path_str, "rb") as tf:
                    thumb_base64 = base64.b64encode(tf.read()).decode("utf-8")

            payload = {
                "glb_base64": glb_base64,
                "usdz_base64": usdz_base64,
                "thumbnail_base64": thumb_base64,
                "vertex_count": metadata.get("vertex_count", 0),
                "face_count": metadata.get("face_count", 0),
                "file_size": metadata.get("file_size_bytes", os.path.getsize(glb_path)),
                "usdz_url": metadata.get("usdz_url", ""),
                "thumbnail_url": metadata.get("thumbnail_url", ""),
            }

            resp = self.session.post(url, headers=self._get_headers(), json=payload, timeout=45)
            if resp.status_code == 200:
                return resp.json()
            return {
                "success": False,
                "status_code": resp.status_code,
                "message": resp.text,
            }
        except Exception as e:
            return {"success": False, "error": str(e), "message": f"Lỗi tải lên: {e}"}

    def publish_product_with_3d(
        self,
        product_data: Dict[str, Any],
        glb_path: Optional[Path] = None,
        usdz_path: Optional[Path] = None,
        thumb_path: Optional[Path] = None,
        metadata: Optional[Dict[str, Any]] = None,
    ) -> Dict[str, Any]:
        """
        One-click publish: Create or update a WooCommerce product on the Shop,
        upload its local/remote product photos, and attach its auto-built .GLB + .USDZ 3D models.
        """
        url = f"{self.api_base}/products/publish"
        meta = metadata or {}

        # Encode product images (supports dict of angles OR list of paths/dicts)
        encoded_images: List[Dict[str, Any]] = []
        raw_angles = product_data.get("angles") or product_data.get("images") or {}

        allowed_img_exts = {".jpg", ".jpeg", ".png", ".webp"}
        if isinstance(raw_angles, dict):
            for angle_key in ["front", "perspective", "side", "back", "top"]:
                entry = raw_angles.get(angle_key)
                if not entry:
                    continue
                path_or_url = entry.get("url") or entry.get("path") if isinstance(entry, dict) else str(entry)
                if not path_or_url:
                    continue
                if path_or_url.startswith("http://") or path_or_url.startswith("https://"):
                    encoded_images.append({"angle": angle_key, "url": path_or_url})
                elif os.path.exists(path_or_url) and Path(path_or_url).suffix.lower() in allowed_img_exts:
                    with open(path_or_url, "rb") as img_f:
                        encoded_images.append({
                            "angle": angle_key,
                            "filename": Path(path_or_url).name,
                            "data_base64": base64.b64encode(img_f.read()).decode("utf-8"),
                        })
        elif isinstance(raw_angles, list):
            angle_keys = ["front", "perspective", "side", "back", "top"]
            for idx, item in enumerate(raw_angles):
                akey = angle_keys[idx] if idx < len(angle_keys) else f"extra_{idx}"
                if isinstance(item, dict):
                    encoded_images.append(item)
                elif isinstance(item, str) and item:
                    if item.startswith("http://") or item.startswith("https://"):
                        encoded_images.append({"angle": akey, "url": item})
                    elif os.path.exists(item) and Path(item).suffix.lower() in allowed_img_exts:
                        with open(item, "rb") as img_f:
                            encoded_images.append({
                                "angle": akey,
                                "filename": Path(item).name,
                                "data_base64": base64.b64encode(img_f.read()).decode("utf-8"),
                            })

        glb_b64 = ""
        glb_size = 0
        resolved_glb = Path(glb_path) if glb_path else (Path(meta["glb_path"]) if meta.get("glb_path") else None)
        if resolved_glb and resolved_glb.exists():
            raw_glb = resolved_glb.read_bytes()
            glb_b64 = base64.b64encode(raw_glb).decode("utf-8")
            glb_size = len(raw_glb)

        usdz_b64 = ""
        resolved_usdz = Path(usdz_path) if usdz_path else (Path(meta["usdz_path"]) if meta.get("usdz_path") else None)
        if resolved_usdz and resolved_usdz.exists():
            usdz_b64 = base64.b64encode(resolved_usdz.read_bytes()).decode("utf-8")

        thumb_b64 = ""
        resolved_thumb = Path(thumb_path) if thumb_path else (Path(meta["thumbnail_path"]) if meta.get("thumbnail_path") else None)
        if resolved_thumb and resolved_thumb.exists():
            thumb_b64 = base64.b64encode(resolved_thumb.read_bytes()).decode("utf-8")

        payload = {
            "sku": product_data.get("sku", ""),
            "name": product_data.get("name") or product_data.get("product_name") or "",
            "name_en": product_data.get("name_en", ""),
            "regular_price": product_data.get("regular_price", ""),
            "sale_price": product_data.get("sale_price", ""),
            "stock_quantity": product_data.get("stock_quantity", 20),
            "category": product_data.get("category", "tu-giay-luu-tru"),
            "badge": product_data.get("badge", "New"),
            "color": product_data.get("color", ""),
            "short_description": product_data.get("short_description", ""),
            "description": product_data.get("description", ""),
            "dimensions": product_data.get("dimensions", {"width": 100, "depth": 40, "height": 80}),
            "images": encoded_images,
            "glb_base64": glb_b64,
            "usdz_base64": usdz_b64,
            "thumbnail_base64": thumb_b64,
            "vertex_count": meta.get("vertex_count", 0),
            "face_count": meta.get("face_count", 0),
            "file_size": meta.get("file_size_bytes", glb_size),
        }

        try:
            resp = self.session.post(url, headers=self._get_headers(), json=payload, timeout=60)
            if resp.status_code in (200, 201):
                return resp.json()
            return {
                "success": False,
                "status_code": resp.status_code,
                "message": resp.text,
            }
        except Exception as e:
            return {"success": False, "error": str(e), "message": f"Lỗi đăng sản phẩm lên Shop: {e}"}

    def batch_sync_excel(self, items: List[Dict[str, Any]]) -> Dict[str, Any]:
        """
        Send parsed Excel items to WordPress for batch queueing.
        """
        url = f"{self.api_base}/batch-sync"
        try:
            resp = self.session.post(url, headers=self._get_headers(), json={"items": items}, timeout=20)
            if resp.status_code == 200:
                return resp.json()
            return {"success": False, "status_code": resp.status_code, "message": resp.text}
        except Exception as e:
            return {"success": False, "error": str(e), "message": f"Lỗi batch sync: {e}"}
