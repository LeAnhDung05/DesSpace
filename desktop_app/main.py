#!/usr/bin/env python3
"""
DeSpace 3D Studio Desktop Application — Entrypoint.

Usage:
    python3 desktop_app/main.py                    # Launches the GUI Application
    python3 desktop_app/main.py --headless          # Runs background sync worker without GUI
    python3 desktop_app/main.py --excel <file.xlsx> # Batch-constructs 3D models from Excel
    python3 desktop_app/main.py --test-connection   # Tests connection to WordPress site
    python3 desktop_app/main.py --gen-template      # Generates standard Excel template
"""

import sys
import time
import argparse
from pathlib import Path

# Add project root to sys.path
ROOT_DIR = Path(__file__).resolve().parent.parent
if str(ROOT_DIR) not in sys.path:
    sys.path.insert(0, str(ROOT_DIR))

from desktop_app.config import Config
from desktop_app.core.reconstruction_engine import ReconstructionEngine
from desktop_app.core.sync_client import DeSpaceSyncClient
from desktop_app.core.local_server import DesktopLocalServer
from desktop_app.core.excel_processor import ExcelProcessor


def run_headless():
    """Run daemon background worker."""
    print("=" * 70)
    print(" DeSpace 3D Studio Desktop — Chế Độ Chạy Ngầm (Headless Daemon) ")
    print("=" * 70)
    print(f"Website Target : {Config.SITE_URL}")
    print(f"Local Port     : {Config.LOCAL_PORT}")
    print(f"Output Dir     : {Config.OUTPUT_DIR}")
    print("Đang lắng nghe lệnh từ website...")

    engine = ReconstructionEngine()
    client = DeSpaceSyncClient()

    def on_task(task_data):
        uuid = task_data.get("job_uuid")
        sku = task_data.get("sku")
        print(f"[Daemon] Nhận được lệnh dựng 3D tức thì: {sku} ({uuid})")
        try:
            res = engine.reconstruct(
                product_id=int(task_data.get("product_id") or 0),
                sku=sku,
                name=task_data.get("product_name", sku),
                category=task_data.get("category", "cabinet"),
                dimensions=task_data.get("dimensions", {}),
                angles=task_data.get("multi_angles") or task_data.get("angles") or {},
                progress_callback=lambda p, m: print(f"  [{p}%] {m}")
            )
            client.upload_completed_model(uuid, Path(res["glb_path"]), res)
            print(f"[Daemon] ✓ Hoàn thành và tải lên website: {sku}")
        except Exception as e:
            print(f"[Daemon] ❌ Lỗi: {e}")

    server = DesktopLocalServer(port=Config.LOCAL_PORT, task_callback=on_task)
    server.start()

    try:
        while True:
            # Poll queued jobs from WordPress
            jobs = client.fetch_pending_jobs(limit=5)
            for j in jobs:
                on_task(j)
            time.sleep(Config.POLL_INTERVAL)
    except KeyboardInterrupt:
        print("\nĐang tắt daemon...")
        server.stop()


def run_excel_cli(excel_path: str):
    """Run batch reconstruction directly from Excel file."""
    path = Path(excel_path)
    if not path.exists():
        print(f"Error: File not found at {excel_path}")
        sys.exit(1)

    print(f"Đang nạp dữ liệu từ: {path}")
    items = ExcelProcessor.parse_file(path)
    print(f"Tìm thấy {len(items)} sản phẩm. Bắt đầu xây dựng 3D...")

    engine = ReconstructionEngine()
    client = DeSpaceSyncClient()

    def prog(idx, total, msg):
        print(f"[{idx}/{total}] {msg}")

    summary = ExcelProcessor.run_batch_build(items, engine, client, prog)
    print("\n" + "=" * 50)
    print(f"KẾT QUẢ BATCH: {summary['successes']}/{summary['total']} thành công.")
    print("=" * 50)


def main():
    parser = argparse.ArgumentParser(description="DeSpace 3D Studio Desktop Application")
    parser.add_argument("--headless", action="store_true", help="Run background daemon without GUI")
    parser.add_argument("--excel", type=str, help="Process Excel batch file")
    parser.add_argument("--test-connection", action="store_true", help="Test connection to WordPress")
    parser.add_argument("--gen-template", action="store_true", help="Generate sample Excel file")
    parser.add_argument("--gui", action="store_true", help="Launch interactive GUI (default)")

    args = parser.parse_args()

    if args.test_connection:
        client = DeSpaceSyncClient()
        res = client.health_check()
        print("Connection Result:", res)
        sys.exit(0)

    if args.gen_template:
        out = ExcelProcessor.generate_template()
        print(f"Đã tạo tệp mẫu Excel tại: {out}")
        sys.exit(0)

    if args.excel:
        run_excel_cli(args.excel)
        sys.exit(0)

    if args.headless:
        run_headless()
        sys.exit(0)

    # Launch GUI
    try:
        from desktop_app.ui.app_window import DeSpaceAppWindow
        app = DeSpaceAppWindow()
        app.mainloop()
    except Exception as e:
        print(f"GUI launch exception (falling back to headless if display unavailable): {e}")
        run_headless()


if __name__ == "__main__":
    main()
