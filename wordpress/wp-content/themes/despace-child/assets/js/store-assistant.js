(function () {
	"use strict";

	var toggle = document.getElementById("ax-chat-toggle");
	var panel = document.getElementById("ax-chat-panel");
	var closeButton = panel && panel.querySelector(".ax-chat-close");
	var form = document.getElementById("ax-chat-form");
	var input = document.getElementById("ax-chat-input");
	var messages = document.getElementById("ax-chat-messages");
	var catalogueNode = document.getElementById("despace-chat-catalogue") || document.getElementById("axgnud-chat-catalogue");
	var english = /^en/i.test(document.documentElement.lang || "");
	var products = [];

	if (!toggle || !panel || !form || !input || !messages || !catalogueNode) return;

	try {
		products = JSON.parse(catalogueNode.textContent || "[]");
	} catch (error) {
		products = [];
	}

	var categoryTerms = {
		"shoe-racks-storage": ["shoe", "shoes", "tu giay", "ke giay", "metro", "slate", "skyline", "minimal"],
		"wardrobes": ["wardrobe", "wardrobes", "tu quan ao", "tu ao", "tower", "wardura", "nexa", "vault"],
		"bookshelves": ["bookshelf", "bookshelves", "ke sach", "tu sach", "tu trung bay", "nova", "shelvo", "aura", "grace"],
		"kitchen-cabinets": ["kitchen", "pantry", "tu bep", "tu do kho", "axis", "spice", "thyme"],
		"chest-of-drawers-sideboards": ["drawer", "drawers", "sideboard", "dresser", "tu ngan keo", "iris", "sera", "avenbury", "regal"]
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
				? "Hello! I can help you browse shoe cabinets, wardrobes, bookshelves, kitchen cabinets, and sideboards. What are you looking for?"
				: "Xin chào! Mình có thể gợi ý tủ giày, tủ quần áo, kệ sách, tủ bếp và tủ ngăn kéo DeSpace. Bạn đang tìm mẫu nào?";
		} else if (isDelivery) {
			answer = english
				? "DeSpace offers nationwide insured delivery and white-glove setup. Orders over 5,000,000 VND qualify for complimentary shipping."
				: "DeSpace giao hàng và lắp đặt tận nơi toàn quốc. Miễn phí vận chuyển cho đơn hàng từ 5.000.000 ₫.";
		} else if (isPayment) {
			answer = english
				? "DeSpace supports Cash on Delivery (COD) and VietQR Bank Transfer (BACS) with instant order confirmation."
				: "DeSpace hỗ trợ Thanh toán khi nhận hàng (COD) và Chuyển khoản ngân hàng qua mã VietQR.";
		} else if (isReturn) {
			answer = english
				? "All DeSpace pieces include a 5-year structural warranty and 30-day return guarantee. Reach our team via Hotline/Zalo 0332 174 590."
				: "Tất cả sản phẩm DeSpace được bảo hành kết cấu 5 năm và hỗ trợ đổi trả trong 30 ngày. Liên hệ Hotline/Zalo 0332 174 590 để được hỗ trợ.";
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
				? "I could not find a matching item. Try searching for shoe cabinet, wardrobe, bookshelf, kitchen cabinet, or drawers."
				: "Mình chưa tìm thấy sản phẩm phù hợp. Hãy thử tìm tủ giày, tủ quần áo, kệ sách, tủ bếp hoặc tủ ngăn kéo nhé.";
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
