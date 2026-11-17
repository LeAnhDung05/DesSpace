"""
Configuration Module for DeSpace 3D Studio Desktop v3.0.
Supports environment variables and persistent local settings.json.
"""

import os
import json
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
SETTINGS_FILE = BASE_DIR / "settings.json"


class Config:
    SITE_URL = os.environ.get("DESPACE_SITE_URL", "http://localhost:8090").rstrip("/")
    API_SECRET_KEY = os.environ.get("DESPACE_DESKTOP_KEY", "despace-desktop-studio-secret-2026")
    LOCAL_PORT = int(os.environ.get("DESPACE_LOCAL_PORT", 58300))
    POLL_INTERVAL = float(os.environ.get("DESPACE_POLL_INTERVAL", 3.0))

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
            except Exception:
                pass

    @classmethod
    def save_persisted_settings(cls, site_url: str, api_secret_key: str, local_port: int):
        cls.SITE_URL = site_url.rstrip("/")
        cls.API_SECRET_KEY = api_secret_key
        cls.LOCAL_PORT = int(local_port)
        try:
            SETTINGS_FILE.write_text(
                json.dumps(
                    {
                        "site_url": cls.SITE_URL,
                        "api_secret_key": cls.API_SECRET_KEY,
                        "local_port": cls.LOCAL_PORT,
                    },
                    indent=2,
                ),
                encoding="utf-8",
            )
        except Exception as e:
            print(f"Warning: Could not persist settings.json: {e}")


Config.ensure_directories()
Config.load_persisted_settings()
