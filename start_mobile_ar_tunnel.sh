#!/usr/bin/env bash
# =============================================================================
# DeSpace Luxury — Mobile QR & HTTPS AR Tunnel Starter (Local Development Only)
# Automatically detects local Wi-Fi IP and starts a Cloudflare HTTPS Tunnel
# so mobile phones (iOS / Android) can scan the QR code from any network.
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NET_JSON="${SCRIPT_DIR}/wordpress/wp-content/uploads/despace-network.json"
DESPACE_PORT="${DESPACE_PORT:-8090}"

if ! [[ "${DESPACE_PORT}" =~ ^[0-9]+$ ]] || (( DESPACE_PORT < 1 || DESPACE_PORT > 65535 )); then
    echo "[!] Invalid DESPACE_PORT '${DESPACE_PORT}'. Must be 1..65535."
    exit 1
fi

detect_lan_ip() {
    local ip=""
    if command -v ipconfig >/dev/null 2>&1; then
        ip="$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || true)"
    fi
    if [ -z "${ip}" ] && command -v hostname >/dev/null 2>&1; then
        ip="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
    fi
    if [ -z "${ip}" ] && command -v ip >/dev/null 2>&1; then
        ip="$(ip route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -n 1 || true)"
    fi
    echo "${ip:-127.0.0.1}"
}

LAN_IP="$(detect_lan_ip)"
LAN_URL="http://${LAN_IP}:${DESPACE_PORT}"

echo "======================================================================="
echo " DeSpace 3D & AR Studio — Mobile QR Connectivity (LOCAL DEV ONLY)"
echo " Local Wi-Fi URL : ${LAN_URL}"
echo " [!] Warning: Public tunnel exposes your local dev site. Never use weak passwords."
echo "======================================================================="

if command -v cloudflared >/dev/null 2>&1; then
    LOG_FILE="$(mktemp -t despace_cloudflared.XXXXXX.log)"
    chmod 600 "${LOG_FILE}"
    pkill -f "cloudflared tunnel --url http://localhost:${DESPACE_PORT}" 2>/dev/null || true
    nohup cloudflared tunnel --url "http://localhost:${DESPACE_PORT}" --logfile "${LOG_FILE}" --no-autoupdate >/dev/null 2>&1 &
    echo "[*] Waiting for Cloudflare HTTPS tunnel..."
    TUNNEL_URL=""
    for _ in {1..20}; do
        if [ -f "${LOG_FILE}" ]; then
            TUNNEL_URL="$(grep -oE "https://[a-z0-9\-]+\.trycloudflare\.com" "${LOG_FILE}" | head -n 1 || true)"
            if [ -n "${TUNNEL_URL}" ]; then
                break
            fi
        fi
        sleep 1
    done
    cat <<EOF > "${NET_JSON}"
{
  "lan_ip": "${LAN_IP}",
  "lan_base_url": "${LAN_URL}",
  "tunnel_base_url": "${TUNNEL_URL}"
}
EOF
    if [ -n "${TUNNEL_URL}" ]; then
        echo "[✓] Public HTTPS AR URL: ${TUNNEL_URL}"
        echo "[*] To enable dynamic tunnel host support locally, set:"
        echo "    DESPACE_ENABLE_DYNAMIC_TUNNEL=1 DESPACE_TUNNEL_HOST=${TUNNEL_URL#https://}"
    else
        echo "[!] Timed out waiting for Cloudflare tunnel URL (check ${LOG_FILE}). Falling back to LAN URL."
    fi
    echo "[✓] Saved configuration to: ${NET_JSON}"
else
    cat <<EOF > "${NET_JSON}"
{
  "lan_ip": "${LAN_IP}",
  "lan_base_url": "${LAN_URL}",
  "tunnel_base_url": ""
}
EOF
    echo "[!] cloudflared not found; configured Wi-Fi LAN URL: ${LAN_URL}"
fi
