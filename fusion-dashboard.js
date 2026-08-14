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
 *   board   a single grid, 24 fluid tracks across, 8-pixel units down
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
 * (llx_user_param) as {v:4, items:[{id,x,y,w,h}]}, with localStorage as an instant
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
	// v4: the board is ONE grid, not a stack of rows. A widget carries where it sits
	// (x, y) as well as how big it is (w, h) — x and w in tracks, y and h in units of
	// UNIT_H pixels. Twenty-four tracks rather than twelve: the finer step is the whole
	// point of placing a widget instead of letting it flow.
	var COLS = 24;             // tracks across the board
	var UNIT_H = 8;            // one vertical unit, in pixels
	var DEFAULT_SPAN = 12;     // a new widget takes half the width
	var WORKBOARD_SPAN = 24;   // the working board is a wide summary, never a half row
	var DEFAULT_UNITS = 40;    // …and 320px tall until it is resized
	var MIN_UNITS = 8;
	var MIN_HEIGHT = 120;
	var MAX_HEIGHT = 4000;
	var ROW_UNIT = 60;         // one row of the layout picker, in pixels
	var RESIZE_STEP = 4;       // units gained per arrow-key press on the resize grip
	var PICKER_ROWS = 8;
	var SAVE_DELAY = 500;
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
	// v4 coordinates. A placed widget has a height like it has a width: explicit. The
	// "automatic" height of v3 cannot survive an explicit y — the widgets under it
	// would have nowhere to be until their neighbour above had finished rendering.
	function clampUnits(value) {
		value = Math.round(parseFloat(value));
		if (!isFinite(value) || value <= 0) return DEFAULT_UNITS;
		return Math.max(MIN_UNITS, Math.min(Math.round(MAX_HEIGHT / UNIT_H), value));
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
	// px -> units, the conversion used by the layout dialog and by the v3 migration
	function unitsFromPx(px) { return clampUnits(Math.round((parseFloat(px) || 0) / UNIT_H)); }
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
		scheduleMasonry();
	}

	// CSS Grid does not provide cross-browser masonry yet. Give each dashboard
	// cell a span over tiny implicit rows; `grid-auto-flow:dense` can then place a
	// following widget in the free space below a shorter neighbour.
	// v4 places every widget on explicit grid rows, so nothing has to be measured to
	// know where it goes: the masonry pass that computed a span from the rendered
	// height would now fight the stored height. Kept as a no-op — the observers and
	// the call sites still exist, and a size change simply has nothing left to correct.
	function layoutMasonry() {}

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
	 *  {v:4, items:[ {id, x:0..23, y:0.., w:1..24, h: units} ]}           *
	 * ------------------------------------------------------------------ */

	// v1/v2/v3 all described a stack of rows: widgets flowed inside a row and a row
	// was as tall as its tallest widget. v4 gives every widget its own coordinates, so
	// the conversion has to invent the y each row never had. Rows are laid out top to
	// bottom, each one as tall as the tallest widget it holds — the picture the user
	// left is the picture they get back, and from there each widget can be moved on
	// its own. `measure` reports what a widget currently occupies on screen, which is
	// how a v3 automatic height becomes an explicit one.
	function migrate(layout, measure) {
		if (!layout || layout.v === 4) return layout;
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
				// twelve tracks became twenty-four: every old width covers the same space
				var w = Math.max(1, Math.min(COLS, (parseInt(item.w, 10) || 6) * 2));
				if (x + w > COLS) { x = 0; }
				var units = item.h ? unitsFromPx(item.h) : unitsFromPx(measure ? measure(String(item.id)) : 0);
				items.push({ id: String(item.id), x: x, y: y, w: w, h: units });
				tallest = Math.max(tallest, units);
				x += w;
			});
			y += tallest;
		});

		return { v: 4, items: items };
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

		return { v: 4, items: items };
	}

	// What a widget currently occupies on screen. Used to turn the automatic heights of
	// v3 — and of a widget just added from the combo — into the explicit one v4 needs.
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
		return { v: 4, items: items };
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
		// v3 pinned a height only sometimes; v4 always does, and the whole sizing and
		// adaptive-chart chain of the stylesheet keys on this class.
		cell.classList.add("fz-has-height");
	}

	// A size change alone, keeping the widget where it is (layout dialog, keyboard).
	function applySize(cell, span, height) {
		var rect = rectFromCell(cell);
		rect.w = clampSpan(span);
		if (height) rect.h = unitsFromPx(height);
		applyRect(cell, rect);
	}

	// The corner grip of Prosono's Drag-And-Drop-Card, ported to this grid: that card
	// drags a card's corner over a pixel canvas and snaps to 10px, here it snaps to
	// the board's tracks and units. The layout dialog stays — this is the direct
	// gesture, not its replacement.
	function buildResizeHandle() {
		return button("fz-dash-resize", "fa-angle-down", tr("dash-resize", "Resize this widget"));
	}

	// A widget's toolbar, the three actions Home Assistant puts on a card: move it,
	// open its layout, remove it. The size can also be set from the corner grip above,
	// but the dialog is where the choice is visible as a grid.
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
		// `canvas` and `.dolgraphchart` are what actually identify a chart.
		// The ClearView selectors below match no markup in the installed module — its
		// boxes emit a bare <canvas height="400"> — so this whole adaptive path never
		// ran for them and their chart kept the height it was drawn at, inside a box
		// that had been resized around it. They are kept for other ClearView versions.
		var adaptiveContent = widget.node.querySelector("canvas, .dolgraphchart, .clearview-interactive-chart, .clearview-worldmap, .clearview-box-graph");
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
		cell.appendChild(buildResizeHandle());

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

		// v4 keeps the height in units and lets CSS derive the pixels, so the dialog —
		// which talks in pixels, and offers a "automatic height" the model no longer
		// has — converts on the way in and on the way out.
		var startRect = rectFromCell(cell);
		var startSpan = startRect.w;
		var startHeight = startRect.h * UNIT_H;

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
			state.h = auto.checked ? startHeight : parseInt(pcell.getAttribute("data-h"), 10) * ROW_UNIT;
			paint();
			notifyResize();
		});
		full.addEventListener("change", function () {
			state.w = full.checked ? COLS : Math.min(startSpan, COLS - 1) || DEFAULT_SPAN;
			paint();
			notifyResize();
		});
		auto.addEventListener("change", function () {
			state.h = auto.checked ? startHeight : (startHeight || ROW_UNIT * 3);
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

	function render(dash, layout, widgets) {
		var byId = {};
		widgets.forEach(function (widget) { byId[widget.id] = widget; });

		// Build into a fragment first: appending a widget moves it out of the old cell,
		// so the container can then be emptied without ever detaching a live node twice.
		var grid = el("div", "fz-dash-grid");
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
		watchMasonry(this.dash);
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
		this.refresh();
		this.save(true);
	};

	/* ---- drag: moving a widget on the board -------------------------- */

	// The board is a grid with explicitly placed items, so a drag has nothing to
	// reorder: it writes two numbers, and CSS puts the widget there. Overlapping is
	// allowed while dragging — the grid stacks the cells — and resolved on drop by
	// pushing what was underneath further down, never by rearranging the board.
	Grid.prototype.metrics = function () {
		var gridEl = $(".fz-dash-grid", this.dash);
		var gap = parseFloat(getComputedStyle(this.dash).getPropertyValue("--fz-dash-gap")) || MASONRY_GAP;
		var width = gridEl ? gridEl.getBoundingClientRect().width : 0;
		return { gridEl: gridEl, gap: gap, pitch: (width + gap) / COLS, unit: UNIT_H };
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
			if (ev.target.closest(".fz-dash-resize, .fz-dash-layout, .fz-dash-del, .fz-dash-expand")) return;
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

			function move(moveEv) {
				applyRect(cell, {
					x: start.x + Math.round((moveEv.clientX - startX) / metrics.pitch),
					y: Math.max(0, start.y + Math.round((moveEv.clientY - startY) / metrics.unit)),
					w: start.w,
					h: start.h
				});
			}
			function up() {
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

			ev.preventDefault();
			ev.stopPropagation();      // the move gesture must not start as well
			try { handle.setPointerCapture(ev.pointerId); } catch (e) {}

			var metrics = grid.metrics();
			var start = rectFromCell(cell);
			var startX = ev.clientX;
			var startY = ev.clientY;
			dash.classList.add("fz-dash-resizing");

			function move(moveEv) {
				var w = start.w + Math.round((moveEv.clientX - startX) / metrics.pitch);
				var h = start.h + Math.round((moveEv.clientY - startY) / metrics.unit);
				applyRect(cell, {
					x: start.x,
					y: start.y,
					w: Math.max(1, Math.min(COLS - start.x, w)),
					h: Math.max(MIN_UNITS, h)
				});
			}
			function up() {
				handle.removeEventListener("pointermove", move);
				handle.removeEventListener("pointerup", up);
				handle.removeEventListener("pointercancel", up);
				dash.classList.remove("fz-dash-resizing");
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

			if (ev.key === "ArrowRight") rect.w = Math.min(COLS - rect.x, rect.w + 1);
			else if (ev.key === "ArrowLeft") rect.w = Math.max(1, rect.w - 1);
			else if (ev.key === "ArrowDown") rect.h = rect.h + RESIZE_STEP;
			else if (ev.key === "ArrowUp") rect.h = Math.max(MIN_UNITS, rect.h - RESIZE_STEP);
			else return;

			ev.preventDefault();
			applyRect(cell, rect);
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
			// The grips are buttons so they can be focused; a plain click must not
			// submit anything or scroll the page.
			if (target.closest(".fz-dash-grip")) ev.preventDefault();
		});

		document.addEventListener("keydown", function (ev) {
			if (ev.key === "Escape" && grid.editing) grid.setEditing(false);
		});

		this.wireDrag();
		this.wireResize();
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
