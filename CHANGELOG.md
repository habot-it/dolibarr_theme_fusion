# Changelog

All notable changes to the Fusion theme are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); this project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html), one `vX.Y.Z` commit per release.

## [Unreleased]

### Fixed
- The Remote Clocking dashboard widget now keeps the same 44px header height and typography as the
  other widgets, truncates its title on one line in narrow cells, and centers its clock, status and
  actions inside the height selected by the user.

### Performance
- Main-menu chevrons no longer require downloading and parsing every module home page on startup.
  Only the section actually opened by the user is fetched; deeper contextual levels are discovered
  during browser idle time with at most two background requests at once.
- Dashboard redraw requests are coalesced to one pass per animation frame, and pointer-driven moves
  and resizes no longer recompute the grid more often than the browser can paint it.
- The obsolete masonry observer has been removed from the explicit-position v4 dashboard. Widget
  size changes therefore no longer schedule an empty layout pass.
- Sidebar tooltip cleanup now reacts only when a tooltip marker is actually added, instead of
  rescanning the complete navigation after every open, close, hover or active-state class change.

## [1.1.0] — 2026-08-14

### Added
- Widgets are placed on the board instead of flowing into rows. Each one carries its own rectangle
  `{x, y, w, h}` — x and w over 24 fluid tracks, y and h in units of 8 pixels — so it can be put
  where it is wanted, holes included, twice as finely as the twelve tracks of the row model. The
  tracks stay fractional, which is what keeps a board adapting to the width it is given; a fixed
  pixel canvas, as Home Assistant's Drag-And-Drop-Card uses, would have cost that and asked for one
  layout per screen size.
- Moving and resizing are direct gestures: the title bar or the grip drags a widget, the corner grip
  resizes it, both snapping to the tracks and units, and both reachable from the keyboard. Dropping a
  widget pushes whatever it covered downwards, in cascade — never sideways, so nothing the user
  placed is rearranged behind their back. The layout dialog stays for setting a size by hand.

### Changed
- Rows are gone from the model and from the interface: no row toolbars, and no "add a row" — a widget
  is simply dropped where there is room. Layouts stored by the earlier versions are converted on
  load: each row becomes a band as tall as its tallest widget, so the picture the user left is the
  picture they get back, and from there every widget moves on its own.
- A height is now always explicit. The "automatic height" of the row model cannot survive an explicit
  y — the widgets below would have nowhere to be until their neighbour above had finished rendering —
  so a widget whose content outgrows its box scrolls inside it. Migration reads what each widget
  occupied on screen, so nothing changes size on the way over.
- `dashboard.php` stores the new shape and still reads the old one; the order sent to `llx_boxes` (the
  degraded view, theme disabled) follows the board's reading order, top to bottom then left to right.
- In portrait the board stops being a grid: it becomes one column in that same reading order, each
  widget at its natural height, and the placement gestures are disabled — there is nothing to aim at
  in a single column.

### Fixed
- Charts follow the size of their widget, in both directions. A chart is recognised by its `canvas`
  rather than by ClearView class names that match no markup in the installed module, the height is
  handed down the chain from the row's cell to the drawing, and the canvas is taken out of flow —
  a table row is never shorter than its content, which is why a chart grew but never shrank. Chart.js
  instances are asked to re-measure directly, since Chart.js 3 observes its container and never the
  window event that was being raised for it.
- In portrait the page sat 66px to the right of the screen: `.fz-collapsed` / `.fz-peek` keep the
  container at the folded rail's offset and carry one class more than the portrait rule, so they won
  the cascade with no rail on screen to justify it. Same leak, same fix, for the native "search into…"
  scope list, which the folded-rail flyout pinned open in the drawer, above the menu.
- The drawer parked off-canvas painted its shadow outside its box — that is, down the left edge of
  the page — at all times. It belongs to the open drawer.
- A chart widget keeps a definite height in portrait, capped at 45% of the screen: released like the
  others it collapsed into a strip of overlapping labels.

## [1.0.34] — 2026-08-14

### Changed
- The buttons of the user panel are back to an even 1 px border on all four sides: the heavier bottom
  edge of 1.0.31 is dropped, along with the `--fz-border-strong` palette color added for it. The
  exclusion that keeps the underline killer off those buttons stays, so their bottom side is still
  drawn.

## [1.0.33] — 2026-08-14

### Fixed
- The heavier bottom edge of the user-panel buttons now reads as an edge. It was mixed from the
  hairline border and the text color with `color-mix()` and rendered indistinguishable from the other
  three sides; it is drawn from a palette color of its own instead — `--fz-border-strong`, 42% black
  in the light palette and 38% white in the dark one.

## [1.0.32] — 2026-08-14

### Fixed
- The buttons of the user panel get their bottom edge back. The sidebar carries a safety net that
  strips the underline Dolibarr draws as a `border-bottom` on every link of `#fz-user`; being written
  with two ids, it outranked the rule that styles those buttons and erased their bottom border — so
  they had been missing it all along, and the relief added in 1.0.31 never showed. The underline
  killer now skips `.button-top-menu-dropdown`, whose border is deliberate.

## [1.0.31] — 2026-08-14

### Changed
- The three buttons of the user panel carry a heavier bottom edge, which gives them relief. Its color
  is mixed from the border and the text color, so it reads as a darker line in the light palette and a
  lighter one in the dark palette without needing a second variable.

## [1.0.30] — 2026-08-14

### Changed
- The user panel is back to the width of the rail, instead of spilling over the content area: its
  three footer buttons keep their single row at that width through tighter paddings, gaps and a
  narrower icon-only virtual-card button. The panel opened from the collapsed rail is given the same
  width, so it does not change size when the rail is folded.

## [1.0.29] — 2026-08-14

### Changed
- The three buttons at the bottom of the user panel (card, virtual card, logout) sit on a single row.
  Dolibarr lays them out with floats (`.pull-left` ×2 + `.pull-right`), which dropped the logout onto a
  second line in a panel that narrow: the row is now a nowrap flex line where the two labelled buttons
  share the leftover width and the icon-only virtual-card button keeps its size, the panel is a little
  wider than the rail so the labels fit whole, and a label ellipsises instead of wrapping if the panel
  ever gets narrower.

## [1.0.28] — 2026-08-14

### Fixed
- A top-menu entry whose picto is an image — `img_picto('', 'logo.png@mymodule')`, stored as
  `<img src="…">` in `llx_menu.prefix` — shows its icon again. Dolibarr only prints a picto that is a
  `<span …>` or an `fa-` class and silently replaces anything else by an empty `.tmenuimageforpng`
  span, so the image was lost in the native top bar as much as in the Fusion rail, which reuses that
  node. The image is re-attached as a background on `div.mainmenu.<code>`, the way module icons are
  already declared, with no core and no module change.

### Changed
- Native menu icons are scaled with `background-size: contain` instead of a forced 20×20 box, so a
  module logo that is not square keeps its ratio.

## [1.0.27] — 2026-08-13

### Fixed
- On Dolibarr 20 and older, the bookmark, quick-add and logout icons now sit in the sidebar tool row
  next to the print button, as on Dolibarr 21+. Those releases print the bookmark and quick-add
  dropdowns inside the user block instead of a `.login_block_tools` container, so they used to be
  carried down to the footer along with the user menu.

## [1.0.26] — 2026-08-07

### Changed
- The brand logo is clipped to the theme corner radius, so a squared logo file gets the same rounded
  corners as the placeholder tile.

## [1.0.25] — 2026-08-05

### Fixed
- Fusion's content width and sidebar offset now take precedence over module-level layout overrides.
- Large jQuery UI popups are sized and centered against Fusion's actual content area, and update while
  the sidebar is expanded or collapsed.
- TimeMoto's Timeline header uses compact 40 px controls, inline filters and square period arrows;
  actions stay inside their available column and wrap instead of overflowing the page.
- The embedded TimeMoto clock is reorganized into a compact horizontal card with clock, details and
  two stacked 40 px actions.

## [1.0.24] — 2026-08-04

### Changed
- Fusion is now self-contained: the Dolibarr 22.0.5 component stylesheet base, its includes and its
  image resources are bundled and versioned with the theme. No runtime dependency on `theme/eldy`
  remains.

## [1.0.23] — 2026-08-03

### Added
- Configurable dashboard grid on every page that shows widgets (the home page and the module home
  pages), built on Home Assistant's model. Dolibarr's two hard-coded columns become independent rows
  twelve tracks wide, and each widget carries its own width (1 to 12 tracks) and height: widgets flow
  inside their row and wrap, so one sitting above another is simply what the widths produce. Sizing is
  done in the widget's *Layout* dialog, where the size is picked as a rectangle in a grid (with
  *full width* and *automatic height* shortcuts) and the widget itself previews the choice live;
  moving is done by the title bar, and
  rows can be added, reordered and deleted. Controls appear on the row or widget being hovered, so
  edit mode stays readable. Widgets are moved, never rebuilt, so their content, links and permissions
  are untouched, and Dolibarr core is not modified.
- A button in every widget header opens that widget in a Dolibarr dialog, at full size.

### Changed
- Dolibarr's close cross and move grip are taken out of the widget header: removing a widget is now
  done from its toolbar in *Customize* mode, so a stray click cannot drop a widget any more. The cross
  itself is kept, hidden, because core's own removal handler is bound to it.
- `fusion-dashboard.js`, loaded on demand by `fusion.js` only on pages that render a widget area.
- `dashboard.php`, the theme's storage endpoint: the layout is saved per user and per zone in
  `llx_user_param` (`llx_boxes` cannot express more than two columns), with localStorage as an instant
  cache. Dolibarr's own box order is kept in sync, so disabling the theme leaves a coherent page.

## [1.0.22] — 2026-07-21

### Fixed
- Active section is now detected from the `mainmenu` URL parameter, falling back to Dolibarr's
  `tmenusel` class — sections whose selected state sits on the inner link are recognized again.
- Left-menu links carrying only `leftmenu=` are rewritten to also carry `mainmenu=`, so opening a
  cached or on-demand submenu keeps Dolibarr's session menu state correct.
- Active submenu item matching prefers the branch selected by `leftmenu` before falling back to a
  same-path match.

## [1.0.21] — 2026-07-09

### Changed
- The dark palette lives in a single PHP heredoc emitted for both forced dark and `auto` + OS dark, so
  the two can no longer drift apart.
- Menu icons follow the nav foreground color instead of the dimmed one.
- The alternate brand logo is preloaded and decoded asynchronously, so the wordmark ⇄ square icon swap
  comes from cache instead of firing a request mid-animation.

## [1.0.20] — 2026-06-29

### Changed
- All `localStorage` / `sessionStorage` access goes through two guarded helpers.
- Dropped the unused accent variables and stale comments; README credits HABOT IT.

## [1.0.19] — 2026-06-29

### Fixed
- Open dropdowns are closed and the brand/tools/favorites blocks hidden while the rail animates, so
  nothing ghosts across the width transition.
- Logo lookup simplified and now prefers Dolibarr's lighter `_small` thumbnail, with a full-resolution
  fallback.

## [1.0.18] — 2026-06-29

### Added
- README and GPLv3 LICENSE.

### Changed
- `auto` mode always follows the OS preference, regardless of Dolibarr's `THEME_DARKMODEENABLED`
  setting — Fusion's own switch is the sole authority for dark mode.
- Brand alignment reworked so the logo keeps the same left margin and size expanded or collapsed.

## [1.0.17] — 2026-06-29

### Removed
- The fallback search form: the native Dolibarr search block is now the only source, and its wiring is
  skipped entirely when no such block exists.

## [1.0.16] — 2026-06-28

### Added
- Dolibarr's own generated menu icons are reused when a module provides one, so native colors and images
  survive; `THEME_MENU_COLORLOGO` is honored.

### Changed
- Shell colors bridge explicitly to Dolibarr's theme options: the *top menu* color drives the sidebar,
  the *left menu* color drives the opened submenus.
- The stored color mode is validated before being applied.

## [1.0.15] — 2026-06-28

### Added
- Real company logo in the brand bar: the wide wordmark when expanded, the dedicated square icon when
  collapsed, resolved from `MAIN_INFO_SOCIETE_LOGO*`.

### Fixed
- Labels are cleaned of HTML entities and tags; the login suffix is stripped from the displayed user name.

## [1.0.14] — 2026-06-25

### Added
- Navigation scroll position is preserved across page loads.

### Fixed
- Sections without a real submenu are detected up front, so no chevron or empty panel is shown for them.
- Tooltip placement tuned and restricted to the primary menu icons.

## [1.0.13] — 2026-06-25

### Changed
- Tooltips reworked: one tooltip per primary icon, shown only when the rail is collapsed; every other
  inherited `title` is stripped and promoted to `aria-label`.
- UI strings come from Dolibarr's translations through CSS custom properties instead of being hardcoded
  in French.

## [1.0.12] — 2026-06-25

### Changed
- A section label now navigates; only its chevron folds/unfolds the submenu. Chevrons became real
  clickable targets at every depth.

## [1.0.11] — 2026-06-25

### Added
- `optioncss=print` support: the script bails out and the stylesheet emits a content-only print layout.

## [1.0.10] — 2026-06-25

### Added
- Light / Auto / Dark segmented switch in the sidebar footer, with a flyout variant on the collapsed
  rail; Escape closes it.

## [1.0.9] — 2026-06-25

### Added
- Dolibarr version badge in the brand bar.
- Recursive submenu preloading behind a limited-concurrency queue with a per-session cache, so every
  level opens instantly.
- Click-to-peek: clicking a section on a collapsed rail expands it as an overlay, closing on Escape,
  outside click or navigation.
- Accordion for nested submenus at any depth.

### Fixed
- The module builder link opens in the same tab instead of the hardcoded `modulebuilder` target.

## [1.0.8] — 2026-06-24

### Added
- The remaining top-right tools (module builder, print, help and plugin icons added through the
  `printTopRightMenu` hook) are moved into the sidebar tool row.

### Changed
- Every tool renders as an identical 34×34 button so the row aligns perfectly.

## [1.0.7] — 2026-06-24

### Fixed
- The search scope list is anchored just under the search field instead of relying on unreliable
  percentage offsets.

## [1.0.6] — 2026-06-24

### Fixed
- The user avatar no longer jumps while the sidebar width animates (eldy's forced paddings neutralized).
- Eldy's hard-coded light backgrounds in the user and bookmark dropdowns are re-skinned with theme
  variables, so dark mode holds.

## [1.0.5] — 2026-06-24

### Fixed
- Icons and logo keep their position when folding the rail: the collapse is animated rather than snapped.

## [1.0.4] — 2026-06-24

### Added
- Submenus fold and unfold with a height animation driven from the element's `scrollHeight`.
- `prefers-reduced-motion` is honored (instant fold/unfold).

### Fixed
- The active section opens instantly on page load, without an unfolding flash.

## [1.0.3] — 2026-06-24

### Added
- On-demand submenus: clicking a section fetches its page in the background and lifts its left menu into
  the sidebar instead of navigating, with the chevron pulsing while loading and a plain navigation
  fallback.
- Accordion behavior: a single section stays unfolded at a time.

## [1.0.2] — 2026-06-24

### Removed
- The redundant "Navigation" section label.

## [1.0.1] — 2026-06-23

### Changed
- The brand logo links to the real Home menu URL.
- The collapse control became a round floating handle straddling the sidebar edge, sliding with it.
- User dropdown footer buttons normalized to a consistent size.

## [1.0.0] — 2026-06-22

Initial release.

### Added
- Unified responsive shell built entirely client-side from Dolibarr's existing menus, with no core
  change: a retractable left sidebar in landscape, a top bar with a slide-in drawer in portrait.
- `style.css.php` layered on top of eldy, `fusion.js`, `activate_fusion.sql` and the theme thumbnail.

[1.0.34]: https://github.com/habot-it/dolibarr_theme_fusion/commit/8072749
[1.0.33]: https://github.com/habot-it/dolibarr_theme_fusion/commit/ee755df
[1.0.32]: https://github.com/habot-it/dolibarr_theme_fusion/commit/3dc3ebf
[1.0.31]: https://github.com/habot-it/dolibarr_theme_fusion/commit/690f3b6
[1.0.30]: https://github.com/habot-it/dolibarr_theme_fusion/commit/00363a7
[1.0.29]: https://github.com/habot-it/dolibarr_theme_fusion/commit/2e7d60a
[1.0.28]: https://github.com/habot-it/dolibarr_theme_fusion/commit/2ee7dd0
[1.0.27]: https://github.com/habot-it/dolibarr_theme_fusion/commit/64ff188
[1.0.26]: https://github.com/habot-it/dolibarr_theme_fusion/commit/4fc5406
[1.0.25]: https://github.com/habot-it/dolibarr_theme_fusion/commit/7b26a98
[1.0.24]: https://github.com/habot-it/dolibarr_theme_fusion/commit/b6723a9
[1.0.23]: https://github.com/habot-it/dolibarr_theme_fusion/commit/20049c3
[1.0.22]: https://github.com/habot-it/dolibarr_theme_fusion/commit/874badd
[1.0.21]: https://github.com/habot-it/dolibarr_theme_fusion/commit/8f0865e
[1.0.20]: https://github.com/habot-it/dolibarr_theme_fusion/commit/46ef730
[1.0.19]: https://github.com/habot-it/dolibarr_theme_fusion/commit/f115d99
[1.0.18]: https://github.com/habot-it/dolibarr_theme_fusion/commit/275b413
[1.0.17]: https://github.com/habot-it/dolibarr_theme_fusion/commit/b05adde
[1.0.16]: https://github.com/habot-it/dolibarr_theme_fusion/commit/ecb84b2
[1.0.15]: https://github.com/habot-it/dolibarr_theme_fusion/commit/9ed94a6
[1.0.14]: https://github.com/habot-it/dolibarr_theme_fusion/commit/6951461
[1.0.13]: https://github.com/habot-it/dolibarr_theme_fusion/commit/c97fb93
[1.0.12]: https://github.com/habot-it/dolibarr_theme_fusion/commit/6c75ab5
[1.0.11]: https://github.com/habot-it/dolibarr_theme_fusion/commit/54c46dc
[1.0.10]: https://github.com/habot-it/dolibarr_theme_fusion/commit/9db5984
[1.0.9]: https://github.com/habot-it/dolibarr_theme_fusion/commit/f320903
[1.0.8]: https://github.com/habot-it/dolibarr_theme_fusion/commit/9df4859
[1.0.7]: https://github.com/habot-it/dolibarr_theme_fusion/commit/a1f9a47
[1.0.6]: https://github.com/habot-it/dolibarr_theme_fusion/commit/02c1cc7
[1.0.5]: https://github.com/habot-it/dolibarr_theme_fusion/commit/a67e30e
[1.0.4]: https://github.com/habot-it/dolibarr_theme_fusion/commit/696ee23
[1.0.3]: https://github.com/habot-it/dolibarr_theme_fusion/commit/605f666
[1.0.2]: https://github.com/habot-it/dolibarr_theme_fusion/commit/caeb9db
[1.0.1]: https://github.com/habot-it/dolibarr_theme_fusion/commit/e9af71b
[1.0.0]: https://github.com/habot-it/dolibarr_theme_fusion/commit/d8e6551
