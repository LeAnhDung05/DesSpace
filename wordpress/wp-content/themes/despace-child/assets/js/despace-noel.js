/**
 * DeSpace Winter Atelier — Luxury Holiday Engine v2.0.
 *
 * Pure ES6+ (zero jQuery), pointer-events: none canvas, 3-depth parallax
 * crystalline snow & champagne bokeh, pointer wind breeze, celebratory
 * starlight bursts, live Christmas Eve countdown, wax-seal gift unwrap,
 * and zero-asset opt-in Web Audio winter music box.
 */
(() => {
	"use strict";

	const cfg = window.DESPACE_NOEL || null;
	if (!cfg || !cfg.active) {
		return;
	}

	const prefersReduced =
		window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	const labels = cfg.labels || {};
	const STORAGE_SNOW_KEY = "despace_noel_snow";
	const SESSION_GIFT_KEY = "despace_noel_gift_unwrapped";

	const readSnowPref = () => {
		try {
			return window.localStorage.getItem(STORAGE_SNOW_KEY) === "off" ? false : true;
		} catch (_) {
			return true;
		}
	};

	const saveSnowPref = (on) => {
		try {
			window.localStorage.setItem(STORAGE_SNOW_KEY, on ? "on" : "off");
		} catch (_) {
			// Ignore storage restrictions.
		}
	};

	const showHolidayToast = (msg) => {
		const toast = document.getElementById("furniva-toast");
		if (toast) {
			toast.textContent = msg;
			toast.classList.add("show");
			window.clearTimeout(window._fvToastTimer);
			window._fvToastTimer = window.setTimeout(() => toast.classList.remove("show"), 3000);
		}
	};

	const copyCodeWithFeedback = (code, originX, originY) => {
		if (!code) return;
		const msg = (labels.copiedToast || "✦ Copied {code}!").replace("{code}", code);
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(code).then(() => showHolidayToast(msg)).catch(() => showHolidayToast(msg));
		} else {
			showHolidayToast(msg);
		}
		if (typeof spawnStarlightBurst === "function" && originX && originY) {
			spawnStarlightBurst(originX, originY);
		}
	};

	/* ----------------------------------------------------------------
	   1. 3-Depth Atmospheric Snow / Crystal / Champagne Bokeh Engine
	   ---------------------------------------------------------------- */
	const heroStage = cfg.heroScopedSnow ? document.querySelector(".fv-featured-hero__stage") : null;
	const hasAmbientSnowStage = !cfg.heroScopedSnow || Boolean(heroStage);
	let spawnStarlightBurst = null;
	let userSnowOn = !prefersReduced && Boolean(cfg.snowEnabled) && hasAmbientSnowStage && readSnowPref();
	let canvas = null;
	let ctx = null;
	let rafId = 0;
	let width = 0;
	let height = 0;
	let dpr = 1;
	let particles = [];
	let bursts = [];
	let pointer = { x: -9999, y: -9999, vx: 0 };
	let windPhase = 0;

	const snowStyle = cfg.snowStyle || "snow_bokeh";

	const createFlake = (randomY) => {
		// Depth 0 = far, 1 = mid crystal, 2 = near warm bokeh
		const layerRoll = Math.random();
		let kind = "dot";
		if (snowStyle === "champagne") {
			kind = layerRoll < 0.62 ? "bokeh" : "spark";
		} else if (snowStyle === "crystals") {
			kind = layerRoll < 0.36 ? "crystal" : "dot";
		} else {
			kind = layerRoll < 0.22 ? "bokeh" : layerRoll < 0.44 ? "crystal" : "dot";
		}

		const depth = kind === "bokeh" ? 1.35 : kind === "crystal" ? 1.0 : 0.65;
		const radius =
			kind === "bokeh"
				? 3.2 + Math.random() * 4.4
				: kind === "crystal"
				? 3.0 + Math.random() * 3.0
				: 1.0 + Math.random() * 1.9;

		return {
			kind,
			x: Math.random() * (width || window.innerWidth),
			y: randomY ? Math.random() * (height || window.innerHeight) : -16 - Math.random() * 50,
			r: radius,
			vy: (kind === "bokeh" ? 0.24 : 0.38) * depth + Math.random() * 0.48,
			vx: (-0.14 + Math.random() * 0.28) * depth,
			swayAmp: 0.22 + Math.random() * 0.42,
			swayFreq: 0.007 + Math.random() * 0.011,
			phase: Math.random() * Math.PI * 2,
			rot: Math.random() * Math.PI,
			rotSpeed: (-0.012 + Math.random() * 0.024),
			alpha:
				kind === "bokeh"
					? 0.12 + Math.random() * 0.14
					: kind === "crystal"
					? 0.36 + Math.random() * 0.34
					: 0.30 + Math.random() * 0.38,
		};
	};

	const drawCrystal = (c, x, y, r, rot, alpha) => {
		c.save();
		c.translate(x, y);
		c.rotate(rot);
		c.strokeStyle = `rgba(255, 252, 246, ${alpha.toFixed(2)})`;
		c.lineWidth = 0.85;
		c.beginPath();
		for (let a = 0; a < 3; a++) {
			const ang = (a * Math.PI) / 3;
			const dx = Math.cos(ang) * r;
			const dy = Math.sin(ang) * r;
			c.moveTo(-dx, -dy);
			c.lineTo(dx, dy);
		}
		c.stroke();
		c.restore();
	};

	const drawBokeh = (c, x, y, r, alpha) => {
		const grad = c.createRadialGradient(x, y, 0, x, y, r * 2.2);
		grad.addColorStop(0, `rgba(232, 202, 134, ${(alpha * 1.15).toFixed(2)})`);
		grad.addColorStop(0.55, `rgba(209, 173, 98, ${(alpha * 0.55).toFixed(2)})`);
		grad.addColorStop(1, "rgba(209, 173, 98, 0)");
		c.fillStyle = grad;
		c.beginPath();
		c.arc(x, y, r * 2.2, 0, Math.PI * 2);
		c.fill();
	};

	const shouldAnimate = () => {
		if (!canvas || !ctx) return false;
		if (document.hidden) return false;
		if (document.body.classList.contains("ax-3d-modal-open")) return false;
		return userSnowOn || bursts.length > 0;
	};

	const syncLoop = () => {
		if (shouldAnimate()) {
			if (!rafId) rafId = window.requestAnimationFrame(tick);
		} else {
			if (rafId) {
				window.cancelAnimationFrame(rafId);
				rafId = 0;
			}
			if (ctx && !userSnowOn && bursts.length === 0) {
				ctx.clearRect(0, 0, width, height);
			}
		}
	};

	const tick = () => {
		if (!shouldAnimate()) {
			rafId = 0;
			return;
		}
		ctx.clearRect(0, 0, width, height);
		windPhase += 0.005;
		const breeze = Math.sin(windPhase) * 0.18;

		if (userSnowOn) {
			for (let i = 0; i < particles.length; i++) {
				const p = particles[i];
				p.phase += p.swayFreq;
				p.rot += p.rotSpeed;

				// Gentle pointer breeze interaction
				const dx = p.x - pointer.x;
				const dy = p.y - pointer.y;
				const distSq = dx * dx + dy * dy;
				if (distSq < 11000 && distSq > 1) {
					const push = (1 - Math.sqrt(distSq) / 105) * 0.55;
					p.x += (dx > 0 ? 1 : -1) * push;
				}

				p.x += p.vx + breeze + Math.sin(p.phase) * p.swayAmp;
				p.y += p.vy;

				if (p.y > height + 18) {
					particles[i] = createFlake(false);
					continue;
				}
				if (p.x < -18) p.x = width + 14;
				if (p.x > width + 18) p.x = -14;

				if (p.kind === "bokeh") {
					drawBokeh(ctx, p.x, p.y, p.r, p.alpha);
				} else if (p.kind === "crystal") {
					drawCrystal(ctx, p.x, p.y, p.r, p.rot, p.alpha);
				} else if (p.kind === "spark") {
					ctx.fillStyle = `rgba(232, 202, 134, ${p.alpha.toFixed(2)})`;
					ctx.beginPath();
					ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
					ctx.fill();
				} else {
					ctx.fillStyle = `rgba(255, 252, 246, ${p.alpha.toFixed(2)})`;
					ctx.beginPath();
					ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
					ctx.fill();
				}
			}
		}

		// Celebratory Champagne Starlight Bursts (gift unwrap / promo copy)
		for (let i = bursts.length - 1; i >= 0; i--) {
			const b = bursts[i];
			b.x += b.vx;
			b.y += b.vy;
			b.vy += 0.06;
			b.life -= 0.022;
			if (b.life <= 0) {
				bursts.splice(i, 1);
				continue;
			}
			ctx.save();
			ctx.globalAlpha = Math.max(0, b.life);
			ctx.fillStyle = b.color;
			ctx.beginPath();
			ctx.arc(b.x, b.y, b.r, 0, Math.PI * 2);
			ctx.fill();
			ctx.restore();
		}

		rafId = window.requestAnimationFrame(tick);
	};

	if (!prefersReduced) {
		canvas = document.createElement("canvas");
		canvas.id = "despace-noel-canvas";
		canvas.setAttribute("aria-hidden", "true");
		if (heroStage) {
			canvas.classList.add("is-hero-scoped");
			heroStage.appendChild(canvas);
		} else {
			canvas.classList.add("is-burst-only");
			document.body.appendChild(canvas);
		}
		ctx = canvas.getContext("2d", { alpha: true });

		const getParticleCount = () => {
			if (!hasAmbientSnowStage) return 0;
			const isMobile = window.innerWidth < 768;
			if (cfg.snowDensity === "medium") return isMobile ? 22 : 38;
			return isMobile ? 14 : 24;
		};

		const resize = () => {
			if (!canvas || !ctx) return;
			if (heroStage) {
				const rect = heroStage.getBoundingClientRect();
				width = Math.max(1, Math.round(rect.width));
				height = Math.max(1, Math.round(rect.height));
			} else {
				width = window.innerWidth;
				height = window.innerHeight;
			}
			dpr = Math.min(window.devicePixelRatio || 1, 2);
			canvas.width = Math.floor(width * dpr);
			canvas.height = Math.floor(height * dpr);
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

			const target = getParticleCount();
			while (particles.length < target) particles.push(createFlake(true));
			if (particles.length > target) particles.length = target;
		};

		spawnStarlightBurst = (x, y) => {
			if (!ctx) return;
			let bx = x;
			let by = y;
			if (heroStage) {
				const rect = heroStage.getBoundingClientRect();
				// If burst originates outside the hero stage, skip hero-canvas burst cleanly
				if (y < rect.top - 40 || y > rect.bottom + 40) return;
				bx = x - rect.left;
				by = y - rect.top;
			}
			const colors = ["#DFC086", "#BD9A62", "#F4F0E8", "#A9443F"];
			for (let i = 0; i < 20; i++) {
				const angle = (Math.PI * 2 * i) / 20 + (Math.random() - 0.5) * 0.25;
				const speed = 1.4 + Math.random() * 2.8;
				bursts.push({
					x: bx,
					y: by,
					vx: Math.cos(angle) * speed,
					vy: Math.sin(angle) * speed - 1.1,
					r: 1.5 + Math.random() * 2.2,
					life: 1,
					color: colors[i % colors.length],
				});
			}
			canvas.classList.remove("is-paused");
			syncLoop();
		};

		window.addEventListener(
			"pointermove",
			(e) => {
				if (heroStage) {
					const rect = heroStage.getBoundingClientRect();
					pointer.x = e.clientX - rect.left;
					pointer.y = e.clientY - rect.top;
				} else {
					pointer.x = e.clientX;
					pointer.y = e.clientY;
				}
			},
			{ passive: true }
		);

		let resizeTimer = 0;
		window.addEventListener(
			"resize",
			() => {
				window.clearTimeout(resizeTimer);
				resizeTimer = window.setTimeout(resize, 160);
			},
			{ passive: true }
		);

		if (heroStage && typeof ResizeObserver === "function") {
			new ResizeObserver(resize).observe(heroStage);
		}

		document.addEventListener("visibilitychange", syncLoop);
		new MutationObserver(syncLoop).observe(document.body, { attributes: true, attributeFilter: ["class"] });

		resize();
		canvas.classList.toggle("is-paused", !userSnowOn);
		syncLoop();
	}

	/* ----------------------------------------------------------------
	   2. Opt-in Web Audio Winter Music-Box Synthesizer (0 MP3 assets!)
	   ---------------------------------------------------------------- */
	let audioCtx = null;
	let audioInterval = 0;
	let audioPlaying = false;

	// Gentle winter celesta arpeggio frequencies (Fmaj7 -> Dm9 -> Bbmaj7 -> C9)
	const chimeNotes = [523.25, 659.25, 783.99, 987.77, 880.0, 659.25, 587.33, 739.99, 880.0, 698.46, 587.33, 523.25];
	let noteIndex = 0;

	const playChimeNote = () => {
		if (!audioCtx || audioCtx.state !== "running" || document.hidden) return;
		const now = audioCtx.currentTime;
		const osc = audioCtx.createOscillator();
		const overtone = audioCtx.createOscillator();
		const gain = audioCtx.createGain();
		const freq = chimeNotes[noteIndex % chimeNotes.length];
		noteIndex++;

		osc.type = "sine";
		osc.frequency.setValueAtTime(freq, now);
		overtone.type = "triangle";
		overtone.frequency.setValueAtTime(freq * 2, now);

		const overGain = audioCtx.createGain();
		overGain.gain.setValueAtTime(0.08, now);

		gain.gain.setValueAtTime(0.0001, now);
		gain.gain.exponentialRampToValueAtTime(0.032, now + 0.04);
		gain.gain.exponentialRampToValueAtTime(0.0001, now + 1.85);

		osc.connect(gain);
		overtone.connect(overGain);
		overGain.connect(gain);
		gain.connect(audioCtx.destination);

		osc.start(now);
		overtone.start(now);
		osc.stop(now + 1.9);
		overtone.stop(now + 1.9);
	};

	const setMusicPlaying = (on) => {
		const AudioCtor = window.AudioContext || window.webkitAudioContext;
		if (!AudioCtor) return false;
		if (on) {
			if (!audioCtx) audioCtx = new AudioCtor();
			if (audioCtx.state === "suspended") audioCtx.resume();
			audioPlaying = true;
			playChimeNote();
			window.clearInterval(audioInterval);
			audioInterval = window.setInterval(playChimeNote, 920);
		} else {
			audioPlaying = false;
			window.clearInterval(audioInterval);
			audioInterval = 0;
		}
		return audioPlaying;
	};

	/* ----------------------------------------------------------------
	   3. Whisper-Quiet Floating Holiday Control Pill
	   ---------------------------------------------------------------- */
	const showSnowCtrl = !prefersReduced && Boolean(cfg.snowEnabled) && hasAmbientSnowStage;
	const showAudioCtrl = Boolean(cfg.audioEnabled) && Boolean(window.AudioContext || window.webkitAudioContext);
	const showPromoCtrl = Boolean(cfg.promoCode);

	if (showSnowCtrl || showAudioCtrl || showPromoCtrl) {
		const dock = document.createElement("div");
		dock.id = "despace-noel-controls";
		dock.setAttribute("role", "region");
		dock.setAttribute("aria-label", cfg.isVi ? "Điều khiển không khí Giáng sinh" : "Holiday atmosphere controls");

		if (showSnowCtrl) {
			const snowBtn = document.createElement("button");
			snowBtn.type = "button";
			snowBtn.id = "despace-noel-snow-toggle";
			snowBtn.className = "ds-noel-ctrl-btn";
			snowBtn.innerHTML = '<span class="ds-noel-ico" aria-hidden="true">❄</span><span class="ds-noel-lbl"></span>';
			const snowLbl = snowBtn.querySelector(".ds-noel-lbl");

			const syncSnowBtn = () => {
				snowBtn.setAttribute("aria-pressed", userSnowOn ? "true" : "false");
				snowBtn.setAttribute("aria-label", userSnowOn ? (labels.pauseSnow || "Pause snow") : (labels.resumeSnow || "Resume snow"));
				snowBtn.setAttribute("title", userSnowOn ? (labels.pauseSnow || "Pause snow") : (labels.resumeSnow || "Resume snow"));
				if (snowLbl) snowLbl.textContent = userSnowOn ? (labels.snowOn || "Snow") : (labels.snowOff || "Paused");
				if (canvas) canvas.classList.toggle("is-paused", !userSnowOn);
			};

			snowBtn.addEventListener("click", () => {
				userSnowOn = !userSnowOn;
				saveSnowPref(userSnowOn);
				syncSnowBtn();
				syncLoop();
			});

			syncSnowBtn();
			dock.appendChild(snowBtn);
		}

		if (showAudioCtrl) {
			if (dock.children.length) {
				const sep = document.createElement("span");
				sep.className = "ds-noel-ctrl-sep";
				sep.setAttribute("aria-hidden", "true");
				dock.appendChild(sep);
			}
			const audioBtn = document.createElement("button");
			audioBtn.type = "button";
			audioBtn.id = "despace-noel-audio-toggle";
			audioBtn.className = "ds-noel-ctrl-btn ds-noel-ctrl-btn--audio";
			audioBtn.innerHTML = `<span class="ds-noel-ico" aria-hidden="true">♪</span><span class="ds-noel-lbl">${labels.musicLabel || "Chime"}</span>`;

			const syncAudioBtn = () => {
				audioBtn.setAttribute("aria-pressed", audioPlaying ? "true" : "false");
				audioBtn.setAttribute("aria-label", audioPlaying ? (labels.musicOn || "Mute music") : (labels.musicOff || "Play winter chime"));
				audioBtn.setAttribute("title", audioPlaying ? (labels.musicOn || "Mute music") : (labels.musicOff || "Play winter chime"));
			};

			audioBtn.addEventListener("click", () => {
				setMusicPlaying(!audioPlaying);
				syncAudioBtn();
			});

			syncAudioBtn();
			dock.appendChild(audioBtn);
		}

		if (showPromoCtrl) {
			if (dock.children.length) {
				const sep = document.createElement("span");
				sep.className = "ds-noel-ctrl-sep";
				sep.setAttribute("aria-hidden", "true");
				dock.appendChild(sep);
			}
			const promoBtn = document.createElement("button");
			promoBtn.type = "button";
			promoBtn.className = "ds-noel-ctrl-btn ds-noel-ctrl-btn--promo";
			promoBtn.setAttribute("aria-label", labels.copyCode || "Copy holiday promo code");
			promoBtn.setAttribute("title", labels.copyCode || "Copy holiday promo code");
			promoBtn.textContent = `✦ ${cfg.promoCode}`;
			promoBtn.addEventListener("click", (e) => {
				const rect = promoBtn.getBoundingClientRect();
				copyCodeWithFeedback(cfg.promoCode, rect.left + rect.width / 2, rect.top + rect.height / 2);
			});
			dock.appendChild(promoBtn);
		}

		document.body.appendChild(dock);
	}

	/* ----------------------------------------------------------------
	   4. Live Christmas Eve Countdown (Dec 25 Auto-Target)
	   ---------------------------------------------------------------- */
	const cdRoot = document.getElementById("dsHolidayCountdown");
	if (cdRoot && cfg.countdownEnabled) {
		const elD = cdRoot.querySelector('[data-cd="days"]');
		const elH = cdRoot.querySelector('[data-cd="hours"]');
		const elM = cdRoot.querySelector('[data-cd="mins"]');
		const elS = cdRoot.querySelector('[data-cd="secs"]');

		const updateCountdown = () => {
			const now = new Date();
			let year = now.getFullYear();
			let target = new Date(year, 11, 25, 0, 0, 0);
			// On Dec 25 itself, show festive greeting!
			if (now.getMonth() === 11 && now.getDate() === 25) {
				cdRoot.innerHTML = `<span class="ds-holiday-kicker" style="margin:0;">✦ ${labels.merryNow || "Merry Christmas!"}</span>`;
				return false;
			}
			if (now > target) {
				target = new Date(year + 1, 11, 25, 0, 0, 0);
			}
			const diff = Math.max(0, target.getTime() - now.getTime());
			const days = Math.floor(diff / 86400000);
			const hours = Math.floor((diff % 86400000) / 3600000);
			const mins = Math.floor((diff % 3600000) / 60000);
			const secs = Math.floor((diff % 60000) / 1000);
			const pad = (n) => String(n).padStart(2, "0");

			if (elD) elD.textContent = pad(days);
			if (elH) elH.textContent = pad(hours);
			if (elM) elM.textContent = pad(mins);
			if (elS) elS.textContent = pad(secs);
			return true;
		};

		if (updateCountdown()) {
			window.setInterval(updateCountdown, 1000);
		}
	}

	/* ----------------------------------------------------------------
	   5. Wax-Seal Interactive Holiday Gift Unwrap
	   ---------------------------------------------------------------- */
	const giftBox = document.getElementById("dsHolidayGiftBox");
	const unwrapBtn = document.getElementById("dsHolidayUnwrapBtn");
	const giftCopyBtn = document.getElementById("dsHolidayCopyGiftBtn");

	if (giftBox) {
		try {
			if (window.sessionStorage.getItem(SESSION_GIFT_KEY) === "1") {
				giftBox.classList.add("is-unwrapped");
			}
		} catch (_) {
			// Ignore sessionStorage errors.
		}

		if (unwrapBtn) {
			unwrapBtn.addEventListener("click", () => {
				giftBox.classList.add("is-unwrapped");
				try {
					window.sessionStorage.setItem(SESSION_GIFT_KEY, "1");
				} catch (_) {
					// Ignore.
				}
				const rect = giftBox.getBoundingClientRect();
				if (typeof spawnStarlightBurst === "function") {
					spawnStarlightBurst(rect.left + rect.width / 2, rect.top + rect.height / 2);
				}
				showHolidayToast(labels.unwrapped || "✦ Holiday privilege unwrapped!");
			});
		}

		if (giftCopyBtn) {
			giftCopyBtn.addEventListener("click", () => {
				const code = giftCopyBtn.getAttribute("data-code") || cfg.promoCode;
				const rect = giftCopyBtn.getBoundingClientRect();
				copyCodeWithFeedback(code, rect.left + rect.width / 2, rect.top + rect.height / 2);
			});
		}
	}
})();
