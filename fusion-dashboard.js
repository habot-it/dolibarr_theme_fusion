/*
 * Fusion theme — configurable dashboard grid.
 *
 * Dolibarr renders its widgets ("boxes") into two hard-coded columns:
 * .twocolumns > #boxhalfleft + #boxhalfright, each box being a
 * <div class="box divboxtable boxdraggable" id="boxto_<id>">.
 *
 * This script replaces that area, with NO core change, by ONE board on which every
 * widget is placed:
 *
	 *   board   a single grid, 192 fluid tracks across, 8-pixel units down
 *   widget  one Dolibarr box, carrying its own rectangle {x, y, w, h} on that board
 *
 * There is no row and no column object: a widget is where the user put it, holes
 * included, and two widgets side by side is a fact of their coordinates rather than
 * a consequence of their widths. The tracks stay fractional, so a board keeps
 * adapting to the width it is given — which is what a fixed pixel canvas gives up.
 * Moving and resizing are the same gesture on two different pairs of numbers, and a
 * drop pushes whatever it covered downwards, never sideways.
 *
 * Widgets are MOVED, never rebuilt, so every link, graph, tooltip and permission
 * stays intact.
 *
 * The layout is stored per user and per zone through theme/fusion/dashboard.php
	 * (llx_user_param) as {v:5, items:[{id,x,y,w,h}]}, with localStorage as an instant
 * cache so the board renders before the round trip. Layouts stored by the earlier
 * row-based versions are converted on load, keeping the picture they described. Dolibarr's own llx_boxes stays in sync for the part it
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
	// v5: the board is ONE grid, not a stack of rows. A widget carries where it sits
	// (x, y) as well as how big it is (w, h) — x and w in tracks, y and h in units of
	// UNIT_H pixels. 192 horizontal tracks make the usual desktop step roughly as fine
	// as the fixed eight-pixel vertical step while widths remain fluid.
	var LAYOUT_VERSION = 5;
	var LEGACY_COLS = 24;
	var COLS = 192;            // tracks across the board
	var UNIT_H = 8;            // one vertical unit, in pixels
	var MIN_SPAN = COLS / LEGACY_COLS; // same minimum visual width as one legacy track
	var DEFAULT_SPAN = COLS / 2;       // a new widget takes half the width
	var WORKBOARD_SPAN = COLS;         // the working board is a wide summary
	var DEFAULT_UNITS = 40;    // …and 320px tall until it is resized
	var MIN_UNITS = 8;
	var MAX_HEIGHT = 4000;
	var MAX_UNITS = Math.round(MAX_HEIGHT / UNIT_H);
	var RESIZE_STEP = 4;       // units gained per arrow-key press on the resize grip
	var SAVE_DELAY = 500;
	var notifyScheduled = false;
	var ROOT_STYLE = null;

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
			if (!ROOT_STYLE) ROOT_STYLE = getComputedStyle(ROOT);
			value = ROOT_STYLE.getPropertyValue("--fz-t-" + key).trim();
		} catch (e) {}
		if (!value) return fallback;
		return value.replace(/^["']|["']$/g, "") || fallback;
	}
	function clampSpan(value) {
		value = parseInt(value, 10);
		if (!isFinite(value)) value = DEFAULT_SPAN;
		return Math.max(MIN_SPAN, Math.min(COLS, value));
	}
	// v5 coordinates. A placed widget has a height like it has a width: explicit. The
	// "automatic" height of v3 cannot survive an explicit y — the widgets under it
	// would have nowhere to be until their neighbour above had finished rendering.
	function clampUnits(value) {
		value = Math.round(parseFloat(value));
		if (!isFinite(value) || value <= 0) return DEFAULT_UNITS;
		return Math.max(MIN_UNITS, Math.min(MAX_UNITS, value));
	}
	function clampX(value) {
		value = Math.round(parseFloat(value));
		if (!isFinite(value) || value < 0) return 0;
		return Math.max(0, Math.min(COLS - 1, value));
	}
	function clampY(value) {
		value = Math.round(parseFloat(value));
		return (!isFinite(value) || value < 0) ? 0 : value;
	}
	// px -> units, the conversion used by the v3 migration and new-widget sizing
	function unitsFromPx(px) { return clampUnits(Math.round((parseFloat(px) || 0) / UNIT_H)); }
	function done() { ROOT.classList.remove("fz-dash-boot"); }

	// Resizing a widget changes the box a chart was drawn into, and a canvas does not
	// reflow on its own. Every charting library redraws on the window's resize event,
	// so raise one: that is what makes the CONTENT follow the size, instead of just
	// being clipped by it.
	// Dispatching a window resize reaches every chart library and every page listener,
	// and Chart.js instances are re-measured on top — heavy enough that a burst of calls
	// (a dialog repaint, a gesture end) must collapse to one pass on the next frame.
	function notifyResize() {
		if (notifyScheduled) return;
		notifyScheduled = true;
		window.requestAnimationFrame(function () {
			notifyScheduled = false;
			try {
				window.dispatchEvent(new Event("resize"));
			} catch (e) {
				try {
					var legacy = document.createEvent("Event");
					legacy.initEvent("resize", true, true);
					window.dispatchEvent(legacy);
				} catch (ignore) {}
			}
			// Chart.js 3 does not listen to the window — it observes the canvas's
			// parent — so the event above reaches none of the dashboard charts. Ask each
			// instance to re-measure instead; the call is idempotent and costs a layout read.
			try {
				var ChartLib = window.Chart;
				if (ChartLib && typeof ChartLib.getChart === "function") {
					$all("#fz-dash canvas").forEach(function (canvasEl) {
						var chart = ChartLib.getChart(canvasEl);
						if (chart) chart.resize();
					});
				}
			} catch (e) {}
		});
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
	 *  {v:5, items:[ {id, x:0..191, y:0.., w:8..192, h: units} ]}         *
	 * ------------------------------------------------------------------ */

	// v1/v2/v3 all described a stack of rows: widgets flowed inside a row and a row
	// was as tall as its tallest widget. v4 gave every widget its own coordinates, so
	// the conversion has to invent the y each row never had. Rows are laid out top to
	// bottom, each one as tall as the tallest widget it holds — the picture the user
	// left is the picture they get back, and from there each widget can be moved on
	// its own. `measure` reports what a widget currently occupies on screen, which is
	// how a v3 automatic height becomes an explicit one. v5 only multiplies v4's
	// horizontal coordinates by eight, preserving every position exactly.
	function migrate(layout, measure) {
		if (!layout || layout.v === LAYOUT_VERSION) return layout;
		if (layout.v === 4 && layout.items) {
			var scale = COLS / LEGACY_COLS;
			return {
				v: LAYOUT_VERSION,
				items: layout.items.map(function (item) {
					return {
						id: item.id,
						x: (parseInt(item.x, 10) || 0) * scale,
						y: item.y,
						w: (parseInt(item.w, 10) || (LEGACY_COLS / 2)) * scale,
						h: item.h
					};
				})
			};
		}
		if (!layout.rows) return layout;

		var items = [];
		var y = 0;
		layout.rows.forEach(function (row) {
			var rowItems = [];
			if (row && row.items) rowItems = row.items;
			else ((row && row.cols) || []).forEach(function (col) {
				((col && col.items) || []).forEach(function (item) {
					rowItems.push({ id: item.id, w: col.w, h: item.h });
				});
			});

			var x = 0;
			var tallest = MIN_UNITS;
			rowItems.forEach(function (item) {
				if (!item || item.id == null) return;
				// The original row model used twelve tracks; scale without changing width.
				var w = Math.max(MIN_SPAN, Math.min(COLS, (parseInt(item.w, 10) || 6) * (COLS / 12)));
				if (x + w > COLS) { x = 0; }
				var units = item.h ? unitsFromPx(item.h) : unitsFromPx(measure ? measure(String(item.id)) : 0);
				items.push({ id: String(item.id), x: x, y: y, w: w, h: units });
				tallest = Math.max(tallest, units);
				x += w;
			});
			y += tallest;
		});

		return { v: LAYOUT_VERSION, items: items };
	}

	// Does the rectangle `a` overlap `b`? Placement is free, so this is only used to
	// push widgets out of the way once a gesture ends, never to forbid a position.
	function overlaps(a, b) {
		return a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;
	}

	// Where a widget of that size fits without covering anything. Candidate rows are
	// the top of the board and the bottom edge of each widget already placed — no other
	// y can open a gap — and for each of them the leftmost free column wins. That is
	// what puts two half-width widgets side by side rather than one under the other.
	function firstFreeSpot(items, w, h) {
		var ys = [0];
		items.forEach(function (item) { ys.push(item.y + item.h); });
		ys.sort(function (a, b) { return a - b; });
		for (var i = 0; i < ys.length; i++) {
			for (var x = 0; x + w <= COLS; x++) {
				var candidate = { x: x, y: ys[i], w: w, h: h };
				var taken = items.some(function (item) { return overlaps(candidate, item); });
				if (!taken) return { x: x, y: ys[i] };
			}
		}
		return { x: 0, y: ys[ys.length - 1] || 0 };
	}

	// Keep only the widgets actually on the page, then place the ones the layout has
	// never seen (first run, or a widget just added from the combo) under everything.
	function normalize(layout, widgets, measure) {
		layout = migrate(layout, measure);

		var byId = {};
		widgets.forEach(function (widget) { byId[widget.id] = widget; });

		var placed = {};
		var items = [];
		((layout && layout.items) || []).forEach(function (item) {
			var id = item && item.id != null ? String(item.id) : "";
			if (!id || !byId[id] || placed[id]) return;
			placed[id] = true;
			var w = clampSpan(item.w);
			var x = clampX(item.x);
			if (x + w > COLS) x = Math.max(0, COLS - w);
			items.push({ id: id, x: x, y: clampY(item.y), w: w, h: clampUnits(item.h) });
		});

		widgets.forEach(function (widget) {
			if (placed[widget.id]) return;
			placed[widget.id] = true;
			var w = (widget.id === "work" ? WORKBOARD_SPAN : DEFAULT_SPAN);
			var h = unitsFromPx(measure ? measure(widget.id) : 0);
			var spot = firstFreeSpot(items, w, h);
			items.push({ id: widget.id, x: spot.x, y: spot.y, w: w, h: h });
		});

		return { v: LAYOUT_VERSION, items: items };
	}

	// What a widget currently occupies on screen. Used to turn the automatic heights of
	// v3 — and of a widget just added from the combo — into the explicit one v5 needs.
	// Read while the widgets still sit in the native columns, so the number is real.
	// Under the shell's breakpoint the board is a single column: x, y and w have no
	// effect there, so a drag would silently rewrite a layout the user cannot see.
	function isNarrow() {
		try { return window.matchMedia("(max-width:920px)").matches; } catch (e) { return false; }
	}

	function measurer(widgets) {
		var heights = {};
		widgets.forEach(function (widget) {
			heights[widget.id] = widget.node.getBoundingClientRect().height;
		});
		return function (id) { return heights[id] || 0; };
	}

	function rectFromCell(cell) {
		return {
			id: cell.getAttribute("data-fz-box"),
			x: clampX(cell.style.getPropertyValue("--fz-cx")),
			y: clampY(cell.style.getPropertyValue("--fz-cy")),
			w: clampSpan(cell.style.getPropertyValue("--fz-cw")),
			h: clampUnits(cell.style.getPropertyValue("--fz-cu"))
		};
	}

	function layoutFromDom(dash) {
		var items = $all(".fz-dash-cell", dash).map(rectFromCell);
		// reading order: top to bottom, then left to right
		items.sort(function (a, b) { return (a.y - b.y) || (a.x - b.x); });
		return { v: LAYOUT_VERSION, items: items };
	}

	/* ------------------------------------------------------------------ *
	 *  Rendering                                                          *
	 * ------------------------------------------------------------------ */

	// The four numbers of the model, written as the four custom properties the grid
	// reads. Nothing else places a cell: what is stored IS what CSS lays out.
	function applyRect(cell, rect) {
		var w = clampSpan(rect.w);
		var x = clampX(rect.x);
		if (x + w > COLS) x = Math.max(0, COLS - w);
		cell.style.setProperty("--fz-cx", String(x));
		cell.style.setProperty("--fz-cy", String(clampY(rect.y)));
		cell.style.setProperty("--fz-cw", String(w));
		cell.style.setProperty("--fz-cu", String(clampUnits(rect.h)));
		cell.setAttribute("data-fz-span", w + "/" + COLS);
		cell.classList.toggle("fz-at-left-edge", x === 0);
		cell.classList.toggle("fz-at-right-edge", x + w === COLS);
		// v3 pinned a height only sometimes; v5 always does, and the whole sizing and
		// adaptive-chart chain of the stylesheet keys on this class.
		cell.classList.add("fz-has-height");
	}

	// Move one corner while the opposite corner stays fixed. Horizontal movement snaps
	// to tracks and vertical movement to UNIT_H pixels, exactly like widget placement.
	function resizeFromCorner(rect, corner, deltaX, deltaY) {
		var next = { id: rect.id, x: rect.x, y: rect.y, w: rect.w, h: rect.h };
		var right = rect.x + rect.w;
		var bottom = rect.y + rect.h;

		if (corner.indexOf("w") !== -1) {
			next.x = Math.max(0, Math.min(right - MIN_SPAN, rect.x + deltaX));
			next.w = right - next.x;
		} else {
			next.w = Math.max(MIN_SPAN, Math.min(COLS - rect.x, rect.w + deltaX));
		}

		if (corner.indexOf("n") !== -1) {
			var minY = Math.max(0, bottom - MAX_UNITS);
			var maxY = bottom - MIN_UNITS;
			next.y = Math.max(minY, Math.min(maxY, rect.y + deltaY));
			next.h = bottom - next.y;
		} else {
			next.h = Math.max(MIN_UNITS, Math.min(MAX_UNITS, rect.h + deltaY));
		}

		return next;
	}

	function buildResizeHandle(corner) {
		var label = tr("dash-resize", "Resize this widget") + " (" + corner.toUpperCase() + ")";
		var handle = button("fz-dash-resize fz-dash-resize-" + corner, "fa-angle-down", label);
		handle.setAttribute("data-fz-corner", corner);
		return handle;
	}

	// A widget's edit toolbar: move it or remove it. Size is changed directly from
	// the corner grip, with no second layout interface to keep in sync.
	function buildCellTools() {
		var tools = el("div", "fz-dash-celltools");
		tools.appendChild(button("fz-dash-grip", "fa-arrows-alt", tr("dash-move", "Move this widget")));
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
		if (widget.node.querySelector(".tm-widget-container > .tm-clock-wrapper")) {
			cell.classList.add("fz-dash-remoteclocking");
		}
		// Keep only generic drawing primitives here. Modules that own responsive widget
		// content adapt themselves to the cell size without a theme-specific selector.
		var adaptiveContent = widget.node.querySelector("canvas, .dolgraphchart");
		if (adaptiveContent) {
			cell.classList.add("fz-dash-adaptive");
			var adaptiveRow = adaptiveContent.closest("tr");
			var adaptiveTable = adaptiveContent.closest("table.boxtable");
			if (adaptiveRow) adaptiveRow.classList.add("fz-dash-grow-row");
			if (adaptiveTable) adaptiveTable.classList.add("fz-dash-adaptive-table");
			// A percentage height only resolves against a definite one, so the height has
			// to be handed down node by node from the row's cell to the drawing. Marking
			// the chain — rather than every child of the cell — is what keeps a KPI line
			// sitting above its chart at its natural height.
			for (var node = adaptiveContent; node && node !== adaptiveRow; node = node.parentNode) {
				if (node.nodeType !== 1 || node.tagName === "TD") break;
				node.classList.add("fz-dash-grow-node");
			}
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
		applyRect(cell, item);
		["nw", "ne", "sw", "se"].forEach(function (corner) {
			cell.appendChild(buildResizeHandle(corner));
		});

		return cell;
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

	function buildGridGuides() {
		var guides = el("div", "fz-dash-guides");
		guides.setAttribute("aria-hidden", "true");
		[
			{ at: 20, label: "1/5" },
			{ at: 25, label: "1/4" },
			{ at: 100 / 3, label: "1/3" },
			{ at: 40, label: "2/5" },
			{ at: 50, label: "1/2" },
			{ at: 60, label: "3/5" },
			{ at: 200 / 3, label: "2/3" },
			{ at: 75, label: "3/4" },
			{ at: 80, label: "4/5" }
		].forEach(function (guide) {
			var line = el("span", "fz-dash-guide");
			line.style.left = guide.at + "%";
			line.setAttribute("data-fz-guide", guide.label);
			line.appendChild(el("b", "fz-dash-guide-label", guide.label));
			guides.appendChild(line);
		});
		return guides;
	}

	function render(dash, layout, widgets) {
		var byId = {};
		widgets.forEach(function (widget) { byId[widget.id] = widget; });

		// Build into a fragment first: appending a widget moves it out of the old cell,
		// so the container can then be emptied without ever detaching a live node twice.
		var grid = el("div", "fz-dash-grid");
		grid.appendChild(buildGridGuides());
		// DOM order IS the portrait order: below the breakpoint the board collapses to a
		// plain column and the cells are read in the order they were rendered, so they
		// have to be rendered top to bottom, then left to right.
		var ordered = (layout.items || []).slice().sort(function (a, b) {
			return (a.y - b.y) || (a.x - b.x);
		});
		ordered.forEach(function (item) {
			var widget = byId[item.id];
			if (widget) grid.appendChild(buildCell(item, widget));
		});

		var bar = $(".fz-dash-bar", dash);
		dash.textContent = "";
		if (bar) dash.appendChild(bar);
		dash.appendChild(grid);
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
		// layoutFromDom() already sorts top to bottom then left to right, which is the
		// closest thing to a reading order a free placement can offer core.
		(layout.items || []).forEach(function (item) {
			if (/^\d+$/.test(item.id)) ids.push(item.id);
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

		// Put the native "add widget" form (#addbox) on the same line as the toolbar
		// buttons: move it to the front of the bar (it sits left, the buttons stay right).
		var addbox = document.getElementById("addbox");
		if (addbox) {
			// The home title bar ("&nbsp;" + this form) only added an empty top margin;
			// once the form is on our toolbar, hide that now-empty title row. Done here,
			// not in CSS, so the native fallback (JS off) still shows the add-widget form.
			var titleHost = addbox.closest("table.table-fiche-title");
			bar.insertBefore(addbox, bar.firstChild);
			if (titleHost) titleHost.style.display = "none";
		}
		// No "add a row" any more: there are no rows to add to, a widget is simply
		// dropped where there is room.
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
		var measure = measurer(widgets); // heights read before the widgets are moved
		render(dash, normalize(store.cached(), widgets, measure), widgets);
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
				var merged = normalize(remote, widgets, measure);
				if (JSON.stringify(merged) !== JSON.stringify(grid.layout())) {
					render(dash, merged, widgets);
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

	Grid.prototype.setEditing = function (editing) {
		this.editing = editing;
		this.dash.classList.toggle("fz-dash-edit", editing);
		var toggle = $(".fz-dash-toggle", this.dash);
		if (toggle) toggle.setAttribute("aria-pressed", editing ? "true" : "false");
		if (!editing) this.save(true);
	};

	// Called after core removed a closed widget from the DOM. The hole it leaves is
	// not filled: a free placement means the user decides what moves.
	Grid.prototype.dropMissing = function () {
		$all(".fz-dash-cell", this.dash).forEach(function (cell) {
			if (!$(".box", cell)) cell.remove();
		});
	};

	Grid.prototype.reset = function () {
		render(this.dash, normalize(null, this.widgets, measurer(this.widgets)), this.widgets);
		this.save(true);
	};

	/* ---- drag: moving a widget on the board -------------------------- */

	// The board is a grid with explicitly placed items, so a drag has nothing to
	// reorder: it writes two numbers, and CSS puts the widget there. Overlapping is
	// allowed while dragging — the grid stacks the cells — and resolved on drop by
	// pushing what was underneath further down, never by rearranging the board.
	Grid.prototype.metrics = function () {
		var gridEl = $(".fz-dash-grid", this.dash);
		var width = gridEl ? gridEl.getBoundingClientRect().width : 0;
		return { gridEl: gridEl, pitch: width / COLS, unit: UNIT_H };
	};

	// Push every widget the moved one now covers straight down, then repeat for what
	// those in turn cover. Downwards only: a widget the user placed never moves aside.
	Grid.prototype.resolveOverlaps = function (movedCell) {
		var cells = $all(".fz-dash-cell", this.dash);
		var byCell = cells.map(function (cell) { return { cell: cell, rect: rectFromCell(cell) }; });
		var moved = byCell.filter(function (entry) { return entry.cell === movedCell; })[0];
		if (!moved) return;

		var queue = [moved];
		var guard = 0;
		while (queue.length && guard++ < 200) {
			var current = queue.shift();
			byCell.forEach(function (entry) {
				if (entry === current || !overlaps(current.rect, entry.rect)) return;
				entry.rect.y = current.rect.y + current.rect.h;
				applyRect(entry.cell, entry.rect);
				queue.push(entry);
			});
		}
	};

	Grid.prototype.wireDrag = function () {
		var grid = this;
		var dash = this.dash;

		dash.addEventListener("pointerdown", function (ev) {
			if (!grid.editing || ev.button || isNarrow()) return;
			if (ev.target.closest(".fz-dash-resize, .fz-dash-del, .fz-dash-expand")) return;
			var handle = ev.target.closest(".fz-dash-grip, .box_titre");
			if (!handle) return;
			var cell = handle.closest(".fz-dash-cell");
			if (!cell) return;

			ev.preventDefault();
			try { handle.setPointerCapture(ev.pointerId); } catch (e) {}

			var metrics = grid.metrics();
			var start = rectFromCell(cell);
			var startX = ev.clientX;
			var startY = ev.clientY;
			dash.classList.add("fz-dash-dragging");
			cell.classList.add("fz-dash-moving");

			// Coalesce pointer moves to one grid re-place per frame: the browser fires
			// pointermove faster than it paints, and each applyRect relays the whole grid.
			var moveRaf = 0, lastMove = null;
			function paintMove() {
				moveRaf = 0;
				if (!lastMove) return;
				var moveEv = lastMove;
				lastMove = null;
				applyRect(cell, {
					x: start.x + Math.round((moveEv.clientX - startX) / metrics.pitch),
					y: Math.max(0, start.y + Math.round((moveEv.clientY - startY) / metrics.unit)),
					w: start.w,
					h: start.h
				});
			}
			function move(moveEv) {
				lastMove = moveEv;
				if (moveRaf) return;
				moveRaf = window.requestAnimationFrame(paintMove);
			}
			function up() {
				if (moveRaf) {
					window.cancelAnimationFrame(moveRaf);
					paintMove();
				}
				handle.removeEventListener("pointermove", move);
				handle.removeEventListener("pointerup", up);
				handle.removeEventListener("pointercancel", up);
				dash.classList.remove("fz-dash-dragging");
				cell.classList.remove("fz-dash-moving");
				grid.resolveOverlaps(cell);
				grid.save();
			}
			handle.addEventListener("pointermove", move);
			handle.addEventListener("pointerup", up);
			handle.addEventListener("pointercancel", up);
		});
	};

	/* ---- corner resize ------------------------------------------------ */

	// Same gesture as the move, on the other two numbers. Pointer events rather than
	// jQuery UI's resizable — which Dolibarr does bundle: resizable writes an inline
	// width/height in pixels, while a cell here is placed by grid tracks and units,
	// and the two would fight on every frame.
	Grid.prototype.wireResize = function () {
		var grid = this;
		var dash = this.dash;

		dash.addEventListener("pointerdown", function (ev) {
			if (!grid.editing || ev.button || isNarrow()) return;
			var handle = ev.target.closest ? ev.target.closest(".fz-dash-resize") : null;
			if (!handle) return;
			var cell = handle.closest(".fz-dash-cell");
			if (!cell) return;
			var corner = handle.getAttribute("data-fz-corner") || "se";

			ev.preventDefault();
			ev.stopPropagation();      // the move gesture must not start as well
			try { handle.setPointerCapture(ev.pointerId); } catch (e) {}

			var metrics = grid.metrics();
			var start = rectFromCell(cell);
			var startX = ev.clientX;
			var startY = ev.clientY;
			dash.classList.add("fz-dash-resizing", "fz-dash-resizing-" + corner);

			var moveRaf = 0, lastMove = null;
			function paintMove() {
				moveRaf = 0;
				if (!lastMove) return;
				var moveEv = lastMove;
				lastMove = null;
				var deltaX = Math.round((moveEv.clientX - startX) / metrics.pitch);
				var deltaY = Math.round((moveEv.clientY - startY) / metrics.unit);
				applyRect(cell, resizeFromCorner(start, corner, deltaX, deltaY));
			}
			function move(moveEv) {
				lastMove = moveEv;
				if (moveRaf) return;
				moveRaf = window.requestAnimationFrame(paintMove);
			}
			function up() {
				if (moveRaf) {
					window.cancelAnimationFrame(moveRaf);
					paintMove();
				}
				handle.removeEventListener("pointermove", move);
				handle.removeEventListener("pointerup", up);
				handle.removeEventListener("pointercancel", up);
				dash.classList.remove("fz-dash-resizing", "fz-dash-resizing-" + corner);
				grid.resolveOverlaps(cell);
				notifyResize(); // a chart redraws into the box it was just given
				grid.save();
			}
			handle.addEventListener("pointermove", move);
			handle.addEventListener("pointerup", up);
			handle.addEventListener("pointercancel", up);
		});

		// The grip is a real button, so the same sizes are reachable from the keyboard —
		// which is also the answer to a corner being hard to aim at with a mouse.
		dash.addEventListener("keydown", function (ev) {
			if (!grid.editing) return;
			var handle = ev.target.closest ? ev.target.closest(".fz-dash-resize") : null;
			if (!handle) return;
			var cell = handle.closest(".fz-dash-cell");
			if (!cell) return;
			var rect = rectFromCell(cell);
			var corner = handle.getAttribute("data-fz-corner") || "se";
			var deltaX = 0;
			var deltaY = 0;

			if (ev.key === "ArrowRight") deltaX = 1;
			else if (ev.key === "ArrowLeft") deltaX = -1;
			else if (ev.key === "ArrowDown") deltaY = RESIZE_STEP;
			else if (ev.key === "ArrowUp") deltaY = -RESIZE_STEP;
			else return;

			ev.preventDefault();
			applyRect(cell, resizeFromCorner(rect, corner, deltaX, deltaY));
			grid.resolveOverlaps(cell);
			notifyResize();
			grid.save();
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
			if (target.closest(".fz-dash-reset")) {
				if (window.confirm(tr("dash-resetask", "Reset the dashboard layout?"))) grid.reset();
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
			// The grips are buttons so they can be focused; a plain click must not
			// submit anything or scroll the page.
			if (target.closest(".fz-dash-grip")) ev.preventDefault();
		});

		document.addEventListener("keydown", function (ev) {
			if (ev.key === "Escape" && grid.editing) grid.setEditing(false);
		});

		this.wireDrag();
		this.wireResize();
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
