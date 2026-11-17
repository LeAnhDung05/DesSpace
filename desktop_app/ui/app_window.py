"""
DeSpace 3D Studio Desktop — Luxury Modern GUI Application.

Built with Tkinter & TTK, styled with DeSpace executive brand identity:
#1C1917 (Deep Obsidian), #C25E3A (Terracotta), #10B981 (Emerald), #FAF7F2 (Cream).
"""

import os
import sys
import time
import json
import threading
import subprocess
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
    Main Application Window for DeSpace 3D Studio Desktop.
    """

    def __init__(self):
        super().__init__()

        self.title("DeSpace 3D Studio Desktop — Xây Dựng & Đồng Bộ Mô Hình 3D v2.5")
        self.geometry("1160x760")
        self.minsize(980, 640)
        self.configure(bg="#FAF7F2")

        # Core Engines
        self.engine = ReconstructionEngine()
        self.sync_client = DeSpaceSyncClient()
        self.local_server = DesktopLocalServer(
            port=Config.LOCAL_PORT,
            task_callback=self._on_local_task_received
        )

        # State Variables
        self.auto_sync_active = tk.BooleanVar(value=True)
        self.site_url_var = tk.StringVar(value=Config.SITE_URL)
        self.secret_key_var = tk.StringVar(value=Config.API_SECRET_KEY)
        self.local_port_var = tk.IntVar(value=Config.LOCAL_PORT)
        self.excel_path_var = tk.StringVar(value="")
        self.excel_items: List[Dict[str, Any]] = []

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

        # Initial health check
        self.after(500, self._check_website_health)

        # Protocol cleanup
        self.protocol("WM_DELETE_WINDOW", self._on_close)

    def _setup_styles(self):
        style = ttk.Style(self)
        try:
            style.theme_use("clam")
        except Exception:
            pass

        # Configure Colors
        style.configure("TNotebook", background="#FAF7F2", borderwidth=0)
        style.configure("TNotebook.Tab", background="#EFEBE4", foreground="#44403C", font=("Arial", 10, "bold"), padding=[16, 8])
        style.map("TNotebook.Tab", background=[("selected", "#1C1917")], foreground=[("selected", "#FAF7F2")])

        style.configure("TFrame", background="#FAF7F2")
        style.configure("Card.TFrame", background="#FFFFFF", relief="solid", borderwidth=1)

        style.configure("TLabel", background="#FAF7F2", foreground="#1C1917", font=("Arial", 10))
        style.configure("Header.TLabel", font=("Arial", 14, "bold"), foreground="#1C1917")
        style.configure("SubHeader.TLabel", font=("Arial", 10), foreground="#78716C")

        style.configure("Primary.TButton", font=("Arial", 10, "bold"), background="#C25E3A", foreground="#FFFFFF")
        style.configure("Success.TButton", font=("Arial", 10, "bold"), background="#10B981", foreground="#FFFFFF")

    # -------------------------------------------------------------------------
    # Top Brand Header
    # -------------------------------------------------------------------------
    def _build_top_header(self):
        header_frame = tk.Frame(self, bg="#1C1917", height=72)
        header_frame.pack(fill="x", side="top")

        content = tk.Frame(header_frame, bg="#1C1917")
        content.pack(fill="both", expand=True, padx=24, pady=12)

        # Left brand
        left_box = tk.Frame(content, bg="#1C1917")
        left_box.pack(side="left", fill="y")

        brand_lbl = tk.Label(
            left_box,
            text="🧊 DESPACE 3D STUDIO DESKTOP",
            font=("Arial", 15, "bold"),
            fg="#FAF7F2",
            bg="#1C1917"
        )
        brand_lbl.pack(anchor="w")

        sub_lbl = tk.Label(
            left_box,
            text="Xây dựng mô hình 3D đa góc (3–5 Views) & Đồng bộ trực tiếp với Website",
            font=("Arial", 9.5),
            fg="#A8A29E",
            bg="#1C1917"
        )
        sub_lbl.pack(anchor="w")

        # Right status pills
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
            relief="flat"
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
            relief="flat"
        )
        self.port_status_pill.pack(side="left", padx=6)

        auto_toggle = tk.Checkbutton(
            right_box,
            text="Tự động đồng bộ",
            variable=self.auto_sync_active,
            font=("Arial", 9.5, "bold"),
            bg="#1C1917",
            fg="#FAF7F2",
            selectcolor="#292524",
            activebackground="#1C1917",
            activeforeground="#FAF7F2"
        )
        auto_toggle.pack(side="left", padx=8)

    # -------------------------------------------------------------------------
    # Main Tabs (Notebook)
    # -------------------------------------------------------------------------
    def _build_notebook(self):
        self.notebook = ttk.Notebook(self)
        self.notebook.pack(fill="both", expand=True, padx=20, pady=16)

        # Tab 1: Live Sync & Active Jobs
        self.tab_sync = ttk.Frame(self.notebook)
        self.notebook.add(self.tab_sync, text="  ⚡ Live Sync & Active Jobs  ")
        self._build_tab_sync(self.tab_sync)

        # Tab 2: Excel Batch Builder
        self.tab_excel = ttk.Frame(self.notebook)
        self.notebook.add(self.tab_excel, text="  📊 Excel Batch Builder  ")
        self._build_tab_excel(self.tab_excel)

        # Tab 3: Manual 3D Studio
        self.tab_manual = ttk.Frame(self.notebook)
        self.notebook.add(self.tab_manual, text="  🛠️ 3D Manual Studio  ")
        self._build_tab_manual(self.tab_manual)

        # Tab 4: Settings
        self.tab_settings = ttk.Frame(self.notebook)
        self.notebook.add(self.tab_settings, text="  ⚙️ Cấu Hình Kết Nối  ")
        self._build_tab_settings(self.tab_settings)

    # -------------------------------------------------------------------------
    # TAB 1: Live Sync & Active Jobs
    # -------------------------------------------------------------------------
    def _build_tab_sync(self, parent):
        pane = tk.PanedWindow(parent, orient="horizontal", bg="#E7E3DC", sashwidth=4)
        pane.pack(fill="both", expand=True)

        # Left Column: Job Queue
        left_frame = tk.Frame(pane, bg="#FFFFFF", padx=14, pady=14)
        pane.add(left_frame, minsize=420)

        queue_hdr = tk.Frame(left_frame, bg="#FFFFFF")
        queue_hdr.pack(fill="x", pady=(0, 8))

        tk.Label(queue_hdr, text="Hàng Đợi Lệnh Từ Website", font=("Arial", 12, "bold"), bg="#FFFFFF", fg="#1C1917").pack(side="left")
        
        btn_refresh = tk.Button(
            queue_hdr,
            text="🔄 Quét ngay",
            font=("Arial", 9, "bold"),
            bg="#F3F4F6",
            fg="#1F2937",
            command=self._manual_fetch_jobs
        )
        btn_refresh.pack(side="right")

        # Treeview Queue
        columns = ("uuid", "sku", "name", "status")
        self.job_tree = ttk.Treeview(left_frame, columns=columns, show="headings", height=14)
        self.job_tree.heading("uuid", text="Mã Lệnh")
        self.job_tree.heading("sku", text="SKU")
        self.job_tree.heading("name", text="Tên Sản Phẩm")
        self.job_tree.heading("status", text="Trạng Thái")

        self.job_tree.column("uuid", width=110, anchor="center")
        self.job_tree.column("sku", width=95, anchor="center")
        self.job_tree.column("name", width=140, anchor="w")
        self.job_tree.column("status", width=85, anchor="center")

        tree_scroll = ttk.Scrollbar(left_frame, orient="vertical", command=self.job_tree.yview)
        self.job_tree.configure(yscrollcommand=tree_scroll.set)

        self.job_tree.pack(side="top", fill="both", expand=True)
        tree_scroll.pack(side="right", fill="y", before=self.job_tree)

        self.job_tree.bind("<<TreeviewSelect>>", self._on_job_tree_selected)

        # Actions under queue
        queue_actions = tk.Frame(left_frame, bg="#FFFFFF")
        queue_actions.pack(fill="x", pady=(10, 0))

        btn_run_selected = tk.Button(
            queue_actions,
            text="🚀 Dựng 3D Tác Vụ Chọn",
            bg="#C25E3A",
            fg="#FFFFFF",
            font=("Arial", 10, "bold"),
            command=self._process_selected_job
        )
        btn_run_selected.pack(side="left", fill="x", expand=True, padx=(0, 4))

        btn_run_all = tk.Button(
            queue_actions,
            text="⚡ Xử Lý Toàn Bộ",
            bg="#10B981",
            fg="#FFFFFF",
            font=("Arial", 10, "bold"),
            command=self._process_all_pending_jobs
        )
        btn_run_all.pack(side="right", fill="x", expand=True, padx=(4, 0))

        # Right Column: Active Task Progress & Live Terminal
        right_frame = tk.Frame(pane, bg="#FAF7F2", padx=16, pady=14)
        pane.add(right_frame, minsize=520)

        # Active Details Card
        detail_card = tk.LabelFrame(right_frame, text=" Chi Tiết Tác Vụ Đang Xử Lý ", font=("Arial", 11, "bold"), bg="#FFFFFF", fg="#1C1917", padx=12, pady=10)
        detail_card.pack(fill="x", pady=(0, 12))

        self.active_prod_label = tk.Label(detail_card, text="Chưa có tác vụ nào đang chạy", font=("Arial", 12, "bold"), bg="#FFFFFF", fg="#1C1917")
        self.active_prod_label.pack(anchor="w")

        self.active_meta_label = tk.Label(detail_card, text="SKU: — | Kích thước: — | Số góc ảnh: —", font=("Arial", 9.5), bg="#FFFFFF", fg="#78716C")
        self.active_meta_label.pack(anchor="w", pady=(2, 8))

        # Progress bar
        prog_header = tk.Frame(detail_card, bg="#FFFFFF")
        prog_header.pack(fill="x")
        self.prog_step_label = tk.Label(prog_header, text="Đang chờ tác vụ...", font=("Arial", 9.5, "bold"), bg="#FFFFFF", fg="#44403C")
        self.prog_step_label.pack(side="left")
        self.prog_pct_label = tk.Label(prog_header, text="0%", font=("Arial", 9.5, "bold"), bg="#FFFFFF", fg="#C25E3A")
        self.prog_pct_label.pack(side="right")

        self.prog_bar = ttk.Progressbar(detail_card, orient="horizontal", mode="determinate")
        self.prog_bar.pack(fill="x", pady=(4, 6))

        # Terminal Log Box
        log_card = tk.LabelFrame(right_frame, text=" Nhật Ký Tái Tạo 3D Thời Gian Thực (Live Terminal) ", font=("Arial", 11, "bold"), bg="#FFFFFF", fg="#1C1917", padx=10, pady=10)
        log_card.pack(fill="both", expand=True)

        self.log_text = tk.Text(log_card, bg="#1C1917", fg="#34D399", font=("Courier", 10), wrap="word", relief="flat", padx=8, pady=8)
        log_scroll = ttk.Scrollbar(log_card, orient="vertical", command=self.log_text.yview)
        self.log_text.configure(yscrollcommand=log_scroll.set)

        self.log_text.pack(side="left", fill="both", expand=True)
        log_scroll.pack(side="right", fill="y")

        self._log("DeSpace 3D Studio Desktop khởi động thành công.")

    # -------------------------------------------------------------------------
    # TAB 2: Excel Batch Builder
    # -------------------------------------------------------------------------
    def _build_tab_excel(self, parent):
        top_bar = tk.Frame(parent, bg="#FFFFFF", padx=18, pady=16, relief="solid", borderwidth=1)
        top_bar.pack(fill="x", pady=(0, 14))

        tk.Label(
            top_bar,
            text="Tự Động Xây Dựng 3D Hàng Loạt Từ Tệp Excel (.XLSX / .CSV)",
            font=("Arial", 12, "bold"),
            bg="#FFFFFF",
            fg="#1C1917"
        ).pack(anchor="w", pady=(0, 6))

        tk.Label(
            top_bar,
            text="Nhập danh sách sản phẩm gồm SKU, Kích thước (WxDxH) và 3–5 đường dẫn ảnh góc chụp. Phần mềm sẽ tự động dựng và tải lên website.",
            font=("Arial", 10),
            bg="#FFFFFF",
            fg="#6B635B"
        ).pack(anchor="w", pady=(0, 10))

        btn_row = tk.Frame(top_bar, bg="#FFFFFF")
        btn_row.pack(fill="x")

        btn_pick_excel = tk.Button(
            btn_row,
            text="📁 Chọn Tệp Excel / CSV",
            font=("Arial", 10, "bold"),
            bg="#1C1917",
            fg="#FAF7F2",
            padx=14,
            pady=6,
            command=self._on_pick_excel_file
        )
        btn_pick_excel.pack(side="left", padx=(0, 10))

        btn_sample = tk.Button(
            btn_row,
            text="⬇️ Tải Tệp Mẫu Excel (.xlsx)",
            font=("Arial", 10),
            bg="#F3F4F6",
            fg="#1F2937",
            padx=12,
            pady=6,
            command=self._on_download_sample_excel
        )
        btn_sample.pack(side="left", padx=(0, 10))

        self.excel_file_label = tk.Label(
            btn_row,
            text="Chưa chọn tệp nào",
            font=("Arial", 9.5, "italic"),
            bg="#FFFFFF",
            fg="#9CA3AF"
        )
        self.excel_file_label.pack(side="left", padx=8)

        # Excel Preview Table
        preview_frame = tk.Frame(parent, bg="#FFFFFF", padx=16, pady=14, relief="solid", borderwidth=1)
        preview_frame.pack(fill="both", expand=True, pady=(0, 14))

        tk.Label(preview_frame, text="Danh Sách Sản Phẩm Được Đọc Từ Excel:", font=("Arial", 11, "bold"), bg="#FFFFFF", fg="#1C1917").pack(anchor="w", pady=(0, 6))

        cols = ("sku", "name", "w", "d", "h", "cat", "angles")
        self.excel_tree = ttk.Treeview(preview_frame, columns=cols, show="headings", height=12)
        self.excel_tree.heading("sku", text="SKU")
        self.excel_tree.heading("name", text="Tên Sản Phẩm")
        self.excel_tree.heading("w", text="Rộng (cm)")
        self.excel_tree.heading("d", text="Sâu (cm)")
        self.excel_tree.heading("h", text="Cao (cm)")
        self.excel_tree.heading("cat", text="Danh Mục")
        self.excel_tree.heading("angles", text="Số Góc Ảnh")

        self.excel_tree.column("sku", width=110, anchor="center")
        self.excel_tree.column("name", width=220, anchor="w")
        self.excel_tree.column("w", width=80, anchor="center")
        self.excel_tree.column("d", width=80, anchor="center")
        self.excel_tree.column("h", width=80, anchor="center")
        self.excel_tree.column("cat", width=100, anchor="center")
        self.excel_tree.column("angles", width=90, anchor="center")

        ex_scroll = ttk.Scrollbar(preview_frame, orient="vertical", command=self.excel_tree.yview)
        self.excel_tree.configure(yscrollcommand=ex_scroll.set)

        self.excel_tree.pack(side="top", fill="both", expand=True)
        ex_scroll.pack(side="right", fill="y", before=self.excel_tree)

        # Batch Execute Bar
        batch_bar = tk.Frame(parent, bg="#FFFFFF", padx=16, pady=12, relief="solid", borderwidth=1)
        batch_bar.pack(fill="x")

        self.btn_batch_run = tk.Button(
            batch_bar,
            text="🚀 BẮT ĐẦU XÂY DỰNG 3D HÀNG LOẠT & TỰ ĐỘNG ĐẨY LÊN WEBSITE",
            font=("Arial", 11, "bold"),
            bg="#C25E3A",
            fg="#FFFFFF",
            padx=18,
            pady=8,
            command=self._run_batch_excel_build
        )
        self.btn_batch_run.pack(side="left")

        self.batch_status_label = tk.Label(
            batch_bar,
            text="Sẵn sàng khi bạn chọn tệp Excel",
            font=("Arial", 10),
            bg="#FFFFFF",
            fg="#6B635B"
        )
        self.batch_status_label.pack(side="left", padx=16)

    # -------------------------------------------------------------------------
    # TAB 3: Manual 3D Studio
    # -------------------------------------------------------------------------
    def _build_tab_manual(self, parent):
        box = tk.Frame(parent, bg="#FFFFFF", padx=24, pady=20, relief="solid", borderwidth=1)
        box.pack(fill="both", expand=True)

        tk.Label(box, text="Dựng Mô Hình 3D Thử Nghiệm Từ Ảnh Cục Bộ", font=("Arial", 13, "bold"), bg="#FFFFFF", fg="#1C1917").pack(anchor="w", pady=(0, 4))
        tk.Label(box, text="Chọn thủ công 3–5 ảnh nhiều góc từ máy tính của bạn và nhập kích thước để dựng mô hình 3D (.glb).", font=("Arial", 10), bg="#FFFFFF", fg="#78716C").pack(anchor="w", pady=(0, 16))

        form_grid = tk.Frame(box, bg="#FFFFFF")
        form_grid.pack(fill="x", pady=(0, 16))

        # SKU & Name
        tk.Label(form_grid, text="Mã SKU:", font=("Arial", 10, "bold"), bg="#FFFFFF").grid(row=0, column=0, sticky="w", pady=6)
        self.manual_sku_var = tk.StringVar(value="TEST-MANUAL-001")
        tk.Entry(form_grid, textvariable=self.manual_sku_var, font=("Arial", 10), width=24).grid(row=0, column=1, sticky="w", padx=10)

        tk.Label(form_grid, text="Tên Sản Phẩm:", font=("Arial", 10, "bold"), bg="#FFFFFF").grid(row=0, column=2, sticky="w", pady=6)
        self.manual_name_var = tk.StringVar(value="Ghế Bành DeSpace Relax")
        tk.Entry(form_grid, textvariable=self.manual_name_var, font=("Arial", 10), width=32).grid(row=0, column=3, sticky="w", padx=10)

        # Category & Dims
        tk.Label(form_grid, text="Danh Mục:", font=("Arial", 10, "bold"), bg="#FFFFFF").grid(row=1, column=0, sticky="w", pady=6)
        self.manual_cat_var = tk.StringVar(value="chair")
        cat_cb = ttk.Combobox(form_grid, textvariable=self.manual_cat_var, values=["sofa", "chair", "table", "cabinet", "bed", "lamp"], state="readonly", width=22)
        cat_cb.grid(row=1, column=1, sticky="w", padx=10)

        tk.Label(form_grid, text="Kích Thước (W x D x H cm):", font=("Arial", 10, "bold"), bg="#FFFFFF").grid(row=1, column=2, sticky="w", pady=6)
        dim_box = tk.Frame(form_grid, bg="#FFFFFF")
        dim_box.grid(row=1, column=3, sticky="w", padx=10)

        self.manual_w_var = tk.StringVar(value="85")
        self.manual_d_var = tk.StringVar(value="80")
        self.manual_h_var = tk.StringVar(value="90")
        tk.Entry(dim_box, textvariable=self.manual_w_var, width=6).pack(side="left")
        tk.Label(dim_box, text=" x ", bg="#FFFFFF").pack(side="left")
        tk.Entry(dim_box, textvariable=self.manual_d_var, width=6).pack(side="left")
        tk.Label(dim_box, text=" x ", bg="#FFFFFF").pack(side="left")
        tk.Entry(dim_box, textvariable=self.manual_h_var, width=6).pack(side="left")
        tk.Label(dim_box, text=" cm", bg="#FFFFFF").pack(side="left")

        # 5 Angles File Selectors
        tk.Label(box, text="Chọn 3–5 Ảnh Góc Chụp Sản Phẩm:", font=("Arial", 11, "bold"), bg="#FFFFFF", fg="#1C1917").pack(anchor="w", pady=(12, 8))

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
            ("front", "Mặt Trước (0° Front) *"),
            ("perspective", "Góc Nghiêng 45° (Perspective) *"),
            ("side", "Mặt Bên (90° Side) *"),
            ("back", "Mặt Sau (180° Back)"),
            ("top", "Mặt Trên (Top View)"),
        ]

        for idx, (akey, alabel) in enumerate(angle_labels):
            row = tk.Frame(angles_box, bg="#FAF7F2")
            row.pack(fill="x", pady=4)
            tk.Label(row, text=alabel, width=28, anchor="w", font=("Arial", 9.5, "bold"), bg="#FAF7F2").pack(side="left")
            tk.Entry(row, textvariable=self.angle_path_vars[akey], font=("Arial", 9.5), width=60).pack(side="left", padx=6)
            tk.Button(row, text="Chọn ảnh...", font=("Arial", 8.5), command=lambda k=akey: self._pick_manual_image(k)).pack(side="left")

        # Action Button
        btn_action_row = tk.Frame(box, bg="#FFFFFF")
        btn_action_row.pack(fill="x", pady=10)

        btn_build_manual = tk.Button(
            btn_action_row,
            text="🧊 DỰNG MÔ HÌNH 3D (.GLB) NGAY",
            font=("Arial", 11, "bold"),
            bg="#10B981",
            fg="#FFFFFF",
            padx=18,
            pady=8,
            command=self._run_manual_build
        )
        btn_build_manual.pack(side="left")

        self.manual_status_label = tk.Label(btn_action_row, text="", font=("Arial", 10), bg="#FFFFFF", fg="#059669")
        self.manual_status_label.pack(side="left", padx=14)

    def _pick_manual_image(self, key: str):
        fn = filedialog.askopenfilename(
            title=f"Chọn ảnh góc: {key.upper()}",
            filetypes=[("Image files", "*.jpg *.jpeg *.png *.webp *.avif"), ("All files", "*.*")]
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

        angles = {}
        for k, v in self.angle_path_vars.items():
            if v.get():
                angles[k] = v.get()

        self.manual_status_label.config(text="⏳ Đang dựng mô hình 3D...", fg="#C25E3A")

        def task():
            try:
                res = self.engine.reconstruct(
                    product_id=0,
                    sku=sku,
                    name=name,
                    category=cat,
                    dimensions={"width": w, "depth": d, "height": h},
                    angles=angles,
                    progress_callback=lambda p, m: self._log(f"[{p}%] {m}")
                )
                self.after(0, lambda: self.manual_status_label.config(
                    text=f"✓ Đã dựng xong: {res['glb_filename']} ({res['vertex_count']} vertices, {res['file_size_bytes']//1024} KB)",
                    fg="#059669"
                ))
                self.after(0, lambda: messagebox.showinfo("Thành Công", f"Đã xuất file 3D tại:\n{res['glb_path']}"))
            except Exception as e:
                self.after(0, lambda: self.manual_status_label.config(text=f"Lỗi: {e}", fg="#DC2626"))

        threading.Thread(target=task, daemon=True).start()

    # -------------------------------------------------------------------------
    # TAB 4: Settings
    # -------------------------------------------------------------------------
    def _build_tab_settings(self, parent):
        card = tk.Frame(parent, bg="#FFFFFF", padx=28, pady=24, relief="solid", borderwidth=1)
        card.pack(fill="both", expand=True)

        tk.Label(card, text="Cấu Hình Kết Nối & Thông Số Đồng Bộ", font=("Arial", 13, "bold"), bg="#FFFFFF", fg="#1C1917").pack(anchor="w", pady=(0, 6))
        tk.Label(card, text="Thiết lập địa chỉ website DeSpace và mã bảo mật API để phần mềm tự động nhận lệnh và tải mô hình lên.", font=("Arial", 10), bg="#FFFFFF", fg="#78716C").pack(anchor="w", pady=(0, 20))

        # Site URL
        tk.Label(card, text="Địa chỉ Website DeSpace (Store URL):", font=("Arial", 10, "bold"), bg="#FFFFFF").pack(anchor="w", pady=(4, 2))
        url_row = tk.Frame(card, bg="#FFFFFF")
        url_row.pack(fill="x", pady=(0, 8))

        tk.Entry(url_row, textvariable=self.site_url_var, font=("Arial", 10), width=48).pack(side="left")
        
        # Quick Port Presets
        for preset_port in [8090, 8088, 10016]:
            btn = tk.Button(
                url_row,
                text=f":{preset_port}",
                font=("Arial", 8.5),
                command=lambda p=preset_port: self.site_url_var.set(f"http://localhost:{p}")
            )
            btn.pack(side="left", padx=4)

        # Secret Key
        tk.Label(card, text="Mã Bí Mật Desktop API (X-DeSpace-Desktop-Key):", font=("Arial", 10, "bold"), bg="#FFFFFF").pack(anchor="w", pady=(8, 2))
        tk.Entry(card, textvariable=self.secret_key_var, font=("Arial", 10), width=48, show="*").pack(anchor="w", pady=(0, 8))

        # Local Webhook Port
        tk.Label(card, text="Cổng Lắng Nghe Cục Bộ (Local Webhook Port):", font=("Arial", 10, "bold"), bg="#FFFFFF").pack(anchor="w", pady=(8, 2))
        tk.Entry(card, textvariable=self.local_port_var, font=("Arial", 10), width=16).pack(anchor="w", pady=(0, 16))

        # Test Connection Button
        btn_row = tk.Frame(card, bg="#FFFFFF")
        btn_row.pack(fill="x", pady=10)

        btn_test = tk.Button(
            btn_row,
            text="🔍 Kiểm Tra Kết Nối Đến Website",
            font=("Arial", 10, "bold"),
            bg="#1C1917",
            fg="#FAF7F2",
            padx=14,
            pady=6,
            command=self._check_website_health
        )
        btn_test.pack(side="left")

        btn_save = tk.Button(
            btn_row,
            text="💾 Lưu Thiết Lập",
            font=("Arial", 10, "bold"),
            bg="#10B981",
            fg="#FFFFFF",
            padx=14,
            pady=6,
            command=self._save_settings
        )
        btn_save.pack(side="left", padx=10)

        self.conn_result_label = tk.Label(card, text="", font=("Arial", 10), bg="#FFFFFF")
        self.conn_result_label.pack(anchor="w", pady=10)

    def _save_settings(self):
        Config.SITE_URL = self.site_url_var.get().strip().rstrip("/")
        Config.API_SECRET_KEY = self.secret_key_var.get().strip()
        self.sync_client.site_url = Config.SITE_URL
        self.sync_client.api_base = f"{Config.SITE_URL}/wp-json/despace/v1/desktop-3d"
        self.sync_client.secret_key = Config.API_SECRET_KEY
        messagebox.showinfo("Thông Báo", "Đã lưu cài đặt kết nối thành công!")
        self._check_website_health()

    # -------------------------------------------------------------------------
    # Bottom Status Bar
    # -------------------------------------------------------------------------
    def _build_bottom_statusbar(self):
        status_bar = tk.Frame(self, bg="#EFEBE4", height=28)
        status_bar.pack(fill="x", side="bottom")

        self.status_bar_label = tk.Label(
            status_bar,
            text="Sẵn sàng tiếp nhận lệnh xây dựng 3D từ DeSpace Store.",
            font=("Arial", 9),
            bg="#EFEBE4",
            fg="#57534E",
            padx=12
        )
        self.status_bar_label.pack(side="left")

        output_link = tk.Label(
            status_bar,
            text=f"📁 Thư mục xuất: {Config.OUTPUT_DIR}",
            font=("Arial", 9, "underline"),
            bg="#EFEBE4",
            fg="#2563EB",
            cursor="pointer",
            padx=12
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
                msg = f"🟢 Kết nối Website thành công: {self.sync_client.site_url}"
                self.after(0, lambda: self.web_status_pill.config(
                    text=f"🌐 Website: Online ({self.sync_client.site_url[-10:]})",
                    bg="#064E3B",
                    fg="#34D399"
                ))
                self.after(0, lambda: self.conn_result_label.config(text=msg, fg="#059669"))
                self._log(f"Đã kết nối với WordPress API tại: {self.sync_client.site_url}")
            else:
                msg = f"🟡 Ngoại tuyến: {res.get('message', 'Không thể kết nối')}"
                self.after(0, lambda: self.web_status_pill.config(
                    text="🌐 Website: Ngoại tuyến",
                    bg="#78350F",
                    fg="#FCD34D"
                ))
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
        """Callback from Local Webhook listener (zero latency)."""
        uuid = task_data.get("job_uuid", "")
        self._log(f"[Local Push] Nhận được yêu cầu dựng 3D tức thì cho lệnh: {uuid}")
        self.active_jobs_data[uuid] = task_data
        self.after(0, lambda: self.job_tree.insert("", 0, iid=uuid, values=(
            uuid, task_data.get("sku", ""), task_data.get("product_name", ""), "PUSHED"
        )))
        # Execute immediately
        self._execute_single_job(task_data)

    def _process_selected_job(self):
        sel = self.job_tree.selection()
        if not sel:
            messagebox.showinfo("Thông Báo", "Vui lòng chọn 1 lệnh trong danh sách để xử lý.")
            return
        uuid = sel[0]
        job = self.active_jobs_data.get(uuid)
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

        self.after(0, lambda: self.active_prod_label.config(text=f"Đang dựng 3D: {name} ({sku})"))
        self._log(f"--- BẮT ĐẦU XÂY DỰNG 3D: {name} (Mã lệnh: {uuid}) ---")

        def progress_tracker(pct: int, msg: str):
            self.after(0, lambda: self.prog_bar.config(value=pct))
            self.after(0, lambda: self.prog_pct_label.config(text=f"{pct}%"))
            self.after(0, lambda: self.prog_step_label.config(text=msg))
            self._log(f"[{pct}%] {msg}")
            # Stream progress to WordPress
            self.sync_client.update_progress(uuid, pct, msg)

        try:
            # 1. Reconstruct 3D Model
            build_res = self.engine.reconstruct(
                product_id=int(job.get("product_id") or 0),
                sku=sku,
                name=name,
                category=cat,
                dimensions=dims,
                angles=angles,
                progress_callback=progress_tracker
            )

            # 2. Upload completed GLB to Website
            progress_tracker(96, "Đang tự động tải mô hình GLB hoàn thiện lên Website DeSpace...")
            upload_res = self.sync_client.upload_completed_model(
                job_uuid=uuid,
                glb_path=Path(build_res["glb_path"]),
                metadata=build_res
            )

            if upload_res.get("success"):
                progress_tracker(100, "✓ Hoàn tất! Mô hình 3D đã được xuất bản trực tiếp lên cửa hàng.")
                self._log(f"✓ THÀNH CÔNG: Mô hình 3D cho {sku} đã gắn kết với sản phẩm trên Web!")
                self.after(0, lambda: self.job_tree.set(uuid, "status", "COMPLETED"))
            else:
                self._log(f"⚠️ Cảnh báo tải lên: {upload_res.get('message')}")

        except Exception as e:
            self._log(f"❌ LỖI trong quá trình tái tạo 3D cho {sku}: {e}")
            self.sync_client.update_progress(uuid, 0, f"Lỗi: {e}", status="failed")
            self.after(0, lambda: self.job_tree.set(uuid, "status", "FAILED"))

        finally:
            self.is_processing = False

    # -------------------------------------------------------------------------
    # Excel Batch Actions
    # -------------------------------------------------------------------------
    def _on_pick_excel_file(self):
        fn = filedialog.askopenfilename(
            title="Chọn Tệp Excel Danh Sách Sản Phẩm 3D",
            filetypes=[("Excel Files", "*.xlsx"), ("CSV Files", "*.csv"), ("All files", "*.*")]
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
                self.excel_tree.insert("", tk.END, values=(
                    it["sku"],
                    it["product_name"],
                    dims.get("width"),
                    dims.get("depth"),
                    dims.get("height"),
                    it["category"],
                    f"{it['angle_count']}/5 ảnh"
                ))

            self.batch_status_label.config(
                text=f"Đã nạp {len(items)} sản phẩm từ Excel. Sẵn sàng dựng 3D hàng loạt!",
                fg="#059669"
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
            filetypes=[("Excel Files", "*.xlsx")]
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
            "Xác Nhận Dựng Hàng Loạt",
            f"Bạn có muốn bắt đầu tự động xây dựng mô hình 3D cho {total} sản phẩm và đồng bộ lên website không?"
        )
        if not confirm:
            return

        self.btn_batch_run.config(state="disabled")
        self.notebook.select(self.tab_sync)  # Switch to sync tab to view logs

        def worker():
            self._log(f"=== BẮT ĐẦU CHẠY BATCH 3D CHO {total} SẢN PHẨM TỪ EXCEL ===")
            for idx, it in enumerate(self.excel_items, start=1):
                mock_job = {
                    "job_uuid": f"EXCEL-{it['sku']}",
                    "product_id": 0,
                    "sku": it["sku"],
                    "product_name": it["product_name"],
                    "category": it["category"],
                    "dimensions": it["dimensions"],
                    "multi_angles": it["angles"],
                }
                self.after(0, lambda i=idx, t=total: self.batch_status_label.config(
                    text=f"Đang xử lý sản phẩm {i}/{t} ({int(i/t*100)}%)...",
                    fg="#C25E3A"
                ))
                self._execute_single_job_sync(mock_job)

            self.after(0, lambda: self.btn_batch_run.config(state="normal"))
            self.after(0, lambda: self.batch_status_label.config(
                text=f"✓ Đã hoàn tất toàn bộ {total} sản phẩm từ Excel!",
                fg="#059669"
            ))
            self._log("=== ĐÃ HOÀN TẤT TOÀN BỘ TIẾN TRÌNH EXCEL BATCH 3D ===")
            self.after(0, lambda: messagebox.showinfo("Hoàn Tất", f"Đã xây dựng thành công {total} mô hình 3D!"))

        threading.Thread(target=worker, daemon=True).start()

    # -------------------------------------------------------------------------
    # App Shutdown
    # -------------------------------------------------------------------------
    def _on_close(self):
        self.local_server.stop()
        self.destroy()
