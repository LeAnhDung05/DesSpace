"""
DeSpace 3D Studio Desktop v3.0 — Luxury Store Publisher & Automated 3D/AR Builder GUI.

Built with Tkinter & TTK, styled with DeSpace executive brand identity:
#1C1917 (Deep Obsidian), #C25E3A (Terracotta), #10B981 (Emerald), #FAF7F2 (Cream).

Key Capabilities in v3.0:
1. One-click Product Upload to WooCommerce Shop -> Automatically constructs PBR .GLB + Apple AR .USDZ 3D models.
2. Zero-latency Live Sync daemon with auto-execution for jobs dispatched from WP-Admin.
3. Excel/CSV Batch Product + 3D + AR Publisher.
4. Standalone Local 3D & AR Asset Studio (.GLB + .USDZ).
"""

import os
import sys
import time
import random
import string
import threading
import subprocess
import webbrowser
from pathlib import Path
from typing import Dict, Any, List, Optional
import tkinter as tk
from tkinter import ttk, filedialog, messagebox

from desktop_app.config import Config
from desktop_app.core.reconstruction_engine import ReconstructionEngine
from desktop_app.core.sync_client import DeSpaceSyncClient
from desktop_app.core.local_server import DesktopLocalServer
from desktop_app.core.excel_processor import ExcelProcessor


class DeSpaceAppWindow(tk.Tk):
    """
    Main Application Window for DeSpace 3D Studio Desktop v3.0.
    """

    def __init__(self):
        super().__init__()

        self.title("DeSpace 3D Studio Desktop v3.0 — Đăng Sản Phẩm & Tự Động Dựng Mô Hình 3D / AR")
        self.geometry("1220x820")
        self.minsize(1020, 680)
        self.configure(bg="#FAF7F2")

        # Core Engines
        self.engine = ReconstructionEngine()
        self.sync_client = DeSpaceSyncClient()
        self.local_server = DesktopLocalServer(
            port=Config.LOCAL_PORT,
            task_callback=self._on_local_task_received,
        )

        # Global State Variables
        self.auto_sync_active = tk.BooleanVar(value=True)
        self.auto_execute_jobs = tk.BooleanVar(value=True)
        self.site_url_var = tk.StringVar(value=Config.SITE_URL)
        self.secret_key_var = tk.StringVar(value=Config.API_SECRET_KEY)
        self.local_port_var = tk.IntVar(value=Config.LOCAL_PORT)
        self.fal_key_var = tk.StringVar(value=Config.FAL_API_KEY)
        self.tripo_key_var = tk.StringVar(value=Config.TRIPO_API_KEY)
        self.excel_path_var = tk.StringVar(value="")
        self.excel_items: List[Dict[str, Any]] = []

        self.store_categories: List[Dict[str, Any]] = []
        self.category_label_to_slug: Dict[str, str] = {}
        self.last_published_url: str = ""

        self.active_jobs_data: Dict[str, Dict[str, Any]] = {}
        self.is_processing = False

        # Setup TTK Styles & UI Layout
        self._setup_styles()
        self._build_top_header()
        self._build_notebook()
        self._build_bottom_statusbar()

        # Start background server & auto-sync thread
        self.local_server.start()
        self._start_auto_sync_thread()

        # Initial health check & live category sync
        self.after(400, self._check_website_health)
        self.after(700, self._refresh_store_categories)

        # Protocol cleanup
        self.protocol("WM_DELETE_WINDOW", self._on_close)

    def _setup_styles(self):
        style = ttk.Style(self)
        try:
            style.theme_use("clam")
        except Exception:
            pass

        style.configure("TNotebook", background="#FAF7F2", borderwidth=0)
        style.configure(
            "TNotebook.Tab",
            background="#EFEBE4",
            foreground="#44403C",
            font=("Arial", 10, "bold"),
            padding=[14, 8],
        )
        style.map(
            "TNotebook.Tab",
            background=[("selected", "#1C1917")],
            foreground=[("selected", "#FAF7F2")],
        )

        style.configure("TFrame", background="#FAF7F2")
        style.configure("Card.TFrame", background="#FFFFFF", relief="solid", borderwidth=1)
        style.configure("TLabel", background="#FAF7F2", foreground="#1C1917", font=("Arial", 10))

    # -------------------------------------------------------------------------
    # Top Brand Header
    # -------------------------------------------------------------------------
    def _build_top_header(self):
        header_frame = tk.Frame(self, bg="#1C1917", height=72)
        header_frame.pack(fill="x", side="top")

        content = tk.Frame(header_frame, bg="#1C1917")
        content.pack(fill="both", expand=True, padx=24, pady=12)

        left_box = tk.Frame(content, bg="#1C1917")
        left_box.pack(side="left", fill="y")

        tk.Label(
            left_box,
            text="🧊 DESPACE 3D STUDIO DESKTOP v3.0",
            font=("Arial", 15, "bold"),
            fg="#FAF7F2",
            bg="#1C1917",
        ).pack(anchor="w")

        tk.Label(
            left_box,
            text="Đăng Sản Phẩm Mới Lên Shop -> Tự Động Xây Dựng Mô Hình 3D (.GLB) & Apple AR (.USDZ)",
            font=("Arial", 9.5),
            fg="#D6CFC7",
            bg="#1C1917",
        ).pack(anchor="w")

        right_box = tk.Frame(content, bg="#1C1917")
        right_box.pack(side="right", fill="y")

        self.web_status_pill = tk.Label(
            right_box,
            text="🌐 Website: Đang kiểm tra...",
            font=("Arial", 9.5, "bold"),
            bg="#292524",
            fg="#F59E0B",
            padx=10,
            pady=4,
            relief="flat",
        )
        self.web_status_pill.pack(side="left", padx=6)

        self.port_status_pill = tk.Label(
            right_box,
            text=f"⚡ Cổng: {Config.LOCAL_PORT} (Sẵn sàng)",
            font=("Arial", 9.5, "bold"),
            bg="#064E3B",
            fg="#34D399",
            padx=10,
            pady=4,
            relief="flat",
        )
        self.port_status_pill.pack(side="left", padx=6)

        auto_toggle = tk.Checkbutton(
            right_box,
            text="Tự động đồng bộ & dựng 3D",
            variable=self.auto_sync_active,
            font=("Arial", 9.5, "bold"),
            bg="#1C1917",
            fg="#FAF7F2",
            selectcolor="#292524",
            activebackground="#1C1917",
            activeforeground="#FAF7F2",
        )
        auto_toggle.pack(side="left", padx=8)

    # -------------------------------------------------------------------------
    # Main Tabs (Notebook)
    # -------------------------------------------------------------------------
    def _build_notebook(self):
        self.notebook = ttk.Notebook(self)
        self.notebook.pack(fill="both", expand=True, padx=18, pady=12)

        # Tab 1 (Flagship): Upload Product to Shop + Auto Build 3D & AR
        self.tab_publish = ttk.Frame(self.notebook)
        self.notebook.add(self.tab_publish, text="  🛍️ Đăng Sản Phẩm Lên Shop (Auto 3D & AR)  ")
        self._build_tab_publish(self.tab_publish)

        # Tab 2: Live Sync & Active Jobs
        self.tab_sync = ttk.Frame(self.notebook)
        self.notebook.add(self.tab_sync, text="  ⚡ Live Sync & Hàng Đợi Web  ")
        self._build_tab_sync(self.tab_sync)

        # Tab 3: Excel Batch Builder
        self.tab_excel = ttk.Frame(self.notebook)
        self.notebook.add(self.tab_excel, text="  📊 Nhập Hàng Loạt Excel (Auto 3D)  ")
        self._build_tab_excel(self.tab_excel)

        # Tab 4: Manual 3D Studio
        self.tab_manual = ttk.Frame(self.notebook)
        self.notebook.add(self.tab_manual, text="  🛠️ Xưởng Dựng 3D Cục Bộ  ")
        self._build_tab_manual(self.tab_manual)

        # Tab 5: Settings
        self.tab_settings = ttk.Frame(self.notebook)
        self.notebook.add(self.tab_settings, text="  ⚙️ Cấu Hình Kết Nối  ")
        self._build_tab_settings(self.tab_settings)

    # -------------------------------------------------------------------------
    # TAB 1: One-Click Product Upload + Auto 3D & AR Builder
    # -------------------------------------------------------------------------
    def _build_tab_publish(self, parent):
        outer = tk.Frame(parent, bg="#FAF7F2")
        outer.pack(fill="both", expand=True)

        columns_pane = tk.Frame(outer, bg="#FAF7F2")
        columns_pane.pack(fill="both", expand=True)
        columns_pane.columnconfigure(0, weight=11)
        columns_pane.columnconfigure(1, weight=10)
        columns_pane.rowconfigure(0, weight=1)

        # LEFT CARD: Product Information
        left_card = tk.Frame(columns_pane, bg="#FFFFFF", padx=20, pady=16, highlightbackground="#E7E0D7", highlightthickness=1)
        left_card.grid(row=0, column=0, sticky="nsew", padx=(0, 8), pady=(0, 10))

        tk.Label(
            left_card,
            text="1. Thông Tin Sản Phẩm Đăng Lên Cửa Hàng",
            font=("Arial", 12, "bold"),
            bg="#FFFFFF",
            fg="#1C1917",
        ).pack(anchor="w", pady=(0, 2))
        tk.Label(
            left_card,
            text="Điền thông tin thương mại và kích thước vật lý thực tế (hệ thống dùng kích thước này để dựng khung 3D tỷ lệ 1:1).",
            font=("Arial", 9.5),
            bg="#FFFFFF",
            fg="#78716C",
            wraplength=520,
            justify="left",
        ).pack(anchor="w", pady=(0, 12))

        form = tk.Frame(left_card, bg="#FFFFFF")
        form.pack(fill="both", expand=True)
        form.columnconfigure(1, weight=1)
        form.columnconfigure(3, weight=1)

        # Product Name (VI)
        tk.Label(form, text="Tên sản phẩm (VN) *:", font=("Arial", 9.5, "bold"), bg="#FFFFFF").grid(row=0, column=0, sticky="w", pady=5)
        self.pub_name_var = tk.StringVar(value="")
        tk.Entry(form, textvariable=self.pub_name_var, font=("Arial", 10)).grid(row=0, column=1, columnspan=3, sticky="ew", padx=(8, 0), pady=5)

        # Product Name (EN - Optional)
        tk.Label(form, text="Tên tiếng Anh (tùy chọn):", font=("Arial", 9.5), bg="#FFFFFF", fg="#57534E").grid(row=1, column=0, sticky="w", pady=5)
        self.pub_name_en_var = tk.StringVar(value="")
        tk.Entry(form, textvariable=self.pub_name_en_var, font=("Arial", 10)).grid(row=1, column=1, columnspan=3, sticky="ew", padx=(8, 0), pady=5)

        # SKU + Auto-generate button
        tk.Label(form, text="Mã SKU *:", font=("Arial", 9.5, "bold"), bg="#FFFFFF").grid(row=2, column=0, sticky="w", pady=5)
        sku_row = tk.Frame(form, bg="#FFFFFF")
        sku_row.grid(row=2, column=1, columnspan=3, sticky="ew", padx=(8, 0), pady=5)
        self.pub_sku_var = tk.StringVar(value=self._generate_new_sku())
        tk.Entry(sku_row, textvariable=self.pub_sku_var, font=("Arial", 10), width=20).pack(side="left")
        tk.Button(
            sku_row,
            text="⚡ Tự sinh mã",
            font=("Arial", 8.5, "bold"),
            bg="#F3EFEA",
            fg="#1C1917",
            command=lambda: self.pub_sku_var.set(self._generate_new_sku()),
        ).pack(side="left", padx=6)

        tk.Label(sku_row, text="Nhãn:", font=("Arial", 9.5, "bold"), bg="#FFFFFF").pack(side="left", padx=(10, 4))
        self.pub_badge_var = tk.StringVar(value="New")
        ttk.Combobox(
            sku_row,
            textvariable=self.pub_badge_var,
            values=["New", "Best Seller", "Featured", "Popular", ""],
            state="readonly",
            width=12,
        ).pack(side="left")

        # Category
        tk.Label(form, text="Danh mục cửa hàng *:", font=("Arial", 9.5, "bold"), bg="#FFFFFF").grid(row=3, column=0, sticky="w", pady=5)
        cat_row = tk.Frame(form, bg="#FFFFFF")
        cat_row.grid(row=3, column=1, columnspan=3, sticky="ew", padx=(8, 0), pady=5)
        self.pub_cat_var = tk.StringVar(value="Tủ giày & Lưu trữ (tu-giay-luu-tru)")
        self.pub_cat_cb = ttk.Combobox(cat_row, textvariable=self.pub_cat_var, width=36)
        self.pub_cat_cb.pack(side="left", fill="x", expand=True)
        tk.Button(
            cat_row,
            text="🔄 Tải danh mục",
            font=("Arial", 8.5),
            bg="#F3EFEA",
            command=self._refresh_store_categories,
        ).pack(side="left", padx=(6, 0))

        # Prices & Stock
        tk.Label(form, text="Giá niêm yết (VNĐ) *:", font=("Arial", 9.5, "bold"), bg="#FFFFFF").grid(row=4, column=0, sticky="w", pady=5)
        self.pub_reg_price_var = tk.StringVar(value="2850000")
        tk.Entry(form, textvariable=self.pub_reg_price_var, font=("Arial", 10), width=16).grid(row=4, column=1, sticky="ew", padx=(8, 8), pady=5)

        tk.Label(form, text="Giá KM (VNĐ):", font=("Arial", 9.5, "bold"), bg="#FFFFFF").grid(row=4, column=2, sticky="w", pady=5)
        self.pub_sale_price_var = tk.StringVar(value="2390000")
        tk.Entry(form, textvariable=self.pub_sale_price_var, font=("Arial", 10), width=16).grid(row=4, column=3, sticky="ew", padx=(6, 0), pady=5)

        tk.Label(form, text="Số lượng tồn kho:", font=("Arial", 9.5, "bold"), bg="#FFFFFF").grid(row=5, column=0, sticky="w", pady=5)
        self.pub_stock_var = tk.StringVar(value="25")
        tk.Entry(form, textvariable=self.pub_stock_var, font=("Arial", 10), width=12).grid(row=5, column=1, sticky="w", padx=(8, 8), pady=5)

        tk.Label(form, text="Màu / Chất liệu:", font=("Arial", 9.5), bg="#FFFFFF").grid(row=5, column=2, sticky="w", pady=5)
        self.pub_color_var = tk.StringVar(value="Gỗ óc chó Columbia")
        tk.Entry(form, textvariable=self.pub_color_var, font=("Arial", 10), width=18).grid(row=5, column=3, sticky="ew", padx=(6, 0), pady=5)

        # Physical Dimensions (W x D x H cm)
        tk.Label(form, text="Kích thước thực (cm) *:", font=("Arial", 9.5, "bold"), bg="#FFFFFF", fg="#C25E3A").grid(row=6, column=0, sticky="w", pady=6)
        dims_row = tk.Frame(form, bg="#FAF7F2", padx=8, pady=6, highlightbackground="#E7E0D7", highlightthickness=1)
        dims_row.grid(row=6, column=1, columnspan=3, sticky="ew", padx=(8, 0), pady=6)

        self.pub_w_var = tk.StringVar(value="114")
        self.pub_d_var = tk.StringVar(value="34")
        self.pub_h_var = tk.StringVar(value="78")

        tk.Label(dims_row, text="Rộng (W):", font=("Arial", 9, "bold"), bg="#FAF7F2").pack(side="left")
        tk.Entry(dims_row, textvariable=self.pub_w_var, width=6, font=("Arial", 10)).pack(side="left", padx=(4, 10))
        tk.Label(dims_row, text="Sâu (D):", font=("Arial", 9, "bold"), bg="#FAF7F2").pack(side="left")
        tk.Entry(dims_row, textvariable=self.pub_d_var, width=6, font=("Arial", 10)).pack(side="left", padx=(4, 10))
        tk.Label(dims_row, text="Cao (H):", font=("Arial", 9, "bold"), bg="#FAF7F2").pack(side="left")
        tk.Entry(dims_row, textvariable=self.pub_h_var, width=6, font=("Arial", 10)).pack(side="left", padx=(4, 6))
        tk.Label(dims_row, text="cm (Tỷ lệ AR 1:1)", font=("Arial", 8.5, "italic"), bg="#FAF7F2", fg="#78716C").pack(side="left")

        # Short Description
        tk.Label(form, text="Mô tả sản phẩm:", font=("Arial", 9.5, "bold"), bg="#FFFFFF").grid(row=7, column=0, sticky="nw", pady=6)
        self.pub_desc_text = tk.Text(form, height=5, font=("Arial", 9.5), wrap="word", relief="solid", borderwidth=1, padx=6, pady=6)
        self.pub_desc_text.grid(row=7, column=1, columnspan=3, sticky="nsew", padx=(8, 0), pady=6)
        self.pub_desc_text.insert(
            "1.0",
            "Thiết kế tối giản phong cách Bắc Âu từ gỗ công nghiệp phủ Laminate chống ẩm cao cấp, tích hợp sẵn mô hình 3D xoay 360° và xem trực tiếp trong phòng qua Camera AR tỷ lệ thực 1:1.",
        )
        form.rowconfigure(7, weight=1)

        # RIGHT CARD: Photos & Auto-3D Configuration
        right_card = tk.Frame(columns_pane, bg="#FFFFFF", padx=20, pady=16, highlightbackground="#E7E0D7", highlightthickness=1)
        right_card.grid(row=0, column=1, sticky="nsew", padx=(8, 0), pady=(0, 10))

        hdr_r = tk.Frame(right_card, bg="#FFFFFF")
        hdr_r.pack(fill="x", pady=(0, 4))
        tk.Label(
            hdr_r,
            text="2. Ảnh Sản Phẩm & Tự Động Xây Dựng 3D / AR",
            font=("Arial", 12, "bold"),
            bg="#FFFFFF",
            fg="#1C1917",
        ).pack(side="left")

        tk.Button(
            hdr_r,
            text="📂 Chọn Nhanh Nhiều Ảnh...",
            font=("Arial", 9, "bold"),
            bg="#1C1917",
            fg="#FAF7F2",
            padx=10,
            pady=3,
            command=self._pick_multiple_publish_images,
        ).pack(side="right")

        tk.Label(
            right_card,
            text="Chọn từ 1 đến 5 ảnh sản phẩm. Ảnh đầu tiên làm ảnh đại diện trên Shop, các ảnh còn lại tạo Gallery & trích xuất vân bề mặt PBR cho mô hình 3D.",
            font=("Arial", 9.5),
            bg="#FFFFFF",
            fg="#78716C",
            wraplength=480,
            justify="left",
        ).pack(anchor="w", pady=(0, 10))

        angles_frame = tk.Frame(right_card, bg="#FAF7F2", padx=10, pady=10, highlightbackground="#E7E0D7", highlightthickness=1)
        angles_frame.pack(fill="x", pady=(0, 12))

        self.pub_angle_vars = {
            "front": tk.StringVar(value=""),
            "perspective": tk.StringVar(value=""),
            "side": tk.StringVar(value=""),
            "back": tk.StringVar(value=""),
            "top": tk.StringVar(value=""),
        }

        pub_angle_specs = [
            ("front", "Ảnh Đại Diện / Mặt Trước *"),
            ("perspective", "Ảnh Phối Cảnh Nghiêng 45°"),
            ("side", "Ảnh Mặt Bên 90°"),
            ("back", "Ảnh Mặt Sau / Chi Tiết"),
            ("top", "Ảnh Mặt Trên / Góc Phụ"),
        ]

        for akey, alabel in pub_angle_specs:
            r = tk.Frame(angles_frame, bg="#FAF7F2")
            r.pack(fill="x", pady=3)
            tk.Label(r, text=alabel, width=23, anchor="w", font=("Arial", 9, "bold"), bg="#FAF7F2").pack(side="left")
            tk.Entry(r, textvariable=self.pub_angle_vars[akey], font=("Arial", 9)).pack(side="left", fill="x", expand=True, padx=6)
            tk.Button(
                r,
                text="Chọn...",
                font=("Arial", 8.5),
                command=lambda k=akey: self._pick_single_publish_image(k),
            ).pack(side="left")
            tk.Button(
                r,
                text="✕",
                font=("Arial", 8),
                fg="#DC2626",
                command=lambda k=akey: self.pub_angle_vars[k].set(""),
            ).pack(side="left", padx=(3, 0))

        # 3D Mode Box
        mode_box = tk.LabelFrame(
            right_card,
            text=" Chế Độ Khởi Tạo Mô Hình 3D & AR ",
            font=("Arial", 10, "bold"),
            bg="#FFFFFF",
            fg="#1C1917",
            padx=12,
            pady=8,
        )
        mode_box.pack(fill="x", pady=(0, 12))

        self.pub_auto_build_3d_var = tk.BooleanVar(value=True)
        tk.Checkbutton(
            mode_box,
            text="✨ Tự động xây dựng mô hình 3D (.GLB + Apple AR .USDZ) khi đăng sản phẩm",
            variable=self.pub_auto_build_3d_var,
            font=("Arial", 9.5, "bold"),
            bg="#FFFFFF",
            fg="#065F46",
            activebackground="#FFFFFF",
            command=self._toggle_custom_glb_row,
        ).pack(anchor="w")

        self.custom_glb_row = tk.Frame(mode_box, bg="#FFFFFF")
        tk.Label(self.custom_glb_row, text="Hoặc chọn file .GLB sẵn có:", font=("Arial", 9), bg="#FFFFFF").pack(side="left")
        self.pub_custom_glb_var = tk.StringVar(value="")
        tk.Entry(self.custom_glb_row, textvariable=self.pub_custom_glb_var, font=("Arial", 9), width=28).pack(side="left", padx=6)
        tk.Button(self.custom_glb_row, text="Chọn .GLB", font=("Arial", 8.5), command=self._pick_custom_glb).pack(side="left")

        # Progress & Result Card
        prog_box = tk.Frame(right_card, bg="#FAF7F2", padx=12, pady=10, highlightbackground="#E7E0D7", highlightthickness=1)
        prog_box.pack(fill="both", expand=True)

        p_top = tk.Frame(prog_box, bg="#FAF7F2")
        p_top.pack(fill="x")
        self.pub_step_lbl = tk.Label(p_top, text="Sẵn sàng đăng sản phẩm & tự động dựng mô hình 3D.", font=("Arial", 9.5, "bold"), bg="#FAF7F2", fg="#44403C")
        self.pub_step_lbl.pack(side="left")
        self.pub_pct_lbl = tk.Label(p_top, text="0%", font=("Arial", 9.5, "bold"), bg="#FAF7F2", fg="#C25E3A")
        self.pub_pct_lbl.pack(side="right")

        self.pub_prog_bar = ttk.Progressbar(prog_box, orient="horizontal", mode="determinate")
        self.pub_prog_bar.pack(fill="x", pady=(6, 10))

        self.pub_result_lbl = tk.Label(
            prog_box,
            text="Sau khi hoàn tất, liên kết sản phẩm và trạng thái 3D/AR sẽ hiển thị tại đây.",
            font=("Arial", 9.5),
            bg="#FAF7F2",
            fg="#6B635B",
            wraplength=460,
            justify="left",
        )
        self.pub_result_lbl.pack(anchor="w", pady=(0, 8))

        self.btn_open_published = tk.Button(
            prog_box,
            text="🌐 Mở Xem Ngay Sản Phẩm & Mô Hình 3D Trên Shop ↗",
            font=("Arial", 9.5, "bold"),
            bg="#059669",
            fg="#FFFFFF",
            padx=12,
            pady=6,
            state="disabled",
            command=self._open_last_published_product,
        )
        self.btn_open_published.pack(anchor="w")

        # BOTTOM PRIMARY CTA BAR
        cta_bar = tk.Frame(outer, bg="#FFFFFF", padx=18, pady=12, highlightbackground="#E7E0D7", highlightthickness=1)
        cta_bar.pack(fill="x", side="bottom")

        self.btn_publish_now = tk.Button(
            cta_bar,
            text="🚀 TẢI SẢN PHẨM LÊN SHOP & TỰ ĐỘNG XÂY DỰNG MÔ HÌNH 3D + AR NGAY",
            font=("Arial", 11, "bold"),
            bg="#C25E3A",
            fg="#FFFFFF",
            padx=22,
            pady=10,
            command=self._run_one_click_publish,
        )
        self.btn_publish_now.pack(side="left")

        tk.Button(
            cta_bar,
            text="🧹 Làm mới biểu mẫu (Sản phẩm tiếp theo)",
            font=("Arial", 9.5),
            bg="#F3EFEA",
            fg="#1C1917",
            padx=12,
            pady=8,
            command=self._reset_publish_form,
        ).pack(side="right")

    @staticmethod
    def _generate_new_sku() -> str:
        suffix = "".join(random.choices(string.ascii_uppercase + string.digits, k=5))
        return f"DSP-{suffix}"

    def _toggle_custom_glb_row(self):
        if self.pub_auto_build_3d_var.get():
            self.custom_glb_row.pack_forget()
        else:
            self.custom_glb_row.pack(fill="x", pady=(6, 0))

    def _pick_single_publish_image(self, key: str):
        fn = filedialog.askopenfilename(
            title=f"Chọn ảnh sản phẩm ({key})",
            filetypes=[("Image files", "*.jpg *.jpeg *.png *.webp"), ("All files", "*.*")],
        )
        if fn:
            self.pub_angle_vars[key].set(fn)

    def _pick_multiple_publish_images(self):
        files = filedialog.askopenfilenames(
            title="Chọn 1 đến 5 ảnh sản phẩm (Ảnh đầu tiên sẽ là ảnh đại diện)",
            filetypes=[("Image files", "*.jpg *.jpeg *.png *.webp"), ("All files", "*.*")],
        )
        if not files:
            return
        keys = ["front", "perspective", "side", "back", "top"]
        for idx, fpath in enumerate(files[:5]):
            self.pub_angle_vars[keys[idx]].set(fpath)

    def _pick_custom_glb(self):
        fn = filedialog.askopenfilename(
            title="Chọn tệp mô hình 3D (.glb)",
            filetypes=[("GLTF Binary", "*.glb"), ("All files", "*.*")],
        )
        if fn:
            self.pub_custom_glb_var.set(fn)

    def _refresh_store_categories(self):
        def worker():
            cats = self.sync_client.fetch_categories()
            self.store_categories = cats
            labels = []
            mapping = {}
            for c in cats:
                lbl = f"{c.get('name', '')} ({c.get('slug', '')})"
                labels.append(lbl)
                mapping[lbl] = c.get("slug") or c.get("name") or "tu-giay-luu-tru"
            self.category_label_to_slug = mapping
            if labels:
                self.after(0, lambda: self.pub_cat_cb.configure(values=labels))
                if self.pub_cat_var.get() not in mapping:
                    self.after(0, lambda: self.pub_cat_var.set(labels[0]))

        threading.Thread(target=worker, daemon=True).start()

    def _reset_publish_form(self):
        self.pub_name_var.set("")
        self.pub_name_en_var.set("")
        self.pub_sku_var.set(self._generate_new_sku())
        for v in self.pub_angle_vars.values():
            v.set("")
        self.pub_prog_bar.config(value=0)
        self.pub_pct_lbl.config(text="0%")
        self.pub_step_lbl.config(text="Đã làm mới biểu mẫu, sẵn sàng cho sản phẩm tiếp theo.")

    def _open_last_published_product(self):
        if self.last_published_url:
            webbrowser.open(self.last_published_url)

    def _run_one_click_publish(self):
        name = self.pub_name_var.get().strip()
        sku = self.pub_sku_var.get().strip() or self._generate_new_sku()
        if not name:
            messagebox.showwarning("Thiếu Thông Tin", "Vui lòng nhập Tên sản phẩm trước khi đăng lên Shop.")
            return

        try:
            reg_price = float(self.pub_reg_price_var.get().replace(",", "").strip() or 0)
            sale_raw = self.pub_sale_price_var.get().replace(",", "").strip()
            sale_price = float(sale_raw) if sale_raw else 0.0
            stock_qty = int(float(self.pub_stock_var.get().strip() or 20))
            w = float(self.pub_w_var.get().strip() or 100)
            d = float(self.pub_d_var.get().strip() or 40)
            h = float(self.pub_h_var.get().strip() or 80)
        except ValueError:
            messagebox.showerror("Dữ Liệu Không Hợp Lệ", "Giá bán, tồn kho và kích thước (Rộng, Sâu, Cao) phải là số hợp lệ.")
            return

        cat_display = self.pub_cat_var.get().strip()
        cat_slug = self.category_label_to_slug.get(cat_display, cat_display)

        angles = {}
        for k, var in self.pub_angle_vars.items():
            val = var.get().strip()
            if val:
                angles[k] = val

        short_desc = self.pub_desc_text.get("1.0", tk.END).strip()

        self.btn_publish_now.config(state="disabled")
        self.btn_open_published.config(state="disabled")

        def update_ui_prog(pct: int, msg: str):
            self.after(0, lambda: self.pub_prog_bar.config(value=pct))
            self.after(0, lambda: self.pub_pct_lbl.config(text=f"{pct}%"))
            self.after(0, lambda: self.pub_step_lbl.config(text=msg))
            self._log(f"[Publish {sku}] [{pct}%] {msg}")

        def worker():
            try:
                glb_path: Optional[Path] = None
                usdz_path: Optional[Path] = None
                thumb_path: Optional[Path] = None
                build_meta: Dict[str, Any] = {}

                if self.pub_auto_build_3d_var.get():
                    build_meta = self.engine.reconstruct(
                        product_id=0,
                        sku=sku,
                        name=name,
                        category=f"{cat_display} {cat_slug}",
                        dimensions={"width": w, "depth": d, "height": h},
                        angles=angles,
                        progress_callback=lambda p, m: update_ui_prog(int(p * 0.72), m),
                        description=f"{short_desc} {self.pub_color_var.get().strip()}",
                    )
                    glb_path = Path(build_meta["glb_path"])
                    usdz_path = Path(build_meta["usdz_path"])
                    thumb_path = Path(build_meta["thumbnail_path"])
                else:
                    custom_glb = self.pub_custom_glb_var.get().strip()
                    if custom_glb and Path(custom_glb).exists():
                        glb_path = Path(custom_glb)

                update_ui_prog(82, "Đang tải sản phẩm, bộ ảnh Gallery & mô hình 3D (.GLB + .USDZ) lên Shop...")

                product_payload = {
                    "sku": sku,
                    "name": name,
                    "name_en": self.pub_name_en_var.get().strip(),
                    "regular_price": reg_price,
                    "sale_price": sale_price if sale_price > 0 else "",
                    "stock_quantity": stock_qty,
                    "category": cat_slug,
                    "badge": self.pub_badge_var.get().strip(),
                    "color": self.pub_color_var.get().strip(),
                    "short_description": short_desc,
                    "description": short_desc,
                    "dimensions": {"width": w, "depth": d, "height": h},
                    "angles": angles,
                }

                res = self.sync_client.publish_product_with_3d(
                    product_data=product_payload,
                    glb_path=glb_path,
                    usdz_path=usdz_path,
                    thumb_path=thumb_path,
                    metadata=build_meta,
                )

                if res.get("success"):
                    self.last_published_url = res.get("permalink", "")
                    pid = res.get("product_id")
                    glb_kb = (build_meta.get("file_size_bytes", 0) or 0) // 1024
                    usdz_kb = (build_meta.get("usdz_size_bytes", 0) or 0) // 1024
                    update_ui_prog(100, f"✓ Đã xuất bản thành công sản phẩm #{pid} kèm 3D & AR!")

                    summary_txt = (
                        f"✓ THÀNH CÔNG: '{name}' (ID #{pid} | SKU: {sku})\n"
                        f"• Mô hình 3D WebGL: model.glb ({glb_kb} KB) | Apple AR: model.usdz ({usdz_kb} KB)\n"
                        f"• Đường dẫn Shop: {self.last_published_url}"
                    )
                    self.after(0, lambda: self.pub_result_lbl.config(text=summary_txt, fg="#065F46"))
                    self.after(0, lambda: self.btn_open_published.config(state="normal"))
                    self.after(
                        0,
                        lambda: messagebox.showinfo(
                            "Đăng Sản Phẩm & Dựng 3D Thành Công",
                            f"Sản phẩm '{name}' đã lên kệ kèm mô hình 3D 360° & Camera AR!\n\nLink: {self.last_published_url}",
                        ),
                    )
                else:
                    err_msg = res.get("message", "Không rõ nguyên nhân")
                    update_ui_prog(0, "⚠️ Lỗi khi tải sản phẩm lên Website")
                    self.after(0, lambda: self.pub_result_lbl.config(text=f"Lỗi: {err_msg}", fg="#DC2626"))
                    self.after(0, lambda: messagebox.showerror("Lỗi Đăng Sản Phẩm", f"Máy chủ phản hồi lỗi:\n{err_msg}"))

            except Exception as e:
                self._log(f"❌ Lỗi ngoại lệ khi đăng sản phẩm: {e}")
                self.after(0, lambda: self.pub_result_lbl.config(text=f"Lỗi: {e}", fg="#DC2626"))
            finally:
                self.after(0, lambda: self.btn_publish_now.config(state="normal"))

        threading.Thread(target=worker, daemon=True).start()

    # -------------------------------------------------------------------------
    # TAB 2: Live Sync & Active Jobs
    # -------------------------------------------------------------------------
    def _build_tab_sync(self, parent):
        pane = tk.PanedWindow(parent, orient="horizontal", bg="#E7E3DC", sashwidth=4)
        pane.pack(fill="both", expand=True)

        left_frame = tk.Frame(pane, bg="#FFFFFF", padx=14, pady=14)
        pane.add(left_frame, minsize=430)

        queue_hdr = tk.Frame(left_frame, bg="#FFFFFF")
        queue_hdr.pack(fill="x", pady=(0, 8))

        tk.Label(queue_hdr, text="Hàng Đợi Lệnh Dựng 3D Từ Website", font=("Arial", 12, "bold"), bg="#FFFFFF", fg="#1C1917").pack(side="left")

        btn_refresh = tk.Button(
            queue_hdr,
            text="🔄 Quét ngay",
            font=("Arial", 9, "bold"),
            bg="#F3F4F6",
            fg="#1F2937",
            command=self._manual_fetch_jobs,
        )
        btn_refresh.pack(side="right")

        tk.Checkbutton(
            left_frame,
            text="⚡ Tự động dựng 3D & đẩy lên Web ngay khi phát hiện lệnh mới",
            variable=self.auto_execute_jobs,
            font=("Arial", 9, "bold"),
            bg="#FFFFFF",
            fg="#065F46",
            activebackground="#FFFFFF",
        ).pack(anchor="w", pady=(0, 6))

        columns = ("uuid", "sku", "name", "status")
        self.job_tree = ttk.Treeview(left_frame, columns=columns, show="headings", height=14)
        self.job_tree.heading("uuid", text="Mã Lệnh")
        self.job_tree.heading("sku", text="SKU")
        self.job_tree.heading("name", text="Tên Sản Phẩm")
        self.job_tree.heading("status", text="Trạng Thái")

        self.job_tree.column("uuid", width=110, anchor="center")
        self.job_tree.column("sku", width=95, anchor="center")
        self.job_tree.column("name", width=150, anchor="w")
        self.job_tree.column("status", width=85, anchor="center")

        tree_scroll = ttk.Scrollbar(left_frame, orient="vertical", command=self.job_tree.yview)
        self.job_tree.configure(yscrollcommand=tree_scroll.set)

        self.job_tree.pack(side="top", fill="both", expand=True)
        tree_scroll.pack(side="right", fill="y", before=self.job_tree)
        self.job_tree.bind("<<TreeviewSelect>>", self._on_job_tree_selected)

        queue_actions = tk.Frame(left_frame, bg="#FFFFFF")
        queue_actions.pack(fill="x", pady=(10, 0))

        tk.Button(
            queue_actions,
            text="🚀 Dựng 3D Tác Vụ Chọn",
            bg="#C25E3A",
            fg="#FFFFFF",
            font=("Arial", 10, "bold"),
            command=self._process_selected_job,
        ).pack(side="left", fill="x", expand=True, padx=(0, 4))

        tk.Button(
            queue_actions,
            text="⚡ Xử Lý Toàn Bộ",
            bg="#10B981",
            fg="#FFFFFF",
            font=("Arial", 10, "bold"),
            command=self._process_all_pending_jobs,
        ).pack(side="right", fill="x", expand=True, padx=(4, 0))

        right_frame = tk.Frame(pane, bg="#FAF7F2", padx=16, pady=14)
        pane.add(right_frame, minsize=520)

        detail_card = tk.LabelFrame(
            right_frame,
            text=" Chi Tiết Tác Vụ Đang Xử Lý ",
            font=("Arial", 11, "bold"),
            bg="#FFFFFF",
            fg="#1C1917",
            padx=12,
            pady=10,
        )
        detail_card.pack(fill="x", pady=(0, 12))

        self.active_prod_label = tk.Label(detail_card, text="Chưa có tác vụ nào đang chạy", font=("Arial", 12, "bold"), bg="#FFFFFF", fg="#1C1917")
        self.active_prod_label.pack(anchor="w")

        self.active_meta_label = tk.Label(detail_card, text="SKU: — | Kích thước: — | Số góc ảnh: —", font=("Arial", 9.5), bg="#FFFFFF", fg="#78716C")
        self.active_meta_label.pack(anchor="w", pady=(2, 8))

        prog_header = tk.Frame(detail_card, bg="#FFFFFF")
        prog_header.pack(fill="x")
        self.prog_step_label = tk.Label(prog_header, text="Đang chờ tác vụ...", font=("Arial", 9.5, "bold"), bg="#FFFFFF", fg="#44403C")
        self.prog_step_label.pack(side="left")
        self.prog_pct_label = tk.Label(prog_header, text="0%", font=("Arial", 9.5, "bold"), bg="#FFFFFF", fg="#C25E3A")
        self.prog_pct_label.pack(side="right")

        self.prog_bar = ttk.Progressbar(detail_card, orient="horizontal", mode="determinate")
        self.prog_bar.pack(fill="x", pady=(4, 6))

        log_card = tk.LabelFrame(
            right_frame,
            text=" Nhật Ký Tái Tạo 3D & AR Thời Gian Thực (Live Terminal) ",
            font=("Arial", 11, "bold"),
            bg="#FFFFFF",
            fg="#1C1917",
            padx=10,
            pady=10,
        )
        log_card.pack(fill="both", expand=True)

        self.log_text = tk.Text(log_card, bg="#1C1917", fg="#34D399", font=("Courier", 10), wrap="word", relief="flat", padx=8, pady=8)
        log_scroll = ttk.Scrollbar(log_card, orient="vertical", command=self.log_text.yview)
        self.log_text.configure(yscrollcommand=log_scroll.set)

        self.log_text.pack(side="left", fill="both", expand=True)
        log_scroll.pack(side="right", fill="y")

        self._log("DeSpace 3D Studio Desktop v3.0 khởi động thành công (Hỗ trợ xuất bản trực tiếp .GLB + Apple AR .USDZ).")

    # -------------------------------------------------------------------------
    # TAB 3: Excel Batch Builder
    # -------------------------------------------------------------------------
    def _build_tab_excel(self, parent):
        top_bar = tk.Frame(parent, bg="#FFFFFF", padx=18, pady=16, relief="solid", borderwidth=1)
        top_bar.pack(fill="x", pady=(0, 14))

        tk.Label(
            top_bar,
            text="Đăng Hàng Loạt Sản Phẩm & Tự Động Dựng Mô Hình 3D + AR Từ Excel (.XLSX / .CSV)",
            font=("Arial", 12, "bold"),
            bg="#FFFFFF",
            fg="#1C1917",
        ).pack(anchor="w", pady=(0, 6))

        tk.Label(
            top_bar,
            text="Hỗ trợ cả tạo mới sản phẩm chưa có trên Shop (Tên, Giá bán, Tồn kho, Danh mục, Kích thước WxDxH, Ảnh) lẫn cập nhật 3D (.GLB + .USDZ) cho SKU hiện có.",
            font=("Arial", 10),
            bg="#FFFFFF",
            fg="#6B635B",
        ).pack(anchor="w", pady=(0, 10))

        btn_row = tk.Frame(top_bar, bg="#FFFFFF")
        btn_row.pack(fill="x")

        tk.Button(
            btn_row,
            text="📁 Chọn Tệp Excel / CSV",
            font=("Arial", 10, "bold"),
            bg="#1C1917",
            fg="#FAF7F2",
            padx=14,
            pady=6,
            command=self._on_pick_excel_file,
        ).pack(side="left", padx=(0, 10))

        tk.Button(
            btn_row,
            text="⬇️ Tải Tệp Mẫu Excel (.xlsx)",
            font=("Arial", 10),
            bg="#F3F4F6",
            fg="#1F2937",
            padx=12,
            pady=6,
            command=self._on_download_sample_excel,
        ).pack(side="left", padx=(0, 10))

        self.excel_file_label = tk.Label(
            btn_row,
            text="Chưa chọn tệp nào",
            font=("Arial", 9.5, "italic"),
            bg="#FFFFFF",
            fg="#9CA3AF",
        )
        self.excel_file_label.pack(side="left", padx=8)

        preview_frame = tk.Frame(parent, bg="#FFFFFF", padx=16, pady=14, relief="solid", borderwidth=1)
        preview_frame.pack(fill="both", expand=True, pady=(0, 14))

        tk.Label(preview_frame, text="Danh Sách Sản Phẩm Được Đọc Từ Excel:", font=("Arial", 11, "bold"), bg="#FFFFFF", fg="#1C1917").pack(anchor="w", pady=(0, 6))

        cols = ("sku", "name", "price", "w", "d", "h", "cat", "angles")
        self.excel_tree = ttk.Treeview(preview_frame, columns=cols, show="headings", height=12)
        self.excel_tree.heading("sku", text="SKU")
        self.excel_tree.heading("name", text="Tên Sản Phẩm")
        self.excel_tree.heading("price", text="Giá Bán (VNĐ)")
        self.excel_tree.heading("w", text="Rộng (cm)")
        self.excel_tree.heading("d", text="Sâu (cm)")
        self.excel_tree.heading("h", text="Cao (cm)")
        self.excel_tree.heading("cat", text="Danh Mục")
        self.excel_tree.heading("angles", text="Số Góc Ảnh")

        self.excel_tree.column("sku", width=105, anchor="center")
        self.excel_tree.column("name", width=220, anchor="w")
        self.excel_tree.column("price", width=110, anchor="e")
        self.excel_tree.column("w", width=75, anchor="center")
        self.excel_tree.column("d", width=75, anchor="center")
        self.excel_tree.column("h", width=75, anchor="center")
        self.excel_tree.column("cat", width=120, anchor="center")
        self.excel_tree.column("angles", width=85, anchor="center")

        ex_scroll = ttk.Scrollbar(preview_frame, orient="vertical", command=self.excel_tree.yview)
        self.excel_tree.configure(yscrollcommand=ex_scroll.set)

        self.excel_tree.pack(side="top", fill="both", expand=True)
        ex_scroll.pack(side="right", fill="y", before=self.excel_tree)

        batch_bar = tk.Frame(parent, bg="#FFFFFF", padx=16, pady=12, relief="solid", borderwidth=1)
        batch_bar.pack(fill="x")

        self.btn_batch_run = tk.Button(
            batch_bar,
            text="🚀 ĐĂNG SẢN PHẨM HÀNG LOẠT & TỰ ĐỘNG DỰNG 3D + AR LÊN SHOP",
            font=("Arial", 11, "bold"),
            bg="#C25E3A",
            fg="#FFFFFF",
            padx=18,
            pady=8,
            command=self._run_batch_excel_build,
        )
        self.btn_batch_run.pack(side="left")

        self.batch_status_label = tk.Label(
            batch_bar,
            text="Sẵn sàng khi bạn chọn tệp Excel",
            font=("Arial", 10),
            bg="#FFFFFF",
            fg="#6B635B",
        )
        self.batch_status_label.pack(side="left", padx=16)

    # -------------------------------------------------------------------------
    # TAB 4: Manual Local 3D Studio
    # -------------------------------------------------------------------------
    def _build_tab_manual(self, parent):
        box = tk.Frame(parent, bg="#FFFFFF", padx=24, pady=20, relief="solid", borderwidth=1)
        box.pack(fill="both", expand=True)

        tk.Label(box, text="Xưởng Dựng Mô Hình 3D (.GLB) & Apple AR (.USDZ) Cục Bộ", font=("Arial", 13, "bold"), bg="#FFFFFF", fg="#1C1917").pack(anchor="w", pady=(0, 4))
        tk.Label(box, text="Xuất nhanh cặp tệp chuẩn .glb và .usdz lưu trên máy tính để kiểm thử ngoại tuyến.", font=("Arial", 10), bg="#FFFFFF", fg="#78716C").pack(anchor="w", pady=(0, 16))

        form_grid = tk.Frame(box, bg="#FFFFFF")
        form_grid.pack(fill="x", pady=(0, 16))

        tk.Label(form_grid, text="Mã SKU:", font=("Arial", 10, "bold"), bg="#FFFFFF").grid(row=0, column=0, sticky="w", pady=6)
        self.manual_sku_var = tk.StringVar(value="TEST-MANUAL-001")
        tk.Entry(form_grid, textvariable=self.manual_sku_var, font=("Arial", 10), width=24).grid(row=0, column=1, sticky="w", padx=10)

        tk.Label(form_grid, text="Tên Sản Phẩm:", font=("Arial", 10, "bold"), bg="#FFFFFF").grid(row=0, column=2, sticky="w", pady=6)
        self.manual_name_var = tk.StringVar(value="Tủ giày 3 cánh DeSpace")
        tk.Entry(form_grid, textvariable=self.manual_name_var, font=("Arial", 10), width=32).grid(row=0, column=3, sticky="w", padx=10)

        tk.Label(form_grid, text="Kiểu dáng 3D:", font=("Arial", 10, "bold"), bg="#FFFFFF").grid(row=1, column=0, sticky="w", pady=6)
        self.manual_cat_var = tk.StringVar(value="shoe_cabinet")
        cat_cb = ttk.Combobox(
            form_grid,
            textvariable=self.manual_cat_var,
            values=["shoe_cabinet", "wardrobe", "bookcase", "cabinet", "sofa", "chair", "table", "bed", "lamp"],
            state="readonly",
            width=22,
        )
        cat_cb.grid(row=1, column=1, sticky="w", padx=10)

        tk.Label(form_grid, text="Kích Thước (W x D x H cm):", font=("Arial", 10, "bold"), bg="#FFFFFF").grid(row=1, column=2, sticky="w", pady=6)
        dim_box = tk.Frame(form_grid, bg="#FFFFFF")
        dim_box.grid(row=1, column=3, sticky="w", padx=10)

        self.manual_w_var = tk.StringVar(value="114")
        self.manual_d_var = tk.StringVar(value="33")
        self.manual_h_var = tk.StringVar(value="76")
        tk.Entry(dim_box, textvariable=self.manual_w_var, width=6).pack(side="left")
        tk.Label(dim_box, text=" x ", bg="#FFFFFF").pack(side="left")
        tk.Entry(dim_box, textvariable=self.manual_d_var, width=6).pack(side="left")
        tk.Label(dim_box, text=" x ", bg="#FFFFFF").pack(side="left")
        tk.Entry(dim_box, textvariable=self.manual_h_var, width=6).pack(side="left")
        tk.Label(dim_box, text=" cm", bg="#FFFFFF").pack(side="left")

        tk.Label(box, text="Chọn 1–5 Ảnh Góc Chụp Sản Phẩm:", font=("Arial", 11, "bold"), bg="#FFFFFF", fg="#1C1917").pack(anchor="w", pady=(12, 8))

        angles_box = tk.Frame(box, bg="#FAF7F2", padx=12, pady=12, relief="solid", borderwidth=1)
        angles_box.pack(fill="x", pady=(0, 16))

        self.angle_path_vars = {
            "front": tk.StringVar(value=""),
            "perspective": tk.StringVar(value=""),
            "side": tk.StringVar(value=""),
            "back": tk.StringVar(value=""),
            "top": tk.StringVar(value=""),
        }

        angle_labels = [
            ("front", "Mặt Trước (0° Front)"),
            ("perspective", "Góc Nghiêng 45° (Perspective)"),
            ("side", "Mặt Bên (90° Side)"),
            ("back", "Mặt Sau (180° Back)"),
            ("top", "Mặt Trên (Top View)"),
        ]

        for akey, alabel in angle_labels:
            row = tk.Frame(angles_box, bg="#FAF7F2")
            row.pack(fill="x", pady=4)
            tk.Label(row, text=alabel, width=28, anchor="w", font=("Arial", 9.5, "bold"), bg="#FAF7F2").pack(side="left")
            tk.Entry(row, textvariable=self.angle_path_vars[akey], font=("Arial", 9.5), width=60).pack(side="left", padx=6)
            tk.Button(row, text="Chọn ảnh...", font=("Arial", 8.5), command=lambda k=akey: self._pick_manual_image(k)).pack(side="left")

        btn_action_row = tk.Frame(box, bg="#FFFFFF")
        btn_action_row.pack(fill="x", pady=10)

        tk.Button(
            btn_action_row,
            text="🧊 XUẤT CẶP MÔ HÌNH 3D (.GLB + .USDZ) CỤC BỘ",
            font=("Arial", 11, "bold"),
            bg="#10B981",
            fg="#FFFFFF",
            padx=18,
            pady=8,
            command=self._run_manual_build,
        ).pack(side="left")

        self.manual_status_label = tk.Label(btn_action_row, text="", font=("Arial", 10), bg="#FFFFFF", fg="#059669")
        self.manual_status_label.pack(side="left", padx=14)

    def _pick_manual_image(self, key: str):
        fn = filedialog.askopenfilename(
            title=f"Chọn ảnh góc: {key.upper()}",
            filetypes=[("Image files", "*.jpg *.jpeg *.png *.webp *.avif"), ("All files", "*.*")],
        )
        if fn:
            self.angle_path_vars[key].set(fn)

    def _run_manual_build(self):
        sku = self.manual_sku_var.get().strip() or "MANUAL-001"
        name = self.manual_name_var.get().strip() or "Sản phẩm thử nghiệm"
        cat = self.manual_cat_var.get()
        try:
            w = float(self.manual_w_var.get() or 80)
            d = float(self.manual_d_var.get() or 80)
            h = float(self.manual_h_var.get() or 80)
        except ValueError:
            messagebox.showerror("Lỗi", "Kích thước W, D, H phải là số hợp lệ.")
            return

        angles = {k: v.get() for k, v in self.angle_path_vars.items() if v.get()}
        self.manual_status_label.config(text="⏳ Đang dựng mô hình .GLB & .USDZ...", fg="#C25E3A")

        def task():
            try:
                res = self.engine.reconstruct(
                    product_id=0,
                    sku=sku,
                    name=name,
                    category=cat,
                    dimensions={"width": w, "depth": d, "height": h},
                    angles=angles,
                    progress_callback=lambda p, m: self._log(f"[{p}%] {m}"),
                    description=name,
                )
                self.after(
                    0,
                    lambda: self.manual_status_label.config(
                        text=f"✓ Đã xuất: {res['glb_filename']} & {res['usdz_filename']} ({res['vertex_count']} vertices)",
                        fg="#059669",
                    ),
                )
                self.after(
                    0,
                    lambda: messagebox.showinfo(
                        "Thành Công",
                        f"Đã xuất cặp file 3D & AR tại:\n• {res['glb_path']}\n• {res['usdz_path']}",
                    ),
                )
            except Exception as e:
                self.after(0, lambda: self.manual_status_label.config(text=f"Lỗi: {e}", fg="#DC2626"))

        threading.Thread(target=task, daemon=True).start()

    # -------------------------------------------------------------------------
    # TAB 5: Settings
    # -------------------------------------------------------------------------
    def _build_tab_settings(self, parent):
        card = tk.Frame(parent, bg="#FFFFFF", padx=28, pady=24, relief="solid", borderwidth=1)
        card.pack(fill="both", expand=True)

        tk.Label(card, text="Cấu Hình Kết Nối & Thông Số Đồng Bộ", font=("Arial", 13, "bold"), bg="#FFFFFF", fg="#1C1917").pack(anchor="w", pady=(0, 6))
        tk.Label(card, text="Thiết lập địa chỉ website DeSpace (hoặc Cloudflare HTTPS Tunnel) và khóa bảo mật API.", font=("Arial", 10), bg="#FFFFFF", fg="#78716C").pack(anchor="w", pady=(0, 20))

        tk.Label(card, text="Địa chỉ Website DeSpace (Store URL):", font=("Arial", 10, "bold"), bg="#FFFFFF").pack(anchor="w", pady=(4, 2))
        url_row = tk.Frame(card, bg="#FFFFFF")
        url_row.pack(fill="x", pady=(0, 8))

        tk.Entry(url_row, textvariable=self.site_url_var, font=("Arial", 10), width=48).pack(side="left")

        for preset_port in [8090, 8088, 10016]:
            tk.Button(
                url_row,
                text=f":{preset_port}",
                font=("Arial", 8.5),
                command=lambda p=preset_port: self.site_url_var.set(f"http://localhost:{p}"),
            ).pack(side="left", padx=4)

        tk.Label(card, text="Mã Bí Mật Desktop API (X-DeSpace-Desktop-Key):", font=("Arial", 10, "bold"), bg="#FFFFFF").pack(anchor="w", pady=(8, 2))
        tk.Entry(card, textvariable=self.secret_key_var, font=("Arial", 10), width=48, show="*").pack(anchor="w", pady=(0, 8))

        tk.Label(card, text="Cổng Lắng Nghe Cục Bộ (Local Webhook Port):", font=("Arial", 10, "bold"), bg="#FFFFFF").pack(anchor="w", pady=(8, 2))
        tk.Entry(card, textvariable=self.local_port_var, font=("Arial", 10), width=16).pack(anchor="w", pady=(0, 14))

        tk.Label(
            card,
            text="Cloud AI Image-to-3D (Tùy chọn — Tự động dự phòng Parametric PBR + USDZ khi để trống)",
            font=("Arial", 11, "bold"),
            bg="#FFFFFF",
            fg="#1C1917",
        ).pack(anchor="w", pady=(6, 4))

        tk.Label(card, text="Fal.ai API Key (Trellis Image-to-3D):", font=("Arial", 9.5, "bold"), bg="#FFFFFF").pack(anchor="w", pady=(4, 2))
        tk.Entry(card, textvariable=self.fal_key_var, font=("Arial", 10), width=48, show="*").pack(anchor="w", pady=(0, 6))

        tk.Label(card, text="Tripo3D v2 API Key:", font=("Arial", 9.5, "bold"), bg="#FFFFFF").pack(anchor="w", pady=(4, 2))
        tk.Entry(card, textvariable=self.tripo_key_var, font=("Arial", 10), width=48, show="*").pack(anchor="w", pady=(0, 14))

        btn_row = tk.Frame(card, bg="#FFFFFF")
        btn_row.pack(fill="x", pady=10)

        tk.Button(
            btn_row,
            text="🔍 Kiểm Tra Kết Nối Đến Website",
            font=("Arial", 10, "bold"),
            bg="#1C1917",
            fg="#FAF7F2",
            padx=14,
            pady=6,
            command=self._check_website_health,
        ).pack(side="left")

        tk.Button(
            btn_row,
            text="💾 Lưu Thiết Lập",
            font=("Arial", 10, "bold"),
            bg="#10B981",
            fg="#FFFFFF",
            padx=14,
            pady=6,
            command=self._save_settings,
        ).pack(side="left", padx=10)

        self.conn_result_label = tk.Label(card, text="", font=("Arial", 10), bg="#FFFFFF")
        self.conn_result_label.pack(anchor="w", pady=10)

    def _save_settings(self):
        new_url = self.site_url_var.get().strip().rstrip("/")
        new_key = self.secret_key_var.get().strip()
        new_fal = self.fal_key_var.get().strip()
        new_tripo = self.tripo_key_var.get().strip()
        try:
            new_port = int(self.local_port_var.get())
        except Exception:
            new_port = Config.LOCAL_PORT
        Config.save_persisted_settings(new_url, new_key, new_port, fal_api_key=new_fal, tripo_api_key=new_tripo)
        self.sync_client.site_url = Config.SITE_URL
        self.sync_client.api_base = f"{Config.SITE_URL}/wp-json/despace/v1/desktop-3d"
        self.sync_client.secret_key = Config.API_SECRET_KEY
        messagebox.showinfo("Thông Báo", "Đã lưu vĩnh viễn cài đặt kết nối thành công!")
        self._check_website_health()
        self._refresh_store_categories()

    # -------------------------------------------------------------------------
    # Bottom Status Bar
    # -------------------------------------------------------------------------
    def _build_bottom_statusbar(self):
        status_bar = tk.Frame(self, bg="#EFEBE4", height=28)
        status_bar.pack(fill="x", side="bottom")

        self.status_bar_label = tk.Label(
            status_bar,
            text="Sẵn sàng đăng sản phẩm mới hoặc tiếp nhận lệnh dựng 3D tự động từ DeSpace Store.",
            font=("Arial", 9),
            bg="#EFEBE4",
            fg="#57534E",
            padx=12,
        )
        self.status_bar_label.pack(side="left")

        output_link = tk.Label(
            status_bar,
            text=f"📁 Thư mục xuất 3D: {Config.OUTPUT_DIR}",
            font=("Arial", 9, "underline"),
            bg="#EFEBE4",
            fg="#2563EB",
            cursor="pointer",
            padx=12,
        )
        output_link.pack(side="right")
        output_link.bind("<Button-1>", lambda e: self._open_output_dir())

    def _open_output_dir(self):
        try:
            if sys.platform == "darwin":
                subprocess.run(["open", str(Config.OUTPUT_DIR)])
            elif sys.platform == "win32":
                os.startfile(str(Config.OUTPUT_DIR))
            else:
                subprocess.run(["xdg-open", str(Config.OUTPUT_DIR)])
        except Exception as e:
            messagebox.showwarning("Thông Báo", f"Đường dẫn: {Config.OUTPUT_DIR}\n({e})")

    # -------------------------------------------------------------------------
    # Logging Helper
    # -------------------------------------------------------------------------
    def _log(self, text: str):
        ts = time.strftime("%H:%M:%S")
        entry = f"[{ts}] {text}\n"

        def append():
            if hasattr(self, "log_text"):
                self.log_text.insert(tk.END, entry)
                self.log_text.see(tk.END)

        self.after(0, append)

    # -------------------------------------------------------------------------
    # Synchronization & Job Handlers
    # -------------------------------------------------------------------------
    def _check_website_health(self):
        def worker():
            res = self.sync_client.health_check()
            if res.get("online"):
                ver = res.get("data", {}).get("version", "3.0")
                msg = f"🟢 Kết nối thành công tới DeSpace API v{ver} ({self.sync_client.site_url})"
                self.after(
                    0,
                    lambda: self.web_status_pill.config(
                        text=f"🌐 Website: Online (API v{ver})",
                        bg="#064E3B",
                        fg="#34D399",
                    ),
                )
                self.after(0, lambda: self.conn_result_label.config(text=msg, fg="#059669"))
                self._log(f"Đã kết nối với WordPress API v{ver} tại: {self.sync_client.site_url}")
            else:
                msg = f"🟡 Ngoại tuyến: {res.get('message', 'Không thể kết nối')}"
                self.after(
                    0,
                    lambda: self.web_status_pill.config(
                        text="🌐 Website: Ngoại tuyến",
                        bg="#78350F",
                        fg="#FCD34D",
                    ),
                )
                self.after(0, lambda: self.conn_result_label.config(text=msg, fg="#D97706"))

        threading.Thread(target=worker, daemon=True).start()

    def _start_auto_sync_thread(self):
        def loop():
            while True:
                if self.auto_sync_active.get() and not self.is_processing:
                    try:
                        jobs = self.sync_client.fetch_pending_jobs(limit=10)
                        if jobs:
                            self.after(0, lambda j=jobs: self._update_job_tree(j))
                            if self.auto_execute_jobs.get():
                                for job_item in jobs:
                                    self._execute_single_job_sync(job_item)
                    except Exception:
                        pass
                time.sleep(Config.POLL_INTERVAL)

        threading.Thread(target=loop, daemon=True).start()

    def _manual_fetch_jobs(self):
        self._log("Đang quét lệnh mới từ website...")

        def worker():
            jobs = self.sync_client.fetch_pending_jobs(limit=20)
            self.after(0, lambda: self._update_job_tree(jobs))
            self._log(f"Tìm thấy {len(jobs)} lệnh đang chờ xử lý.")

        threading.Thread(target=worker, daemon=True).start()

    def _update_job_tree(self, jobs: List[Dict[str, Any]]):
        self.job_tree.delete(*self.job_tree.get_children())
        self.active_jobs_data.clear()

        for j in jobs:
            uuid = j.get("job_uuid", "")
            sku = j.get("sku", "")
            name = j.get("product_name", "")
            status = j.get("status", "")
            self.active_jobs_data[uuid] = j
            if not self.job_tree.exists(uuid):
                self.job_tree.insert("", tk.END, iid=uuid, values=(uuid, sku, name, status.upper()))

    def _on_job_tree_selected(self, event):
        sel = self.job_tree.selection()
        if not sel:
            return
        uuid = sel[0]
        job = self.active_jobs_data.get(uuid)
        if job:
            self.active_prod_label.config(text=f"Lệnh: {job.get('product_name', 'Sản phẩm')}")
            dims = job.get("dimensions", {})
            angles = job.get("multi_angles", {})
            self.active_meta_label.config(
                text=f"SKU: {job.get('sku')} | Kích thước: {dims.get('width', 0)}x{dims.get('depth', 0)}x{dims.get('height', 0)} cm | Đã nạp {len(angles)}/5 góc ảnh"
            )

    def _on_local_task_received(self, task_data: Dict[str, Any]):
        uuid = task_data.get("job_uuid", "")
        self._log(f"[Local Push] Nhận được yêu cầu dựng 3D tức thì cho lệnh: {uuid}")
        self.active_jobs_data[uuid] = task_data

        def insert_row():
            if not self.job_tree.exists(uuid):
                self.job_tree.insert(
                    "",
                    0,
                    iid=uuid,
                    values=(uuid, task_data.get("sku", ""), task_data.get("product_name", ""), "PUSHED"),
                )

        self.after(0, insert_row)
        self._execute_single_job(task_data)

    def _process_selected_job(self):
        sel = self.job_tree.selection()
        if not sel:
            messagebox.showinfo("Thông Báo", "Vui lòng chọn 1 lệnh trong danh sách để xử lý.")
            return
        job = self.active_jobs_data.get(sel[0])
        if job:
            self._execute_single_job(job)

    def _process_all_pending_jobs(self):
        jobs = list(self.active_jobs_data.values())
        if not jobs:
            messagebox.showinfo("Thông Báo", "Không có lệnh nào trong hàng đợi.")
            return

        def worker():
            for j in jobs:
                self._execute_single_job_sync(j)
            self._log("Đã hoàn tất xử lý toàn bộ hàng đợi!")

        threading.Thread(target=worker, daemon=True).start()

    def _execute_single_job(self, job_data: Dict[str, Any]):
        threading.Thread(target=lambda: self._execute_single_job_sync(job_data), daemon=True).start()

    def _execute_single_job_sync(self, job: Dict[str, Any]):
        self.is_processing = True
        uuid = job.get("job_uuid", "")
        sku = job.get("sku", "UNKNOWN")
        name = job.get("product_name", sku)
        cat = job.get("category", "cabinet")
        dims = job.get("dimensions", {"width": 100, "depth": 80, "height": 75})
        angles = job.get("multi_angles") or job.get("angles") or {}

        self.after(0, lambda: self.active_prod_label.config(text=f"Đang dựng 3D + AR: {name} ({sku})"))
        self._log(f"--- BẮT ĐẦU XÂY DỰNG 3D (.GLB + .USDZ): {name} (Mã lệnh: {uuid}) ---")

        def progress_tracker(pct: int, msg: str):
            self.after(0, lambda: self.prog_bar.config(value=pct))
            self.after(0, lambda: self.prog_pct_label.config(text=f"{pct}%"))
            self.after(0, lambda: self.prog_step_label.config(text=msg))
            self._log(f"[{pct}%] {msg}")
            self.sync_client.update_progress(uuid, pct, msg)

        try:
            build_res = self.engine.reconstruct(
                product_id=int(job.get("product_id") or 0),
                sku=sku,
                name=name,
                category=cat,
                dimensions=dims,
                angles=angles,
                progress_callback=progress_tracker,
            )

            progress_tracker(96, "Đang tự động tải cặp mô hình .GLB & .USDZ hoàn thiện lên Website DeSpace...")
            upload_res = self.sync_client.upload_completed_model(
                job_uuid=uuid,
                glb_path=Path(build_res["glb_path"]),
                metadata=build_res,
            )

            if upload_res.get("success"):
                progress_tracker(100, "✓ Hoàn tất! Mô hình 3D (.GLB) & AR (.USDZ) đã xuất bản lên cửa hàng.")
                self._log(f"✓ THÀNH CÔNG: Mô hình 3D & AR cho {sku} đã gắn kết trực tiếp với sản phẩm trên Web!")
                self.after(0, lambda: self.job_tree.exists(uuid) and self.job_tree.set(uuid, "status", "COMPLETED"))
            else:
                self._log(f"⚠️ Cảnh báo tải lên: {upload_res.get('message')}")

        except Exception as e:
            self._log(f"❌ LỖI trong quá trình tái tạo 3D cho {sku}: {e}")
            self.sync_client.update_progress(uuid, 0, f"Lỗi: {e}", status="failed")
            self.after(0, lambda: self.job_tree.exists(uuid) and self.job_tree.set(uuid, "status", "FAILED"))

        finally:
            self.is_processing = False

    # -------------------------------------------------------------------------
    # Excel Batch Actions
    # -------------------------------------------------------------------------
    def _on_pick_excel_file(self):
        fn = filedialog.askopenfilename(
            title="Chọn Tệp Excel Danh Sách Sản Phẩm 3D",
            filetypes=[("Excel Files", "*.xlsx"), ("CSV Files", "*.csv"), ("All files", "*.*")],
        )
        if not fn:
            return

        self.excel_path_var.set(fn)
        self.excel_file_label.config(text=Path(fn).name, fg="#1C1917")

        try:
            items = ExcelProcessor.parse_file(Path(fn))
            self.excel_items = items
            self.excel_tree.delete(*self.excel_tree.get_children())

            for it in items:
                dims = it["dimensions"]
                price_val = it.get("regular_price")
                price_str = f"{int(price_val):,} ₫" if isinstance(price_val, (int, float)) and price_val > 0 else "Mặc định"
                self.excel_tree.insert(
                    "",
                    tk.END,
                    values=(
                        it["sku"],
                        it["product_name"],
                        price_str,
                        dims.get("width"),
                        dims.get("depth"),
                        dims.get("height"),
                        it["category"],
                        f"{it['angle_count']}/5 ảnh",
                    ),
                )

            self.batch_status_label.config(
                text=f"Đã nạp {len(items)} sản phẩm từ Excel. Sẵn sàng đăng sản phẩm & dựng 3D hàng loạt!",
                fg="#059669",
            )
            self._log(f"Đã nhập {len(items)} sản phẩm từ tệp: {Path(fn).name}")

        except Exception as e:
            messagebox.showerror("Lỗi Đọc File Excel", str(e))
            self._log(f"Lỗi đọc file Excel: {e}")

    def _on_download_sample_excel(self):
        target = filedialog.asksaveasfilename(
            title="Lưu Tệp Mẫu Excel 3D",
            initialfile="mau_danh_sach_san_pham_3d.xlsx",
            defaultextension=".xlsx",
            filetypes=[("Excel Files", "*.xlsx")],
        )
        if target:
            ExcelProcessor.generate_template(Path(target))
            messagebox.showinfo("Thành Công", f"Đã lưu tệp Excel mẫu chuẩn tại:\n{target}")

    def _run_batch_excel_build(self):
        if not self.excel_items:
            messagebox.showinfo("Thông Báo", "Vui lòng chọn tệp Excel có chứa dữ liệu trước khi chạy.")
            return

        total = len(self.excel_items)
        confirm = messagebox.askyesno(
            "Xác Nhận Xuất Bản Hàng Loạt",
            f"Bắt đầu tự động tạo/cập nhật {total} sản phẩm kèm dựng mô hình 3D (.GLB + .USDZ) lên Shop?",
        )
        if not confirm:
            return

        self.btn_batch_run.config(state="disabled")
        self.notebook.select(self.tab_sync)

        def worker():
            self._log(f"=== BẮT ĐẦU ĐĂNG HÀNG LOẠT & DỰNG 3D CHO {total} SẢN PHẨM TỪ EXCEL ===")

            def on_prog(idx, tot, msg):
                self._log(msg)
                self.after(
                    0,
                    lambda i=idx, t=tot: self.batch_status_label.config(
                        text=f"Đang xử lý {i}/{t} ({int(i / t * 100)}%)...",
                        fg="#C25E3A",
                    ),
                )

            summary = ExcelProcessor.run_batch_build(
                items=self.excel_items,
                engine=self.engine,
                sync_client=self.sync_client,
                progress_callback=on_prog,
            )

            self.after(0, lambda: self.btn_batch_run.config(state="normal"))
            self.after(
                0,
                lambda: self.batch_status_label.config(
                    text=f"✓ Đã hoàn tất {summary['successes']}/{total} sản phẩm lên Shop!",
                    fg="#059669",
                ),
            )
            self._log(f"=== HOÀN TẤT BATCH EXCEL: {summary['successes']}/{total} THÀNH CÔNG ===")
            self.after(
                0,
                lambda: messagebox.showinfo(
                    "Hoàn Tất",
                    f"Đã xây dựng mô hình 3D + AR và đồng bộ {summary['successes']}/{total} sản phẩm lên Shop!",
                ),
            )

        threading.Thread(target=worker, daemon=True).start()

    # -------------------------------------------------------------------------
    # App Shutdown
    # -------------------------------------------------------------------------
    def _on_close(self):
        self.local_server.stop()
        self.destroy()
