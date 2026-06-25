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

	// Honour the OS "reduce motion" setting: fold/unfold instantly instead of animating.
	var REDUCE = false;
	try { REDUCE = window.matchMedia("(prefers-reduced-motion: reduce)").matches; } catch (e) {}

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
		// Dolibarr version, shown as a small badge right after the app name
		var verEl = $(".aversion");
		var verText = verEl ? verEl.textContent.replace(/\s+/g, " ").trim() : "";
		if (verText) {
			var ver = el("span", "fz-version", "");
			ver.textContent = "v" + verText.replace(/^v/i, "");
			ver.title = "Dolibarr " + verText;
			brand.appendChild(ver);
		}
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
			var asub = activeGroup.querySelector(".fz-sub");
			fillSubFromRoot(asub, document);
			setupSubAccordion(asub);
			openActiveSub(asub);
			preloadAll(asub);
			activeGroup.classList.add("fz-active");
			setGroupOpen(activeGroup, true, false); // open instantly, no load-time flash
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
		// the "other" top-right tools (module builder, print, help, and any plugin
		// icons added via the printTopRightMenu hook) are .login_block_elem / .login,
		// not .dropdown, so they were dropped. Bring them too — but keep the logout
		// (it goes to the footer) and the version label out of the icon row.
		var loginOther = $(".login_block_other");
		if (loginOther) {
			Array.prototype.slice.call(loginOther.children).forEach(function (n) {
				if (n.nodeType !== 1 || n.closest("#fz-tools")) return;
				if (n.classList.contains("logout-btn") || n.querySelector("a[href*='logout']")) return;
				if (n.querySelector(".aversion")) return; // version label, not an icon
				extraSlot.appendChild(n);
			});
		}

		// Dolibarr core hard-codes target="modulebuilder" on the module builder
		// link (main.inc.php) — open it in the SAME tab instead.
		var mb = extraSlot.querySelector("a[href*='modulebuilder']");
		if (mb) mb.removeAttribute("target");

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
		// The collapsed PREFERENCE lives in localStorage; the .fz-collapsed class can be
		// momentarily lifted by the hover-peek below, so toggle from the stored value.
		function prefersCollapsed() { try { return localStorage.getItem("fz-collapsed") === "1"; } catch (e) { return false; } }
		function setCollapsed(collapsed) {
			ROOT.classList.toggle("fz-collapsed", collapsed);
			ROOT.classList.remove("fz-peek", "fz-search-open");
			collapseBtn.setAttribute("aria-expanded", collapsed ? "false" : "true");
			try { localStorage.setItem("fz-collapsed", collapsed ? "1" : "0"); } catch (e) {}
		}
		collapseBtn.addEventListener("click", function () { setCollapsed(!prefersCollapsed()); });

		// Click-to-peek : on a collapsed rail (desktop), clicking a nav section expands
		// it as an overlay and opens that submenu (wired in the nav click handler below).
		// It re-collapses when you navigate to a child page (page reload restores the
		// pref), press Escape, or click outside the rail.
		var peekWide = window.matchMedia("(min-width: 921px)");
		function endPeek() {
			if (!ROOT.classList.contains("fz-peek")) return;
			ROOT.classList.remove("fz-peek", "fz-search-open");
			if (prefersCollapsed()) ROOT.classList.add("fz-collapsed");
		}
		document.addEventListener("mousedown", function (e) {
			if (!ROOT.classList.contains("fz-peek")) return;
			if (e.target.closest && e.target.closest("#fz-sidebar")) return; // incl. the fixed collapse btn (DOM child)
			endPeek();
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
			if (e.key === "Escape") { ROOT.classList.remove("fz-drawer", "fz-search-open"); endPeek(); }
		});

		// Main-menu click: expand the submenu instead of navigating. The active
		// section already has its submenu in the DOM; other sections are fetched
		// on demand (their left menu only exists server-side for the open section).
		nav.addEventListener("click", function (ev) {
			// sub-menu accordion (any depth): a collapsible head toggles its own panel and
			// collapses the other open heads at the same level.
			var subhead = ev.target.closest(".fz-subhead");
			if (subhead) {
				var pnl = subhead.nextElementSibling;
				if (pnl && pnl.classList && pnl.classList.contains("fz-subsub")) {
					ev.preventDefault();
					var willOpen = !subhead.classList.contains("fz-subopen");
					if (willOpen) {
						var sibs;
						if (subhead.classList.contains("menu_titre")) {
							sibs = $all(".menu_titre.fz-subhead.fz-subopen", subhead.closest(".fz-sub"));
						} else {
							var pp = subhead.parentNode;
							sibs = $all(".fz-subhead.fz-subopen", pp).filter(function (o) { return o.parentNode === pp; });
						}
						sibs.forEach(function (o) {
							if (o === subhead) return;
							o.classList.remove("fz-subopen");
							var op = o.nextElementSibling;
							if (op && op.classList && op.classList.contains("fz-subsub")) animatePanel(op, false, true);
						});
					}
					subhead.classList.toggle("fz-subopen", willOpen);
					animatePanel(pnl, willOpen, true);
					return;
				}
			}
			var head = ev.target.closest(".fz-head");
			if (!head) return;
			if (head.getAttribute("target")) return; // opens elsewhere -> let it through
			var group = head.closest(".fz-group");
			if (!group) return;
			var sub = group.querySelector(".fz-sub");

			// Collapsed rail (desktop): the first click EXPANDS the rail as an overlay and
			// opens this section's submenu; clicking a child link then navigates and the
			// page reload restores the collapsed pref. (Once expanded we fall through to
			// the normal accordion below.)
			if (peekWide.matches && ROOT.classList.contains("fz-collapsed")) {
				ev.preventDefault();
				ROOT.classList.remove("fz-collapsed");
				ROOT.classList.add("fz-peek");
				if (sub && sub.children.length) { closeOtherGroups(group); setGroupOpen(group, true, false); }
				else { var h = head.getAttribute("href"); if (h && h !== "#") loadSub(group, sub, h); }
				return;
			}

			if (sub && sub.children.length) {
				ev.preventDefault();
				var willOpen = !group.classList.contains("fz-open");
				if (willOpen) closeOtherGroups(group);
				setGroupOpen(group, willOpen, true);
				return;
			}
			var href = head.getAttribute("href");
			if (!href || href === "#") return;
			ev.preventDefault();
			loadSub(group, sub, href);
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

	// Move every left-menu (.vmenu) block found under `root` into the group's
	// submenu container `sub`. `root` is the live document for the active section,
	// or a parsed AJAX document for sections loaded on demand. Search/bookmarks/
	// help blocks are skipped (they live elsewhere in the shell). Returns true if
	// at least one entry was added.
	function fillSubFromRoot(sub, root) {
		var vmenu = root.querySelector(".vmenu");
		if (vmenu) {
			$all(".blockvmenu", vmenu).forEach(function (b) {
				if (b.id === "blockvmenusearch" || b.id === "blockvmenubookmarks") return;
				sub.appendChild(b);
			});
		}
		// also catch any stray vmenu blocks not matched above
		$all("#id-left .vmenu > div", root).forEach(function (b) {
			if (/blockvmenusearch|blockvmenubookmarks|blockvmenuhelp/.test(b.id || "")) return;
			if (b.parentNode === sub) return;
			if (b.classList.contains("blockvmenu") || b.classList.contains("blockvmenuend")) sub.appendChild(b);
		});
		return sub.children.length > 0;
	}

	// Fold/unfold a group's submenu with a height animation. CSS can't animate to
	// max-height:auto, so we drive the pixel height from scrollHeight and hand back
	// to "none" once open (lets long menus grow freely). `animate=false` is used for
	// the initial active section so it doesn't flash on page load.
	function setGroupOpen(group, open, animate) {
		var sub = group.querySelector(".fz-sub");
		if (!sub) { group.classList.toggle("fz-open", open); return; }
		// drop any pending end-handler from a previous, still-running animation
		if (sub._fzEnd) { sub.removeEventListener("transitionend", sub._fzEnd); sub._fzEnd = null; }
		if (!animate || REDUCE) {
			group.classList.toggle("fz-open", open);
			sub.style.maxHeight = open ? "none" : "0px";
			return;
		}
		if (open) {
			group.classList.add("fz-open");
			sub.style.maxHeight = sub.scrollHeight + "px";
			sub._fzEnd = function (e) {
				if (e.target !== sub || e.propertyName !== "max-height") return;
				sub.style.maxHeight = "none"; // release the cap so the menu can grow
				sub.removeEventListener("transitionend", sub._fzEnd); sub._fzEnd = null;
			};
			sub.addEventListener("transitionend", sub._fzEnd);
		} else {
			// from "none" -> fixed px (reflow) -> 0 so the collapse has a start height
			sub.style.maxHeight = sub.scrollHeight + "px";
			void sub.offsetHeight;
			group.classList.remove("fz-open");
			sub.style.maxHeight = "0px";
		}
	}

	// Generic max-height fold/unfold for any panel (used by the sub-sub accordion).
	function animatePanel(panel, open, animate) {
		if (!panel) return;
		if (panel._fzEnd) { panel.removeEventListener("transitionend", panel._fzEnd); panel._fzEnd = null; }
		if (!animate || REDUCE) { panel.style.maxHeight = open ? "none" : "0px"; return; }
		if (open) {
			panel.style.maxHeight = panel.scrollHeight + "px";
			panel._fzEnd = function (e) {
				if (e.target !== panel || e.propertyName !== "max-height") return;
				panel.style.maxHeight = "none";
				panel.removeEventListener("transitionend", panel._fzEnd); panel._fzEnd = null;
			};
			panel.addEventListener("transitionend", panel._fzEnd);
		} else {
			panel.style.maxHeight = panel.scrollHeight + "px";
			void panel.offsetHeight;
			panel.style.maxHeight = "0px";
		}
	}

	// One left-menu node's depth, from the leading &nbsp; eldy adds (3 per level).
	function levelOf(item) {
		var lead = ((item.textContent || "").match(/^[\u00a0\s]*/) || [""])[0];
		return Math.floor((lead.match(/\u00a0/g) || []).length / 3) + 1; // 1-based
	}
	function rowLevel(row) { return row.classList.contains("menu_titre") ? 0 : levelOf(row); }
	function titleOf(row) {
		var a = row.querySelector("a, span");
		return ((a ? a.textContent : row.textContent) || "").replace(/[\u00a0\s]+/g, " ").trim();
	}

	// Turn an element into a collapsible header: toggle class + chevron + initial state.
	function makeHead(header, open) {
		header.classList.add("fz-subhead");
		header.classList.toggle("fz-subopen", !!open);
		if (!header.querySelector(".fz-subchev")) {
			header.appendChild(el("span", "fz-subchev", '<i class="fas fa-chevron-right"></i>'));
		}
	}

	// Build a RECURSIVE collapsible tree: re-nest the flat rows `items` under `header`
	// (whose own depth is `hl`) so every node with deeper children becomes its own
	// collapsible head. Everything starts collapsed.
	function buildTreeUnder(header, hl, items) {
		var rootPanel = el("div", "fz-subsub");
		header.parentNode.insertBefore(rootPanel, header.nextSibling);
		makeHead(header, false);
		rootPanel.style.maxHeight = "0px";
		var stack = [{ level: hl, header: header, panel: rootPanel }];
		items.forEach(function (item) {
			var L = rowLevel(item);
			while (stack.length > 1 && stack[stack.length - 1].level >= L) stack.pop();
			var parent = stack[stack.length - 1];
			if (!parent.panel) {
				parent.panel = el("div", "fz-subsub");
				parent.header.parentNode.insertBefore(parent.panel, parent.header.nextSibling);
				makeHead(parent.header, false);
				parent.panel.style.maxHeight = "0px";
			}
			parent.panel.appendChild(item);
			stack.push({ level: L, header: item, panel: null });
		});
	}

	// Sections whose children are ALREADY in the page (the active branch).
	function setupSubAccordion(sub) {
		$all(".blockvmenu", sub).forEach(function (block) {
			if (block.classList.contains("fz-hassub")) return;
			var titre = block.querySelector(".menu_titre");
			var items = $all(".menu_contenu", block);
			if (titre && items.length) { block.classList.add("fz-hassub"); buildTreeUnder(titre, 0, items); }
		});
	}

	// Open the chain of collapsible heads leading to the current page.
	function openHead(h) {
		h.classList.add("fz-subopen");
		var pn = h.nextElementSibling;
		if (pn && pn.classList && pn.classList.contains("fz-subsub")) animatePanel(pn, true, false);
	}
	function openActiveSub(sub) {
		var full = location.pathname + location.search, links = $all("a[href]", sub), active = null;
		for (var i = 0; i < links.length && !active; i++) if ((links[i].pathname + links[i].search) === full) active = links[i];
		if (!active) for (var k = 0; k < links.length && !active; k++) if (links[k].pathname === location.pathname) active = links[k];
		if (!active) return;
		var ownHead = active.closest(".fz-subhead");
		if (ownHead) openHead(ownHead);
		var pnl = active.closest(".fz-subsub");
		while (pnl) {
			var h = pnl.previousElementSibling;
			if (h && h.classList && h.classList.contains("fz-subhead")) openHead(h);
			pnl = h ? h.closest(".fz-subsub") : null;
		}
	}

	// Pull a node's child rows out of its fetched page (matched by title + depth).
	function childrenFromDoc(doc, title, baseLevel) {
		var blocks = $all(".vmenu .blockvmenu", doc);
		for (var bi = 0; bi < blocks.length; bi++) {
			var rows = $all(".menu_titre, .menu_contenu", blocks[bi]);
			for (var ri = 0; ri < rows.length; ri++) {
				if (rowLevel(rows[ri]) !== baseLevel || titleOf(rows[ri]) !== title) continue;
				var kids = [];
				for (var rj = ri + 1; rj < rows.length; rj++) {
					if (rowLevel(rows[rj]) <= baseLevel) break;
					kids.push(rows[rj]);
				}
				if (kids.length) return kids;
			}
		}
		return [];
	}

	// Background fetch queue (limited concurrency) + per-session cache, so the recursive
	// preload doesn't hammer the server and is reused across navigations.
	var FZ_MAX = 4, fzActive = 0, fzQueue = [];
	function fzCacheGet(k) { try { return sessionStorage.getItem("fzpm:" + k); } catch (e) { return null; } }
	function fzCacheSet(k, v) { try { sessionStorage.setItem("fzpm:" + k, v); } catch (e) {} }
	function fzPump() {
		while (fzActive < FZ_MAX && fzQueue.length) {
			var job = fzQueue.shift();
			fzActive++;
			fetch(job.href, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
				.then(function (r) { return r.text(); }).then(job.done)
				.catch(function () {}).then(function () { fzActive--; fzPump(); });
		}
	}

	// Recursively preload EVERY collapsible node under `container` so all chevrons show
	// and every level opens instantly. Leaves are tried once and left as plain links.
	function preloadAll(container) {
		$all(".menu_titre, .menu_contenu", container).forEach(function (row) {
			if (row._fzTried || row.classList.contains("fz-subhead")) return;
			var link = row.querySelector("a[href]");
			var href = link && link.getAttribute("href");
			if (!href || href === "#") return;
			row._fzTried = true;
			var title = titleOf(row), lvl = rowLevel(row), key = href + "\n" + title + "\n" + lvl;
			function build(kids) {
				if (row.classList.contains("fz-subhead") || !kids.length) return;
				buildTreeUnder(row, lvl, kids);
				preloadAll(row.nextElementSibling);
			}
			var cached = fzCacheGet(key);
			if (cached !== null) {
				if (cached) {
					var d = new DOMParser().parseFromString("<ul class=\"vmenu\"><div class=\"blockvmenu\">" + cached + "</div></ul>", "text/html");
					build($all(".menu_titre, .menu_contenu", d));
				}
				return;
			}
			fzQueue.push({ href: href, done: function (htmlText) {
				var doc = new DOMParser().parseFromString(htmlText, "text/html");
				var kids = childrenFromDoc(doc, title, lvl);
				fzCacheSet(key, kids.map(function (k) { return k.outerHTML; }).join(""));
				build(kids);
			} });
			fzPump();
		});
	}

	// Accordion: keep a single section unfolded at a time by collapsing every
	// other open group (the one passed in is left untouched).
	function closeOtherGroups(keep) {
		$all(".fz-group.fz-open").forEach(function (g) {
			if (g !== keep) setGroupOpen(g, false, true);
		});
	}

	// Fetch a section's page in the background, lift its left menu into the group's
	// submenu and open it — no full navigation. Falls back to navigating when the
	// section has no submenu of its own or the request fails.
	function loadSub(group, sub, href) {
		if (group.classList.contains("fz-loading")) return;
		group.classList.add("fz-loading");
		fetch(href, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
			.then(function (r) { return r.text(); })
			.then(function (html) {
				var doc = new DOMParser().parseFromString(html, "text/html");
				group.classList.remove("fz-loading");
				if (fillSubFromRoot(sub, doc)) { setupSubAccordion(sub); openActiveSub(sub); preloadAll(sub); closeOtherGroups(group); setGroupOpen(group, true, true); }
				else window.location.href = href;
			})
			.catch(function () {
				group.classList.remove("fz-loading");
				window.location.href = href;
			});
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
