#!/usr/bin/env bash
# DeSpace 3D Studio Desktop — Launcher Script
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

echo "=========================================================================="
echo "    DeSpace 3D Studio Desktop — Ứng Dụng Xây Dựng 3D & Đồng Bộ Tự Động   "
echo "=========================================================================="

cd "$ROOT_DIR"

# Check Python3
if ! command -v python3 &> /dev/null; then
    echo "Lỗi: Không tìm thấy Python 3 trên máy của bạn."
    exit 1
fi

# Run application
python3 "$SCRIPT_DIR/main.py" "$@"
