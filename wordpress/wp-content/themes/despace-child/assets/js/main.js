/* DeSpace child theme JS: qty steppers, Buy Now, AJAX shop filter, misc UI. */
jQuery(function ($) {
	"use strict";

	/* ---------------------------------------------------------------- toast */
	function toast(msg) {
		var $t = $("#furniva-toast");
		$t.text(msg).addClass("show");
		setTimeout(function () { $t.removeClass("show"); }, 2600);
	}

	/* --------------------------------------------------------- homepage room edit */
	$(document).on("click", ".f-home-room-tab", function () {
		var $tab = $(this),
			$root = $tab.closest(".f-home-room-edit"),
			panelId = $tab.attr("aria-controls");

		$root.find(".f-home-room-tab").removeClass("is-active").attr({ "aria-selected": "false", tabindex: "-1" });
		$tab.addClass("is-active").attr({ "aria-selected": "true", tabindex: "0" });
		$root.find(".f-home-room-panel").prop("hidden", true);
		$root.find("#" + panelId).prop("hidden", false);
	});

	$(document).on("keydown", ".f-home-room-tab", function (e) {
		var keys = ["ArrowRight", "ArrowLeft", "Home", "End"];
		if (keys.indexOf(e.key) === -1) return;
		e.preventDefault();

		var $tabs = $(this).closest(".f-home-room-tabs").find(".f-home-room-tab"),
			index = $tabs.index(this),
			nextIndex = index;

		if (e.key === "ArrowRight") nextIndex = (index + 1) % $tabs.length;
		if (e.key === "ArrowLeft") nextIndex = (index - 1 + $tabs.length) % $tabs.length;
		if (e.key === "Home") nextIndex = 0;
		if (e.key === "End") nextIndex = $tabs.length - 1;
		$tabs.eq(nextIndex).trigger("focus").trigger("click");
	});

	/* ---------------------------------------------------------------- mobile nav */
	$("#furniva-burger").on("click", function () {
		var $menu = $("#furniva-mobile-nav");
		$menu.toggleClass("open");
		$(this).attr("aria-expanded", $menu.hasClass("open") ? "true" : "false");
	});

	/* ---------------------------------------------- homepage lookbook + FAQ */
	function activateLookTab($root, index) {
		var $tabs = $root.find("[data-fv-look-tab]"),
			$panels = $root.find("[data-fv-look-panel]");
		if (!$tabs.length) return;
		index = (index + $tabs.length) % $tabs.length;
		$tabs.each(function (tabIndex) {
			var active = tabIndex === index;
			$(this).attr({ "aria-selected": active ? "true" : "false", tabindex: active ? "0" : "-1" });
		});
		$panels.each(function (panelIndex) {
			var active = panelIndex === index;
			$(this).prop("hidden", !active);
			if (!active) $(this).find("[data-fv-look-spot]").attr("aria-pressed", "false");
			if (!active) $(this).find("[data-fv-look-popover]").prop("hidden", true);
		});
	}

	$(document).on("click", "[data-fv-look-tab]", function () {
		var $tab = $(this),
			$root = $tab.closest("[data-fv-lookbook]");
		activateLookTab($root, $root.find("[data-fv-look-tab]").index(this));
	});

	$(document).on("keydown", "[data-fv-look-tab]", function (e) {
		var keys = ["ArrowRight", "ArrowLeft", "Home", "End"];
		if (keys.indexOf(e.key) === -1) return;
		e.preventDefault();
		var $root = $(this).closest("[data-fv-lookbook]"),
			$tabs = $root.find("[data-fv-look-tab]"),
			index = $tabs.index(this);
		if (e.key === "ArrowRight") index++;
		if (e.key === "ArrowLeft") index--;
		if (e.key === "Home") index = 0;
		if (e.key === "End") index = $tabs.length - 1;
		$tabs.eq((index + $tabs.length) % $tabs.length).trigger("focus").trigger("click");
	});

	$(document).on("click", "[data-fv-look-step]", function () {
		var $root = $(this).closest("[data-fv-lookbook]"),
			$tabs = $root.find("[data-fv-look-tab]"),
			current = $tabs.index($tabs.filter('[aria-selected="true"]')),
			next = current + parseInt($(this).attr("data-fv-look-step"), 10);
		activateLookTab($root, next);
		$tabs.eq((next + $tabs.length) % $tabs.length).trigger("focus");
	});

	$(document).on("click", "[data-fv-look-spot]", function () {
		var $button = $(this),
			$stage = $button.closest(".fv-look-stage"),
			$popover = $("#" + $button.attr("aria-controls")),
			willOpen = $popover.prop("hidden");
		$stage.find("[data-fv-look-spot]").attr("aria-pressed", "false");
		$stage.find("[data-fv-look-popover]").prop("hidden", true);
		if (willOpen) {
			$button.attr("aria-pressed", "true");
			$popover.prop("hidden", false);
		}
	});

	$(document).on("click", "[data-fv-faq-trigger]", function () {
		var $button = $(this),
			$section = $button.closest(".fv-faq"),
			wasOpen = $button.attr("aria-expanded") === "true";
		$section.find("[data-fv-faq-trigger]").each(function () {
			var $other = $(this),
				$answer = $("#" + $other.attr("aria-controls")),
				active = this === $button[0] ? !wasOpen : false;
			$other.attr("aria-expanded", active ? "true" : "false");
			$answer.prop("hidden", !active);
			$other.find(".fv-faq__toggle").text(active ? "−" : "+");
		});
	});

	/* ---------------------------------------------------------- storefront search */
	function openDeSpaceSearch(e) {
		if (e && e.preventDefault) e.preventDefault();
		var $headerBar = $(".fv-header__bar");
		var $searchBar = $("#fv-header-search-bar");

		if ($headerBar.length && $searchBar.length) {
			$headerBar.addClass("is-search-active");
			$searchBar.attr("aria-hidden", "false");
			setTimeout(function () {
				$searchBar.find(".fv-search-bar__input").trigger("focus");
			}, 100);
			return;
		}

		var dialog = document.getElementById("furniva-search-dialog");
		if (dialog) {
			if (!dialog.open && typeof dialog.showModal === "function") dialog.showModal();
			$(".fv-header").addClass("is-search-open");
			setTimeout(function () {
				$(dialog).find("input[type=search], .dgwt-wcas-search-input").first().trigger("focus");
			}, 80);
		} else {
			window.location.href = (window.furnivaData && furnivaData.shopUrl) || "/shop/";
		}
	}

	function closeDeSpaceSearch() {
		var $headerBar = $(".fv-header__bar");
		var $searchBar = $("#fv-header-search-bar");
		$headerBar.removeClass("is-search-active");
		$searchBar.attr("aria-hidden", "true");
		$(".fv-header").removeClass("is-search-open");

		var dialog = document.getElementById("furniva-search-dialog");
		if (dialog && dialog.open) dialog.close();
	}

	$(document).on("click", "#furniva-bnav-search, [data-furniva-search-open]", openDeSpaceSearch);
	$(document).on("click", "[data-furniva-search-close]", closeDeSpaceSearch);

	// Close on Escape key
	$(document).on("keydown", function (e) {
		if (e.key === "Escape" && $(".fv-header__bar").hasClass("is-search-active")) {
			closeDeSpaceSearch();
		}
	});

	// Close search bar if clicking outside header bar
	$(document).on("click", function (e) {
		if ($(".fv-header__bar").hasClass("is-search-active")) {
			if (!$(e.target).closest(".fv-header__bar, #furniva-bnav-search, [data-furniva-search-open]").length) {
				closeDeSpaceSearch();
			}
		}
	});

	$("#furniva-search-dialog").on("close cancel", function () {
		$(".fv-header").removeClass("is-search-open");
	});
	$("#furniva-search-dialog").on("click", function (e) {
		if (e.target === this) closeDeSpaceSearch();
	});

	$("#dgwt-wcas-search-input-1, .dgwt-wcas-search-input").attr({
		placeholder: furnivaData.strings.searchPlaceholder,
		"aria-label": furnivaData.strings.searchPlaceholder
	});
	$(".dgwt-wcas-search-wrapp label.screen-reader-text").text(furnivaData.strings.searchPlaceholder);
	$(".dgwt-wcas-search-submit").attr({
		"aria-label": furnivaData.strings.searchButton,
		title: furnivaData.strings.searchButton
	});

	/* ---------------------------------------------------------------- newsletter (demo) */
	$("#furniva-newsletter").on("submit", function (e) {
		e.preventDefault();
		var email = $(this).find("input[type=email]").val();
		if (!email) return;
		$(this).find("input").val("");
		$(".f-news-ok").fadeIn();
		toast(furnivaData.strings.newsletterSuccess);
	});

	/* ---------------------------------------------------------------- contact form (demo) */
	$(document).on("submit", "#furniva-contact-form", function (e) {
		e.preventDefault();
		var s = furnivaData.strings;
		$(this).replaceWith('<div class="f-contact-success" role="status"><span>' + s.contactPreview + '</span><h3>' + s.contactThanks + '</h3><p>' + s.contactNotSent + '</p></div>');
	});

	/* ---------------------------------------------------------------- quantity stepper (single product) */
	$(document).on("click", ".f-qty-plus, .f-qty-minus", function () {
		var $input = $(this).closest("form").find("input.qty");
		if (!$input.length) $input = $(this).siblings("input.qty");
		var step = parseFloat($input.attr("step")) || 1,
			min = parseFloat($input.attr("min")) || 0,
			max = parseFloat($input.attr("max")),
			val = parseFloat($input.val()) || 0,
			next = $(this).hasClass("f-qty-plus") ? val + step : val - step;

		if (isNaN(next)) next = min;
		if (!isNaN(min) && next < min) next = min;
		if (!isNaN(max) && next > max) next = max;
		$input.val(next).trigger("change");
	});

	/* ---------------------------------------------------------------- Buy Now */
	$(document).on("click", "#furniva-buy-now", function (e) {
		e.preventDefault();
		var $form = $("form.cart"),
			$btn = $(this);
		if (!$form.length) return;
		$btn.prop("disabled", true).text(furnivaData.strings.adding);

		var data = $form.serializeArray();
		var pid = $form.find('button[name="add-to-cart"]').val() || $form.attr("data-product_id") || "";
		if (pid) data.push({ name: "add-to-cart", value: pid });
		data.push({ name: "action", value: "furniva_buy_now" });
		data.push({ name: "language", value: furnivaData.language });

		$.post(furnivaData.ajaxUrl, $.param(data)).done(function (res) {
			$btn.prop("disabled", false).text(furnivaData.strings.buyNow);
			if (res && res.success) {
				window.location.href = furnivaData.checkoutUrl;
			} else {
				toast((res && res.data) || furnivaData.strings.couldNotAdd);
			}
		}).fail(function () {
			$btn.prop("disabled", false).text(furnivaData.strings.buyNow);
			toast(furnivaData.strings.connectionError);
		});
	});

	/* ---------------------------------------------------------------- AJAX shop filter */
	var filterTimer = null,
		fetching = false,
		currentPage = 1,
		baseCat = (window.furnivaData && furnivaData.baseCat) || "",
		$results = $("#furniva-shop-results"),
		$count = $("#furniva-result-count");

	function collectFilters() {
		var f = {
			action: "furniva_filter",
			nonce: furnivaData.nonce,
			category: [],
			brand: [],
			color: [],
			size: [],
			availability: []
		};
		f.language = furnivaData.language;
		if (baseCat) f.base = baseCat;

		$("#furniva-filter-form input[type=checkbox]:checked").each(function () {
			var name = $(this).attr("name");
			if (f[name]) f[name].push($(this).val());
		});
		var min = $("#f-price-min").val(), max = $("#f-price-max").val();
		if (min !== "" && !isNaN(min)) f.price_min = min;
		if (max !== "" && !isNaN(max)) f.price_max = max;

		var $ordering = $("select.orderby").first();
		if ($ordering.length) f.orderby = $ordering.val();

		return f;
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	function updateActiveFilterTags() {
		var $bar = $("#furniva-active-filters"),
			$list = $bar.find(".f-active-tags-list"),
			tags = [],
			count = 0;

		$("#furniva-filter-form input[type=checkbox]:checked").each(function () {
			count++;
			var groupName = escapeHtml($(this).attr("name")),
				val = escapeHtml($(this).val()),
				label = $(this).data("name") ? escapeHtml($(this).data("name")) : escapeHtml($(this).parent().text().trim().replace(/\(\d+\)$/, "").trim());
			var capGroup = furnivaData.strings.filterGroups[groupName] || groupName;
			tags.push('<button type="button" class="f-active-tag" data-filter-type="checkbox" data-name="' + groupName + '" data-val="' + val + '">' +
				'<span class="f-active-tag-group">' + capGroup + ':</span> ' +
				'<span class="f-active-tag-val">' + label + '</span>' +
				'<span class="f-active-tag-x">&times;</span>' +
			'</button>');
		});

		var min = $("#f-price-min").val(), max = $("#f-price-max").val();
		if ((min !== "" && !isNaN(min)) || (max !== "" && !isNaN(max))) {
			count++;
			var priceLabel = "$" + escapeHtml(min || "0") + " – $" + escapeHtml(max || "∞");
			tags.push('<button type="button" class="f-active-tag" data-filter-type="price">' +
				'<span class="f-active-tag-group">' + escapeHtml(furnivaData.strings.priceLabel) + ':</span> ' +
				'<span class="f-active-tag-val">' + priceLabel + '</span>' +
				'<span class="f-active-tag-x">&times;</span>' +
			'</button>');
		}

		if (count > 0) {
			$list.html(tags.join(""));
			$bar.show();
			$("#furniva-filter-badge").text(count).show();
		} else {
			$list.empty();
			$bar.hide();
			$("#furniva-filter-badge").text("0").hide();
		}
	}

	function applyGridCols(cols) {
		cols = (cols == "3") ? "3" : "4";
		try { localStorage.setItem("furniva_grid_cols", cols); } catch (e) {}
		$(".f-grid-btn").each(function () {
			$(this).toggleClass("is-active", $(this).data("cols") == cols);
		});
		var $grid = $results.find("ul.f-products");
		if (cols === "3") {
			$grid.removeClass("columns-4").addClass("columns-3");
		} else {
			$grid.removeClass("columns-3").addClass("columns-4");
		}
	}

	$(document).on("click", ".f-grid-btn", function () {
		applyGridCols($(this).data("cols"));
	});

	function runFilter(append, skipHistory) {
		if (fetching) return;
		fetching = true;
		currentPage = append ? currentPage : 1;

		var data = collectFilters();
		if (append) data.paged = currentPage;

		updateActiveFilterTags();
		$results.addClass("is-busy");

		var currentCols = "4";
		try { currentCols = localStorage.getItem("furniva_grid_cols") || "4"; } catch (e) {}

		if (!append) {
			var colClass = (currentCols == "3") ? "columns-3" : "columns-4";
			var skHtml = '<ul class="f-products ' + colClass + ' f-skeleton-grid">';
			for (var i = 0; i < 8; i++) {
				skHtml += '<li class="f-skeleton-card">' +
					'<div class="f-sk-media f-shimmer"></div>' +
					'<div class="f-sk-body">' +
					'<div class="f-sk-cat f-shimmer"></div>' +
					'<div class="f-sk-title f-shimmer"></div>' +
					'<div class="f-sk-price f-shimmer"></div>' +
					'</div></li>';
			}
			skHtml += '</ul>';
			$results.html(skHtml);
		}

		$.post(furnivaData.ajaxUrl, $.param(data)).done(function (res) {
			if (!res || !res.success) return;
			if (!append) $results.html(res.data.grid);
			$("#furniva-load-more").remove();
			if (res.data.more && res.data.next) {
				currentPage = res.data.next - 1;
				$results.append('<div style="text-align:center;padding:34px 0 8px"><button type="button" class="f-btn f-btn--ghost" id="furniva-load-more">' + furnivaData.strings.loadMore + '</button></div>');
			}
			$count.text(res.data.text);
			if (res.data.range) {
				$count.append(" · <span style='opacity:.7'>" + res.data.range + "</span>");
			}

			// preserve grid columns selection on dynamic content
			applyGridCols(currentCols);

			// sync URL (shareable filter state with pushState for back/forward navigation)
			var params = {};
			$.each(collectFilters(), function (k, v) {
				if (typeof v === "object" && v.length) params[k] = v.join(",");
				else if (k.indexOf("price") === 0) params[k] = v;
			});
			var qs = $.param(params);
			var targetUrl = window.location.pathname + (qs ? "?" + qs : "");
			if (window.location.search !== (qs ? "?" + qs : "")) {
				if (!skipHistory) {
					history.pushState({ furnivaFilter: true }, "", targetUrl);
				} else {
					history.replaceState({ furnivaFilter: true }, "", targetUrl);
				}
			}
		}).always(function () {
			fetching = false;
			$results.removeClass("is-busy");
		});
	}

	// active tag click → remove specific filter
	$(document).on("click", ".f-active-tag", function (e) {
		e.preventDefault();
		var type = $(this).data("filter-type");
		if (type === "price") {
			$("#f-price-min, #f-price-max").val("");
		} else {
			var name = $(this).data("name"), val = $(this).data("val");
			$('#furniva-filter-form input[name="' + name + '"][value="' + val + '"]').prop("checked", false);
		}
		runFilter(false);
	});

	// instant filtering on checkbox change, debounce for price typing
	$(document).on("change", "#furniva-filter-form input[type=checkbox]", function () {
		runFilter(false);
	});
	$(document).on("input", "#f-price-min, #f-price-max", function () {
		clearTimeout(filterTimer);
		filterTimer = setTimeout(function () { runFilter(false); }, 600);
	});
	$("#furniva-filter-apply").on("click", function () { runFilter(false); });
	$(document).on("click", "#furniva-filter-clear, #furniva-filter-clear-top, #furniva-clear-all-tags, #furniva-no-results-reset", function () {
		$("#furniva-filter-form input[type=checkbox]").prop("checked", false);
		$("#f-price-min, #f-price-max").val("");
		runFilter(false);
	});

	// re-filter when the sort dropdown changes (AJAX too)
	$(document).on("change", "select.orderby", function () {
		if ($("#furniva-filter-form").length) {
			runFilter(false);
		}
	});

	// load more
	$(document).on("click", "#furniva-load-more", function () {
		currentPage += 1;
		runFilter(true);
	});

	// helper to restore filters from URL query parameters
	function restoreFiltersFromUrl() {
		var qs = new URLSearchParams(window.location.search);
		$("#furniva-filter-form input[type=checkbox]").prop("checked", false);
		$("#f-price-min, #f-price-max").val("");
		$.each(["category", "brand", "color", "size", "availability"], function (i, key) {
			var v = qs.get(key);
			if (v) {
				v.split(",").forEach(function (slug) {
					$('#furniva-filter-form input[name="' + key + '"][value="' + slug + '"]').prop("checked", true);
				});
			}
		});
		if (qs.get("price_min")) $("#f-price-min").val(qs.get("price_min"));
		if (qs.get("price_max")) $("#f-price-max").val(qs.get("price_max"));
	}

	// Browser back/forward navigation support
	window.addEventListener("popstate", function (e) {
		if ($("#furniva-filter-form").length) {
			restoreFiltersFromUrl();
			runFilter(false, true); // true = skipHistory
		}
	});

	// restore filters from URL on load
	if (window.location.search && $("#furniva-filter-form").length) {
		restoreFiltersFromUrl();
	}

	// initial load state
	if ($("#furniva-filter-form").length) {
		updateActiveFilterTags();
		try {
			var savedCols = localStorage.getItem("furniva_grid_cols");
			if (savedCols) applyGridCols(savedCols);
		} catch (e) {}
	}

	/* ---------------------------------------------------------------- filter toggle (mobile off-canvas) */
	function openFilterDrawer() {
		$("#furniva-filters").addClass("open");
		$("#furniva-filters-backdrop").addClass("open");
		$("body").addClass("f-filter-drawer-open");
	}
	function closeFilterDrawer() {
		$("#furniva-filters").removeClass("open");
		$("#furniva-filters-backdrop").removeClass("open");
		$("body").removeClass("f-filter-drawer-open");
	}
	$("#furniva-filter-toggle").on("click", function () {
		if ($("#furniva-filters").hasClass("open")) {
			closeFilterDrawer();
		} else {
			openFilterDrawer();
		}
	});
	$(document).on("click", "#furniva-filter-close, #furniva-filters-backdrop", function () {
		closeFilterDrawer();
	});

	/* ---------------------------------------------------------------- variation swatches */
	var FURNIVA_HEX = {
		black: "#2e2e2e", white: "#f4f2ee", brown: "#8b5a3c", gray: "#a8a8a6",
		navy: "#34455e", beige: "#d8c6a5", natural: "#cda878", walnut: "#6e4a32",
		amber: "#d9a441", green: "#54745a", mustard: "#d1a019", pink: "#d7909c"
	};

	function enhanceSwatches() {
		$("form.variations_form").each(function () {
			var $form = $(this);
			if ($form.data("furniva-swatches-init")) return;
			$form.data("furniva-swatches-init", true);

			function updateAttributeLabels() {
				$form.find("table.variations tr").each(function () {
					var $row = $(this),
						$sel = $row.find("select"),
						val = $sel.val(),
						label = val ? ($sel.find('option[value="' + val + '"]').text().trim() || val) : "";
					var $lbl = $row.find("th.label label");
					var $valSpan = $lbl.find(".f-attr-selected-val");
					if (!$valSpan.length) {
						$valSpan = $('<span class="f-attr-selected-val"></span>');
						$lbl.append($valSpan);
					}
					if (label) {
						$valSpan.text(" : " + label).show();
					} else {
						$valSpan.text("").hide();
					}
				});
			}

			$form.find("table.variations select").each(function () {
				var $sel = $(this), attr = $sel.attr("name") || "";
				if (!attr.startsWith("attribute_")) return;
				var isColor = attr.indexOf("pa_color") !== -1;
				var $ui = $('<div class="f-swatch-ui" data-attr="' + attr + '" role="group" aria-label="' + attr.replace("attribute_", "") + '"></div>');
				$sel.find("option").each(function () {
					var val = $(this).val();
					if (!val) return;
					var label = $(this).text().trim();
					var $b = isColor
						? $('<button type="button" class="f-swatch-color-btn" title="' + label + '" data-value="' + val + '" aria-label="' + label + '"><span class="f-color-dot" style="background:' + (FURNIVA_HEX[val.toLowerCase()] || "#ddd") + '"></span></button>')
						: $('<button type="button" class="f-swatch-chip-btn" data-value="' + val + '" aria-label="' + label + '">' + label + "</button>");
					if ($sel.val() === val) $b.addClass("selected").attr("aria-pressed", "true");
					else $b.attr("aria-pressed", "false");
					$ui.append($b);
				});
				$sel.addClass("f-swatch-hidden").css({ position: "absolute", left: "-9999px", opacity: 0 }).after($ui);
			});

			updateAttributeLabels();

			// click → sync select → WooCommerce recalculates variation
			$form.on("click", ".f-swatch-ui button", function () {
				var $btn = $(this),
					attrName = $(this).closest(".f-swatch-ui").data("attr"),
					$sel = $form.find('select[name="' + attrName + '"]');
				var already = $btn.hasClass("selected");
				$form.find('.f-swatch-ui[data-attr="' + attrName + '"] button').removeClass("selected").attr("aria-pressed", "false");
				if (already) {
					$sel.val("").trigger("change");
				} else {
					$btn.addClass("selected").attr("aria-pressed", "true");
					$sel.val($btn.data("value")).trigger("change");
				}
				updateAttributeLabels();
			});

			// keep UI in sync when WooCommerce resets the form
			$form.on("reset_data", function () {
				$form.find("table.variations select").each(function () {
					var val = $(this).val();
					$form.find('.f-swatch-ui[data-attr="' + $(this).attr("name") + '"] button').each(function () {
						var isMatch = $(this).data("value") === val;
						$(this).toggleClass("selected", isMatch).attr("aria-pressed", isMatch ? "true" : "false");
					});
				});
				updateAttributeLabels();

				// Reset top single price back to default
				var $singlePrice = $("#furniva-single-price");
				var defaultPrice = $singlePrice.data("default-price");
				if (defaultPrice) {
					$singlePrice.html(defaultPrice);
				}
				$("#furniva-variation-summary").hide().empty();

				// Reset sticky bar
				var $stickyPrice = $("#furniva-sticky-bar .f-sticky-price");
				if (defaultPrice && $stickyPrice.length) {
					$stickyPrice.html(defaultPrice);
				}
			});

			// variation image swap (theme-managed gallery) & price synchronization
			var $mainImg = $(".f-single-gallery .wp-post-image").first();
			var original = {
				src: $mainImg.attr("src"),
				srcset: $mainImg.attr("srcset") || "",
				full: $mainImg.attr("data-large_image") || ""
			};

			$form.on("found_variation", function (e, variation) {
				updateAttributeLabels();

				// 1. Image update
				if (variation && variation.image && variation.image.src) {
					$mainImg.attr({
						src: variation.image.src,
						srcset: variation.image.srcset || variation.image.src,
						"data-large_image": variation.image.full_src || variation.image.src
					});
				}

				// 2. UNIFIED PRICE SYNCHRONIZATION (Priority 1)
				var $singlePrice = $("#furniva-single-price");
				if ($singlePrice.length && variation) {
					var priceHtml = variation.price_html || "";
					if (!priceHtml && typeof variation.display_price !== "undefined") {
						priceHtml = '<ins><span class="woocommerce-Price-amount amount">$' + Number(variation.display_price).toFixed(2) + "</span></ins>";
					}

					if (variation.display_regular_price && variation.display_regular_price > variation.display_price) {
						var diff = (variation.display_regular_price - variation.display_price).toFixed(2);
						if (priceHtml.indexOf("f-save-tag") === -1) {
						priceHtml += ' <span class="f-save-tag">' + furnivaData.strings.save + ' $' + diff + "</span>";
						}
					}

					if (priceHtml) {
						$singlePrice.html(priceHtml);
						$("#furniva-sticky-bar .f-sticky-price").html(priceHtml);
					}
				}

				// 3. Variation summary pill & stock indication
				var $summary = $("#furniva-variation-summary");
				if ($summary.length && variation) {
					var parts = [];
					$form.find("table.variations select").each(function () {
						var v = $(this).val();
						if (v) {
							var t = $(this).find('option[value="' + v + '"]').text().trim() || v;
							parts.push(t);
						}
					});
					var attrsText = parts.join(" · ");
					var rawStock = variation.availability_html ? $(variation.availability_html).text().trim() : "";
					rawStock = rawStock.replace(/^availability:\s*/i, "").trim();
					var stockText = variation.is_in_stock ? (rawStock || furnivaData.strings.inStock) : furnivaData.strings.outOfStock;
					var isAvail = variation.is_in_stock;

					$summary.html(
						'<span class="f-summary-pill-selection">' + (attrsText || furnivaData.strings.selected) + '</span>' +
						'<span class="f-summary-pill-stock' + (isAvail ? " in-stock" : " out-of-stock") + '">' +
						(isAvail ? "✓ " : "✕ ") + (stockText || furnivaData.strings.inStock) +
						'</span>'
					).fadeIn(200);

					$form.find(".reset_variations").each(function () {
						var $r = $(this);
						if (!$r.data("enhanced-icon")) {
							$r.data("enhanced-icon", true);
							var t = $r.text().trim();
							$r.html('<span class="f-reset-icon" aria-hidden="true">↺</span> ' + (t || "Đặt lại"));
						}
					});
				}
			});

			$form.on("reset_image hide_variation", function () {
				$mainImg.attr({ src: original.src, srcset: original.srcset, "data-large_image": original.full });
				var $singlePrice = $("#furniva-single-price");
				var defaultPrice = $singlePrice.data("default-price");
				if (defaultPrice) {
					$singlePrice.html(defaultPrice);
				}
				$("#furniva-variation-summary").hide().empty();
			});
		});
	}
	enhanceSwatches();

	/* ---------------------------------------------------------------- Mini-Cart Drawer */
	var $drawer = $("#furniva-cart-drawer"),
		$drawerOverlay = $("#furniva-drawer-overlay"),
		$drawerContent = $("#furniva-drawer-content");

	function openCartDrawer() {
		$drawer.addClass("is-open");
		$drawerOverlay.addClass("is-open");
		$("body").addClass("drawer-open");
	}

	function closeCartDrawer() {
		$drawer.removeClass("is-open");
		$drawerOverlay.removeClass("is-open");
		$("body").removeClass("drawer-open");
	}

	function refreshCartDrawer(openAfter) {
		$.post(furnivaData.ajaxUrl, { action: "furniva_get_cart_drawer", nonce: furnivaData.nonce, language: furnivaData.language }).done(function (res) {
			if (res && res.success) {
				$drawerContent.html(res.data.html);
				if (res.data.count !== undefined) $(".f-badge, .f-cart-count, .fv-cart-count, .ax-cart-badge").text(res.data.count);
				if (res.data.total !== undefined) $(".f-cart-total").html(res.data.total);
				if (openAfter) openCartDrawer();
			}
		});
	}

	// Click header or bottom nav cart button: open drawer instead of redirecting if on other pages
	$(document).on("click", ".f-cart-btn, .f-bnav-cart-trigger", function (e) {
		if (window.location.pathname.indexOf("/cart") !== -1 || window.location.pathname.indexOf("/checkout") !== -1) {
			return true;
		}
		e.preventDefault();
		openCartDrawer();
		refreshCartDrawer(false);
	});

	// Close drawer
	$(document).on("click", "#furniva-drawer-close, #furniva-drawer-overlay", function () {
		closeCartDrawer();
	});
	$(document).keyup(function (e) {
		if (e.key === "Escape") closeCartDrawer();
	});

	// Drawer steppers
	$(document).on("click", ".f-drawer-plus, .f-drawer-minus", function () {
		var $btn = $(this),
			key = $btn.data("key"),
			$qtySpan = $btn.siblings(".f-drawer-qty"),
			current = parseInt($qtySpan.text(), 10) || 1,
			isPlus = $btn.hasClass("f-drawer-plus"),
			next = isPlus ? current + 1 : current - 1;

		$drawerContent.addClass("is-busy");
		$.post(furnivaData.ajaxUrl, {
			action: "furniva_update_drawer_qty",
			nonce: furnivaData.nonce,
			language: furnivaData.language,
			cart_key: key,
			quantity: next
		}).done(function (res) {
			if (res && res.success) {
				$drawerContent.html(res.data.html);
				$(".f-badge, .f-cart-count, .fv-cart-count, .ax-cart-badge").text(res.data.count);
				$(".f-cart-total").html(res.data.total);
			}
		}).always(function () {
			$drawerContent.removeClass("is-busy");
		});
	});

	// Drawer remove item
	$(document).on("click", ".f-drawer-remove", function () {
		var key = $(this).data("key");
		$drawerContent.addClass("is-busy");
		$.post(furnivaData.ajaxUrl, {
			action: "furniva_remove_drawer_item",
			nonce: furnivaData.nonce,
			language: furnivaData.language,
			cart_key: key
		}).done(function (res) {
			if (res && res.success) {
				$drawerContent.html(res.data.html);
				$(".f-badge, .f-cart-count, .fv-cart-count, .ax-cart-badge").text(res.data.count);
				$(".f-cart-total").html(res.data.total);
				toast(furnivaData.strings.itemRemoved);
			}
		}).always(function () {
			$drawerContent.removeClass("is-busy");
		});
	});

	// When WooCommerce fires added_to_cart event (from AJAX card Add to Cart)
	$(document.body).on("added_to_cart", function (e, fragments, cart_hash, $button) {
		var title = $button && $button.closest(".f-card").find(".f-card-title").text().trim();
		toast(furnivaData.strings.addedToCart.replace("{product}", title || furnivaData.strings.genericItem));
		refreshCartDrawer(true);
	});

	/* ---------------------------------------------------------------- 1-Click Copy Coupon */
	$(document).on("click", ".f-copy-coupon", function (e) {
		e.preventDefault();
		var code = $(this).data("code") || $(this).text().trim();
		if (navigator.clipboard) {
			navigator.clipboard.writeText(code).then(function () {
				toast(furnivaData.strings.copiedCode.replace("{code}", code));
			});
		} else {
			toast(furnivaData.strings.useCode.replace("{code}", code));
		}
	});

	/* ---------------------------------------------------------------- Share Product */
	$(document).on("click", "#furniva-share-product", function () {
		if (navigator.clipboard) {
			navigator.clipboard.writeText(window.location.href).then(function () {
				toast(furnivaData.strings.productLinkCopied);
			});
		} else {
			toast(furnivaData.strings.linkLabel + window.location.href);
		}
	});

	/* ---------------------------------------------------------------- Sticky Add to Cart (Single Product) */
	var $stickyBar = $("#furniva-sticky-bar");
	if ($stickyBar.length) {
		var getMainBuy = function () {
			var $visible = $(".ax-buy-actions:visible, .f-variation-form:visible, form.cart:visible").first();
			if ($visible.length) return $visible;
			return $(".ax-buy-actions, .f-variation-form, form.cart").first();
		};

		var checkSticky = function () {
			if ($(window).scrollTop() < 450) {
				$stickyBar.removeClass("is-visible");
				return;
			}
			var $target = getMainBuy();
			if ($target.length && $target.is(":visible")) {
				var targetOffset = $target.offset();
				if (targetOffset && targetOffset.top > 0) {
					var triggerPos = targetOffset.top + $target.outerHeight() + 30;
					if ($(window).scrollTop() > triggerPos) {
						$stickyBar.addClass("is-visible");
						return;
					}
				}
			}
			$stickyBar.removeClass("is-visible");
		};

		$(window).on("scroll resize", checkSticky);
		setTimeout(checkSticky, 400);

		$(document).on("click", ".f-sticky-add-btn", function (e) {
			e.preventDefault();
			if ($("#axAddToCart").length) {
				$("#axAddToCart").trigger("click");
			} else {
				var $btn = $("form.cart .single_add_to_cart_button, .single_add_to_cart_button");
				if ($btn.length) {
					$btn.trigger("click");
				}
			}
		});

		$(document).on("click", ".f-sticky-scroll-btn", function (e) {
			e.preventDefault();
			var $target = getMainBuy();
			if ($target.length && $target.offset()) {
				$("html, body").animate({ scrollTop: $target.offset().top - 120 }, 350);
			} else {
				$("html, body").animate({ scrollTop: 0 }, 350);
			}
		});
	}

	/* ---------------------------------------------------------------- sync header cart with block cart pages */
	function watchBlockCart() {
		var $zone = $(".wp-block-woocommerce-cart, .wp-block-woocommerce-checkout");
		if (!$zone.length) return;

		var timer = null, lastHtml = "";
		function refreshCartHeader() {
			$.post(furnivaData.ajaxUrl, { action: "furniva_cart_state", nonce: furnivaData.nonce, language: furnivaData.language }).done(function (res) {
				if (res && res.success && res.data.html && res.data.html !== lastHtml) {
					lastHtml = res.data.html;
					$(".f-cart-js").replaceWith(lastHtml);
				}
			});
		}
		refreshCartHeader();

		var observer = new MutationObserver(function () {
			clearTimeout(timer);
			timer = setTimeout(refreshCartHeader, 400);
			polishEmptyCartBlock();
		});
		observer.observe(document.body, { childList: true, subtree: true });
	}
	watchBlockCart();

	/* ---------------------------------------------------------------- jump to reviews */
	$(document).on("click", "#furniva-jump-reviews", function (e) {
		e.preventDefault();
		var $tab = $(".woocommerce-tabs ul.tabs li.reviews_tab a");
		if ($tab.length) $tab.trigger("click");
		var target = $("#tab-reviews");
		if (target.length) {
			$("html, body").animate({ scrollTop: target.offset().top - 120 }, 350);
		}
	});

	/* ---------------------------------------------------------------- empty cart CTA enhancement */
	function polishEmptyCartBlock() {
		var $emptyCart = $(".wp-block-woocommerce-empty-cart-block");
		if ($emptyCart.length && !$emptyCart.find(".f-empty-cart-action").length) {
			var shopUrl = (typeof furnivaData !== "undefined" && furnivaData.shopUrl) ? furnivaData.shopUrl : "/shop/";
			var btnText = (typeof furnivaData !== "undefined" && furnivaData.language === "vi") ? "Khám phá bộ sưu tập →" : "Explore Collection →";
			$emptyCart.find(".wc-block-cart__empty-cart__title").after(
				'<div class="f-empty-cart-action"><a href="' + shopUrl + '" class="f-btn f-btn--primary f-empty-cart-btn">' + btnText + '</a></div>'
			);
		}
	}
	polishEmptyCartBlock();

	/* ---------------------------------------------------------------- sticky header scroll effect */
	var $header = $(".fv-header");
	if ($header.length) {
		$(window).on("scroll", function () {
			if ($(window).scrollTop() > 15) {
				$header.addClass("is-scrolled");
			} else {
				$header.removeClass("is-scrolled");
			}
		});
	}

	/* -------------------------------------------------------- homepage category recommendations */
	function setRoomRecommendations($card, open) {
		$card.toggleClass("is-recommendations-open", open);
		$card.find("[data-room-recommend-trigger]").attr("aria-expanded", open ? "true" : "false");
		$card.find(".fv-room-card__recommendations").attr("aria-hidden", open ? "false" : "true");
	}

	$(document).on("mouseenter", "[data-room-card]", function () {
		if (window.matchMedia && window.matchMedia("(hover: hover) and (pointer: fine)").matches) {
			setRoomRecommendations($(this), true);
		}
	}).on("mouseleave", "[data-room-card]", function () {
		setRoomRecommendations($(this), false);
	});
	$(document).on("click", "[data-room-recommend-trigger]", function () {
		var $card = $(this).closest("[data-room-card]"),
			isOpen = $(this).attr("aria-expanded") === "true";
		setRoomRecommendations($card, !isOpen);
	});
	$(document).on("focusout", "[data-room-card]", function (e) {
		if (!this.contains(e.relatedTarget)) setRoomRecommendations($(this), false);
	});

	/* ---------------------------------------------------------------- DeSpace Luxury Wishlist & Cart Interactivity */
	function showAxToast(msg) {
		var $t = $("#axToast, #axCartToast");
		if (!$t.length) {
			$t = $('<div class="ax-toast" id="axToast"></div>').appendTo("body");
		}
		$t.text(msg).addClass("is-visible");
		clearTimeout(window._axToastTimer);
		window._axToastTimer = setTimeout(function () {
			$t.removeClass("is-visible");
		}, 3000);
	}

	// 1. Wishlist: Share Button
	$(document).on("click", "#axShareWishlistBtn", function () {
		var url = window.location.href;
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(function () {
				showAxToast(furnivaData && furnivaData.language === 'vi' ? 'Đã sao chép liên kết danh sách yêu thích!' : 'Wishlist link copied to clipboard!');
			});
		} else {
			showAxToast(furnivaData && furnivaData.language === 'vi' ? 'Đã sao chép liên kết!' : 'Link copied!');
		}
	});

	// 2. Wishlist: Add to Cart from Wishlist Card
	$(document).on("click", ".ax-btn-wl--addcart", function () {
		var $btn = $(this),
			pid = $btn.data("product-id");
		if (!pid) return;

		var origText = $btn.find("span").text();
		$btn.find("span").text(furnivaData && furnivaData.language === 'vi' ? 'Đang thêm…' : 'Adding…');
		$btn.prop("disabled", true);

		$.post(furnivaData ? furnivaData.ajaxUrl : '/wp-admin/admin-ajax.php', {
			action: 'furniva_wishlist_add_to_cart',
			product_id: pid
		}, function (res) {
			$btn.find("span").text(origText);
			$btn.prop("disabled", false);
			if (res && res.success) {
				showAxToast(res.data.message || (furnivaData && furnivaData.language === 'vi' ? 'Đã thêm vào giỏ hàng!' : 'Added to cart!'));
				if (res.data.cart_count) {
					$(".f-cart-count, .fv-cart-count, .ax-cart-badge").text(res.data.cart_count);
				}
			} else {
				showAxToast(furnivaData && furnivaData.language === 'vi' ? 'Đã thêm vào giỏ hàng!' : 'Added to cart!');
			}
		}).fail(function () {
			$btn.find("span").text(origText);
			$btn.prop("disabled", false);
			showAxToast(furnivaData && furnivaData.language === 'vi' ? 'Đã thêm vào giỏ hàng!' : 'Added to cart!');
		});
	});

	// 3. Wishlist: Remove item
	$(document).on("click", ".ax-btn-wl--remove", function () {
		var $card = $(this).closest(".ax-wishlist-card"),
			pid = $(this).data("product-id");

		$card.addClass("is-removing");
		setTimeout(function () {
			$card.slideUp(280, function () {
				$card.remove();
				var remaining = $(".ax-wishlist-card").length;
				var text = remaining === 1 ? '1 item saved' : remaining + ' items saved';
				if (furnivaData && furnivaData.language === 'vi') {
					text = remaining + ' sản phẩm đã lưu';
				}
				$("#axWishlistCountBadge").text(text);
				showAxToast(furnivaData && furnivaData.language === 'vi' ? 'Đã xóa khỏi danh sách yêu thích' : 'Removed from wishlist');
				if (remaining === 0) {
					$("#axWishlistEmptyCard").show();
				}
			});
		}, 300);

		if (pid && furnivaData) {
			$.post(furnivaData.ajaxUrl, {
				action: 'furniva_wishlist_remove',
				product_id: pid
			});
		}
	});

	// 4. Cart: Recalculate Totals & Free Shipping Bar
	function recalculateCart() {
		var subtotal = 0;
		var totalItems = 0;
		var threshold = 500.00;

		$(".ax-cart-card").each(function () {
			var $card = $(this);
			var isChecked = $card.find(".ax-item-checkbox").is(":checked");
			var price = parseFloat($card.data("price")) || 0;
			var qty = parseInt($card.find(".ax-qty-val").text(), 10) || 1;
			if (isChecked) {
				subtotal += price * qty;
				totalItems += qty;
			}
		});

		var remaining = Math.max(0, threshold - subtotal);
		var percent = Math.min(100, (subtotal / threshold) * 100);

		$("#axSummarySubtotal, #axSummaryTotal").text("$" + subtotal.toFixed(2));
		$("#axSummaryItemCount").text(totalItems);
		$("#axShippingTarget").text("$" + subtotal.toFixed(2) + " / $500.00");
		$("#axShippingFill").css("width", percent + "%");

		if (remaining <= 0) {
			$("#axShippingMessage").html('<strong>' + (furnivaData && furnivaData.language === 'vi' ? 'Chúc mừng!' : 'Congratulations!') + '</strong> ' + (furnivaData && furnivaData.language === 'vi' ? 'Bạn đã nhận MIỄN PHÍ VẬN CHUYỂN!' : 'You unlocked FREE Shipping!') + ' 🎉');
		} else {
			$("#axShippingMessage").html((furnivaData && furnivaData.language === 'vi' ? 'Thêm ' : 'Add ') + '<strong id="axShippingDiff">$' + remaining.toFixed(2) + '</strong> ' + (furnivaData && furnivaData.language === 'vi' ? 'nữa để được ' : 'more for ') + '<strong>' + (furnivaData && furnivaData.language === 'vi' ? 'MIỄN PHÍ VẬN CHUYỂN!' : 'FREE Shipping!') + '</strong>');
		}
	}

	// 5. Cart: Quantity +/-
	$(document).on("click", ".ax-qty-plus", function () {
		var $btn = $(this),
			$card = $btn.closest(".ax-cart-card"),
			$val = $card.find(".ax-qty-val"),
			qty = parseInt($val.text(), 10) || 1,
			cartKey = $btn.data("key");

		qty++;
		$val.text(qty);
		$card.data("qty", qty);
		recalculateCart();

		if (cartKey && !cartKey.startsWith("demo_") && furnivaData) {
			$.post(furnivaData.ajaxUrl, {
				action: 'furniva_cart_update_qty',
				cart_key: cartKey,
				quantity: qty
			}).done(function (res) {
				if (res && res.success) {
					if (res.data.subtotal_html) $(".f-cart-total").html(res.data.subtotal_html);
					if (res.data.item_count !== undefined) $(".f-badge, .f-cart-count, .fv-cart-count, .ax-cart-badge").text(res.data.item_count);
				}
			});
		}
	});

	$(document).on("click", ".ax-qty-minus", function () {
		var $btn = $(this),
			$card = $btn.closest(".ax-cart-card"),
			$val = $card.find(".ax-qty-val"),
			qty = parseInt($val.text(), 10) || 1,
			cartKey = $btn.data("key");

		if (qty > 1) {
			qty--;
			$val.text(qty);
			$card.data("qty", qty);
			recalculateCart();

			if (cartKey && !cartKey.startsWith("demo_") && furnivaData) {
				$.post(furnivaData.ajaxUrl, {
					action: 'furniva_cart_update_qty',
					cart_key: cartKey,
					quantity: qty
				}).done(function (res) {
					if (res && res.success) {
						if (res.data.subtotal_html) $(".f-cart-total").html(res.data.subtotal_html);
						if (res.data.item_count !== undefined) $(".f-badge, .f-cart-count, .fv-cart-count, .ax-cart-badge").text(res.data.item_count);
					}
				});
			}
		} else {
			$card.find(".ax-cart-action-remove").trigger("click");
		}
	});

	// 6. Cart: Select all checkbox
	$(document).on("change", "#axSelectAllCheckbox", function () {
		var checked = $(this).is(":checked");
		$(".ax-item-checkbox").prop("checked", checked);
		recalculateCart();
	});

	// 7. Cart: Individual item checkbox
	$(document).on("change", ".ax-item-checkbox", function () {
		var allChecked = $(".ax-item-checkbox:checked").length === $(".ax-item-checkbox").length;
		$("#axSelectAllCheckbox").prop("checked", allChecked);
		$("#axSelectCount").text($(".ax-item-checkbox:checked").length);
		recalculateCart();
	});

	// 8. Cart: Remove item
	$(document).on("click", ".ax-cart-action-remove", function () {
		var $card = $(this).closest(".ax-cart-card"),
			cartKey = $(this).data("key");

		$card.addClass("is-removing");
		setTimeout(function () {
			$card.slideUp(280, function () {
				$card.remove();
				$("#axSelectCount").text($(".ax-cart-card").length);
				recalculateCart();
				showAxToast(furnivaData && furnivaData.language === 'vi' ? 'Đã xóa sản phẩm khỏi giỏ hàng' : 'Item removed from cart');
			});
		}, 300);

		if (cartKey && !cartKey.startsWith("demo_") && furnivaData) {
			$.post(furnivaData.ajaxUrl, {
				action: 'furniva_cart_remove_item',
				cart_key: cartKey
			}).done(function (res) {
				if (res && res.success) {
					if (res.data.subtotal_html) $(".f-cart-total").html(res.data.subtotal_html);
					if (res.data.item_count !== undefined) $(".f-badge, .f-cart-count, .fv-cart-count, .ax-cart-badge").text(res.data.item_count);
				}
			});
		}
	});

	// 9. Cart: Remove selected
	$(document).on("click", "#axRemoveSelectedBtn", function () {
		var $selected = $(".ax-item-checkbox:checked").closest(".ax-cart-card");
		if (!$selected.length) {
			showAxToast(furnivaData && furnivaData.language === 'vi' ? 'Chưa chọn sản phẩm nào' : 'No items selected');
			return;
		}

		$selected.addClass("is-removing");
		setTimeout(function () {
			$selected.slideUp(280, function () {
				$selected.remove();
				$("#axSelectCount").text($(".ax-cart-card").length);
				recalculateCart();
				showAxToast(furnivaData && furnivaData.language === 'vi' ? 'Đã xóa các mục đã chọn' : 'Removed selected items');
			});
		}, 300);
	});

	// 10. Cart: Color swatch click
	$(document).on("click", ".ax-swatch", function () {
		var $sw = $(this),
			colorName = $sw.data("color-name"),
			$group = $sw.closest(".ax-cart-swatches"),
			$info = $sw.closest(".ax-cart-card__info");

		$group.find(".ax-swatch").removeClass("is-active");
		$sw.addClass("is-active");
		if (colorName) {
			$info.find(".ax-current-color").text(colorName);
		}
	});

	// 11. Wishlist Heart Toggles (Save to wishlist buttons)
	$(document).on("click", ".ax-toggle-wishlist-btn, .ax-cart-action-wishlist", function () {
		var $btn = $(this);
		$btn.toggleClass("is-active");
		$btn.css("transform", "scale(1.25)");
		setTimeout(function () {
			$btn.css("transform", "");
		}, 250);

		var isActive = $btn.hasClass("is-active");
		var msg = isActive ?
			(furnivaData && furnivaData.language === 'vi' ? 'Đã thêm vào danh sách yêu thích ♥' : 'Saved to your wishlist ♥') :
			(furnivaData && furnivaData.language === 'vi' ? 'Đã xóa khỏi danh sách yêu thích' : 'Removed from wishlist');
		showAxToast(msg);
	});

	// 12. Single Product Page Wishlist Button (#axAddToWishlist)
	$(document).on("click", "#axAddToWishlist", function (e) {
		e.preventDefault();
		var $btn = $(this);
		var pid = $btn.data("product-id");
		if (!pid) return;

		$btn.prop("disabled", true);
		$.post(furnivaData ? furnivaData.ajaxUrl : "/wp-admin/admin-ajax.php", {
			action: "furniva_wishlist_toggle",
			product_id: pid
		}, function (res) {
			$btn.prop("disabled", false);
			if (res && res.success) {
				var isAdded = res.data.added;
				$btn.toggleClass("is-added", isAdded);
				$btn.find("span").text(res.data.button_text);
				$btn.find("svg").attr("fill", isAdded ? "currentColor" : "none");

				showAxToast(res.data.message);

				// Update header / footer wishlist badges
				var count = res.data.count;
				$(".fv-wishlist-count").text(count).toggle(count > 0);

				// Trigger YITH & document events
				$(document.body).trigger(isAdded ? "added_to_wishlist" : "removed_from_wishlist");
			}
		}).fail(function () {
			$btn.prop("disabled", false);
		});
	});

	// 13. Sync Wishlist Badges on YITH / Theme Wishlist Events
	$(document.body).on("added_to_wishlist removed_from_wishlist", function () {
		$.post(furnivaData ? furnivaData.ajaxUrl : "/wp-admin/admin-ajax.php", {
			action: "furniva_get_wishlist_count"
		}, function (res) {
			if (res && res.success) {
				var count = res.data.count;
				$(".fv-wishlist-count").text(count).toggle(count > 0);
			}
		});
	});

	/* --------------------------------------------------------- 14. Luxury My Account Interactivity */
	// A. Segmented Auth Tab Switching (Login / Register)
	$(document).on("click", ".ax-auth-tab", function (e) {
		e.preventDefault();
		var $tab = $(this);
		var target = $tab.data("target");
		if (!target) return;

		$(".ax-auth-tab").removeClass("is-active").attr("aria-selected", "false");
		$tab.addClass("is-active").attr("aria-selected", "true");

		$(".ax-auth-panel").removeClass("is-active");
		var $targetPanel = $("#ax-panel-" + target);
		$targetPanel.addClass("is-active");

		var $firstInput = $targetPanel.find("input:visible:first");
		if ($firstInput.length) {
			$firstInput.focus();
		}
	});

	// Check if URL hash has #register on page load
	if (window.location.hash === "#register") {
		$('.ax-auth-tab[data-target="register"]').trigger("click");
	}

	// B. Password Visibility Toggle
	$(document).on("click", ".ax-pwd-toggle", function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $input = $btn.siblings("input");
		if (!$input.length) return;

		var currentType = $input.attr("type");
		if (currentType === "password") {
			$input.attr("type", "text");
			$btn.addClass("is-visible");
			$btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>');
		} else {
			$input.attr("type", "password");
			$btn.removeClass("is-visible");
			$btn.html('<svg class="ax-eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>');
		}
	});

	// C. Voucher Copy to Clipboard
	$(document).on("click", ".ax-voucher-copy-btn, [data-copy-code]", function (e) {
		e.preventDefault();
		var $btn = $(this);
		var code = $btn.data("copy-code") || $btn.closest("[data-copy-code]").data("copy-code");
		if (!code) return;

		function showVoucherCopySuccess($el, c) {
			var $span = $el.find("span");
			var originalText = $span.text();
			$span.text(furnivaData && furnivaData.language === "vi" ? "Đã chép ✓" : "Copied ✓");
			$el.css("background", "#1e5a32");
			showAxToast(furnivaData && furnivaData.language === "vi" ? "Đã sao chép mã ưu đãi " + c + "!" : "Voucher code " + c + " copied!");

			setTimeout(function () {
				$span.text(originalText);
				$el.css("background", "");
			}, 2200);
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(code).then(function () {
				showVoucherCopySuccess($btn, code);
			}).catch(function () {
				var $temp = $("<input>");
				$("body").append($temp);
				$temp.val(code).select();
				document.execCommand("copy");
				$temp.remove();
				showVoucherCopySuccess($btn, code);
			});
		} else {
			var $temp = $("<input>");
			$("body").append($temp);
			$temp.val(code).select();
			document.execCommand("copy");
			$temp.remove();
			showVoucherCopySuccess($btn, code);
		}
	});

	/* --------------------------------------------------------- 15. Require Login / Auth Gate Handling */
	function openAuthGateModal(customMessage, redirectUrl) {
		var $modal = $("#axAuthGateModal");
		var targetRedirect = redirectUrl || window.location.href;
		var baseLoginUrl = (furnivaData && furnivaData.loginUrl) ? furnivaData.loginUrl : "/my-account/";
		var baseRegUrl = (furnivaData && furnivaData.registerUrl) ? furnivaData.registerUrl : "/my-account/?action=register";

		var sepLogin = baseLoginUrl.indexOf("?") !== -1 ? "&" : "?";
		var sepReg = baseRegUrl.indexOf("?") !== -1 ? "&" : "?";

		var loginHref = baseLoginUrl + sepLogin + "action=login&redirect_to=" + encodeURIComponent(targetRedirect);
		var regHref = baseRegUrl + sepReg + "redirect_to=" + encodeURIComponent(targetRedirect);

		$("#axAuthGateLoginLink").attr("href", loginHref);
		$("#axAuthGateRegisterLink").attr("href", regHref);

		if (customMessage) {
			$("#axAuthGateDesc").text(customMessage);
		}

		if ($modal.length) {
			$modal.addClass("is-open").attr("aria-hidden", "false");
			$("body").addClass("ax-auth-modal-open");
		} else {
			window.location.href = loginHref;
		}
	}

	function closeAuthGateModal() {
		$("#axAuthGateModal").removeClass("is-open").attr("aria-hidden", "true");
		$("body").removeClass("ax-auth-modal-open");
	}

	$(document).on("click", "#axAuthGateClose, #axAuthGateBackdrop", function (e) {
		e.preventDefault();
		closeAuthGateModal();
	});

	$(document).on("keydown", function (e) {
		if (e.key === "Escape" && $("#axAuthGateModal").hasClass("is-open")) {
			closeAuthGateModal();
		}
	});

	// If customer is NOT logged in, gate all interactive store features
	if (typeof furnivaData !== "undefined" && !furnivaData.isLoggedIn) {
		var isVi = furnivaData.language === "vi";

		// Intercept during capturing phase before any jQuery or WooCommerce handlers
		document.addEventListener("click", function (e) {
			var $target = $(e.target);

			// A. Add to Cart triggers
			var $addBtn = $target.closest(".add_to_cart_button, .single_add_to_cart_button, #axAddToCart, .f-card-add, .f-sticky-add-btn, .ajax_add_to_cart, form.cart button[type='submit']");
			if ($addBtn.length) {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				var msg = isVi 
					? "Quý khách vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng, nhận mã ưu đãi WELCOME10 và kích hoạt gói bảo hành toàn diện 5 năm."
					: "Please sign in or create an account to add items to your cart and unlock VIP member benefits.";
				openAuthGateModal(msg, window.location.href);
				return false;
			}

			// B. Buy Now button
			var $buyBtn = $target.closest("#furniva-buy-now, .f-buy-now");
			if ($buyBtn.length) {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				var msgBuy = isVi
					? "Quý khách vui lòng đăng nhập để mua ngay sản phẩm và tiến hành thanh toán."
					: "Please sign in to proceed with direct purchasing.";
				openAuthGateModal(msgBuy, (furnivaData.checkoutUrl || "/checkout/"));
				return false;
			}

			// C. Wishlist triggers
			var $wlBtn = $target.closest(".ax-toggle-wishlist-btn, .ax-cart-action-wishlist, .yith-wcwl-add-button a, .add_to_wishlist, #axAddToWishlist, .ax-btn-wishlist, .yith-wcwl-add-to-wishlist-button");
			if ($wlBtn.length) {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				var msgWl = isVi
					? "Quý khách vui lòng đăng nhập để lưu sản phẩm vào danh sách yêu thích và đồng bộ trên mọi thiết bị."
					: "Please sign in to save this product to your personal wishlist.";
				openAuthGateModal(msgWl, window.location.href);
				return false;
			}

			// D. Mini-cart checkout & drawer triggers
			var $checkoutBtn = $target.closest(".f-drawer-checkout, .f-drawer-footer a[href*='checkout'], a.checkout-button, .wc-block-cart__submit-button");
			if ($checkoutBtn.length) {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				var msgCo = isVi
					? "Quý khách vui lòng đăng nhập để thanh toán đơn hàng và áp dụng mã giảm giá thành viên."
					: "Please sign in to proceed to checkout and apply member discounts.";
				openAuthGateModal(msgCo, (furnivaData.checkoutUrl || "/checkout/"));
				return false;
			}
		}, true);
	}
});
