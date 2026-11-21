#!/usr/bin/env python3
"""
DeSpace 3D Studio Desktop Core Test Suite (TC-PY01 - TC-PY10).

Validates:
1. ReconstructionEngine: GLB format validity, vertex/index buffer, PBR material embedding.
2. Furniture category parametric modeling (sofa, chair, table, cabinet, bed, lamp).
3. Physical dimension bounding box accuracy.
4. ExcelProcessor: template creation, parsing, multi-angle field mapping.
5. DesktopLocalServer: HTTP lifecycle, CORS, /api/health, task reception.
6. DeSpaceSyncClient: payload structure and authentication headers.
"""

import sys
import time
import json
import struct
import tempfile
import urllib.error
import urllib.request
from pathlib import Path

# Add project root to path
ROOT_DIR = Path(__file__).resolve().parent.parent
if str(ROOT_DIR) not in sys.path:
    sys.path.insert(0, str(ROOT_DIR))

from desktop_app.config import Config
from desktop_app.core.reconstruction_engine import ReconstructionEngine
from desktop_app.core.sync_client import DeSpaceSyncClient
from desktop_app.core.local_server import DesktopLocalServer
from desktop_app.core.excel_processor import ExcelProcessor

print("=" * 72)
print("  DeSpace 3D Studio Desktop — Core Engine Test Suite (TC-PY01 - TC-PY10) ")
print("=" * 72)

passed = 0
total = 18

def check(name: str, condition: bool):
    global passed
    if condition:
        print(f"[PASS] {name}")
        passed += 1
    else:
        print(f"[FAIL] {name}")

_tmp_dir_ctx = tempfile.TemporaryDirectory(prefix="despace_py_tests_")
TMP_OUT_DIR = Path(_tmp_dir_ctx.name)
engine = ReconstructionEngine(output_dir=TMP_OUT_DIR)

# TC-PY01: GLTF 2.0 Binary Header
res_sofa = engine.reconstruct(
    product_id=1,
    sku="TEST-SOFA",
    name="Sofa Milano PBR",
    category="sofa",
    dimensions={"width": 200, "depth": 90, "height": 85},
    angles={}
)
with open(res_sofa["glb_path"], "rb") as f:
    header = f.read(12)
    magic, ver, total_len = struct.unpack("<4sII", header)

check(
    "TC-PY01: Khronos glTF 2.0 binary header integrity (magic=glTF, ver=2)",
    magic == b"glTF" and ver == 2 and total_len == res_sofa["file_size_bytes"]
)

# TC-PY02: JSON Chunk & BIN Chunk formatting
with open(res_sofa["glb_path"], "rb") as f:
    f.seek(12)
    c0_len, c0_type = struct.unpack("<I4s", f.read(8))
    c0_data = f.read(c0_len)
    c1_len, c1_type = struct.unpack("<I4s", f.read(8))
    gltf_json = json.loads(c0_data.decode("utf-8").strip())

check(
    "TC-PY02: JSON chunk (b'JSON') and BIN chunk (b'BIN\\x00') alignment",
    c0_type == b"JSON" and c1_type == b"BIN\x00" and "meshes" in gltf_json and "materials" in gltf_json
)

# TC-PY03: Multi-category parametric geometry
categories = ["chair", "table", "cabinet", "bed", "lamp"]
all_cat_ok = True
for cat in categories:
    p, n, uv, idx = engine._build_geometry(cat, 1.0, 1.0, 1.0)
    if len(p) == 0 or len(idx) == 0 or len(p) % 3 != 0 or len(idx) % 3 != 0:
        all_cat_ok = False
        break

check(
    "TC-PY03: Parametric geometry generation for 5 furniture archetypes",
    all_cat_ok
)

# TC-PY04: Physical dimension scaling
res_dim = engine.reconstruct(
    product_id=2,
    sku="TEST-TABLE",
    name="Bàn Trà Metro",
    category="table",
    dimensions={"width": 140, "depth": 70, "height": 45},
    angles={}
)
check(
    "TC-PY04: Real-world physical dimensions scaled accurately (m / cm)",
    res_dim["dimensions"]["width_m"] == 1.4 and res_dim["dimensions"]["depth_m"] == 0.7 and res_dim["dimensions"]["height_m"] == 0.45
)

# TC-PY05: PBR texture atlas generation with PIL
tex_bytes = engine._synthesize_pbr_texture({}, "Test Product", "sofa")
check(
    "TC-PY05: PBR Texture Atlas synthesis produces valid PNG buffer",
    len(tex_bytes) > 500 and tex_bytes[:8] == b"\x89PNG\r\n\x1a\n"
)

# TC-PY06: Excel template generation (isolated in temp dir so Git tree stays clean)
tpl_path = ExcelProcessor.generate_template(output_path=TMP_OUT_DIR / "test_template.xlsx")
check(
    "TC-PY06: Generates valid openpyxl Excel template file (.xlsx)",
    tpl_path.exists() and tpl_path.stat().st_size > 1000
)

# TC-PY07: Excel parsing and field normalization
items = ExcelProcessor.parse_file(tpl_path)
check(
    "TC-PY07: Parses Excel items, dimensions & multi-angle image mappings",
    len(items) == 3 and items[0]["sku"] in ("AXG-STR-001", "AXG-METRO-001") and items[0]["angle_count"] >= 3
)

# TC-PY08: Local HTTP server lifecycle & /api/health
test_port = 58319
received_tasks = []
srv = DesktopLocalServer(port=test_port, task_callback=lambda t: received_tasks.append(t))
srv.start()
time.sleep(0.3)

health_ok = False
try:
    with urllib.request.urlopen(f"http://127.0.0.1:{test_port}/api/health", timeout=3) as resp:
        data = json.loads(resp.read().decode("utf-8"))
        health_ok = data.get("status") == "online" and data.get("port") == test_port
except Exception as e:
    print(f"Server check error: {e}")

srv.stop()
check(
    "TC-PY08: Local HTTP server starts, handles /api/health & stops cleanly",
    health_ok
)

# TC-PY09: Sync client headers & API URL construction
client = DeSpaceSyncClient(site_url="http://localhost:8090", secret_key="test-key-2026")
headers = client._get_headers()
check(
    "TC-PY09: Sync client constructs correct REST base & X-DeSpace-Desktop-Key",
    client.api_base == "http://localhost:8090/wp-json/despace/v1/desktop-3d" and headers.get("X-DeSpace-Desktop-Key") == "test-key-2026"
)

# TC-PY10: End-to-end task flow simulation
e2e_res = engine.reconstruct(
    product_id=88,
    sku="DESPACE-E2E-CHAIR",
    name="Ghế Gỗ Sồi DeSpace",
    category="chair",
    dimensions={"width": 60, "depth": 65, "height": 85},
    angles={"front": "", "side": "", "perspective": ""},
)
check(
    "TC-PY10: End-to-end simulation: reconstruction, thumbnail & GLB output",
    e2e_res["success"] and Path(e2e_res["glb_path"]).exists() and Path(e2e_res["thumbnail_path"]).exists()
)

# TC-PY11: Apple AR Quick Look USDZ generation & strict 64-byte alignment
usdz_ok = False
if "usdz_path" in e2e_res and Path(e2e_res["usdz_path"]).exists():
    usdz_raw = Path(e2e_res["usdz_path"]).read_bytes()
    # Verify PK\x03\x04 local header and 64-byte data offset for first entry (model.usda)
    if usdz_raw[:4] == b"PK\x03\x04":
        fname_len, extra_len = struct.unpack("<HH", usdz_raw[26:30])
        first_data_offset = 30 + fname_len + extra_len
        usdz_ok = (first_data_offset % 64 == 0) and (b"UsdPreviewSurface" in usdz_raw) and (b'subdivisionScheme = "none"' in usdz_raw)

check(
    "TC-PY11: Apple AR Quick Look (.usdz) 64-byte ZIP alignment & UsdPreviewSurface",
    usdz_ok
)

# TC-PY12: Bilingual Vietnamese & English archetype resolution + new archetypes
arch_ok = (
    engine._resolve_archetype("tu-giay-luu-tru", "Tủ giày 3 cánh DeSpace") == "shoe_cabinet"
    and engine._resolve_archetype("tu-quan-ao", "Tủ quần áo 2 cánh") == "wardrobe"
    and engine._resolve_archetype("ke-sach-tu-trung-bay", "Kệ sách gỗ sồi") == "bookcase"
    and engine._resolve_archetype("ghe-don", "Ghế bành Bắc Âu") == "chair"
)
check(
    "TC-PY12: Bilingual VI/EN furniture archetype detection (shoe_cabinet, wardrobe, bookcase)",
    arch_ok
)

# TC-PY13: Cloud AI GLB Physical Dimension Calibration (unit cube -> exact WxDxH meters at Y=0)
unit_glb = Path(e2e_res["glb_path"]).read_bytes()
cal_glb, cal_verts, cal_faces = ReconstructionEngine._calibrate_glb_scale(unit_glb, 1.14, 0.33, 0.76)
cal_json_len = struct.unpack_from("<I", cal_glb, 12)[0]
cal_gltf = json.loads(cal_glb[20 : 20 + cal_json_len].decode("utf-8"))
root_node = cal_gltf["nodes"][cal_gltf["scenes"][0]["nodes"][0]]
check(
    "TC-PY13: Cloud AI GLB scale calibration wraps DeSpace_PhysicalScaleRoot with valid 4-byte GLB padding",
    cal_glb[:4] == b"glTF"
    and cal_json_len % 4 == 0
    and root_node.get("name") == "DeSpace_PhysicalScaleRoot"
    and cal_verts > 0
)

# TC-PY14: Articulated Hollow Interior + Hinged Doors & Sliding Drawers GLB
art_res = engine.reconstruct(
    product_id=89,
    sku="DESPACE-ARTICULATED-CABINET",
    name="Tủ giày 3 cánh kèm 2 ngăn kéo DeSpace Studio",
    category="shoe-racks-storage",
    dimensions={"width": 114, "depth": 34, "height": 118},
    angles={},
)
art_glb = Path(art_res["glb_path"]).read_bytes()
art_jlen = struct.unpack_from("<I", art_glb, 12)[0]
art_gltf = json.loads(art_glb[20 : 20 + art_jlen].decode("utf-8"))
art_nodes = art_gltf.get("nodes", [])
interactive_nodes = [nd for nd in art_nodes if (nd.get("extras") or {}).get("despaceInteractive")]
hinge_doors = [nd for nd in interactive_nodes if nd["extras"].get("type") == "hinge_door"]
drawers = [nd for nd in interactive_nodes if nd["extras"].get("type") == "drawer"]
has_openall_anim = any(a.get("name") == "DeSpace_OpenAll" for a in art_gltf.get("animations", []))
check(
    "TC-PY14: Articulated cabinet GLB builds hollow carcass + 3 hinged doors + 2 drawers + DeSpace_OpenAll animation",
    len(hinge_doors) == 3 and len(drawers) == 2 and has_openall_anim and art_res.get("interactive_parts") == 5
)

# TC-PY15: Barn Door + 4 Drawers Sideboard Articulation
barn_res = engine.reconstruct(
    product_id=90,
    sku="DESPACE-BARN-TEST",
    name="Tủ Sideboard 4 ngăn kéo kèm cánh lùa Barn Door DeSpace Avenbury",
    category="chest-of-drawers-sideboards",
    dimensions={"width": 102, "depth": 40, "height": 85},
    angles={},
)
barn_glb = Path(barn_res["glb_path"]).read_bytes()
barn_jlen = struct.unpack_from("<I", barn_glb, 12)[0]
barn_gltf = json.loads(barn_glb[20 : 20 + barn_jlen].decode("utf-8"))
barn_interactive = [nd for nd in barn_gltf.get("nodes", []) if (nd.get("extras") or {}).get("despaceInteractive")]
slide_doors = [nd for nd in barn_interactive if nd["extras"].get("type") == "slide_door"]
barn_drawers = [nd for nd in barn_interactive if nd["extras"].get("type") == "drawer"]
check(
    "TC-PY15: Sideboard Barn Door routes to chest_of_drawers with 1 sliding barn door + 4 stacked drawers",
    len(slide_doors) == 1 and len(barn_drawers) == 4 and barn_res.get("interactive_parts") == 5
)

# TC-PY16: Strict 0-drawer adherence & no phantom drawers on 3-door cabinets / open bookcases
zero_drawer_res = engine.reconstruct(
    product_id=91,
    sku="DESPACE-ZERO-DRAWER-TEST",
    name="Tủ giày 3 cánh DeSpace Metro",
    category="shoe-racks-storage",
    dimensions={"width": 114, "depth": 33, "height": 76},
    angles={},
    description="3 Cánh cửa | 4 Ngăn kệ | 0 Ngăn kéo",
)
book_zero_res = engine.reconstruct(
    product_id=92,
    sku="DESPACE-OPEN-SHELF-TEST",
    name="Kệ sách 4 tầng DeSpace Nova",
    category="bookshelves",
    dimensions={"width": 75, "depth": 30, "height": 145},
    angles={},
    description="0 Cánh cửa | 4 Ngăn kệ | 0 Ngăn kéo",
)
check(
    "TC-PY16: Strict spec adherence produces 3 doors / 0 drawers and open shelf / 0 phantom drawers",
    zero_drawer_res.get("interactive_parts") == 3 and book_zero_res.get("interactive_parts") == 0
)

# TC-PY17: Local HTTP server enforces X-DeSpace-Desktop-Key auth & rejects wildcard CORS
auth_port = 58321
auth_received = []
orig_key = Config.API_SECRET_KEY
Config.API_SECRET_KEY = "test-local-server-secret-key-2026"
auth_srv = DesktopLocalServer(port=auth_port, task_callback=lambda t: auth_received.append(t))
auth_srv.start()
time.sleep(0.25)

unauth_status = 0
auth_status = 0
evil_cors_header = None
try:
    bad_req = urllib.request.Request(
        f"http://127.0.0.1:{auth_port}/api/task",
        data=json.dumps({"job_uuid": "UNAUTH-1"}).encode("utf-8"),
        headers={"Content-Type": "application/json", "Origin": "https://evil.example"},
        method="POST",
    )
    try:
        urllib.request.urlopen(bad_req, timeout=3)
    except urllib.error.HTTPError as he:
        unauth_status = he.code
        evil_cors_header = he.headers.get("Access-Control-Allow-Origin")

    good_req = urllib.request.Request(
        f"http://127.0.0.1:{auth_port}/api/task",
        data=json.dumps({"job_uuid": "AUTH-1"}).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "X-DeSpace-Desktop-Key": "test-local-server-secret-key-2026",
        },
        method="POST",
    )
    with urllib.request.urlopen(good_req, timeout=3) as ok_resp:
        auth_status = ok_resp.status
    time.sleep(0.15)
finally:
    auth_srv.stop()
    Config.API_SECRET_KEY = orig_key
    _tmp_dir_ctx.cleanup()

check(
    "TC-PY17: Local HTTP server rejects unauth POST (401), blocks evil CORS & accepts valid key (200)",
    unauth_status == 401 and evil_cors_header is None and auth_status == 200 and len(auth_received) == 1
)

# TC-PY18: Door-only cabinets/wardrobes never generate phantom drawers; Vault places hidden drawer centered
with tempfile.TemporaryDirectory(prefix="despace_tc18_") as tc18_dir:
    tc18_eng = ReconstructionEngine(output_dir=tc18_dir)
    cases = [
        ("AXG-STR-003", "shoe-racks-storage", "Tủ giày 3 cánh DeSpace Slate", {"width": 114, "depth": 34, "height": 88}, "3 Cánh cửa | 3 Ngăn kệ | 0 Ngăn kéo", 3, 0),
        ("AXG-STR-005", "shoe-racks-storage", "Tủ giày 2 cánh Farmhouse Willowbrook", {"width": 115, "depth": 35, "height": 95}, "2 Cánh cửa | 3 Ngăn kệ | 0 Ngăn kéo", 2, 0),
        ("AXG-STR-019", "wardrobes", "Tủ quần áo 4 cánh DeSpace Tower", {"width": 120, "depth": 47, "height": 185}, "4 Cánh cửa | 6 Ngăn kệ | 0 Ngăn kéo", 4, 0),
        ("AXG-STR-021", "wardrobes", "Tủ quần áo 3 cánh DeSpace Wardura", {"width": 115, "depth": 45, "height": 182}, "3 Cánh cửa | 5 Ngăn kệ | 0 Ngăn kéo", 3, 0),
        ("AXG-STR-023", "wardrobes", "Tủ quần áo 2 cánh có ngăn kéo ẩn bảo mật DeSpace Vault", {"width": 80, "depth": 48, "height": 180}, "2 Cánh cửa | 4 Ngăn kệ | 1 Ngăn kéo ẩn bảo mật", 2, 1),
    ]
    tc18_ok = True
    for sku, cat, nm, dims, desc, exp_doors, exp_drawers in cases:
        parts = tc18_eng._build_articulated_parts(cat, nm, dims["width"]/100.0, dims["depth"]/100.0, dims["height"]/100.0, description=desc)
        n_doors = sum(1 for pt in parts if pt["name"].startswith("DESPACE_DOOR_"))
        n_drawers = sum(1 for pt in parts if pt["name"].startswith("DESPACE_DRAWER_"))
        if n_doors != exp_doors or n_drawers != exp_drawers:
            tc18_ok = False
check(
    "TC-PY18: Door-only cabinets/wardrobes (003/005/019/021) have 0 phantom drawers; Vault (023) keeps 1 inner drawer",
    tc18_ok
)

pct = round((passed / total) * 100, 1) if total else 0.0
print("=" * 72)
print(f"RESULT: {passed}/{total} PYTHON CORE TESTS PASSED ({pct}% Success Rate)")
print("=" * 72)

if passed != total:
    sys.exit(1)
