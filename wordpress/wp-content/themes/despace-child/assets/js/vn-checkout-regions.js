/**
 * DeSpace 2-Tier Vietnam Administrative Address Selector (2025 Reform: 34 Provinces -> 3,321 Wards/Communes)
 *
 * Progressively enhances WooCommerce Classic Checkout (`form.checkout`) and
 * My Account Edit Address (`/my-account/edit-address/{billing|shipping}/`)
 * by turning `#billing_city` and `#shipping_city` into cascading `<select>`
 * dropdowns synced with `VietnamRegions` REST endpoints while preserving
 * native `<label for="...">` association, autofill tokens, and free-text fallback.
 */
(function () {
	"use strict";

	var config = window.furnivaData || window.despaceData || {};
	var restBase = (config.restUrl || "/wp-json/despace/v1").replace(/\/+$/, "");
	var isEnglish = /^en/i.test(document.documentElement.lang || config.language || "");

	var i18n = {
		selectProvinceFirst: isEnglish
			? "— Select Province / City first —"
			: "— Chọn Tỉnh / Thành phố trước —",
		loadingCommunes: isEnglish
			? "Loading Wards / Communes…"
			: "Đang tải Phường / Xã / Đặc khu…",
		selectCommune: isEnglish
			? "— Select Ward / Commune / Special Zone —"
			: "— Chọn Phường / Xã / Đặc khu —"
	};

	var communeCache = new Map();
	var inflightTokens = { billing: 0, shipping: 0 };

	function normalizeCommuneText(str) {
		return String(str || "")
			.toLocaleLowerCase("vi")
			.normalize("NFD")
			.replace(/[\u0300-\u036f]/g, "")
			.replace(/đ/g, "d")
			.replace(/^(phuong|xa|thi tran|dac khu|p\.|x\.|tt\.)\s+/i, "")
			.replace(/[^a-z0-9 ]/g, " ")
			.replace(/\s+/g, " ")
			.trim();
	}

	function hideRawCommuneCodeRows() {
		document.querySelectorAll(".despace-commune-code-field").forEach(function (row) {
			row.hidden = true;
			var inp = row.querySelector("input");
			if (inp) {
				inp.tabIndex = -1;
			}
		});
	}

	function fetchCommunes(provinceCode) {
		var code = String(provinceCode || "").trim();
		if (!/^\d{2}$/.test(code)) {
			return Promise.resolve([]);
		}
		if (communeCache.has(code)) {
			return Promise.resolve(communeCache.get(code));
		}
		var url = restBase + "/regions/communes?province=" + encodeURIComponent(code);
		return window.fetch(url, { credentials: "same-origin" })
			.then(function (res) {
				if (!res.ok) throw new Error("HTTP " + res.status);
				return res.json();
			})
			.then(function (data) {
				var list = (data && Array.isArray(data.communes)) ? data.communes : [];
				communeCache.set(code, list);
				return list;
			})
			.catch(function () {
				return [];
			});
	}

	function getCountryValue(group) {
		var countryEl = document.getElementById(group + "_country") || document.querySelector('[name="' + group + '_country"]');
		if (!countryEl) return "VN";
		return (countryEl.value || "VN").toUpperCase();
	}

	function getStateValue(group) {
		var stateEl = document.getElementById(group + "_state") || document.querySelector('[name="' + group + '_state"]');
		if (!stateEl) return "";
		return String(stateEl.value || "").trim();
	}

	function isGroupRequired(group, cityRow) {
		if (!cityRow || !cityRow.classList.contains("validate-required")) {
			return false;
		}
		if (group === "shipping") {
			var shipDiff = document.getElementById("ship-to-different-address-checkbox");
			if (shipDiff && !shipDiff.checked) {
				return false;
			}
		}
		return true;
	}

	function restoreFreeTextInput(group) {
		var cityInput = document.querySelector('input[name="' + group + '_city"]');
		var existingSelect = document.querySelector('select.despace-commune-select[data-group="' + group + '"]');
		var codeInput = document.querySelector('input[name="' + group + '_despace_commune_code"]');

		if (existingSelect && existingSelect.parentNode) {
			existingSelect.parentNode.removeChild(existingSelect);
		}
		if (cityInput) {
			cityInput.type = "text";
			cityInput.id = group + "_city";
			cityInput.classList.remove("despace-city-hidden-backing");
		}
		if (codeInput) {
			codeInput.value = "";
		}
	}

	function ensureSelectElement(group, cityInput, cityRow) {
		var select = document.querySelector('select.despace-commune-select[data-group="' + group + '"]');
		if (!select) {
			select = document.createElement("select");
			select.className = "select input-text despace-commune-select";
			select.setAttribute("data-group", group);
			select.setAttribute("autocomplete", group + " address-level2");
			cityInput.insertAdjacentElement("afterend", select);

			select.addEventListener("change", function () {
				var opt = select.options[select.selectedIndex];
				var communeName = (opt && opt.getAttribute("data-commune-name")) || "";
				var communeCode = select.value || "";
				var currentCityInput = document.querySelector('input[name="' + group + '_city"]');
				var currentCodeInput = document.querySelector('input[name="' + group + '_despace_commune_code"]');

				if (currentCityInput) {
					currentCityInput.value = communeName;
					currentCityInput.dispatchEvent(new Event("change", { bubbles: true }));
				}
				if (currentCodeInput) {
					currentCodeInput.value = communeCode;
				}

				select.setCustomValidity("");
				select.removeAttribute("aria-invalid");

				if (window.jQuery && document.querySelector("form.checkout")) {
					window.jQuery(document.body).trigger("update_checkout");
				}
			});
		}

		// Keep <label for="{group}_city"> bound directly to the visible <select>
		if (cityInput.type !== "hidden") {
			cityInput.setAttribute("data-orig-id", cityInput.id || (group + "_city"));
			cityInput.removeAttribute("id");
			cityInput.type = "hidden";
			cityInput.classList.add("despace-city-hidden-backing");
		}
		select.id = group + "_city";
		select.required = isGroupRequired(group, cityRow);

		// If Select2/SelectWoo wrapped a previous state, ensure our native clean select isn't hidden
		select.style.display = "";
		return select;
	}

	function syncGroup(group, userChangedProvince) {
		hideRawCommuneCodeRows();

		var cityInput = document.querySelector('input[name="' + group + '_city"]');
		if (!cityInput) return;

		var cityRow = document.getElementById(group + "_city_field") || cityInput.closest(".form-row");
		var codeInput = document.querySelector('input[name="' + group + '_despace_commune_code"]');
		var country = getCountryValue(group);

		if (country !== "VN") {
			restoreFreeTextInput(group);
			return;
		}

		var provCode = getStateValue(group);
		var select = ensureSelectElement(group, cityInput, cityRow);

		if (userChangedProvince) {
			cityInput.value = "";
			if (codeInput) codeInput.value = "";
		}

		if (!/^\d{2}$/.test(provCode)) {
			select.innerHTML = "";
			var emptyOpt = document.createElement("option");
			emptyOpt.value = "";
			emptyOpt.textContent = i18n.selectProvinceFirst;
			select.appendChild(emptyOpt);
			select.disabled = true;
			return;
		}

		var token = ++inflightTokens[group];
		select.disabled = true;
		if (!communeCache.has(provCode)) {
			select.innerHTML = "";
			var loadOpt = document.createElement("option");
			loadOpt.value = "";
			loadOpt.textContent = i18n.loadingCommunes;
			select.appendChild(loadOpt);
		}

		fetchCommunes(provCode).then(function (communes) {
			if (token !== inflightTokens[group]) return;

			if (!communes.length) {
				// Graceful fallback to free-text input if dataset unavailable
				restoreFreeTextInput(group);
				return;
			}

			var savedCode = codeInput ? String(codeInput.value || "").trim() : "";
			var savedName = String(cityInput.value || "").trim();
			var savedNorm = normalizeCommuneText(savedName);

			select.innerHTML = "";
			var placeholderOpt = document.createElement("option");
			placeholderOpt.value = "";
			placeholderOpt.textContent = i18n.selectCommune;
			select.appendChild(placeholderOpt);

			var matchedItem = null;
			communes.forEach(function (item) {
				var opt = document.createElement("option");
				opt.value = item.code;
				opt.setAttribute("data-commune-name", item.name);
				opt.textContent = item.name;

				if (!matchedItem) {
					if (savedCode && item.code === savedCode) {
						matchedItem = item;
					} else if (!savedCode && savedNorm && normalizeCommuneText(item.name) === savedNorm) {
						matchedItem = item;
					}
				}
				select.appendChild(opt);
			});

			select.disabled = false;
			select.required = isGroupRequired(group, cityRow);

			if (matchedItem) {
				select.value = matchedItem.code;
				cityInput.value = matchedItem.name;
				if (codeInput) codeInput.value = matchedItem.code;
			} else {
				select.value = "";
				if (savedCode && codeInput) {
					codeInput.value = "";
				}
			}
		});
	}

	function bindAddressListeners() {
		["billing", "shipping"].forEach(function (group) {
			syncGroup(group, false);
		});

		document.addEventListener("change", function (e) {
			var target = e.target;
			if (!target) return;
			var name = target.getAttribute("name") || target.id || "";
			if (name === "billing_state" || name === "billing_country") {
				syncGroup("billing", name === "billing_state");
			} else if (name === "shipping_state" || name === "shipping_country") {
				syncGroup("shipping", name === "shipping_state");
			} else if (target.id === "ship-to-different-address-checkbox") {
				syncGroup("shipping", false);
			}
		});

		if (window.jQuery) {
			window.jQuery(document.body).on("country_to_state_changed updated_checkout", function () {
				["billing", "shipping"].forEach(function (group) {
					var existingSelect = document.querySelector('select.despace-commune-select[data-group="' + group + '"]');
					if (!existingSelect || existingSelect.options.length <= 1) {
						syncGroup(group, false);
					} else {
						hideRawCommuneCodeRows();
					}
				});
			});

			// Select2 / SelectWoo fires jQuery change events that don't always bubble natively
			window.jQuery(document).on("change", "#billing_state, #billing_country", function (ev) {
				if (ev.originalEvent) return; // already handled by native listener
				syncGroup("billing", this.id === "billing_state");
			});
			window.jQuery(document).on("change", "#shipping_state, #shipping_country", function (ev) {
				if (ev.originalEvent) return;
				syncGroup("shipping", this.id === "shipping_state");
			});
		}
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", bindAddressListeners);
	} else {
		bindAddressListeners();
	}
})();
