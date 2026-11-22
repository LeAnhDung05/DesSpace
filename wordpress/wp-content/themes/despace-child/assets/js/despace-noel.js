/**
 * DeSpace Seasonal Mode — Lightweight Luxury Snow & Champagne Bokeh Canvas.
 *
 * Pure ES6+ (zero jQuery), pointer-events: none, respects prefers-reduced-motion,
 * pauses automatically on background tabs and while 3D/AR modal is open, and
 * persists visitor snow toggle preference in localStorage.
 */
(() => {
	"use strict";

	const cfg = window.DESPACE_NOEL || null;
	if (!cfg || !cfg.active || !cfg.snowEnabled) {
		return;
	}

	const prefersReduced =
		window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	if (prefersReduced) {
		return;
	}

	const STORAGE_KEY = "despace_noel_snow";
	const labels = cfg.labels || {
		pauseSnow: "Tắt hiệu ứng tuyết rơi",
		resumeSnow: "Bật hiệu ứng tuyết rơi",
		snowOn: "Tuyết: Bật",
		snowOff: "Tuyết: Tắt",
	};

	const readPref = () => {
		try {
			const v = window.localStorage.getItem(STORAGE_KEY);
			return v === "off" ? false : true;
		} catch (_) {
			return true;
		}
	};

	const savePref = (enabled) => {
		try {
			window.localStorage.setItem(STORAGE_KEY, enabled ? "on" : "off");
		} catch (_) {
			// Ignore storage quota / private mode errors.
		}
	};

	let userSnowOn = readPref();
	let rafId = 0;
	let width = 0;
	let height = 0;
	let dpr = 1;
	let particles = [];

	// Create lightweight overlay canvas (strictly pointer-events: none).
	const canvas = document.createElement("canvas");
	canvas.id = "despace-noel-canvas";
	canvas.setAttribute("aria-hidden", "true");
	document.body.appendChild(canvas);

	const ctx = canvas.getContext("2d", { alpha: true });
	if (!ctx) {
		canvas.remove();
		return;
	}

	// Create minimal visitor snow toggle button.
	const toggleBtn = document.createElement("button");
	toggleBtn.type = "button";
	toggleBtn.id = "despace-noel-snow-toggle";
	toggleBtn.innerHTML =
		'<span class="ds-noel-flake" aria-hidden="true">❄</span>' +
		'<span class="ds-noel-toggle-text"></span>';
	document.body.appendChild(toggleBtn);

	const toggleTextEl = toggleBtn.querySelector(".ds-noel-toggle-text");

	const updateToggleUi = () => {
		toggleBtn.setAttribute("aria-pressed", userSnowOn ? "true" : "false");
		toggleBtn.setAttribute("aria-label", userSnowOn ? labels.pauseSnow : labels.resumeSnow);
		toggleBtn.setAttribute("title", userSnowOn ? labels.pauseSnow : labels.resumeSnow);
		if (toggleTextEl) {
			toggleTextEl.textContent = userSnowOn ? labels.snowOn : labels.snowOff;
		}
		canvas.classList.toggle("is-paused", !userSnowOn);
	};

	const getParticleCount = () => {
		const isMobile = window.innerWidth < 768;
		if (cfg.snowDensity === "medium") {
			return isMobile ? 28 : 48;
		}
		return isMobile ? 16 : 28;
	};

	const isGoldAccentPreset = cfg.preset === "midnight" || cfg.preset === "burgundy";

	const createParticle = (randomY) => {
		const isBokeh = Math.random() < (isGoldAccentPreset ? 0.32 : 0.22);
		const radius = isBokeh ? 1.8 + Math.random() * 2.6 : 1.1 + Math.random() * 2.1;
		return {
			x: Math.random() * width,
			y: randomY ? Math.random() * height : -12 - Math.random() * 40,
			r: radius,
			vy: 0.35 + Math.random() * 0.65,
			vx: -0.18 + Math.random() * 0.36,
			swayAmp: 0.25 + Math.random() * 0.45,
			swayFreq: 0.008 + Math.random() * 0.012,
			phase: Math.random() * Math.PI * 2,
			alpha: isBokeh ? 0.16 + Math.random() * 0.22 : 0.28 + Math.random() * 0.38,
			isBokeh,
		};
	};

	const resize = () => {
		width = window.innerWidth;
		height = window.innerHeight;
		dpr = Math.min(window.devicePixelRatio || 1, 2);
		canvas.width = Math.floor(width * dpr);
		canvas.height = Math.floor(height * dpr);
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

		const target = getParticleCount();
		if (particles.length < target) {
			while (particles.length < target) {
				particles.push(createParticle(true));
			}
		} else if (particles.length > target) {
			particles.length = target;
		}
	};

	const shouldAnimate = () => {
		if (!userSnowOn) return false;
		if (document.hidden) return false;
		// Dedicate 100% GPU frame budget to <model-viewer> while 3D/AR modal is open.
		if (document.body.classList.contains("ax-3d-modal-open")) return false;
		return true;
	};

	const stopLoop = () => {
		if (rafId) {
			window.cancelAnimationFrame(rafId);
			rafId = 0;
		}
	};

	const tick = () => {
		if (!shouldAnimate()) {
			rafId = 0;
			return;
		}

		ctx.clearRect(0, 0, width, height);

		for (let i = 0; i < particles.length; i++) {
			const p = particles[i];
			p.phase += p.swayFreq;
			p.x += p.vx + Math.sin(p.phase) * p.swayAmp;
			p.y += p.vy;

			if (p.y > height + 14) {
				particles[i] = createParticle(false);
				continue;
			}
			if (p.x < -14) p.x = width + 10;
			if (p.x > width + 14) p.x = -10;

			ctx.beginPath();
			ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
			if (p.isBokeh) {
				ctx.fillStyle = `rgba(212, 175, 55, ${p.alpha.toFixed(2)})`;
			} else {
				ctx.fillStyle = `rgba(255, 252, 245, ${p.alpha.toFixed(2)})`;
			}
			ctx.fill();
		}

		rafId = window.requestAnimationFrame(tick);
	};

	const syncLoop = () => {
		if (shouldAnimate()) {
			if (!rafId) {
				rafId = window.requestAnimationFrame(tick);
			}
		} else {
			stopLoop();
			if (!userSnowOn) {
				ctx.clearRect(0, 0, width, height);
			}
		}
	};

	toggleBtn.addEventListener("click", () => {
		userSnowOn = !userSnowOn;
		savePref(userSnowOn);
		updateToggleUi();
		syncLoop();
	});

	document.addEventListener("visibilitychange", syncLoop);

	// Pause smoothly when 3D/AR modal opens, resume on close.
	const bodyObserver = new MutationObserver(syncLoop);
	bodyObserver.observe(document.body, { attributes: true, attributeFilter: ["class"] });

	let resizeTimer = 0;
	window.addEventListener(
		"resize",
		() => {
			window.clearTimeout(resizeTimer);
			resizeTimer = window.setTimeout(resize, 160);
		},
		{ passive: true }
	);

	resize();
	updateToggleUi();
	syncLoop();
})();
