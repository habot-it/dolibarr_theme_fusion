/*
 * Fusion theme — configurable dashboard grid.
 *
 * Dolibarr renders its widgets ("boxes") into two hard-coded columns:
 * .twocolumns > #boxhalfleft + #boxhalfright, each box being a
 * <div class="box divboxtable boxdraggable" id="boxto_<id>">.
 *
 * This script replaces that area, with NO core change, by the Home Assistant model:
 *
 *   row     an independent band, twelve grid tracks wide (HA's "section")
 *   widget  one Dolibarr box, carrying its own width (1..12 tracks) and height
 *
 * There is no column object. Widgets simply flow inside their row and wrap when
 * they no longer fit, so two widgets sitting one above the other is just what the
 * widths produce — the same way HA lays out its cards. Sizing a widget is
 * therefore the only structural gesture: everything else follows from it.
 *
 * Widgets are MOVED, never rebuilt, so every link, graph, tooltip and permission
 * stays intact.
 *
 * The layout is stored per user and per zone through theme/fusion/dashboard.php
 * (llx_user_param), with localStorage as an instant cache so the grid renders
 * before the round trip. Dolibarr's own llx_boxes stays in sync for the part it
 * can express (which widgets are active, and a two-column reading order), so
 * disabling the theme brings back a sane native page.
 *
 * Loaded on demand by fusion.js, only on pages that render the widget area.
 * Companion stylesheet: theme/fusion/style.css.php
 */
(function () {
	"use strict";

	var ROOT = document.documentElement;
	var SELF = document.currentScript;
	var COLS = 12;             // a row is twelve tracks wide, like HA's grid
	var DEFAULT_SPAN = 6;      // a new widget takes half a row (the native two-column feel)
	var WORKBOARD_SPAN = 12;   // the working board is a wide summary, never a half row
	var MIN_HEIGHT = 120;
	var MAX_HEIGHT = 4000;
	var ROW_UNIT = 60;         // one row of the layout picker, in pixels
	var PICKER_ROWS = 8;
	var SAVE_DELAY = 500;
	var MASONRY_UNIT = 8;
	var MASONRY_GAP = 16;
	var masonryObserved = new WeakSet();
	var masonryObserver = null;
	var masonryScheduled = false;

	function $(sel, ctx) { return (ctx || document).querySelector(sel); }
	function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
	function el(tag, cls, html) {
		var e = document.createElement(tag);
		if (cls) e.className = cls;
		if (html != null) e.innerHTML = html;
		return e;
	}
	function button(cls, icon, label) {
		var b = el("button", cls, '<i class="fas ' + icon + '"></i>');
		b.type = "button";
		b.setAttribute("aria-label", label);
		b.setAttribute("title", label);
		return b;
	}
	function storageGet(key) {
		try { return window.localStorage.getItem(key); } catch (e) { return null; }
	}
	function storageSet(key, value) {
		try { window.localStorage.setItem(key, value); } catch (e) {}
	}
	// Same PHP -> CSS -> JS channel as the rest of the theme: labels are exported
	// by style.css.php as --fz-t-* custom properties.
	function tr(key, fallback) {
		var value = "";
		try {
			value = getComputedStyle(ROOT).getPropertyValue("--fz-t-" + key).trim();
		} catch (e) {}
		if (!value) return fallback;
		return value.replace(/^["']|["']$/g, "") || fallback;
	}
	function clampSpan(value) {
		value = parseInt(value, 10);
		if (!isFinite(value)) value = DEFAULT_SPAN;
		return Math.max(1, Math.min(COLS, value));
	}
	function clampHeight(value) {
		value = parseInt(value, 10);
		if (!isFinite(value) || value <= 0) return 0; // 0 = automatic height
		return Math.max(MIN_HEIGHT, Math.min(MAX_HEIGHT, value));
	}
	function done() { ROOT.classList.remove("fz-dash-boot"); }

	// Resizing a widget changes the box a chart was drawn into, and a canvas does not
	// reflow on its own. Every charting library redraws on the window's resize event,
	// so raise one: that is what makes the CONTENT follow the size, instead of just
	// being clipped by it.
	function notifyResize() {
		try {
			window.dispatchEvent(new Event("resize"));
		} catch (e) {
			try {
				var legacy = document.createEvent("Event");
				legacy.initEvent("resize", true, true);
				window.dispatchEvent(legacy);
			} catch (ignore) {}
		}
		scheduleMasonry();
	}

	// CSS Grid does not provide cross-browser masonry yet. Give each dashboard
	// cell a span over tiny implicit rows; `grid-auto-flow:dense` can then place a
	// following widget in the free space below a shorter neighbour.
	function layoutMasonry(root) {
		root = root || document;
		$all(root === document ? "#fz-dash .fz-dash-cell" : ".fz-dash-cell", root).forEach(function (cell) {
			var height = cell.getBoundingClientRect().height;
			if (!height) return;
			cell.style.gridRowEnd = "span " + Math.max(1, Math.ceil((height + MASONRY_GAP) / MASONRY_UNIT));
		});
	}

	function scheduleMasonry(root) {
		if (masonryScheduled) return;
		masonryScheduled = true;
		window.requestAnimationFrame(function () {
			masonryScheduled = false;
			layoutMasonry(root || document);
		});
	}

	function watchMasonry(dash) {
		if (typeof ResizeObserver !== "undefined" && !masonryObserver) {
			masonryObserver = new ResizeObserver(function () { scheduleMasonry(dash); });
		}
		$all(".fz-dash-cell", dash).forEach(function (cell) {
			if (masonryObserved.has(cell)) return;
			masonryObserved.add(cell);
			if (masonryObserver) masonryObserver.observe(cell);
		});
		scheduleMasonry(dash);
	}

	/* ------------------------------------------------------------------ *
	 *  Context: what the core page tells us about this widget area        *
	 * ------------------------------------------------------------------ */

	// The zone code and the box ajax url only exist inside the inline script that
	// FormOther::getBoxesArea() prints, and inside the "add widget" form when the
	// user already has a personalized set. Read both, fall back to the defaults.
	function readContext() {
		var ctx = { zone: "0", userid: 0, boxurl: "", token: "" };

		var form = $("#addbox");
		if (form) {
			var areacode = form.querySelector("input[name='areacode']");
			var userid = form.querySelector("input[name='userid']");
			if (areacode && areacode.value !== "") ctx.zone = areacode.value;
			if (userid && userid.value !== "") ctx.userid = parseInt(userid.value, 10) || 0;
		}

		$all("script:not([src])").some(function (node) {
			var text = node.textContent || "";
			var url = text.match(/([^'"\s]*\/core\/ajax\/box\.php)\?/);
			if (!url) return false;
			ctx.boxurl = url[1];
			var zone = text.match(/[?&]zone=([a-zA-Z0-9_]*)&userid=/);
			if (zone && !form) ctx.zone = zone[1];
			var userid = text.match(/&userid=(?:'\s*\+\s*)?(\d+)/);
			if (userid && !ctx.userid) ctx.userid = parseInt(userid[1], 10) || 0;
			return true;
		});

		var meta = $('meta[name="anti-csrf-newtoken"]');
		ctx.token = meta ? meta.getAttribute("content") : "";

		return ctx;
	}

	/* ------------------------------------------------------------------ *
	 *  Widgets                                                            *
	 * ------------------------------------------------------------------ */

	// A widget id is the core box_id, so the layout survives any reordering. The
	// working board (index.php's $boxwork) is a plain .box with no id: it gets a
	// stable synthetic id so it can be placed and sized like any other widget.
	function widgetId(node, index) {
		var match = (node.id || "").match(/^boxto_(\d+)$/);
		if (match) return match[1];
		if (node.id) return "";  // boxto_A / boxto_B: core's empty drop placeholders
		if (node.querySelector(".boxworkingboard")) return "work";
		return "sys" + index;
	}

	function collectWidgets(columns) {
		var widgets = [];
		columns.forEach(function (column) {
			if (!column) return;
			Array.prototype.slice.call(column.children).forEach(function (node) {
				if (!node.classList || !node.classList.contains("box")) return;
				var id = widgetId(node, widgets.length);
				if (!id) return;
				widgets.push({ id: id, node: node });
			});
		});
		return widgets;
	}

	/* ------------------------------------------------------------------ *
	 *  Layout model                                                       *
	 *  {v:3, rows:[ {items:[{id, w:1..12, h}]} ]}                         *
	 * ------------------------------------------------------------------ */

	// v1 already sized each widget the same way, so it needs no conversion. v2 wrapped
	// widgets in explicit columns: flatten those, each widget inheriting its column's
	// width, which draws the same picture without the extra object.
	function migrate(layout) {
		if (!layout || !layout.rows) return layout;
		return {
			v: 3,
			rows: layout.rows.map(function (row) {
				if (row && row.items) return { items: row.items };
				var items = [];
				((row && row.cols) || []).forEach(function (col) {
					((col && col.items) || []).forEach(function (item) {
						items.push({ id: item.id, w: col.w, h: item.h });
					});
				});
				return { items: items };
			})
		};
	}

	// Keep only the widgets actually on the page, then append the ones the layout has
	// never seen (first run, or a widget just added from the combo).
	function normalize(layout, widgets) {
		layout = migrate(layout);

		var byId = {};
		widgets.forEach(function (widget) { byId[widget.id] = widget; });

		var placed = {};
		var rows = [];
		((layout && layout.rows) || []).forEach(function (row) {
			var items = [];
			((row && row.items) || []).forEach(function (item) {
				var id = item && item.id != null ? String(item.id) : "";
				if (!id || !byId[id] || placed[id]) return;
				placed[id] = true;
				items.push({ id: id, w: clampSpan(item.w), h: clampHeight(item.h) });
			});
			if (items.length) rows.push({ items: items });
		});

		widgets.forEach(function (widget) {
			if (placed[widget.id]) return;
			placed[widget.id] = true;
			var span = (widget.id === "work" ? WORKBOARD_SPAN : DEFAULT_SPAN);
			var last = rows[rows.length - 1];
			var used = 0;
			if (last) last.items.forEach(function (item) { used += item.w; });
			if (!last || used + span > COLS) {
				last = { items: [] };
				rows.push(last);
			}
			last.items.push({ id: widget.id, w: span, h: 0 });
		});

		if (!rows.length) rows.push({ items: [] });

		return { v: 3, rows: rows };
	}

	function layoutFromDom(dash) {
		var rows = [];
		$all(".fz-dash-row", dash).forEach(function (rowEl) {
			var items = [];
			$all(".fz-dash-cell", rowEl).forEach(function (cell) {
				items.push({
					id: cell.getAttribute("data-fz-box"),
					w: clampSpan(cell.style.getPropertyValue("--fz-cw")),
					h: cell.classList.contains("fz-has-height") ? clampHeight(parseFloat(cell.style.getPropertyValue("--fz-ch"))) : 0
				});
			});
			// An empty row is a scratch area while editing; there is nothing to persist.
			if (items.length) rows.push({ items: items });
		});
		return { v: 3, rows: rows };
	}

	/* ------------------------------------------------------------------ *
	 *  Rendering                                                          *
	 * ------------------------------------------------------------------ */

	function applySize(cell, span, height) {
		cell.style.setProperty("--fz-cw", String(span));
		cell.setAttribute("data-fz-span", span + "/" + COLS);

		if (height) {
			cell.style.setProperty("--fz-ch", height + "px");
			cell.classList.add("fz-has-height");
		} else {
			cell.style.removeProperty("--fz-ch");
			cell.classList.remove("fz-has-height");
		}
		scheduleMasonry();
	}

	// A widget's toolbar, the three actions Home Assistant puts on a card: move it,
	// open its layout, remove it. The size itself is not set from here — dragging
	// handles on a live widget proved both hard to aim and hard to find, so it is set
	// in the layout dialog below, where the choice is visible as a grid.
	function buildCellTools() {
		var tools = el("div", "fz-dash-celltools");
		tools.appendChild(button("fz-dash-grip", "fa-arrows-alt", tr("dash-move", "Move this widget")));
		tools.appendChild(button("fz-dash-layout", "fa-pen", tr("dash-layout", "Layout")));
		tools.appendChild(button("fz-dash-del", "fa-trash-alt", tr("dash-delwidget", "Remove this widget")));
		return tools;
	}

	// Dolibarr's header carries a move grip (dead here: its sortable managed the native
	// columns) and a close cross, one stray click away from dropping a widget. Both are
	// MOVED OUT of the header into a hidden holder rather than merely styled away, so
	// they cannot come back in a context the stylesheet does not cover — the dialog, a
	// third-party box, a stale cached stylesheet. The holder rides inside the widget
	// itself, so it survives a re-render, and the cross keeps the handler core bound to
	// it: clicking it is still how a removal is performed.
	function parkNativeControls(widget) {
		var holder = widget.node.querySelector("div.boxclose");
		if (!holder) return;
		var parked = widget.node.querySelector(".fz-dash-native");
		if (!parked) {
			parked = el("div", "fz-dash-native");
			widget.node.appendChild(parked);
		}
		$all(".boxhandle, [id^='imgclose'], .boxclose", holder).forEach(function (node) {
			parked.appendChild(node);
		});
	}

	// In their place, the button that opens the widget in a Dolibarr window.
	function decorateHeader(cell, widget) {
		parkNativeControls(widget);

		var holder = widget.node.querySelector("div.boxclose");
		if (!holder) return;                                 // no header (the working board)
		if (holder.querySelector(".fz-dash-expand")) return; // a re-render reuses the node
		var jq = window.jQuery;
		if (!jq || !jq.fn || !jq.fn.dialog) return;          // no dialog, no button to offer
		holder.appendChild(button("fz-dash-expand", "fa-window-maximize", tr("dash-expand", "Open in a window")));
	}

	// The close control core bound its handler to, if this widget has one.
	function closeControl(cell) {
		return cell.querySelector("[id^='imgclose']");
	}

	function buildCell(item, widget) {
		var cell = el("div", "fz-dash-cell");
		cell.setAttribute("data-fz-box", item.id);
		if (widget.node.querySelector(".clearview-box-cell")) {
			cell.classList.add("fz-dash-clearview");
		}
		var adaptiveContent = widget.node.querySelector(".clearview-interactive-chart, .clearview-worldmap, .clearview-box-graph");
		if (adaptiveContent) {
			cell.classList.add("fz-dash-adaptive");
			var adaptiveRow = adaptiveContent.closest("tr");
			var adaptiveTable = adaptiveContent.closest("table.boxtable");
			if (adaptiveRow) adaptiveRow.classList.add("fz-dash-grow-row");
			if (adaptiveTable) adaptiveTable.classList.add("fz-dash-adaptive-table");
		}

		// The edit controls stay in the DOM and are only revealed by CSS, so toggling
		// edit mode never re-renders the grid (and never reloads a widget's content).
		cell.appendChild(buildCellTools());

		var inner = el("div", "fz-dash-cellinner");
		inner.appendChild(widget.node); // MOVES the live node, nothing is recreated
		cell.appendChild(inner);

		decorateHeader(cell, widget);
		// A widget core gave no close control to (the working board) cannot be removed:
		// drop the button rather than leave one that does nothing.
		if (!closeControl(cell)) {
			var del = $(".fz-dash-del", cell);
			if (del) del.remove();
		}
		applySize(cell, clampSpan(item.w), clampHeight(item.h));

		return cell;
	}

	/* ------------------------------------------------------------------ *
	 *  The layout dialog                                                  *
	 * ------------------------------------------------------------------ */

	// Home Assistant sets a card's size in a "Layout" tab: a grid where the chosen
	// width and height are simply the highlighted rectangle. That is what this is —
	// twelve columns wide, and rows of ROW_UNIT pixels, plus the two escapes the model
	// needs (full width, automatic height).
	//
	// A size is applied only after a click in the grid. Moving the pointer must not
	// resize the widget unexpectedly; Cancel puts back the size the dialog opened with.
	function openLayoutDialog(cell, grid) {
		var jq = window.jQuery;
		if (!cell || !jq || !jq.fn || !jq.fn.dialog) return;

		var startSpan = clampSpan(cell.style.getPropertyValue("--fz-cw"));
		var startHeight = cell.classList.contains("fz-has-height")
			? clampHeight(parseFloat(cell.style.getPropertyValue("--fz-ch")))
			: 0;

		var state = { w: startSpan, h: startHeight };
		var dialogFinished = false;

		var host = el("div", "fz-dash-layoutdlg");
		var picker = el("div", "fz-dash-picker");
		var cells = [];
		for (var row = 1; row <= PICKER_ROWS; row++) {
			for (var col = 1; col <= COLS; col++) {
				var pcell = el("button", "fz-dash-pcell");
				pcell.type = "button";
				pcell.setAttribute("data-w", String(col));
				pcell.setAttribute("data-h", String(row));
				pcell.setAttribute("aria-label", col + "/" + COLS + " × " + row);
				picker.appendChild(pcell);
				cells.push(pcell);
			}
		}
		host.appendChild(picker);

		var readout = el("div", "fz-dash-readout", "");
		host.appendChild(readout);

		var opts = el("div", "fz-dash-opts");
		var fullLabel = el("label", "fz-dash-opt");
		var full = document.createElement("input");
		full.type = "checkbox";
		fullLabel.appendChild(full);
		fullLabel.appendChild(document.createTextNode(" " + tr("dash-fullwidth", "Full width")));
		opts.appendChild(fullLabel);

		var autoLabel = el("label", "fz-dash-opt");
		var auto = document.createElement("input");
		auto.type = "checkbox";
		autoLabel.appendChild(auto);
		autoLabel.appendChild(document.createTextNode(" " + tr("dash-autoheight", "Automatic height")));
		opts.appendChild(autoLabel);
		host.appendChild(opts);

		// Reflect `state` everywhere: the widget itself, the highlighted rectangle, the
		// readout and the two checkboxes.
		function paint(preview) {
			var shown = preview || state;
			var rows = shown.h ? Math.max(1, Math.round(shown.h / ROW_UNIT)) : 0;
			cells.forEach(function (pcell) {
				var on = (parseInt(pcell.getAttribute("data-w"), 10) <= shown.w)
					&& (rows ? parseInt(pcell.getAttribute("data-h"), 10) <= rows : parseInt(pcell.getAttribute("data-h"), 10) === 1);
				pcell.classList.toggle("is-on", on);
				pcell.classList.toggle("is-auto", on && !rows);
			});
			readout.textContent = shown.w + "/" + COLS + " · "
				+ (shown.h ? (rows + " × " + ROW_UNIT + "px") : tr("dash-autoheight", "Automatic height"));
			full.checked = (state.w === COLS);
			auto.checked = !state.h;
			applySize(cell, shown.w, shown.h);
			// ClearView SVGs and third-party canvas charts must recompute against
			// the selected dimensions.
			notifyResize();
		}

		picker.addEventListener("click", function (ev) {
			var pcell = ev.target.closest(".fz-dash-pcell");
			if (!pcell) return;
			ev.preventDefault();
			state.w = parseInt(pcell.getAttribute("data-w"), 10);
			state.h = auto.checked ? 0 : parseInt(pcell.getAttribute("data-h"), 10) * ROW_UNIT;
			paint();
			notifyResize();
		});
		full.addEventListener("change", function () {
			state.w = full.checked ? COLS : Math.min(startSpan, COLS - 1) || DEFAULT_SPAN;
			paint();
			notifyResize();
		});
		auto.addEventListener("change", function () {
			state.h = auto.checked ? 0 : (startHeight || ROW_UNIT * 3);
			paint();
			notifyResize();
		});

		paint();

		var buttons = {};
		buttons[tr("dash-cancel", "Cancel")] = function () {
			applySize(cell, startSpan, startHeight);
			notifyResize();
			dialogFinished = true;
			jq(this).dialog("close");
		};
		buttons[tr("dash-save", "Save")] = function () {
			applySize(cell, state.w, state.h);
			notifyResize();
			grid.save(true);
			dialogFinished = true;
			jq(this).dialog("close");
		};

		document.body.appendChild(host);
		jq(host).dialog({
			title: tr("dash-layout", "Layout"),
			modal: true,
			width: Math.min(560, Math.round(window.innerWidth * 0.92)),
			closeText: "",
			buttons: buttons,
			close: function () {
				// The title-bar close button and Escape bypass the Cancel callback.
				// They must still undo a live hover preview.
				if (!dialogFinished) {
					applySize(cell, startSpan, startHeight);
					notifyResize();
				}
				jq(this).dialog("destroy");
				host.remove();
			}
		});
	}

	/* ------------------------------------------------------------------ *
	 *  Opening a widget in a window                                       *
	 * ------------------------------------------------------------------ */

	// The widget is MOVED into the dialog and moved back on close, never cloned: a
	// cloned <canvas> comes out blank, and half the widgets here draw charts.
	// jQuery UI's dialog is the one Dolibarr itself uses, so the window looks native.
	function openPopup(cell) {
		var box = $(".box", cell);
		var inner = $(".fz-dash-cellinner", cell);
		var jq = window.jQuery;
		if (!box || !inner || !jq || !jq.fn || !jq.fn.dialog) return;

		var titleEl = box.querySelector(".box_titre th");
		var title = titleEl ? (titleEl.getAttribute("title") || titleEl.textContent || "") : "";

		var host = el("div", "fz-dash-popup");
		document.body.appendChild(host);
		host.appendChild(box);

		var smallScreen = window.innerWidth <= 820;
		var width = Math.min(1400, Math.round(window.innerWidth * (smallScreen ? 0.94 : 0.90)));
		var maxHeight = Math.round(window.innerHeight * (smallScreen ? 0.90 : 0.88));

		jq(host).dialog({
			title: title.replace(/\s+/g, " ").trim(),
			dialogClass: "fz-dash-widget-dialog",
			modal: true,
			draggable: false,
			width: width,
			height: maxHeight,
			maxHeight: maxHeight,
			position: { my: "center", at: "center", of: window },
			closeText: "",
			open: function () { notifyResize(); },  // the widget just changed size, twice over
			close: function () {
				inner.appendChild(box); // hand the live widget back to its cell
				jq(this).dialog("destroy");
				host.remove();
				notifyResize();
			}
		});
	}

	function buildRow(row, byId) {
		var rowEl = el("div", "fz-dash-row");

		var tools = el("div", "fz-dash-rowtools");
		tools.appendChild(button("fz-dash-rowgrip", "fa-grip-vertical", tr("dash-moverow", "Move this row")));
		tools.appendChild(button("fz-dash-rowdel", "fa-trash-alt", tr("dash-delrow", "Delete this row")));
		rowEl.appendChild(tools);

		var grid = el("div", "fz-dash-grid");
		(row.items || []).forEach(function (item) {
			var widget = byId[item.id];
			if (widget) grid.appendChild(buildCell(item, widget));
		});
		rowEl.appendChild(grid);

		return rowEl;
	}

	function render(dash, layout, widgets) {
		var byId = {};
		widgets.forEach(function (widget) { byId[widget.id] = widget; });

		// Build into a fragment first: appending a widget moves it out of the old cell,
		// so the container can then be emptied without ever detaching a live node twice.
		var frag = document.createDocumentFragment();
		layout.rows.forEach(function (row) { frag.appendChild(buildRow(row, byId)); });

		var bar = $(".fz-dash-bar", dash);
		var addRow = $(".fz-dash-addrow", dash);
		dash.textContent = "";
		if (bar) dash.appendChild(bar);
		dash.appendChild(frag);
		if (addRow) dash.appendChild(addRow);
	}

	/* ------------------------------------------------------------------ *
	 *  Persistence                                                        *
	 * ------------------------------------------------------------------ */

	function Store(ctx, base) {
		this.ctx = ctx;
		this.base = base;
		// A browser can be used by several Dolibarr accounts. Scoping only by zone
		// briefly showed the previous user's layout and could publish it when the
		// next account had no server-side layout yet.
		this.key = "fz-dash:" + ctx.userid + ":" + ctx.zone;
		this.token = ctx.token;
	}
	Store.prototype.cached = function () {
		var raw = storageGet(this.key);
		if (!raw) return null;
		try { return JSON.parse(raw); } catch (e) { return null; }
	};
	Store.prototype.load = function (callback) {
		if (!window.fetch) return callback(null);
		var store = this;
		fetch(this.base + "dashboard.php?action=load&zone=" + encodeURIComponent(this.ctx.zone), {
			credentials: "same-origin",
			headers: { "X-Requested-With": "XMLHttpRequest" }
		}).then(function (response) {
			return response.ok ? response.json() : null;
		}).then(function (data) {
			if (data && data.token) store.token = data.token;
			callback(data ? data.layout : null);
		}).catch(function () { callback(null); });
	};
	Store.prototype.save = function (layout, retrying) {
		storageSet(this.key, JSON.stringify(layout));
		if (!window.fetch) return;
		var store = this;
		fetch(this.base + "dashboard.php?action=save&zone=" + encodeURIComponent(this.ctx.zone) + "&token=" + encodeURIComponent(this.token), {
			method: "POST",
			credentials: "same-origin",
			headers: { "X-Requested-With": "XMLHttpRequest" },
			body: JSON.stringify(layout)
		}).then(function (response) {
			if (response.ok || retrying) return;
			// Dolibarr rolls its anti-CSRF token on every page load, so opening another
			// page in a second tab invalidates ours. Pick up a fresh one and retry once;
			// the localStorage copy above means nothing is lost meanwhile.
			if (response.status === 403) {
				store.load(function () { store.save(layout, true); });
			}
		}).catch(function () {});
	};

	/* ------------------------------------------------------------------ *
	 *  Keeping Dolibarr's own box order in sync                           *
	 * ------------------------------------------------------------------ */

	// llx_boxes only knows two columns, so the grid is flattened back into the
	// "A:id,id,A-B:id,B" string core expects (the trailing letters are its empty drop
	// placeholders). This keeps the active widget set correct and leaves a sensible
	// two-column page behind if the theme is ever disabled.
	function boxOrderString(layout) {
		var ids = [];
		layout.rows.forEach(function (row) {
			row.items.forEach(function (item) {
				if (/^\d+$/.test(item.id)) ids.push(item.id);
			});
		});
		var half = Math.ceil(ids.length / 2);
		var left = ids.slice(0, half);
		var right = ids.slice(half);
		return "A:" + (left.length ? left.join(",") + "," : "") + "A"
			+ "-B:" + (right.length ? right.join(",") + "," : "") + "B";
	}

	function Core(ctx) {
		this.ctx = ctx;
		this.lastOrder = "";
	}
	Core.prototype.push = function (layout, extra, callback) {
		var ctx = this.ctx;
		var order = boxOrderString(layout);
		if (!ctx.boxurl || !ctx.userid) return callback && callback();
		if (order === this.lastOrder && !extra) return callback && callback();
		this.lastOrder = order;

		var url = ctx.boxurl + "?closing=1&boxorder=" + encodeURIComponent(order)
			+ "&zone=" + encodeURIComponent(ctx.zone) + "&userid=" + encodeURIComponent(ctx.userid)
			+ (extra || "");
		if (!window.fetch) return callback && callback();
		fetch(url, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
			.then(function () { if (callback) callback(); })
			.catch(function () { if (callback) callback(); });
	};

	/* ------------------------------------------------------------------ *
	 *  Boot                                                               *
	 * ------------------------------------------------------------------ */

	function buildBar(dash) {
		var bar = el("div", "fz-dash-bar");

		var edit = el("button", "fz-dash-btn fz-dash-toggle",
			'<i class="fas fa-sliders-h"></i><span>' + tr("dash-customize", "Customize") + '</span>');
		edit.type = "button";
		edit.setAttribute("aria-pressed", "false");
		bar.appendChild(edit);

		var reset = el("button", "fz-dash-btn fz-dash-reset",
			'<i class="fas fa-undo"></i><span>' + tr("dash-reset", "Reset") + '</span>');
		reset.type = "button";
		bar.appendChild(reset);

		var doneBtn = el("button", "fz-dash-btn fz-dash-done",
			'<i class="fas fa-check"></i><span>' + tr("dash-done", "Done") + '</span>');
		doneBtn.type = "button";
		bar.appendChild(doneBtn);

		dash.appendChild(bar);

		var addRow = el("button", "fz-dash-addrow",
			'<i class="fas fa-plus"></i><span>' + tr("dash-addrow", "Add a row") + '</span>');
		addRow.type = "button";
		dash.appendChild(addRow);
	}

	function boot() {
		var left = document.getElementById("boxhalfleft");
		var right = document.getElementById("boxhalfright");
		var host = left ? left.parentNode : null;
		if (!left || !host) return done();

		var widgets = collectWidgets([left, right]);
		if (!widgets.length) return done();

		var ctx = readContext();
		var base = SELF ? SELF.src.replace(/[^/]*(\?.*)?$/, "") : "";
		var store = new Store(ctx, base);
		var core = new Core(ctx);

		// The native columns stay in the DOM (core's inline script still queries them)
		// but are emptied by the move and hidden by the stylesheet.
		left.classList.add("fz-dash-legacy");
		if (right) right.classList.add("fz-dash-legacy");

		var dash = el("div");
		dash.id = "fz-dash";
		dash.setAttribute("data-fz-zone", ctx.zone);
		host.insertBefore(dash, left);

		buildBar(dash);
		render(dash, normalize(store.cached(), widgets), widgets);
		done();
		// The widgets were drawn server-side for the native half-width columns and have
		// just landed in cells of a different size: let the charts redraw for it.
		notifyResize();

		var grid = new Grid(dash, widgets, store, core);
		grid.wire();

		// The server copy is authoritative; it lands a moment after the cached one and
		// only costs a re-render when the two actually differ (another browser).
		store.load(function (remote) {
			if (remote) {
				var merged = normalize(remote, widgets);
				if (JSON.stringify(merged) !== JSON.stringify(grid.layout())) {
					render(dash, merged, widgets);
					grid.refresh();
					notifyResize();
				}
			} else if (store.cached()) {
				grid.save(); // first run on this account: publish the cached layout
			}
		});
	}

	/* ------------------------------------------------------------------ *
	 *  Grid behaviour (edit mode, drag & drop, resize)                    *
	 * ------------------------------------------------------------------ */

	function Grid(dash, widgets, store, core) {
		this.dash = dash;
		this.widgets = widgets;
		this.store = store;
		this.core = core;
		this.saveTimer = null;
		this.editing = false;
	}

	Grid.prototype.layout = function () { return layoutFromDom(this.dash); };

	Grid.prototype.save = function (immediate) {
		var grid = this;
		clearTimeout(this.saveTimer);
		function run() {
			var layout = grid.layout();
			grid.store.save(layout);
			grid.core.push(layout);
		}
		if (immediate) run();
		else this.saveTimer = setTimeout(run, SAVE_DELAY);
	};

	Grid.prototype.refresh = function () {
		this.wireSortables();
		watchMasonry(this.dash);
	};

	Grid.prototype.setEditing = function (editing) {
		this.editing = editing;
		this.dash.classList.toggle("fz-dash-edit", editing);
		var toggle = $(".fz-dash-toggle", this.dash);
		if (toggle) toggle.setAttribute("aria-pressed", editing ? "true" : "false");
		this.enableSortables(editing);
		if (!editing) {
			this.dropEmptyRows();
			this.save(true);
		}
	};

	// A row the user emptied while editing has no reason to survive the session, but
	// the grid must never end up with no row at all to drop widgets into.
	Grid.prototype.dropEmptyRows = function () {
		var rows = $all(".fz-dash-row", this.dash);
		var empty = rows.filter(function (rowEl) { return !$(".fz-dash-cell", rowEl); });
		var keepOne = (empty.length === rows.length);
		empty.forEach(function (rowEl, index) {
			if (keepOne && index === 0) return;
			rowEl.remove();
		});
	};

	// Called after core removed a closed widget from the DOM.
	Grid.prototype.dropMissing = function () {
		$all(".fz-dash-cell", this.dash).forEach(function (cell) {
			if (!$(".box", cell)) cell.remove();
		});
		this.dropEmptyRows();
	};

	Grid.prototype.addRow = function () {
		this.dash.insertBefore(buildRow({ items: [] }, {}), $(".fz-dash-addrow", this.dash));
		this.refresh();
	};

	// Deleting a row never destroys widgets: they fall back into the neighbouring row.
	Grid.prototype.deleteRow = function (rowEl) {
		var rows = $all(".fz-dash-row", this.dash);
		if (rows.length < 2) return;
		var index = rows.indexOf(rowEl);
		var target = rows[index > 0 ? index - 1 : 1];
		var targetGrid = $(".fz-dash-grid", target);
		$all(".fz-dash-cell", rowEl).forEach(function (cell) { targetGrid.appendChild(cell); });
		rowEl.remove();
		this.refresh();
		this.save();
	};

	Grid.prototype.reset = function () {
		render(this.dash, normalize(null, this.widgets), this.widgets);
		this.refresh();
		this.save(true);
	};

	/* ---- drag & drop ------------------------------------------------- */

	// jQuery UI is already loaded on every page that shows widgets (core uses its
	// sortable for the native two columns), so reuse it. Without it the grid stays
	// fully usable: only reordering by drag is unavailable.
	Grid.prototype.jq = function () {
		var jq = window.jQuery;
		return (jq && jq.fn && jq.fn.sortable) ? jq : null;
	};

	Grid.prototype.enableSortables = function (editing) {
		var jq = this.jq();
		if (!jq) return;
		jq(this.dash).add(jq(".fz-dash-grid", this.dash)).each(function () {
			try { jq(this).sortable("option", "disabled", !editing); } catch (e) {}
		});
	};

	Grid.prototype.wireSortables = function () {
		var jq = this.jq();
		if (!jq) return;
		var grid = this;

		// Widgets, inside a row and across rows.
		jq(".fz-dash-grid", this.dash).each(function () {
			try { jq(this).sortable("destroy"); } catch (e) {}
			jq(this).sortable({
				items: "> .fz-dash-cell",
				// The widget's own title bar drags it, like its native handle used to;
				// the grip is there for widgets that have no title bar.
				handle: ".fz-dash-grip, .box_titre",
				// jQuery UI refuses to start a drag from anything matching `cancel`, and
				// its default list contains "button" — which is exactly what our handles
				// are (real buttons, so they stay focusable and keyboard-operable). Keep
				// only the controls that must keep their own click: form fields, links,
				// and Dolibarr's close-widget cross inside the title bar.
				cancel: "input,textarea,select,option,a,.boxclose",
				connectWith: "#fz-dash .fz-dash-grid",
				placeholder: "fz-dash-ph",
				forcePlaceholderSize: true,
				tolerance: "pointer",
				disabled: !grid.editing,
				start: function (event, ui) {
					// A pixel-sized placeholder means nothing to a grid track: give it the
					// dragged widget's own span so the hole matches where it will land.
					ui.placeholder[0].style.setProperty("--fz-cw", ui.item[0].style.getPropertyValue("--fz-cw") || String(DEFAULT_SPAN));
					ui.placeholder[0].style.gridRowEnd = ui.item[0].style.gridRowEnd || "span 10";
				},
				// Deferred by a tick: rebuilding the sortables from inside the stop of the
				// very sortable that is still finishing its own cleanup is a good way to
				// tear the widget apart under jQuery UI's feet.
				stop: function () {
					setTimeout(function () {
						grid.refresh();
						grid.save();
					}, 0);
				}
			});
		});

		// Rows.
		try { jq(this.dash).sortable("destroy"); } catch (e) {}
		jq(this.dash).sortable({
			items: "> .fz-dash-row",
			handle: ".fz-dash-rowgrip",
			cancel: "input,textarea,select,option,a", // see the note above
			placeholder: "fz-dash-rowph",
			forcePlaceholderSize: true,
			tolerance: "pointer",
			axis: "y",
			disabled: !this.editing,
			stop: function () { grid.save(); }
		});
	};

	/* ---- events ------------------------------------------------------ */

	Grid.prototype.wire = function () {
		var grid = this;

		this.dash.addEventListener("click", function (ev) {
			var target = ev.target;
			var expand = target.closest(".fz-dash-expand");
			if (expand) {
				ev.preventDefault();
				openPopup(expand.closest(".fz-dash-cell"));
				return;
			}
			if (target.closest(".fz-dash-toggle")) { grid.setEditing(!grid.editing); return; }
			if (target.closest(".fz-dash-done")) { grid.setEditing(false); return; }
			if (target.closest(".fz-dash-addrow")) { grid.addRow(); return; }
			if (target.closest(".fz-dash-reset")) {
				if (window.confirm(tr("dash-resetask", "Reset the dashboard layout?"))) grid.reset();
				return;
			}
			var layout = target.closest(".fz-dash-layout");
			if (layout) {
				ev.preventDefault();
				openLayoutDialog(layout.closest(".fz-dash-cell"), grid);
				return;
			}
			var delWidget = target.closest(".fz-dash-del");
			if (delWidget) {
				// Delegate to Dolibarr's own close control: it is what knows how to put the
				// widget back in the "add a widget" list and to persist the new set.
				var control = closeControl(delWidget.closest(".fz-dash-cell"));
				if (control) control.click();
				return;
			}
			var del = target.closest(".fz-dash-rowdel");
			if (del) { grid.deleteRow(del.closest(".fz-dash-row")); return; }
			// The grips are buttons so they can be focused; a plain click must not
			// submit anything or scroll the page.
			if (target.closest(".fz-dash-grip, .fz-dash-rowgrip")) ev.preventDefault();
		});

		document.addEventListener("keydown", function (ev) {
			if (ev.key === "Escape" && grid.editing) grid.setEditing(false);
		});

		this.refresh();
		this.overrideCore();
	};

	// Core's inline script serializes #boxhalfleft / #boxhalfright to persist the
	// widget list. Those columns are now empty, so its updateBoxOrder() would save an
	// empty list and wipe the user's widgets. Replace the two entry points: the close
	// button and the "add widget" combo keep working, but the order they send is read
	// from the Fusion grid.
	Grid.prototype.overrideCore = function () {
		var grid = this;
		var ctx = this.store.ctx;

		window.updateBoxOrder = function (closing) {
			grid.dropMissing();
			var layout = grid.layout();
			grid.store.save(layout);
			var empty = (boxOrderString(layout) === "A:A-B:B");
			grid.core.push(layout, "", function () {
				// Core reloads when the last widget is closed, so the page can offer the
				// full list again; reproduce that, without its action=delbox round trip.
				if (empty && String(closing) === "1") window.location.reload();
			});
		};

		var jq = window.jQuery;
		if (!jq) return;
		jq(function () {
			$all("#boxhalfleft, #boxhalfright").forEach(function (column) {
				try { jq(column).sortable("destroy"); } catch (e) {}
			});
			jq("#boxcombo").off("change").on("change", function () {
				var boxid = jq(this).val();
				if (!(boxid > 0) || !ctx.boxurl || !ctx.userid) return;
				grid.core.push(grid.layout(), "&boxid=" + encodeURIComponent(boxid), function () {
					window.location.reload(); // the new widget is rendered server-side
				});
			});
		});
	};

	// Same contract as fusion.js: anything unexpected leaves the page on Dolibarr's
	// own two-column layout rather than on a half-built grid.
	function init() {
		try {
			boot();
		} catch (e) {
			done();
			var dash = document.getElementById("fz-dash");
			var left = document.getElementById("boxhalfleft");
			// Give the widgets back to the native column before dropping the grid:
			// they are the live nodes, there is no second copy of them anywhere.
			if (dash && left) {
				$all(".box", dash).forEach(function (box) { left.appendChild(box); });
			}
			if (dash) dash.remove();
			$all(".fz-dash-legacy").forEach(function (column) { column.classList.remove("fz-dash-legacy"); });
			if (window.console && console.error) console.error("Fusion dashboard failed to initialize", e);
		}
	}

	if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
	else init();
})();
