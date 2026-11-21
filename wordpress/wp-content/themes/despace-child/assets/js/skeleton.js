/**
 * DeSpace Unified Skeleton Screen Engine
 *
 * Provides consistent, zero-CLS, compositor-friendly skeleton screens across:
 *  1. Progressive image / media placeholders (all storefront cards & galleries)
 *  2. Shop & Category AJAX filter + Load More product grids
 *  3. REST Catalog API grids (despace-catalog.js)
 *  4. Off-canvas Mini-Cart Drawer loading & updates
 *  5. Grounded AI Interior Concierge response & suggestion cards
 *  6. Smart route-aware navigation progress bar & delayed skeleton overlay
 */
(function (window, document) {
	"use strict";

	var MEDIA_CONTAINER_SELECTORS = [
		".f-card-media",
		".ds-card__thumb",
		".fv-room-card__media",
		".fv-journal-card__media",
		".fv-bento-card__media",
		".f-post-img",
		".ax-gallery-main",
		".ax-thumb",
		".ax-cart-card__img",
		".ax-wishlist-card__media",
		".f-drawer-item-img",
		".f-shop-hero-media"
	].join(", ");

	function isEnglish() {
		var lang = (document.documentElement.lang || (window.furnivaData && window.furnivaData.language) || "vi");
		return /^en/i.test(lang);
	}

	function escapeHtml(str) {
		return String(str == null ? "" : str)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	/* ------------------------------------------------------------------
	   1. Accessible Live Region Helper
	   ------------------------------------------------------------------ */
	var liveRegion = null;
	function ensureLiveRegion() {
		if (liveRegion && document.body.contains(liveRegion)) return liveRegion;
		liveRegion = document.createElement("div");
		liveRegion.className = "ds-sk-sr-only";
		liveRegion.setAttribute("role", "status");
		liveRegion.setAttribute("aria-live", "polite");
		liveRegion.setAttribute("aria-atomic", "true");
		document.body.appendChild(liveRegion);
		return liveRegion;
	}

	function announce(text) {
		if (!document.body || !text) return;
		var region = ensureLiveRegion();
		region.textContent = "";
		window.requestAnimationFrame(function () {
			region.textContent = text;
		});
	}

	/* ------------------------------------------------------------------
	   2. Skeleton Templates (Product Cards, Mini-Cart, AI Chat, Routes)
	   ------------------------------------------------------------------ */
	function renderProductCardItem(index, extraClass) {
		var cls = "ds-sk-card f-skeleton-card" + (extraClass ? " " + extraClass : "");
		return (
			'<li class="' + cls + '" style="--ds-sk-i:' + index + '" aria-hidden="true">' +
				'<div class="ds-sk-card__media">' +
					'<span class="ds-sk-bone ds-sk-card__img"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill ds-sk-card__badge"></span>' +
					'<span class="ds-sk-card__actions">' +
						'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--circle ds-sk-card__action-dot"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--circle ds-sk-card__action-dot"></span>' +
					'</span>' +
				'</div>' +
				'<div class="ds-sk-card__body">' +
					'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-card__cat"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-card__title"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-card__title-sub"></span>' +
					'<div class="ds-sk-card__rating">' +
						'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-card__stars"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-card__reviews"></span>' +
					'</div>' +
					'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill ds-sk-card__price"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-card__cta"></span>' +
				'</div>' +
			'</li>'
		);
	}

	function renderProductItems(count, extraClass) {
		var n = Math.max(1, parseInt(count, 10) || 4);
		var html = "";
		for (var i = 0; i < n; i++) {
			html += renderProductCardItem(i, extraClass);
		}
		return html;
	}

	function renderProductGrid(options) {
		options = options || {};
		var count = Math.max(1, parseInt(options.count, 10) || 8);
		var cols = String(options.cols || "4") === "3" ? "columns-3" : "columns-4";
		var restMode = Boolean(options.restMode);
		var loadingText = isEnglish() ? "Loading furniture collection…" : "Đang tải danh sách sản phẩm…";

		if (restMode) {
			return (
				'<ul class="ds-product-grid ds-product-grid--skeleton ds-sk-product-grid" role="status" aria-label="' + escapeHtml(loadingText) + '">' +
					renderProductItems(count) +
				'</ul>'
			);
		}

		return (
			'<ul class="f-products ' + cols + ' f-skeleton-grid ds-sk-product-grid" role="status" aria-label="' + escapeHtml(loadingText) + '">' +
				renderProductItems(count) +
			'</ul>'
		);
	}

	function renderCartDrawer(itemCount) {
		var count = Math.max(1, parseInt(itemCount, 10) || 3);
		var loadingText = isEnglish() ? "Updating shopping cart…" : "Đang cập nhật giỏ hàng…";
		var itemsHtml = "";
		for (var i = 0; i < count; i++) {
			itemsHtml +=
				'<div class="ds-sk-drawer__item" style="--ds-sk-i:' + i + '" aria-hidden="true">' +
					'<span class="ds-sk-bone ds-sk-drawer__thumb"></span>' +
					'<div class="ds-sk-drawer__info">' +
						'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-drawer__title"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill ds-sk-drawer__price"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-drawer__qty"></span>' +
					'</div>' +
				'</div>';
		}

		return (
			'<div class="ds-sk-drawer" role="status" aria-label="' + escapeHtml(loadingText) + '">' +
				'<div class="ds-sk-drawer__header" aria-hidden="true">' +
					'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-drawer__heading"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--circle ds-sk-drawer__close"></span>' +
				'</div>' +
				'<div class="ds-sk-drawer__freeship" aria-hidden="true">' +
					'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-drawer__freeship-text"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill ds-sk-drawer__freeship-bar"></span>' +
				'</div>' +
				'<div class="ds-sk-drawer__list">' + itemsHtml + '</div>' +
				'<div class="ds-sk-drawer__footer" aria-hidden="true">' +
					'<div class="ds-sk-drawer__subtotal">' +
						'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-drawer__sub-label"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill ds-sk-drawer__sub-val"></span>' +
					'</div>' +
					'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill ds-sk-drawer__btn"></span>' +
				'</div>' +
			'</div>'
		);
	}

	function renderChatReply(statusText) {
		var label = statusText || (isEnglish()
			? "✦ Measuring catalog dimensions & inventory…"
			: "✦ Đang đo khớp kích thước & kiểm tra kho hàng…");

		var cards = "";
		for (var i = 0; i < 2; i++) {
			cards +=
				'<div class="ds-sk-chat__card" style="--ds-sk-i:' + i + '">' +
					'<span class="ds-sk-bone ds-sk-chat__thumb"></span>' +
					'<div class="ds-sk-chat__meta">' +
						'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-chat__name"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-chat__sub"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill ds-sk-chat__pill"></span>' +
					'</div>' +
				'</div>';
		}

		return (
			'<div class="ds-sk-chat" aria-hidden="true">' +
				'<div class="ds-sk-chat__status">' + escapeHtml(label) + '</div>' +
				'<div class="ds-sk-chat__lines">' +
					'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-chat__line ds-sk-chat__line--1"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-chat__line ds-sk-chat__line--2"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--pill ds-sk-chat__line ds-sk-chat__line--3"></span>' +
				'</div>' +
				'<div class="ds-sk-chat__cards">' + cards + '</div>' +
			'</div>'
		);
	}

	/* ------------------------------------------------------------------
	   3. Progressive Media Skeleton Hydration (Zero Flicker for Cached Imgs)
	   ------------------------------------------------------------------ */
	function markMediaLoaded(container) {
		container.classList.remove("ds-media-loading");
		container.classList.add("ds-media-loaded");
	}

	function hydrateMedia(root) {
		var scope = root && root.querySelectorAll ? root : document;
		var containers = scope.querySelectorAll(MEDIA_CONTAINER_SELECTORS);

		containers.forEach(function (container) {
			if (container.dataset.dsSkBound === "1") return;
			var img = container.querySelector("img:not(.f-card-img--hover)");
			if (!img) return;
			container.dataset.dsSkBound = "1";

			// Cached or already decoded images appear immediately with zero flicker
			if (img.complete && img.naturalWidth > 0) {
				container.classList.add("ds-media-loaded");
				return;
			}

			container.classList.add("ds-media-loading");

			var finished = false;
			var done = function () {
				if (finished) return;
				finished = true;
				markMediaLoaded(container);
			};

			img.addEventListener("load", done, { once: true });
			img.addEventListener("error", done, { once: true });

			if (typeof img.decode === "function") {
				img.decode().then(done).catch(function () {
					// Wait for native load/error or safety timeout
				});
			}

			// Safety guard: never allow an image to remain hidden indefinitely
			window.setTimeout(done, 4500);
		});
	}

	/* ------------------------------------------------------------------
	   4. Smart Route-Aware Navigation Progress & Delayed Skeleton Overlay
	   ------------------------------------------------------------------ */
	var navProgressEl = null;
	var routeOverlayEl = null;
	var routeTimer = null;

	function classifyRoute(urlObj, linkEl) {
		if (linkEl && linkEl.getAttribute) {
			var explicit = linkEl.getAttribute("data-skeleton-route");
			if (explicit) return explicit;
		}

		// Strip optional TranslatePress language prefix (/en/, /vi/)
		var rawPath = (urlObj.pathname || "/").toLowerCase();
		var path = rawPath.replace(/^\/(en|vi)(?=\/|$)/, "") || "/";

		// 1. Homepage (unless carrying search query)
		if (path === "/" || path === "") {
			if (urlObj.searchParams && urlObj.searchParams.has("s")) return "shop";
			return "home";
		}

		// 2. Product detail page
		if (/^\/(product|san-pham)\/[^/]+/.test(path)) return "pdp";

		// 3. Shop, categories, tags, search
		if (/^\/(shop|cua-hang|product-category|danh-muc-san-pham|product-tag)\b/.test(path)) return "shop";

		// 4. Cart & Checkout
		if (/^\/(cart|gio-hang)\b/.test(path)) return "cart";
		if (/^\/(checkout|thanh-toan)\b/.test(path)) return "checkout";

		// 5. Wishlist & Account
		if (/^\/(wishlist|yeu-thich|danh-sach-yeu-thich)\b/.test(path)) return "wishlist";
		if (/^\/(my-account|tai-khoan)\b/.test(path)) return "account";

		// 6. About & Contact
		if (/^\/(about|ve-despace|gioi-thieu)\b/.test(path)) return "about";
		if (/^\/(contact|lien-he)\b/.test(path)) return "contact";

		// 7. Journal index / archive
		if (/^\/(blog|tap-chi|category|tag)\b/.test(path)) return "journal";

		// 8. Policy / FAQ / Shipping pages
		if (/^\/(faqs?|cau-hoi-thuong-gap|shipping-returns|giao-hang-doi-tra|privacy-policy|chinh-sach)\b/.test(path)) return "info";

		// 9. Context-aware detection from clicked DOM element
		if (linkEl && linkEl.closest) {
			if (linkEl.closest(".f-card, .ds-card, .fv-mega-product-card, .ax-chat-product")) return "pdp";
			if (linkEl.closest(".f-post-card, .f-featured-post, .fv-journal-card, .fv-home-journal, .f-blog-sidebar")) return "article";
		}

		// Single WordPress blog post (e.g. /chon-tu-giay-cho-can-ho-chung-cu/)
		if (/^\/[a-z0-9-]+\/?$/.test(path)) return "article";

		return "info";
	}

	function renderRouteSkeletonMarkup(type) {
		if (type === "home") {
			var chips = "";
			for (var c = 0; c < 5; c++) {
				chips += '<span class="ds-sk-bone ds-sk-bone--pill" style="width:148px;height:44px;flex:0 0 auto;--ds-sk-delay:' + (c * 55) + 'ms"></span>';
			}
			var promises = "";
			for (var pr = 0; pr < 4; pr++) {
				promises += '<span class="ds-sk-bone ds-sk-bone--md" style="height:82px;--ds-sk-delay:' + (pr * 60) + 'ms"></span>';
			}
			return (
				'<div class="ds-route-skeleton__wrap">' +
					'<div class="ds-route-sk-home__concierge">' +
						'<div style="display:flex;flex-direction:column;gap:8px;flex:1">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:160px;height:12px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:320px;max-width:90%;height:26px"></span>' +
						'</div>' +
						'<span class="ds-sk-bone ds-sk-bone--pill" style="width:360px;max-width:45%;height:46px"></span>' +
					'</div>' +
					'<div class="ds-route-sk-home__chips">' + chips + '</div>' +
					'<div class="ds-route-sk-home__hero">' +
						'<span class="ds-sk-bone ds-route-sk-home__stage"></span>' +
						'<div class="ds-route-sk-home__aside">' +
							'<span class="ds-sk-bone ds-route-sk-home__aside-card" style="--ds-sk-delay:90ms"></span>' +
							'<span class="ds-sk-bone ds-route-sk-home__aside-card" style="--ds-sk-delay:160ms"></span>' +
						'</div>' +
					'</div>' +
					'<div class="ds-route-sk-home__promise">' + promises + '</div>' +
					'<ul class="ds-route-sk-grid">' + renderProductItems(4) + '</ul>' +
				'</div>'
			);
		}

		if (type === "pdp") {
			return (
				'<div class="ds-route-skeleton__wrap">' +
					'<span class="ds-sk-bone ds-sk-bone--pill" style="width:240px;height:12px;margin-bottom:22px"></span>' +
					'<div class="ds-route-sk-pdp">' +
						'<div class="ds-route-sk-pdp__gallery">' +
							'<span class="ds-sk-bone ds-route-sk-pdp__main"></span>' +
							'<div class="ds-route-sk-pdp__thumbs">' +
								'<span class="ds-sk-bone ds-route-sk-pdp__thumb"></span>' +
								'<span class="ds-sk-bone ds-route-sk-pdp__thumb" style="--ds-sk-delay:60ms"></span>' +
								'<span class="ds-sk-bone ds-route-sk-pdp__thumb" style="--ds-sk-delay:120ms"></span>' +
								'<span class="ds-sk-bone ds-route-sk-pdp__thumb" style="--ds-sk-delay:180ms"></span>' +
							'</div>' +
						'</div>' +
						'<div class="ds-route-sk-pdp__info">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:32%;height:13px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:88%;height:32px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:62%;height:24px"></span>' +
							'<div style="display:flex;gap:10px;align-items:center">' +
								'<span class="ds-sk-bone ds-sk-bone--pill" style="width:94px;height:14px"></span>' +
								'<span class="ds-sk-bone ds-sk-bone--pill" style="width:64px;height:14px"></span>' +
							'</div>' +
							'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill" style="width:42%;height:28px;margin-top:4px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:78px;margin-top:6px"></span>' +
							'<div style="display:flex;gap:10px;margin-top:4px">' +
								'<span class="ds-sk-bone ds-sk-bone--circle" style="width:34px;height:34px"></span>' +
								'<span class="ds-sk-bone ds-sk-bone--circle" style="width:34px;height:34px;--ds-sk-delay:60ms"></span>' +
								'<span class="ds-sk-bone ds-sk-bone--circle" style="width:34px;height:34px;--ds-sk-delay:120ms"></span>' +
							'</div>' +
							'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill" style="width:100%;height:48px;margin-top:10px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:100%;height:46px"></span>' +
						'</div>' +
					'</div>' +
				'</div>'
			);
		}

		if (type === "journal") {
			var pills = "";
			for (var j = 0; j < 5; j++) {
				pills += '<span class="ds-sk-bone ds-sk-bone--pill" style="width:' + (92 + (j % 3) * 24) + 'px;height:34px;--ds-sk-delay:' + (j * 55) + 'ms"></span>';
			}
			var postCards = "";
			for (var p = 0; p < 3; p++) {
				postCards +=
					'<div class="ds-sk-card" style="--ds-sk-i:' + p + '">' +
						'<span class="ds-sk-bone" style="width:100%;aspect-ratio:16/10;border-radius:0"></span>' +
						'<div class="ds-sk-card__body">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:36%;height:11px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:90%;height:18px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:70%;height:14px"></span>' +
						'</div>' +
					'</div>';
			}
			return (
				'<div class="ds-route-skeleton__wrap">' +
					'<span class="ds-sk-bone ds-sk-bone--pill" style="width:110px;height:12px;margin-bottom:10px"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--pill" style="width:260px;height:34px;margin-bottom:16px"></span>' +
					'<div class="ds-route-sk-journal__pills">' + pills + '</div>' +
					'<div class="ds-route-sk-journal__feat">' +
						'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;aspect-ratio:16/10"></span>' +
						'<div style="display:flex;flex-direction:column;gap:14px;justify-content:center">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:34%;height:13px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:92%;height:28px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:74%;height:24px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:86%;height:14px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:65%;height:14px"></span>' +
						'</div>' +
					'</div>' +
					'<div class="ds-route-sk-journal__grid">' + postCards + '</div>' +
				'</div>'
			);
		}

		if (type === "article") {
			return (
				'<div class="ds-route-skeleton__wrap">' +
					'<div class="ds-route-sk-article">' +
						'<div class="ds-route-sk-article__head">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:128px;height:24px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:84%;height:34px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:58%;height:28px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:220px;height:13px;margin-top:4px"></span>' +
						'</div>' +
						'<span class="ds-sk-bone ds-route-sk-article__cover"></span>' +
						'<div class="ds-route-sk-article__prose">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:100%;height:14px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:96%;height:14px;--ds-sk-delay:50ms"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:88%;height:14px;--ds-sk-delay:100ms"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:92%;height:14px;--ds-sk-delay:150ms;margin-top:10px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:74%;height:14px;--ds-sk-delay:200ms"></span>' +
						'</div>' +
					'</div>' +
				'</div>'
			);
		}

		if (type === "about") {
			var stats = "";
			for (var s = 0; s < 4; s++) {
				stats +=
					'<div class="ds-route-sk-about__stat" style="--ds-sk-delay:' + (s * 70) + 'ms">' +
						'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill" style="width:54%;height:28px"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--pill" style="width:82%;height:13px"></span>' +
					'</div>';
			}
			return (
				'<div class="ds-route-skeleton__wrap">' +
					'<div class="ds-route-sk-about__hero">' +
						'<div style="display:flex;flex-direction:column;gap:14px">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:140px;height:13px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:88%;height:36px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:68%;height:32px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:94%;height:14px;margin-top:6px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:86%;height:14px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:72%;height:14px"></span>' +
						'</div>' +
						'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;aspect-ratio:4/3"></span>' +
					'</div>' +
					'<div class="ds-route-sk-about__stats">' + stats + '</div>' +
					'<div class="ds-route-sk-about__hero">' +
						'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:220px"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:220px;--ds-sk-delay:90ms"></span>' +
					'</div>' +
				'</div>'
			);
		}

		if (type === "contact") {
			return (
				'<div class="ds-route-skeleton__wrap">' +
					'<span class="ds-sk-bone ds-sk-bone--pill" style="width:120px;height:12px;margin-bottom:10px"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--pill" style="width:280px;height:34px;margin-bottom:26px"></span>' +
					'<div class="ds-route-sk-contact">' +
						'<div class="ds-route-sk-panel">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:55%;height:22px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:68px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:68px;--ds-sk-delay:70ms"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:68px;--ds-sk-delay:140ms"></span>' +
							'<div style="display:flex;gap:12px;margin-top:6px">' +
								'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill" style="width:124px;height:40px"></span>' +
								'<span class="ds-sk-bone ds-sk-bone--pill" style="width:124px;height:40px"></span>' +
							'</div>' +
						'</div>' +
						'<div class="ds-route-sk-panel">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:48%;height:22px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:46px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:46px;--ds-sk-delay:60ms"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:124px;--ds-sk-delay:120ms"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill" style="width:180px;height:46px"></span>' +
						'</div>' +
					'</div>' +
				'</div>'
			);
		}

		if (type === "cart") {
			var cartRows = "";
			for (var r = 0; r < 3; r++) {
				cartRows +=
					'<div class="ds-route-sk-cart-row" style="--ds-sk-delay:' + (r * 75) + 'ms">' +
						'<span class="ds-sk-bone ds-sk-bone--md" style="width:96px;height:96px"></span>' +
						'<div style="display:flex;flex-direction:column;gap:10px">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:76%;height:17px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:44%;height:13px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:108px;height:32px"></span>' +
						'</div>' +
						'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill" style="width:96px;height:20px"></span>' +
					'</div>';
			}
			return (
				'<div class="ds-route-skeleton__wrap">' +
					'<span class="ds-sk-bone ds-sk-bone--pill" style="width:240px;height:32px;margin-bottom:18px"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:56px;margin-bottom:24px"></span>' +
					'<div class="ds-route-sk-split-order">' +
						'<div style="display:flex;flex-direction:column;gap:14px">' + cartRows + '</div>' +
						'<div class="ds-route-sk-panel">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:52%;height:20px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:100%;height:14px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:100%;height:14px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:44px;margin-top:4px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill" style="width:100%;height:48px;margin-top:8px"></span>' +
						'</div>' +
					'</div>' +
				'</div>'
			);
		}

		if (type === "checkout") {
			return (
				'<div class="ds-route-skeleton__wrap">' +
					'<span class="ds-sk-bone ds-sk-bone--pill" style="width:220px;height:32px;margin-bottom:24px"></span>' +
					'<div class="ds-route-sk-split-order">' +
						'<div class="ds-route-sk-panel">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:42%;height:20px"></span>' +
							'<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">' +
								'<span class="ds-sk-bone ds-sk-bone--md" style="height:46px"></span>' +
								'<span class="ds-sk-bone ds-sk-bone--md" style="height:46px;--ds-sk-delay:50ms"></span>' +
							'</div>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:46px;--ds-sk-delay:90ms"></span>' +
							'<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">' +
								'<span class="ds-sk-bone ds-sk-bone--md" style="height:46px;--ds-sk-delay:120ms"></span>' +
								'<span class="ds-sk-bone ds-sk-bone--md" style="height:46px;--ds-sk-delay:150ms"></span>' +
							'</div>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:38%;height:18px;margin-top:10px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:64px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:64px;--ds-sk-delay:80ms"></span>' +
						'</div>' +
						'<div class="ds-route-sk-panel">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:54%;height:20px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:68px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:68px;--ds-sk-delay:70ms"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:100%;height:16px;margin-top:8px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill" style="width:100%;height:50px;margin-top:8px"></span>' +
						'</div>' +
					'</div>' +
				'</div>'
			);
		}

		if (type === "wishlist") {
			return (
				'<div class="ds-route-skeleton__wrap">' +
					'<div class="ds-route-sk-shop__head">' +
						'<div style="display:flex;flex-direction:column;gap:8px">' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:130px;height:12px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:260px;height:32px"></span>' +
						'</div>' +
						'<span class="ds-sk-bone ds-sk-bone--pill" style="width:136px;height:40px"></span>' +
					'</div>' +
					'<ul class="ds-route-sk-wishlist-grid">' + renderProductItems(6) + '</ul>' +
				'</div>'
			);
		}

		if (type === "account") {
			var navItems = "";
			for (var n = 0; n < 5; n++) {
				navItems += '<span class="ds-sk-bone ds-sk-bone--pill" style="width:100%;height:40px;--ds-sk-delay:' + (n * 50) + 'ms"></span>';
			}
			return (
				'<div class="ds-route-skeleton__wrap">' +
					'<div class="ds-route-sk-account">' +
						'<div class="ds-route-sk-panel">' +
							'<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">' +
								'<span class="ds-sk-bone ds-sk-bone--circle" style="width:46px;height:46px;flex:0 0 auto"></span>' +
								'<div style="flex:1;display:flex;flex-direction:column;gap:6px">' +
									'<span class="ds-sk-bone ds-sk-bone--pill" style="width:76%;height:14px"></span>' +
									'<span class="ds-sk-bone ds-sk-bone--pill" style="width:52%;height:11px"></span>' +
								'</div>' +
							'</div>' +
							navItems +
						'</div>' +
						'<div>' +
							'<div class="ds-route-sk-account__kpis">' +
								'<span class="ds-sk-bone ds-sk-bone--md" style="height:96px"></span>' +
								'<span class="ds-sk-bone ds-sk-bone--md" style="height:96px;--ds-sk-delay:70ms"></span>' +
								'<span class="ds-sk-bone ds-sk-bone--md" style="height:96px;--ds-sk-delay:140ms"></span>' +
							'</div>' +
							'<div class="ds-route-sk-panel">' +
								'<span class="ds-sk-bone ds-sk-bone--pill" style="width:36%;height:20px"></span>' +
								'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:56px"></span>' +
								'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:56px;--ds-sk-delay:60ms"></span>' +
								'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:56px;--ds-sk-delay:120ms"></span>' +
							'</div>' +
						'</div>' +
					'</div>' +
				'</div>'
			);
		}

		if (type === "info") {
			var rows = "";
			for (var q = 0; q < 5; q++) {
				rows += '<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:62px;--ds-sk-delay:' + (q * 60) + 'ms"></span>';
			}
			return (
				'<div class="ds-route-skeleton__wrap">' +
					'<div class="ds-route-sk-info">' +
						'<span class="ds-sk-bone ds-sk-bone--pill" style="width:130px;height:13px"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--pill" style="width:360px;max-width:85%;height:34px;margin-bottom:8px"></span>' +
						rows +
					'</div>' +
				'</div>'
			);
		}

		// Default: Shop / Category / Search grid
		var shopCats = "";
		for (var sc = 0; sc < 5; sc++) {
			shopCats += '<span class="ds-sk-bone ds-sk-bone--md" style="height:64px;--ds-sk-delay:' + (sc * 50) + 'ms"></span>';
		}
		return (
			'<div class="ds-route-skeleton__wrap">' +
				'<div class="ds-route-sk-shop__cats">' + shopCats + '</div>' +
				'<div class="ds-route-sk-shop__head">' +
					'<span class="ds-sk-bone ds-sk-bone--pill" style="width:220px;height:28px"></span>' +
					'<span class="ds-sk-bone ds-sk-bone--pill" style="width:160px;height:36px"></span>' +
				'</div>' +
				'<div class="ds-route-sk-shop__layout">' +
					'<div class="ds-route-sk-sidebar">' +
						'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:180px"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:140px;--ds-sk-delay:90ms"></span>' +
						'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:120px;--ds-sk-delay:180ms"></span>' +
					'</div>' +
					'<ul class="ds-route-sk-grid">' + renderProductItems(8) + '</ul>' +
				'</div>' +
			'</div>'
		);
	}

	function ensureNavElements() {
		if (!navProgressEl) {
			navProgressEl = document.createElement("div");
			navProgressEl.className = "ds-nav-progress";
			navProgressEl.setAttribute("aria-hidden", "true");
			navProgressEl.innerHTML = '<div class="ds-nav-progress__bar"></div>';
			document.body.appendChild(navProgressEl);
		}
		if (!routeOverlayEl) {
			routeOverlayEl = document.createElement("div");
			routeOverlayEl.className = "ds-route-skeleton";
			routeOverlayEl.setAttribute("aria-hidden", "true");
			document.body.appendChild(routeOverlayEl);
		}
	}

	function clearNavigationSkeleton() {
		if (routeTimer) {
			window.clearTimeout(routeTimer);
			routeTimer = null;
		}
		if (navProgressEl) navProgressEl.classList.remove("is-active");
		if (routeOverlayEl) {
			routeOverlayEl.classList.remove("is-visible");
			routeOverlayEl.innerHTML = "";
		}
	}

	var safetyClearTimer = null;

	function triggerRoutePreview(routeType) {
		ensureNavElements();
		var header = document.querySelector(".fv-header");
		var topOffset = header ? Math.max(56, Math.round(header.getBoundingClientRect().bottom)) : 76;
		routeOverlayEl.style.top = topOffset + "px";
		routeOverlayEl.innerHTML = renderRouteSkeletonMarkup(routeType || "shop");
		navProgressEl.classList.add("is-active");
		window.requestAnimationFrame(function () {
			routeOverlayEl.classList.add("is-visible");
		});
		if (safetyClearTimer) window.clearTimeout(safetyClearTimer);
		safetyClearTimer = window.setTimeout(clearNavigationSkeleton, 7000);
	}

	function initNavigationSkeleton() {
		document.addEventListener("click", function (event) {
			if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}
			var link = event.target && event.target.closest ? event.target.closest("a[href]") : null;
			if (!link) return;

			// Skip non-navigation anchors, downloads, cart/wishlist AJAX triggers, or external links
			if (
				link.hasAttribute("download") ||
				(link.target && link.target !== "_self") ||
				link.classList.contains("ajax_add_to_cart") ||
				link.classList.contains("add_to_wishlist") ||
				link.classList.contains("f-cart-btn") ||
				link.classList.contains("f-copy-coupon") ||
				link.classList.contains("ax-open-3d-btn")
			) {
				return;
			}

			var rawHref = link.getAttribute("href") || "";
			if (!rawHref || rawHref.charAt(0) === "#" || /^(mailto:|tel:|javascript:)/i.test(rawHref)) {
				return;
			}

			var urlObj;
			try {
				urlObj = new URL(link.href, window.location.origin);
			} catch (e) {
				return;
			}

			if (urlObj.origin !== window.location.origin) return;
			if (urlObj.pathname === window.location.pathname && urlObj.search === window.location.search) return;
			if (/\/wp-admin|\/wp-login/.test(urlObj.pathname)) return;
			if (urlObj.searchParams.has("add-to-cart") || urlObj.searchParams.has("remove_item")) return;

			ensureNavElements();
			navProgressEl.classList.add("is-active");

			// Reveal tailored destination-page skeleton after 85ms (zero flash on instant cached/bfcache restores)
			var routeType = classifyRoute(urlObj, link);
			if (routeTimer) window.clearTimeout(routeTimer);
			routeTimer = window.setTimeout(function () {
				triggerRoutePreview(routeType);
			}, 85);
		}, false);

		// Restore cleanly when returning via browser Back/Forward Cache (bfcache) or pressing Escape
		window.addEventListener("pageshow", clearNavigationSkeleton);
		document.addEventListener("keydown", function (e) {
			if (e.key === "Escape") clearNavigationSkeleton();
		});
		window.addEventListener("pagehide", function () {
			if (routeTimer) window.clearTimeout(routeTimer);
			if (safetyClearTimer) window.clearTimeout(safetyClearTimer);
		});
	}

	/* ------------------------------------------------------------------
	   5. Auto-Observe Dynamic AJAX Containers
	   ------------------------------------------------------------------ */
	function observeDynamicContainers() {
		if (!("MutationObserver" in window)) return;
		var targets = ["#furniva-shop-results", "#furniva-results", "#furniva-drawer-content", "#ax-chat-messages", ".ds-woocommerce-content"];
		var pendingTargets = new Set();
		var rafPending = false;
		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				if (m.addedNodes && m.addedNodes.length) {
					pendingTargets.add(m.target);
				}
			});
			if (pendingTargets.size && !rafPending) {
				rafPending = true;
				window.requestAnimationFrame(function () {
					pendingTargets.forEach(function (t) { hydrateMedia(t); });
					pendingTargets.clear();
					rafPending = false;
				});
			}
		});

		targets.forEach(function (sel) {
			var el = document.querySelector(sel);
			if (el) observer.observe(el, { childList: true, subtree: true });
		});
	}

	function init() {
		hydrateMedia(document);
		initNavigationSkeleton();
		observeDynamicContainers();
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}

	window.DeSpaceSkeleton = {
		renderProductGrid: renderProductGrid,
		renderProductItems: renderProductItems,
		renderCartDrawer: renderCartDrawer,
		renderChatReply: renderChatReply,
		hydrateMedia: hydrateMedia,
		announce: announce,
		classifyRoute: classifyRoute,
		previewRoute: triggerRoutePreview,
		clearRoute: clearNavigationSkeleton
	};
})(window, document);
