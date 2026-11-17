#!/usr/bin/env bash
# =============================================================================
# DeSpace Luxury — Mobile QR & HTTPS AR Tunnel Starter
# Automatically detects local Wi-Fi IP and starts a Cloudflare HTTPS Tunnel
# so mobile phones (iOS / Android) can scan the QR code from any network.
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NET_JSON="${SCRIPT_DIR}/wordpress/wp-content/uploads/despace-network.json"
LOG_FILE="/tmp/despace_cloudflared.log"

LAN_IP=$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || echo "192.168.1.123")
LAN_URL="http://${LAN_IP}:8090"

echo "======================================================================="
echo " DeSpace 3D & AR Studio — Mobile QR Connectivity"
echo " Local Wi-Fi URL : ${LAN_URL}"
echo "======================================================================="

if command -v cloudflared >/dev/null 2>&1; then
    pkill -f "cloudflared tunnel --url http://localhost:8090" 2>/dev/null || true
    rm -f "${LOG_FILE}"
    nohup cloudflared tunnel --url http://localhost:8090 --logfile "${LOG_FILE}" --no-autoupdate >/dev/null 2>&1 &
    echo "[*] Waiting for Cloudflare HTTPS tunnel..."
    TUNNEL_URL=""
    for _ in {1..20}; do
        if [ -f "${LOG_FILE}" ]; then
            TUNNEL_URL=$(grep -oE "https://[a-z0-9\-]+\.trycloudflare\.com" "${LOG_FILE}" | head -n 1 || true)
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
    echo "[✓] Public HTTPS AR URL: ${TUNNEL_URL}"
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
