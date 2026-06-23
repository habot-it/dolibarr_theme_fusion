/*
 * Fusion theme — client-side menu merge.
 *
 * Dolibarr renders two separate menus (horizontal top + vertical left) plus a
 * top-right tools block. This script restructures them, with NO core change,
 * into a single responsive navigation:
 *   - landscape : one retractable left sidebar (full <-> icons only)
 *   - portrait  : one top bar with a hamburger that opens the same menu (drawer)
 *
 * It reuses Dolibarr's own DOM nodes (it MOVES them) so every link, dropdown,
 * tooltip and permission stays intact.
 *
 * Companion stylesheet: theme/fusion/style.css.php
 */
(function () {
	"use strict";

	// Engage the CSS shell immediately (documentElement exists during <head> parse)
	// so the legacy menus are hidden before they can flash on screen.
	var ROOT = document.documentElement;
	ROOT.classList.add("fusion");

	// Restore persisted preferences early (collapsed state + color mode).
	try {
		if (localStorage.getItem("fz-collapsed") === "1") ROOT.classList.add("fz-collapsed");
		var savedMode = localStorage.getItem("fz-mode") || "auto";
		ROOT.setAttribute("data-fz-mode", savedMode);
	} catch (e) { ROOT.setAttribute("data-fz-mode", "auto"); }

	// FontAwesome 5 icon for each known main-menu code (Dolibarr ships FA5, not FA6).
	var ICONS = {
		home: "fa-home", companies: "fa-building", thirdparties: "fa-building",
		products: "fa-box", commercial: "fa-shopping-cart", billing: "fa-file-invoice-dollar",
		accountancy: "fa-calculator", bank: "fa-university", project: "fa-project-diagram",
		hrm: "fa-users-cog", agenda: "fa-calendar-alt", members: "fa-users",
		ticket: "fa-life-ring", tools: "fa-tools", website: "fa-globe",
		mrp: "fa-industry", knowledgemanagement: "fa-book", eventorganization: "fa-calendar-check",
		cron: "fa-clock", externalsite: "fa-external-link-alt", ecm: "fa-folder-open"
	};

	function $(sel, ctx) { return (ctx || document).querySelector(sel); }
	function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
	function el(tag, cls, html) {
		var e = document.createElement(tag);
		if (cls) e.className = cls;
		if (html != null) e.innerHTML = html;
		return e;
	}

	function build() {
		// Abort gracefully on pages without the standard menu (login, popups, …).
		var tmenu = $("ul.tmenu");
		var idRight = $("#id-right") || $("#id-container");
		if (!tmenu || !idRight) { ROOT.classList.remove("fusion"); return; }
		if ($("#fz-sidebar")) return; // already built

		var body = document.body;

		// ---- 1. Build the shell ------------------------------------------------
		var sidebar = el("aside"); sidebar.id = "fz-sidebar";
		var topbar = el("div"); topbar.id = "fz-topbar";
		var scrim = el("div"); scrim.id = "fz-scrim";

		// Application name (passed from the theme via a CSS custom property)
		var appName = (getComputedStyle(ROOT).getPropertyValue("--fz-appname") || "").trim().replace(/^["']|["']$/g, "");
		if (!appName) appName = "Dolibarr";

		// Brand bar (reuse company logo if present in the top menu)
		var brand = el("div"); brand.id = "fz-brand";
		var logoImg = $(".menulogocontainer img.mycompany");
		// logo is a link to the home page (real "Home" menu URL when available)
		var homeA = document.querySelector("#mainmenutd_home a[href]");
		var logo = el("a", "fz-logo" + (logoImg ? " has-logo" : ""));
		logo.setAttribute("href", homeA ? homeA.getAttribute("href") : "/index.php?mainmenu=home");
		logo.title = appName;
		if (logoImg) {
			var img = logoImg.cloneNode(false);
			img.removeAttribute("id");
			img.setAttribute("alt", "");
			logo.appendChild(img);
		} else {
			logo.textContent = appName.charAt(0).toUpperCase();
		}
		brand.appendChild(logo);
		var brandName = el("div", "fz-brand-name");
		brandName.textContent = appName;
		brandName.title = appName;
		brand.appendChild(brandName);
		var collapseBtn = el("button", "fz-collapse", '<i class="fas fa-angle-double-left"></i>');
		collapseBtn.type = "button";
		collapseBtn.title = "Réduire / agrandir le menu";
		collapseBtn.setAttribute("aria-label", collapseBtn.title);
		collapseBtn.setAttribute("aria-expanded", ROOT.classList.contains("fz-collapsed") ? "false" : "true");
		brand.appendChild(collapseBtn);

		// Tools row : a loupe button (visible only when collapsed), the search
		// field (grows), and the icon dropdowns (+, star, import…) pushed right.
		var tools = el("div"); tools.id = "fz-tools";
		var searchToggle = el("button", "", '<i class="fas fa-search"></i>');
		searchToggle.id = "fz-search-toggle"; searchToggle.type = "button"; searchToggle.title = "Rechercher";
		searchToggle.setAttribute("aria-label", "Rechercher");
		var searchSlot = el("div", "fz-tools-search");
		var extraSlot = el("div", "fz-tools-extra");
		tools.appendChild(searchToggle);
		tools.appendChild(searchSlot);
		tools.appendChild(extraSlot);

		var nav = el("nav"); nav.id = "fz-nav";
		nav.appendChild(el("div", "fz-sec-label", "Navigation"));

		var foot = el("div"); foot.id = "fz-foot";

		sidebar.appendChild(brand);
		sidebar.appendChild(tools);
		sidebar.appendChild(nav);
		sidebar.appendChild(foot);

		// Top bar (portrait)
		topbar.appendChild((function () {
			var b = el("button", "fz-burger", '<i class="fas fa-bars"></i>');
			b.type = "button"; b.id = "fz-burger"; return b;
		})());
		topbar.appendChild(el("div", "fz-tb-title", appName));

		body.appendChild(topbar);
		body.appendChild(scrim);
		body.appendChild(sidebar);

		// ---- 2. Populate navigation from the horizontal top menu ---------------
		var activeGroup = null;
		Array.prototype.slice.call(tmenu.children).forEach(function (li) {
			if (li.tagName !== "LI") return;
			if (li.classList.contains("tmenucompanylogo") || li.classList.contains("tmenuend")) return;
			var m = (li.id || "").match(/mainmenutd_(.+)$/);
			var code = m ? m[1] : "";
			var labelSpan = li.querySelector(".mainmenuaspan");
			var label = labelSpan ? labelSpan.textContent.trim()
				: (li.querySelector("a") ? (li.querySelector("a").getAttribute("title") || "").trim() : "");
			var linkA = li.querySelector("a.tmenulabel") || li.querySelector("a");
			var href = linkA ? linkA.getAttribute("href") : null;
			if (!label && !href) return;
			if (href === "#" || (linkA && /size12x|fa-bars/.test(linkA.innerHTML)) && !label) return; // skip "all modules" toggle

			var group = el("div", "fz-group");
			group.setAttribute("data-code", code);

			var head = el("a", "fz-head");
			head.setAttribute("data-fzlabel", label);
			if (href) head.setAttribute("href", href);
			var target = linkA ? linkA.getAttribute("target") : null;
			if (target) head.setAttribute("target", target);

			head.appendChild(el("span", "fz-ic", iconFor(code, li)));
			head.appendChild(el("span", "fz-label", "")).textContent = label;
			head.appendChild(el("span", "fz-chev", '<i class="fas fa-chevron-right"></i>'));

			group.appendChild(head);
			group.appendChild(el("div", "fz-sub"));
			nav.appendChild(group);

			if (li.classList.contains("tmenusel")) activeGroup = group;
		});

		// ---- 3. Move the active section's left submenu under its group ---------
		var vmenu = $(".vmenu");
		var bookmarksBlock = vmenu ? vmenu.querySelector("#blockvmenubookmarks") : null;
		var helpBlock = vmenu ? vmenu.querySelector("#blockvmenuhelp") : null;

		if (vmenu && activeGroup) {
			var sub = activeGroup.querySelector(".fz-sub");
			$all(".blockvmenu", vmenu).forEach(function (b) {
				if (b.id === "blockvmenusearch" || b.id === "blockvmenubookmarks") return;
				sub.appendChild(b);
			});
			// also catch any stray vmenu blocks not matched above
			$all("#id-left .vmenu > div", document).forEach(function (b) {
				if (/blockvmenusearch|blockvmenubookmarks|blockvmenuhelp/.test(b.id || "")) return;
				if (b.parentNode === sub) return;
				if (b.classList.contains("blockvmenu") || b.classList.contains("blockvmenuend")) sub.appendChild(b);
			});
			activeGroup.classList.add("fz-open", "fz-active");
		}

		// ---- 4. Favorites / bookmark section ----------------------------------
		if (bookmarksBlock) {
			var favWrap = el("div"); favWrap.id = "fz-fav";
			favWrap.appendChild(el("div", "fz-sec-label", "Favoris"));
			favWrap.appendChild(bookmarksBlock);
			nav.appendChild(favWrap);
		}

		// ---- 5. Tools : the NATIVE Dolibarr search. By default it asks which scope
		// to search ("Search into …"); a plugin like ATM turns it into a global
		// search via the searchform hook. Both live inside #blockvmenusearch, so
		// relocating that block is plugin-agnostic. No search => drop slot + loupe.
		var nativeSearch = $("#topmenu-global-search-dropdown") || $("#blockvmenusearch");
		if (nativeSearch) {
			searchSlot.appendChild(nativeSearch);
		} else {
			searchSlot.remove();
			searchToggle.remove();
		}
		// every remaining tool dropdown (quick-add, bookmark, import, module tools)
		var loginTools = $(".login_block_tools");
		if (loginTools) {
			$all(".dropdown", loginTools).forEach(function (n) {
				if (n.closest("#fz-tools")) return;       // already moved
				if (n.parentNode && n.parentNode.closest(".dropdown")) return; // nested
				extraSlot.appendChild(n);
			});
		}

		// ---- 6. Footer : help/version + user dropdown -------------------------
		var userBlock = $(".login_block_user");
		var logoutBlock = $(".login_block_other .logout-btn, .login_block_other a[href*='logout']");
		if (helpBlock) foot.appendChild(helpBlock);
		// color-mode segmented control (its own row)
		foot.appendChild(makeModeToggle());
		var userWrap = el("div"); userWrap.id = "fz-user";
		if (userBlock) userWrap.appendChild(userBlock);
		if (logoutBlock && !userWrap.contains(logoutBlock)) {
			var lb = logoutBlock.closest(".login_block_elem") || logoutBlock;
			userWrap.appendChild(lb);
		}
		foot.appendChild(userWrap);

		// ---- 7. Wire interactions ---------------------------------------------
		collapseBtn.addEventListener("click", function () {
			ROOT.classList.toggle("fz-collapsed");
			ROOT.classList.remove("fz-search-open");
			collapseBtn.setAttribute("aria-expanded", ROOT.classList.contains("fz-collapsed") ? "false" : "true");
			try { localStorage.setItem("fz-collapsed", ROOT.classList.contains("fz-collapsed") ? "1" : "0"); } catch (e) {}
		});
		$("#fz-burger").addEventListener("click", function () { ROOT.classList.add("fz-drawer"); });
		scrim.addEventListener("click", function () { ROOT.classList.remove("fz-drawer"); });

		// Collapsed mode : the loupe opens the search field as an animated flyout
		searchToggle.addEventListener("click", function (e) {
			e.stopPropagation();
			// close any open +/star/import dropdown so it doesn't overlap the search flyout
			$all("#fz-tools .dropdown.open").forEach(function (d) { d.classList.remove("open"); });
			ROOT.classList.toggle("fz-search-open");
			if (ROOT.classList.contains("fz-search-open")) {
				var inp = document.getElementById("top-global-search-input") || searchSlot.querySelector("input");
				if (inp) setTimeout(function () { inp.focus(); }, 90);
			}
		});
		// opening a tool dropdown (+, star, …) closes the search flyout
		extraSlot.addEventListener("click", function () { ROOT.classList.remove("fz-search-open"); });
		document.addEventListener("mousedown", function (e) {
			if (!ROOT.classList.contains("fz-search-open")) return;
			if (e.target.closest && e.target.closest("#fz-tools")) return;
			ROOT.classList.remove("fz-search-open");
		});
		document.addEventListener("keydown", function (e) {
			if (e.key === "Escape") ROOT.classList.remove("fz-drawer", "fz-search-open");
		});

		// group expand/collapse (only toggle when it has a loaded submenu; otherwise just navigate)
		nav.addEventListener("click", function (ev) {
			var chev = ev.target.closest(".fz-chev");
			var head = ev.target.closest(".fz-head");
			if (!head) return;
			var group = head.closest(".fz-group");
			var hasSub = group && group.querySelector(".fz-sub") && group.querySelector(".fz-sub").children.length > 0;
			if (chev && hasSub) { ev.preventDefault(); group.classList.toggle("fz-open"); return; }
			// no loaded submenu -> let the link navigate (server will render that section)
		});

		// keep the page title in the portrait top bar in sync
		var tbTitle = topbar.querySelector(".fz-tb-title");
		if (activeGroup) tbTitle.textContent = activeGroup.querySelector(".fz-label").textContent;
	}

	function iconFor(code, li) {
		// 1) reuse an explicit FA glyph provided by the module's menu entry
		var glyph = li.querySelector("i.fa, i.fas, i.far, i.fab, span.fa, span.fas");
		if (glyph) {
			var classes = (glyph.getAttribute("class") || "").match(/\bfa-[\w-]+/g);
			if (classes && classes.length) return '<i class="fas ' + classes.join(" ") + '"></i>';
		}
		// 2) known code
		if (ICONS[code]) return '<i class="fas ' + ICONS[code] + '"></i>';
		// 3) fallback
		return '<i class="fas fa-puzzle-piece"></i>';
	}

	function makeFallbackSearch() {
		// Minimal search that posts to Dolibarr's global search page.
		var form = el("form", "fz-fallback-search");
		form.setAttribute("action", (window.DOL_URL_ROOT || "") + "/core/search_page.php");
		form.setAttribute("method", "GET");
		form.innerHTML = '<span class="fas fa-search fz-fallback-search-icon"></span>'
			+ '<input name="search_all" placeholder="Rechercher…" autocomplete="off" '
			+ '>';
		return form;
	}

	function makeModeToggle() {
		var wrap = el("div", "fz-modes");
		var modes = [
			{ k: "light", i: "fa-sun", t: "Clair" },
			{ k: "auto", i: "fa-adjust", t: "Auto" },
			{ k: "dark", i: "fa-moon", t: "Sombre" }
		];
		var current = ROOT.getAttribute("data-fz-mode") || "auto";
		modes.forEach(function (m) {
			var b = el("button", "fz-mode-btn" + (m.k === current ? " is-active" : ""),
				'<i class="fas ' + m.i + '"></i><span class="fz-mode-lbl">' + m.t + '</span>');
			b.type = "button"; b.title = "Mode " + m.t; b.setAttribute("data-mode", m.k);
			b.addEventListener("click", function () {
				ROOT.setAttribute("data-fz-mode", m.k);
				try { localStorage.setItem("fz-mode", m.k); } catch (e) {}
				$all(".fz-mode-btn", wrap).forEach(function (x) { x.classList.remove("is-active"); });
				b.classList.add("is-active");
			});
			wrap.appendChild(b);
		});
		return wrap;
	}

	function init() {
		try {
			build();
		} catch (e) {
			ROOT.classList.remove("fusion", "fz-collapsed", "fz-drawer", "fz-search-open");
			if (window.console && console.error) console.error("Fusion theme failed to initialize", e);
		}
	}

	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
	else init();
})();
