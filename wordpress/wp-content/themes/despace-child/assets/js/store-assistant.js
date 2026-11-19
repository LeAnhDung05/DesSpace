/**
 * DeSpace Grounded AI Interior Design Concierge (Frontend Client)
 *
 * Connects the storefront assistant dock to `POST /wp-json/despace/v1/concierge/chat`
 * (Gemini 2.5 Flash RAG + deterministic 1:1 Spatial Fit Engine), renders real-scale
 * product cards with dimensions (`W × D × H cm`), and wires inline `🧊 Xem 3D / AR`
 * buttons directly into the DeSpace 3D & Live Camera AR Studio modal.
 */
(function () {
	"use strict";

	var toggle = document.getElementById("ax-chat-toggle");
	var panel = document.getElementById("ax-chat-panel");
	var closeButton = panel && panel.querySelector(".ax-chat-close");
	var form = document.getElementById("ax-chat-form");
	var input = document.getElementById("ax-chat-input");
	var messages = document.getElementById("ax-chat-messages");

	if (!toggle || !panel || !form || !input || !messages) return;

	var config = window.furnivaData || window.despaceData || {};
	var restBase = (config.restUrl || "/wp-json/despace/v1").replace(/\/+$/, "");
	var english = /^en/i.test(document.documentElement.lang || config.language || "");
	var isSending = false;
	var lastCompositionEndAt = null;

	function addMessage(text, role) {
		var message = document.createElement("div");
		message.className = "ax-chat-message ax-chat-message--" + role;
		message.textContent = text;
		messages.appendChild(message);
		messages.scrollTop = messages.scrollHeight;
		return message;
	}

	function createTypingBubble() {
		var bubble = document.createElement("div");
		bubble.className = "ax-chat-message ax-chat-message--assistant ax-chat-typing";
		// Hide transient loading text from aria-live="polite" log so screen readers only announce final answer
		bubble.setAttribute("aria-hidden", "true");
		var statusLabel = english
			? "✦ Measuring catalog dimensions & inventory…"
			: "✦ Đang đo khớp kích thước & kiểm tra kho hàng…";
		if (window.DeSpaceSkeleton && typeof window.DeSpaceSkeleton.renderChatReply === "function") {
			bubble.innerHTML = window.DeSpaceSkeleton.renderChatReply(statusLabel);
		} else {
			bubble.textContent = statusLabel;
		}
		messages.appendChild(bubble);
		messages.scrollTop = messages.scrollHeight;
		return bubble;
	}

	function addProductSuggestions(parent, items) {
		if (!Array.isArray(items) || !items.length) return;
		var list = document.createElement("div");
		list.className = "ax-chat-products";

		items.forEach(function (product) {
			var card = document.createElement("div");
			card.className = "ax-chat-product-card";

			var link = document.createElement("a");
			link.className = "ax-chat-product";
			link.href = product.url || "#";

			if (product.image) {
				var image = document.createElement("img");
				image.src = product.image;
				image.alt = "";
				image.loading = "lazy";
				link.appendChild(image);
			}

			var details = document.createElement("span");
			details.className = "ax-chat-product__details";

			var displayTitle = (english && product.name_en) ? product.name_en : (product.name || "");
			var name = document.createElement("strong");
			name.textContent = displayTitle;
			details.appendChild(name);

			var metaRow = document.createElement("span");
			metaRow.className = "ax-chat-product__meta";

			var price = document.createElement("small");
			price.textContent = product.price || "";
			metaRow.appendChild(price);

			if (product.dimensions) {
				var dims = document.createElement("span");
				dims.className = "ax-chat-product__dims";
				dims.textContent = product.dimensions;
				metaRow.appendChild(dims);
			}

			details.appendChild(metaRow);
			link.appendChild(details);

			var arrow = document.createElement("span");
			arrow.className = "ax-chat-product__arrow";
			arrow.setAttribute("aria-hidden", "true");
			arrow.textContent = "↗";
			link.appendChild(arrow);

			card.appendChild(link);

			if (product.has_3d && product.glb_url) {
				var btn3d = document.createElement("button");
				btn3d.type = "button";
				btn3d.className = "ax-chat-3d-btn ax-open-3d-btn";
				btn3d.setAttribute("data-product-id", String(product.id || 0));
				btn3d.setAttribute("data-product-name", displayTitle);
				btn3d.setAttribute("data-product-price", product.price || "");
				btn3d.setAttribute("data-product-raw-price", String(product.price_num || ""));
				btn3d.setAttribute("data-product-image", product.image || "");
				btn3d.setAttribute("data-product-dimensions", product.dimensions || "");
				btn3d.setAttribute("data-glb-url", product.glb_url || "");
				btn3d.setAttribute("data-usdz-url", product.usdz_url || "");
				btn3d.setAttribute("data-has-3d", "yes");
				btn3d.setAttribute("data-ar-enabled", product.ar_enabled ? "yes" : "no");
				var labelText = english ? "🧊 Preview 1:1 3D / AR" : "🧊 Xem 3D / Đặt thử AR 1:1";
				btn3d.textContent = labelText;
				btn3d.setAttribute("aria-label", labelText + " — " + displayTitle);
				card.appendChild(btn3d);
			}

			list.appendChild(card);
		});

		parent.appendChild(list);
		messages.scrollTop = messages.scrollHeight;
	}

	function sendQuestion(question) {
		if (!question || isSending) return;
		isSending = true;
		var sendBtn = form.querySelector(".ax-chat-send");
		if (sendBtn) sendBtn.disabled = true;
		messages.setAttribute("aria-busy", "true");

		var typingBubble = createTypingBubble();

		window.fetch(restBase + "/concierge/chat", {
			method: "POST",
			credentials: "same-origin",
			headers: {
				"Content-Type": "application/json"
			},
			body: JSON.stringify({
				message: question,
				language: english ? "en" : "vi"
			})
		})
			.then(function (res) {
				if (!res.ok) throw new Error("HTTP " + res.status);
				return res.json();
			})
			.then(function (data) {
				if (typingBubble && typingBubble.parentNode) {
					typingBubble.parentNode.removeChild(typingBubble);
				}
				var replyText = (data && data.reply)
					? data.reply
					: (english ? "Here are our matching designs:" : "Dưới đây là các mẫu phù hợp từ bộ sưu tập DeSpace:");
				var msgEl = addMessage(replyText, "assistant");
				if (data && Array.isArray(data.products) && data.products.length) {
					addProductSuggestions(msgEl, data.products);
				}
			})
			.catch(function () {
				if (typingBubble && typingBubble.parentNode) {
					typingBubble.parentNode.removeChild(typingBubble);
				}
				addMessage(
					english
						? "Connection interrupted. Please try again or reach our studio directly via Zalo / Hotline 0332 174 590."
						: "Kết nối tạm gián đoạn. Vui lòng thử lại hoặc liên hệ nhanh qua Zalo / Hotline 0332 174 590 nhé.",
					"assistant"
				);
			})
			.finally(function () {
				isSending = false;
				if (sendBtn) sendBtn.disabled = false;
				messages.removeAttribute("aria-busy");
			});
	}

	function setOpen(open) {
		toggle.setAttribute("aria-expanded", open ? "true" : "false");
		panel.setAttribute("aria-hidden", open ? "false" : "true");
		if (open) {
			panel.hidden = false;
			window.requestAnimationFrame(function () { panel.classList.add("is-open"); });
			window.setTimeout(function () { input.focus(); }, 40);
		} else {
			panel.classList.remove("is-open");
			window.setTimeout(function () {
				if (!panel.classList.contains("is-open")) panel.hidden = true;
			}, 220);
			toggle.focus();
		}
	}

	toggle.addEventListener("click", function () {
		setOpen(toggle.getAttribute("aria-expanded") !== "true");
	});
	if (closeButton) closeButton.addEventListener("click", function () { setOpen(false); });
	panel.addEventListener("keydown", function (event) {
		if (event.key === "Escape") setOpen(false);
	});

	// IME-safe Enter handling (Vietnamese Telex/VNI & Safari compositionend WebKit bug guard)
	input.addEventListener("compositionend", function (event) {
		lastCompositionEndAt = event.timeStamp;
	});
	input.addEventListener("keydown", function (event) {
		if (event.key === "Enter") {
			event.preventDefault();
			if (event.isComposing || event.keyCode === 229) {
				return;
			}
			if (lastCompositionEndAt !== null && Math.abs(event.timeStamp - lastCompositionEndAt) < 50) {
				return;
			}
			if (typeof form.requestSubmit === "function") {
				form.requestSubmit();
			} else {
				form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
			}
		}
	});

	form.addEventListener("submit", function (event) {
		event.preventDefault();
		var question = input.value.trim();
		if (!question || isSending) return;
		addMessage(question, "user");
		input.value = "";
		sendQuestion(question);
		input.focus();
	});

	document.querySelectorAll("[data-chat-prompt]").forEach(function (button) {
		button.addEventListener("click", function () {
			var question = button.getAttribute("data-chat-prompt") || "";
			if (!question || isSending) return;
			addMessage(question, "user");
			sendQuestion(question);
		});
	});
})();
