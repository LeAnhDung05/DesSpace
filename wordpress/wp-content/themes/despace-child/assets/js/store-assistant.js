(function () {
	"use strict";

	var toggle = document.getElementById("ax-chat-toggle");
	var panel = document.getElementById("ax-chat-panel");
	var closeButton = panel && panel.querySelector(".ax-chat-close");
	var form = document.getElementById("ax-chat-form");
	var input = document.getElementById("ax-chat-input");
	var messages = document.getElementById("ax-chat-messages");
	var catalogueNode = document.getElementById("axgnud-chat-catalogue");
	var english = /^en/i.test(document.documentElement.lang || "");
	var products = [];

	if (!toggle || !panel || !form || !input || !messages || !catalogueNode) return;

	try {
		products = JSON.parse(catalogueNode.textContent || "[]");
	} catch (error) {
		products = [];
	}

	var categoryTerms = {
		chairs: ["chair", "chairs", "ghe", "ghe tua", "ghe an", "ghe banh"],
		tables: ["table", "tables", "ban", "ban an", "ban tra", "ban lam viec"],
		sofas: ["sofa", "sofas", "couch", "ghe sofa", "ghe banh"],
		beds: ["bed", "beds", "giuong", "giuong ngu"],
		lighting: ["light", "lighting", "lamp", "den", "den ban", "den thả"],
		decoration: ["decor", "decoration", "trang tri", "binh gom", "tranh"]
	};

	function normalize(value) {
		return String(value || "").toLocaleLowerCase()
			.normalize("NFD")
			.replace(/[\u0300-\u036f]/g, "")
			.replace(/đ/g, "d")
			.replace(/[^a-z0-9$ ]/g, " ")
			.replace(/\s+/g, " ")
			.trim();
	}

	function addMessage(text, role) {
		var message = document.createElement("div");
		message.className = "ax-chat-message ax-chat-message--" + role;
		message.textContent = text;
		messages.appendChild(message);
		messages.scrollTop = messages.scrollHeight;
		return message;
	}

	function addProductSuggestions(parent, items) {
		if (!items.length) return;
		var list = document.createElement("div");
		list.className = "ax-chat-products";

		items.forEach(function (product) {
			var link = document.createElement("a");
			link.className = "ax-chat-product";
			link.href = product.url;

			if (product.image) {
				var image = document.createElement("img");
				image.src = product.image;
				image.alt = "";
				image.loading = "lazy";
				link.appendChild(image);
			}

			var details = document.createElement("span");
			details.className = "ax-chat-product__details";
			var name = document.createElement("strong");
			name.textContent = product.name;
			var price = document.createElement("small");
			price.textContent = product.price;
			details.appendChild(name);
			details.appendChild(price);
			link.appendChild(details);
			var arrow = document.createElement("span");
			arrow.className = "ax-chat-product__arrow";
			arrow.setAttribute("aria-hidden", "true");
			arrow.textContent = "↗";
			link.appendChild(arrow);
			list.appendChild(link);
		});

		parent.appendChild(list);
	}

	function matchedCategory(query) {
		var categories = Object.keys(categoryTerms);
		for (var i = 0; i < categories.length; i++) {
			var slug = categories[i];
			if (categoryTerms[slug].some(function (term) { return query.indexOf(normalize(term)) !== -1; })) return slug;
		}
		return "";
	}

	function findProducts(rawQuery) {
		var query = normalize(rawQuery);
		var category = matchedCategory(query);
		var pool = category ? products.filter(function (product) {
			return product.categories.indexOf(category) !== -1;
		}) : products;
		var tokens = query.split(" ").filter(function (token) { return token.length > 2; });

		var exact = pool.filter(function (product) {
			var haystack = normalize(product.name);
			return tokens.some(function (token) { return haystack.indexOf(token) !== -1; });
		});
		return (exact.length ? exact : pool).slice(0, 3);
	}

	function respond(rawQuery) {
		var query = normalize(rawQuery);
		var hasGreeting = /^(hi|hello|xin chao|chao|alo|hey)( |$)/.test(query);
		var isDelivery = /(delivery|shipping|giao hang|van chuyen|phi ship)/.test(query);
		var isPayment = /(payment|pay|thanh toan|tra tien|cod|chuyen khoan)/.test(query);
		var isReturn = /(return|refund|doi tra|hoan tien)/.test(query);
		var hasCategory = !!matchedCategory(query);
		var suggestions = findProducts(rawQuery);
		var answer;

		if (hasGreeting) {
			answer = english
				? "Hello! I can help you browse sofas, chairs, tables, beds, lighting and decor. What are you looking for?"
				: "Xin chào! Mình có thể gợi ý sofa, ghế, bàn, giường, đèn và đồ trang trí. Bạn đang tìm món nào?";
		} else if (isDelivery) {
			answer = english
				? "This is a catalogue demo. The checkout page shows the sample delivery options configured for this project."
				: "Đây là website demo. Trang thanh toán hiển thị các lựa chọn giao hàng mẫu đang cấu hình cho bài tập.";
		} else if (isPayment) {
			answer = english
				? "The demo checkout currently offers Cash on Delivery and Bank Transfer. No live card gateway is connected."
				: "Trang thanh toán demo hiện có Thanh toán khi nhận hàng và Chuyển khoản. Chưa kết nối cổng thẻ trực tuyến.";
		} else if (isReturn) {
			answer = english
				? "Please check the sample Shipping & Returns page for this demo, or contact the shop owner through the links beside this assistant."
				: "Bạn xem trang Giao hàng & Đổi trả mẫu của bài demo, hoặc liên hệ chủ shop qua các liên kết bên cạnh trợ lý.";
		} else if (hasCategory && suggestions.length) {
			answer = english
				? "Here are a few popular pieces in that collection:"
				: "Mình tìm thấy một vài sản phẩm nổi bật trong danh mục này:";
		} else if (suggestions.length) {
			answer = english
				? "These catalogue items look like a good place to start:"
				: "Bạn có thể tham khảo một vài sản phẩm phù hợp trong danh mục:";
		} else {
			answer = english
				? "I could not find a matching item. Try a category such as sofa, chair, table, bed, lamp or decor."
				: "Mình chưa tìm thấy sản phẩm phù hợp. Hãy thử tìm sofa, ghế, bàn, giường, đèn hoặc đồ trang trí nhé.";
		}

		var reply = addMessage(answer, "assistant");
		if (!isDelivery && !isPayment && !isReturn && !hasGreeting) addProductSuggestions(reply, suggestions);
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
	form.addEventListener("submit", function (event) {
		event.preventDefault();
		var question = input.value.trim();
		if (!question) return;
		addMessage(question, "user");
		input.value = "";
		respond(question);
		input.focus();
	});
	document.querySelectorAll("[data-chat-prompt]").forEach(function (button) {
		button.addEventListener("click", function () {
			var question = button.getAttribute("data-chat-prompt") || "";
			if (!question) return;
			addMessage(question, "user");
			respond(question);
		});
	});
})();
