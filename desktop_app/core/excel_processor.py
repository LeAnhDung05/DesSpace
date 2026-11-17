"""
DeSpace 3D Studio — Excel Batch Product & 3D Processing Module v3.0.

Allows users to import product specifications, pricing, stock, and multi-angle photo paths
from an Excel (.xlsx / .csv) file, automatically construct high-detail .GLB + .USDZ 3D models,
and create/update products directly on the DeSpace shop.
"""

import csv
from pathlib import Path
from typing import List, Dict, Any, Optional, Callable
import openpyxl
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side

from desktop_app.config import Config
from desktop_app.core.reconstruction_engine import ReconstructionEngine
from desktop_app.core.sync_client import DeSpaceSyncClient


class ExcelProcessor:
    """
    Parser, template generator, and batch execution runner for Excel Product + 3D workflows.
    """

    COLUMNS = [
        ("SKU", "Mã sản phẩm (Bắt buộc, ví dụ: AXG-METRO-001)", 20),
        ("Ten_San_Pham", "Tên sản phẩm đầy đủ", 35),
        ("Gia_Ban_VND", "Giá niêm yết (VNĐ)", 16),
        ("Gia_Khuyen_Mai_VND", "Giá khuyến mãi (VNĐ, tùy chọn)", 18),
        ("Ton_Kho", "Số lượng tồn kho", 14),
        ("Chieu_Rong_cm", "Chiều rộng W (cm)", 16),
        ("Chieu_Sau_cm", "Chiều sâu D (cm)", 16),
        ("Chieu_Cao_cm", "Chiều cao H (cm)", 16),
        ("DanhMuc", "Danh mục (tu-giay-luu-tru, tu-quan-ao, ke-sach-tu-trung-bay, sofa, chair, table, bed)", 22),
        ("Mo_Ta_Ngan", "Mô tả ngắn sản phẩm", 36),
        ("Anh_Mat_Truoc", "Ảnh mặt trước (URL hoặc đường dẫn tệp máy tính)", 40),
        ("Anh_Goc_Nghieng_45", "Ảnh góc nghiêng 45° phối cảnh", 40),
        ("Anh_Mat_Ben", "Ảnh mặt bên 90°", 40),
        ("Anh_Mat_Sau", "Ảnh mặt sau (khuyến nghị)", 35),
        ("Anh_Mat_Tren", "Ảnh mặt trên (khuyến nghị)", 35),
    ]

    @classmethod
    def generate_template(cls, output_path: Optional[Path] = None) -> Path:
        """
        Generate a standardized, executive-styled Excel (.xlsx) template file.
        """
        if output_path is None:
            output_path = Config.SAMPLE_DIR / "mau_danh_sach_san_pham_3d.xlsx"
        else:
            output_path = Path(output_path)

        output_path.parent.mkdir(parents=True, exist_ok=True)

        wb = openpyxl.Workbook()
        ws = wb.active
        ws.title = "DeSpace_3D_Batch_Import"

        header_fill = PatternFill(start_color="1C1917", end_color="1C1917", fill_type="solid")
        header_font = Font(name="Arial", size=11, bold=True, color="FAF7F2")
        align_center = Alignment(horizontal="center", vertical="center", wrap_text=True)
        thin_border = Border(
            left=Side(style="thin", color="E7E3DC"),
            right=Side(style="thin", color="E7E3DC"),
            top=Side(style="thin", color="E7E3DC"),
            bottom=Side(style="thin", color="E7E3DC"),
        )

        for col_idx, (col_name, col_desc, col_width) in enumerate(cls.COLUMNS, start=1):
            cell = ws.cell(row=1, column=col_idx, value=col_name)
            cell.fill = header_fill
            cell.font = header_font
            cell.alignment = align_center
            cell.border = thin_border
            ws.column_dimensions[openpyxl.utils.get_column_letter(col_idx)].width = col_width

        ws.row_dimensions[1].height = 32

        sample_rows = [
            [
                "AXG-STR-001",
                "Tủ giày 3 cánh DeSpace Metro - Vân gỗ Linear",
                2700000,
                2100000,
                25,
                114.0,
                33.0,
                76.0,
                "tu-giay-luu-tru",
                "Tủ giày 3 cánh chớp thoáng khí chuẩn kiến trúc Bắc Âu.",
                "http://localhost:8090/wp-content/uploads/despace-catalog/images/furniva_AXG-STR-001_0.jpg",
                "http://localhost:8090/wp-content/uploads/despace-catalog/images/furniva_AXG-STR-001_1.jpg",
                "http://localhost:8090/wp-content/uploads/despace-catalog/images/furniva_AXG-STR-001_2.jpg",
                "",
                "",
            ],
            [
                "AXG-STR-002",
                "Tủ giày 2 cánh DeSpace Metro - Gỗ óc chó Columbia",
                2400000,
                1800000,
                18,
                76.0,
                33.0,
                76.0,
                "tu-giay-luu-tru",
                "Tủ giày 2 cánh nhỏ gọn, vân gỗ óc chó sang trọng.",
                "http://localhost:8090/wp-content/uploads/despace-catalog/images/furniva_AXG-STR-002_0.jpg",
                "http://localhost:8090/wp-content/uploads/despace-catalog/images/furniva_AXG-STR-002_1.jpg",
                "http://localhost:8090/wp-content/uploads/despace-catalog/images/furniva_AXG-STR-002_2.jpg",
                "",
                "",
            ],
            [
                "AXG-STR-003",
                "Tủ giày 3 cánh kèm ngăn kéo DeSpace Slate - Xám hiện đại",
                3100000,
                2550000,
                15,
                114.0,
                34.0,
                88.0,
                "tu-giay-luu-tru",
                "Tủ giày 3 cánh tích hợp ngăn kéo phụ kiện tiện lợi.",
                "http://localhost:8090/wp-content/uploads/despace-catalog/images/furniva_AXG-STR-003_0.jpg",
                "http://localhost:8090/wp-content/uploads/despace-catalog/images/furniva_AXG-STR-003_1.jpg",
                "http://localhost:8090/wp-content/uploads/despace-catalog/images/furniva_AXG-STR-003_2.jpg",
                "",
                "",
            ],
        ]

        row_fill = PatternFill(start_color="FAF7F2", end_color="FAF7F2", fill_type="solid")
        row_font = Font(name="Arial", size=10)

        for row_idx, row_data in enumerate(sample_rows, start=2):
            for col_idx, val in enumerate(row_data, start=1):
                cell = ws.cell(row=row_idx, column=col_idx, value=val)
                cell.font = row_font
                cell.border = thin_border
                if row_idx % 2 == 0:
                    cell.fill = row_fill
            ws.row_dimensions[row_idx].height = 24

        wb.save(output_path)
        return output_path

    @classmethod
    def parse_file(cls, filepath: Path) -> List[Dict[str, Any]]:
        filepath = Path(filepath)
        if not filepath.exists():
            raise FileNotFoundError(f"File not found: {filepath}")

        ext = filepath.suffix.lower()
        if ext == ".xlsx":
            return cls._parse_xlsx(filepath)
        elif ext == ".csv":
            return cls._parse_csv(filepath)
        else:
            raise ValueError(f"Định dạng tệp không được hỗ trợ ({ext}). Vui lòng dùng .xlsx hoặc .csv.")

    @classmethod
    def _parse_xlsx(cls, filepath: Path) -> List[Dict[str, Any]]:
        wb = openpyxl.load_workbook(filepath, data_only=True)
        ws = wb.active
        rows = list(ws.iter_rows(values_only=True))
        if not rows:
            return []

        headers = [str(c).strip() if c is not None else "" for c in rows[0]]
        items = []

        for row in rows[1:]:
            if not any(row):
                continue
            data = dict(zip(headers, row))
            item = cls._normalize_row(data)
            if item:
                items.append(item)

        return items

    @classmethod
    def _parse_csv(cls, filepath: Path) -> List[Dict[str, Any]]:
        items = []
        with open(filepath, "r", encoding="utf-8-sig") as f:
            reader = csv.DictReader(f)
            for row in reader:
                item = cls._normalize_row(row)
                if item:
                    items.append(item)
        return items

    @classmethod
    def _normalize_row(cls, data: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        def get_val(keys: List[str], default="") -> Any:
            for k in keys:
                for d_k in data.keys():
                    if d_k and k.lower() == str(d_k).strip().lower():
                        v = data[d_k]
                        return v if v is not None else default
            return default

        def to_float(val: Any, fallback: float = 0.0) -> float:
            if val is None or str(val).strip() == "":
                return fallback
            try:
                cleaned = str(val).replace(",", "").replace("₫", "").replace("đ", "").strip()
                return float(cleaned)
            except Exception:
                return fallback

        sku = str(get_val(["sku", "ma_san_pham", "product_sku"])).strip()
        name = str(get_val(["ten_san_pham", "product_name", "title", "name"])).strip()
        if (not sku or sku.lower() == "none") and not name:
            return None
        if not sku or sku.lower() == "none":
            sku = "DSP-" + "".join(c for c in name.upper() if c.isalnum())[:8]

        name = name or sku
        reg_price = to_float(get_val(["gia_ban_vnd", "gia_ban", "regular_price", "price"], ""), 0.0)
        sale_price = to_float(get_val(["gia_khuyen_mai_vnd", "gia_khuyen_mai", "sale_price"], ""), 0.0)
        stock_qty = int(to_float(get_val(["ton_kho", "stock", "stock_quantity"], 20), 20))
        short_desc = str(get_val(["mo_ta_ngan", "short_description", "description"], "")).strip()

        width = to_float(get_val(["chieu_rong_cm", "width", "rong", "length", "w"], 100), 100.0)
        depth = to_float(get_val(["chieu_sau_cm", "depth", "sau", "d"], 80), 80.0)
        height = to_float(get_val(["chieu_cao_cm", "height", "cao", "h"], 75), 75.0)
        category = str(get_val(["danhmuc", "category", "cat"], "tu-giay-luu-tru")).strip()

        front = str(get_val(["anh_mat_truoc", "front_image", "front", "anh_truoc", "image_url"])).strip()
        perspective = str(get_val(["anh_goc_nghieng_45", "perspective_image", "perspective", "anh_45"])).strip()
        side = str(get_val(["anh_mat_ben", "side_image", "side", "anh_ben"])).strip()
        back = str(get_val(["anh_mat_sau", "back_image", "back", "anh_sau"])).strip()
        top = str(get_val(["anh_mat_tren", "top_image", "top", "anh_tren"])).strip()

        angles = {}
        if front:
            angles["front"] = {"url": front, "label": "Mặt trước (Front)"}
        if perspective:
            angles["perspective"] = {"url": perspective, "label": "Góc nghiêng 45°"}
        if side:
            angles["side"] = {"url": side, "label": "Mặt bên (Side)"}
        if back:
            angles["back"] = {"url": back, "label": "Mặt sau (Back)"}
        if top:
            angles["top"] = {"url": top, "label": "Mặt trên (Top)"}

        return {
            "sku": sku,
            "product_name": name,
            "name": name,
            "regular_price": reg_price if reg_price > 0 else "",
            "sale_price": sale_price if sale_price > 0 else "",
            "stock_quantity": stock_qty,
            "short_description": short_desc,
            "dimensions": {
                "width": max(10.0, width),
                "depth": max(10.0, depth),
                "height": max(10.0, height),
                "unit": "cm",
            },
            "category": category or "tu-giay-luu-tru",
            "angles": angles,
            "angle_count": len(angles),
        }

    @classmethod
    def run_batch_build(
        cls,
        items: List[Dict[str, Any]],
        engine: ReconstructionEngine,
        sync_client: Optional[DeSpaceSyncClient] = None,
        progress_callback: Optional[Callable[[int, int, str], None]] = None,
    ) -> Dict[str, Any]:
        """
        Execute sequential 3D + AR construction and auto-publish each product to the Shop.
        """
        total = len(items)
        successes = 0
        failures = 0
        results = []

        for idx, item in enumerate(items, start=1):
            sku = item["sku"]
            name = item["product_name"]
            cat = item["category"]
            dims = item["dimensions"]
            angles = item["angles"]

            if progress_callback:
                progress_callback(idx, total, f"[{idx}/{total}] Đang dựng 3D (.GLB + .USDZ) & đăng {sku} — {name}...")

            try:
                build_result = engine.reconstruct(
                    product_id=0,
                    sku=sku,
                    name=name,
                    category=cat,
                    dimensions=dims,
                    angles=angles,
                )

                synced = False
                permalink = ""
                if sync_client:
                    pub_res = sync_client.publish_product_with_3d(
                        product_data=item,
                        glb_path=Path(build_result["glb_path"]),
                        usdz_path=Path(build_result["usdz_path"]),
                        thumb_path=Path(build_result["thumbnail_path"]),
                        metadata=build_result,
                    )
                    synced = bool(pub_res.get("success", False))
                    permalink = pub_res.get("permalink", "")

                build_result["synced_to_web"] = synced
                build_result["permalink"] = permalink
                results.append(build_result)
                successes += 1

            except Exception as e:
                failures += 1
                results.append({
                    "sku": sku,
                    "success": False,
                    "error": str(e),
                })

        return {
            "total": total,
            "successes": successes,
            "failures": failures,
            "results": results,
        }
