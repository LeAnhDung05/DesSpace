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

	function classifyRoute(urlObj) {
		var path = (urlObj.pathname || "/").toLowerCase();
		if (/\/product\/[^/]+/.test(path) || /\/san-pham\/[^/]+/.test(path)) return "pdp";
		if (/\/(shop|cua-hang|product-category|danh-muc-san-pham)/.test(path)) return "shop";
		if (/\/(blog|tap-chi|category|tag)\b/.test(path)) return "journal";
		return "shop";
	}

	function renderRouteSkeletonMarkup(type) {
		if (type === "pdp") {
			return (
				'<div class="ds-route-skeleton__wrap">' +
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
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:62%;height:26px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill" style="width:42%;height:28px;margin-top:6px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--md" style="width:100%;height:76px;margin-top:8px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill" style="width:100%;height:48px;margin-top:10px"></span>' +
							'<span class="ds-sk-bone ds-sk-bone--pill" style="width:100%;height:46px"></span>' +
						'</div>' +
					'</div>' +
				'</div>'
			);
		}

		if (type === "journal") {
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

		return (
			'<div class="ds-route-skeleton__wrap">' +
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

			// Only reveal full route skeleton overlay if navigation takes > 200ms (zero flash on instant loads)
			var routeType = classifyRoute(urlObj);
			if (routeTimer) window.clearTimeout(routeTimer);
			routeTimer = window.setTimeout(function () {
				triggerRoutePreview(routeType);
			}, 200);
		}, false);

		// Restore cleanly when returning via browser Back/Forward Cache (bfcache)
		window.addEventListener("pageshow", clearNavigationSkeleton);
		window.addEventListener("pagehide", function () {
			if (routeTimer) window.clearTimeout(routeTimer);
		});
	}

	/* ------------------------------------------------------------------
	   5. Auto-Observe Dynamic AJAX Containers
	   ------------------------------------------------------------------ */
	function observeDynamicContainers() {
		if (!("MutationObserver" in window)) return;
		var targets = ["#furniva-results", "#furniva-drawer-content", "#ax-chat-messages", ".ds-woocommerce-content"];
		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				if (m.addedNodes && m.addedNodes.length) {
					hydrateMedia(m.target);
				}
			});
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
		previewRoute: triggerRoutePreview,
		clearRoute: clearNavigationSkeleton
	};
})(window, document);
