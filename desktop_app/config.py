"""
Configuration Module for DeSpace 3D Studio Desktop.
"""

import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent

class Config:
    # WordPress Sync Settings
    SITE_URL = os.environ.get("DESPACE_SITE_URL", "http://localhost:8090").rstrip("/")
    API_SECRET_KEY = os.environ.get("DESPACE_DESKTOP_KEY", "despace-desktop-studio-secret-2026")
    
    # Local Webhook Port
    LOCAL_PORT = int(os.environ.get("DESPACE_LOCAL_PORT", 58300))
    
    # Background Polling Interval
    POLL_INTERVAL = float(os.environ.get("DESPACE_POLL_INTERVAL", 3.0))
    
    # File Paths
    OUTPUT_DIR = BASE_DIR / "output"
    TEMP_DIR = BASE_DIR / "temp"
    SAMPLE_DIR = BASE_DIR / "sample_data"
    
    # 3D Reconstruction Settings
    DEFAULT_TEXTURE_SIZE = 1024
    TARGET_POLY_COUNT = 5000  # Optimized for Mobile WebXR / AR Quick Look
    DEFAULT_UNIT = "cm"

    @classmethod
    def ensure_directories(cls):
        cls.OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
        cls.TEMP_DIR.mkdir(parents=True, exist_ok=True)
        cls.SAMPLE_DIR.mkdir(parents=True, exist_ok=True)

Config.ensure_directories()
