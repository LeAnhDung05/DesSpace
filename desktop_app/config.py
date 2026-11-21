"""
Configuration Module for DeSpace 3D Studio Desktop v3.1.
Supports environment variables, Cloud AI Image-to-3D keys (Fal.ai / Tripo3D),
and persistent local settings.json.
"""

import os
import json
from pathlib import Path
from typing import Optional

BASE_DIR = Path(__file__).resolve().parent
SETTINGS_FILE = BASE_DIR / "settings.json"


class Config:
    SITE_URL = os.environ.get("DESPACE_SITE_URL", "http://localhost:8090").rstrip("/")
    API_SECRET_KEY = os.environ.get("DESPACE_DESKTOP_KEY", "")
    LOCAL_PORT = int(os.environ.get("DESPACE_LOCAL_PORT", 58300))
    POLL_INTERVAL = float(os.environ.get("DESPACE_POLL_INTERVAL", 3.0))

    # Optional Cloud AI Image-to-3D API Keys (automatic Parametric PBR fallback when empty)
    FAL_API_KEY = os.environ.get("FAL_KEY", os.environ.get("DESPACE_FAL_API_KEY", ""))
    TRIPO_API_KEY = os.environ.get("TRIPO_API_KEY", os.environ.get("DESPACE_TRIPO_API_KEY", ""))

    OUTPUT_DIR = BASE_DIR / "output"
    TEMP_DIR = BASE_DIR / "temp"
    SAMPLE_DIR = BASE_DIR / "sample_data"

    DEFAULT_TEXTURE_SIZE = 1024
    TARGET_POLY_COUNT = 5000
    DEFAULT_UNIT = "cm"

    @classmethod
    def ensure_directories(cls):
        cls.OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
        cls.TEMP_DIR.mkdir(parents=True, exist_ok=True)
        cls.SAMPLE_DIR.mkdir(parents=True, exist_ok=True)

    @classmethod
    def load_persisted_settings(cls):
        if SETTINGS_FILE.exists():
            try:
                data = json.loads(SETTINGS_FILE.read_text(encoding="utf-8"))
                if "DESPACE_SITE_URL" not in os.environ and data.get("site_url"):
                    cls.SITE_URL = str(data["site_url"]).rstrip("/")
                if "DESPACE_DESKTOP_KEY" not in os.environ and data.get("api_secret_key"):
                    cls.API_SECRET_KEY = str(data["api_secret_key"])
                if "DESPACE_LOCAL_PORT" not in os.environ and data.get("local_port"):
                    cls.LOCAL_PORT = int(data["local_port"])
                if "FAL_KEY" not in os.environ and "DESPACE_FAL_API_KEY" not in os.environ and "fal_api_key" in data:
                    cls.FAL_API_KEY = str(data.get("fal_api_key") or "")
                if "TRIPO_API_KEY" not in os.environ and "DESPACE_TRIPO_API_KEY" not in os.environ and "tripo_api_key" in data:
                    cls.TRIPO_API_KEY = str(data.get("tripo_api_key") or "")
            except Exception as e:
                print(f"Warning: Could not read settings.json: {e}")

    @classmethod
    def save_persisted_settings(
        cls,
        site_url: str,
        api_secret_key: str,
        local_port: int,
        fal_api_key: Optional[str] = None,
        tripo_api_key: Optional[str] = None,
    ):
        cls.SITE_URL = site_url.rstrip("/")
        cls.API_SECRET_KEY = api_secret_key
        cls.LOCAL_PORT = int(local_port)
        if fal_api_key is not None:
            cls.FAL_API_KEY = fal_api_key.strip()
        if tripo_api_key is not None:
            cls.TRIPO_API_KEY = tripo_api_key.strip()
        try:
            payload = json.dumps(
                {
                    "site_url": cls.SITE_URL,
                    "api_secret_key": cls.API_SECRET_KEY,
                    "local_port": cls.LOCAL_PORT,
                    "fal_api_key": cls.FAL_API_KEY,
                    "tripo_api_key": cls.TRIPO_API_KEY,
                },
                indent=2,
            )
            fd = os.open(str(SETTINGS_FILE), os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
            with os.fdopen(fd, "w", encoding="utf-8") as f:
                f.write(payload)
            try:
                os.chmod(SETTINGS_FILE, 0o600)
            except OSError:
                pass
        except Exception as e:
            print(f"Warning: Could not persist settings.json: {e}")


Config.ensure_directories()
Config.load_persisted_settings()
