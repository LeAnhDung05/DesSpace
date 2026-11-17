#!/usr/bin/env python3
"""
DeSpace 3D Studio Desktop Application v3.0 — Entrypoint.

Usage:
    python3 desktop_app/main.py                    # Launches the Luxury GUI Application
    python3 desktop_app/main.py --headless         # Runs background auto-3D sync daemon
    python3 desktop_app/main.py --publish-product --name "..." --sku "..." --price 2500000 --dims 114x33x76 --images img1.jpg
    python3 desktop_app/main.py --excel <file.xlsx># Batch-publishes products + 3D models from Excel
    python3 desktop_app/main.py --test-connection  # Tests connection to WordPress site
    python3 desktop_app/main.py --gen-template     # Generates standard Excel template
"""

import sys
import time
import argparse
from pathlib import Path

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
    print("=" * 72)
    print(" DeSpace 3D Studio Desktop v3.0 — Chế Độ Chạy Ngầm (Headless Daemon) ")
    print("=" * 72)
    print(f"Website Target : {Config.SITE_URL}")
    print(f"Local Port     : {Config.LOCAL_PORT}")
    print(f"Output Dir     : {Config.OUTPUT_DIR}")
    print("Đang lắng nghe lệnh dựng 3D + AR (.GLB & .USDZ) từ website...")

    engine = ReconstructionEngine()
    client = DeSpaceSyncClient()
    in_flight = set()

    def on_task(task_data):
        uuid = task_data.get("job_uuid")
        if not uuid or uuid in in_flight:
            return
        in_flight.add(uuid)
        sku = task_data.get("sku")
        print(f"[Daemon] Nhận lệnh dựng 3D tức thì: {sku} ({uuid})")
        try:
            client.update_progress(uuid, 5, "Desktop Daemon đã tiếp nhận lệnh...", status="processing")
            res = engine.reconstruct(
                product_id=int(task_data.get("product_id") or 0),
                sku=sku,
                name=task_data.get("product_name", sku),
                category=task_data.get("category", "cabinet"),
                dimensions=task_data.get("dimensions", {}),
                angles=task_data.get("multi_angles") or task_data.get("angles") or {},
                progress_callback=lambda p, m: print(f"  [{p}%] {m}"),
            )
            client.upload_completed_model(uuid, Path(res["glb_path"]), res)
            print(f"[Daemon] ✓ Hoàn thành .GLB + .USDZ và tải lên website: {sku}")
        except Exception as e:
            print(f"[Daemon] ❌ Lỗi: {e}")
            client.update_progress(uuid, 0, f"Lỗi: {e}", status="failed")
        finally:
            in_flight.discard(uuid)

    server = DesktopLocalServer(port=Config.LOCAL_PORT, task_callback=on_task)
    server.start()

    try:
        while True:
            jobs = client.fetch_pending_jobs(limit=5)
            for j in jobs:
                on_task(j)
            time.sleep(Config.POLL_INTERVAL)
    except KeyboardInterrupt:
        print("\nĐang tắt daemon...")
        server.stop()


def run_excel_cli(excel_path: str):
    """Run batch product + 3D reconstruction directly from Excel file."""
    path = Path(excel_path)
    if not path.exists():
        print(f"Error: File not found at {excel_path}")
        sys.exit(1)

    print(f"Đang nạp dữ liệu từ: {path}")
    items = ExcelProcessor.parse_file(path)
    print(f"Tìm thấy {len(items)} sản phẩm. Bắt đầu dựng 3D (.GLB + .USDZ) & đăng lên Shop...")

    engine = ReconstructionEngine()
    client = DeSpaceSyncClient()

    summary = ExcelProcessor.run_batch_build(
        items, engine, client, lambda idx, total, msg: print(msg)
    )
    print("\n" + "=" * 56)
    print(f"KẾT QUẢ BATCH: {summary['successes']}/{summary['total']} thành công.")
    print("=" * 56)


def run_publish_cli(args):
    """One-click CLI product upload + automated 3D (.GLB + .USDZ) generation."""
    dims_parts = [float(x) for x in args.dims.lower().replace("cm", "").split("x")]
    if len(dims_parts) != 3:
        raise ValueError("Kích thước --dims phải theo định dạng WxDxH (ví dụ: 114x33x76)")

    img_list = [s.strip() for s in (args.images or "").split(",") if s.strip()]
    angle_keys = ["front", "perspective", "side", "back", "top"]
    angles = {angle_keys[i]: img_list[i] for i in range(min(len(img_list), 5))}

    sku = args.sku or f"DSP-{int(time.time()) % 100000}"
    name = args.name or sku

    engine = ReconstructionEngine()
    client = DeSpaceSyncClient()

    print(f"1. Đang tự động xây dựng mô hình 3D (.GLB + .USDZ) cho '{name}' ({sku})...")
    build_res = engine.reconstruct(
        product_id=0,
        sku=sku,
        name=name,
        category=args.category,
        dimensions={"width": dims_parts[0], "depth": dims_parts[1], "height": dims_parts[2]},
        angles=angles,
        progress_callback=lambda p, m: print(f"   [{p}%] {m}"),
    )

    print("2. Đang đăng sản phẩm, bộ ảnh và cặp mô hình 3D + AR lên DeSpace Shop...")
    pub_res = client.publish_product_with_3d(
        product_data={
            "sku": sku,
            "name": name,
            "regular_price": args.price,
            "sale_price": args.sale_price or "",
            "stock_quantity": args.stock,
            "category": args.category,
            "short_description": args.desc or "",
            "dimensions": {"width": dims_parts[0], "depth": dims_parts[1], "height": dims_parts[2]},
            "angles": angles,
        },
        glb_path=Path(build_res["glb_path"]),
        usdz_path=Path(build_res["usdz_path"]),
        thumb_path=Path(build_res["thumbnail_path"]),
        metadata=build_res,
    )
    print("KẾT QUẢ XUẤT BẢN:", pub_res)
    if not pub_res.get("success"):
        sys.exit(1)


def main():
    parser = argparse.ArgumentParser(description="DeSpace 3D Studio Desktop Application v3.0")
    parser.add_argument("--headless", action="store_true", help="Run background daemon without GUI")
    parser.add_argument("--excel", type=str, help="Process Excel batch file")
    parser.add_argument("--test-connection", action="store_true", help="Test connection to WordPress")
    parser.add_argument("--gen-template", action="store_true", help="Generate sample Excel file")
    parser.add_argument("--gui", action="store_true", help="Launch interactive GUI (default)")

    # One-click CLI Product Publisher arguments
    parser.add_argument("--publish-product", action="store_true", help="Publish a product to Shop & auto-build 3D/AR")
    parser.add_argument("--sku", type=str, default="", help="Product SKU")
    parser.add_argument("--name", type=str, default="", help="Product Name")
    parser.add_argument("--price", type=float, default=2500000, help="Regular Price (VND)")
    parser.add_argument("--sale-price", type=float, default=0, help="Sale Price (VND)")
    parser.add_argument("--stock", type=int, default=20, help="Stock Quantity")
    parser.add_argument("--category", type=str, default="tu-giay-luu-tru", help="Category slug or archetype")
    parser.add_argument("--dims", type=str, default="114x33x76", help="Dimensions WxDxH in cm")
    parser.add_argument("--images", type=str, default="", help="Comma-separated local image paths or URLs")
    parser.add_argument("--desc", type=str, default="", help="Short description")

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

    if args.publish_product:
        run_publish_cli(args)
        sys.exit(0)

    if args.excel:
        run_excel_cli(args.excel)
        sys.exit(0)

    if args.headless:
        run_headless()
        sys.exit(0)

    try:
        from desktop_app.ui.app_window import DeSpaceAppWindow
        app = DeSpaceAppWindow()
        app.mainloop()
    except Exception as e:
        print(f"GUI launch exception (falling back to headless if display unavailable): {e}")
        run_headless()


if __name__ == "__main__":
    main()
