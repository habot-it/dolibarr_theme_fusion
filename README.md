# Fusion — unified responsive theme for Dolibarr

**Fusion** is a [Dolibarr](https://www.dolibarr.org/) theme derived from **eldy**.
It reuses every component style of eldy (tables, forms, cards, badges, …) and only
overrides the application shell to **merge Dolibarr's two menus** (horizontal top
bar + vertical left menu) into a **single responsive navigation**:

- **landscape** — one retractable left sidebar (full ⇄ icons only);
- **portrait** — a top bar with a hamburger button that opens the same menu as a
  slide-in drawer.

The DOM merge is performed client-side by `fusion.js`, **with no change to the
Dolibarr core**: the script moves the existing DOM nodes, so every link,
dropdown, tooltip and permission stays intact.

Fusion also replaces the two fixed widget columns of the dashboards by a
**configurable grid**, built like Home Assistant: a row is twelve tracks wide and
every widget carries its own width and height, so widgets flow inside their row
and each row ends up with exactly the columns you gave it. Click *Customize*,
then hover a widget to reveal its three controls: move it, open its *Layout* —
where you pick the size as a rectangle in a grid, the widget previewing your
choice live — or remove it. Rows can be added, reordered and deleted the same
way. The layout is saved per user, so it follows you from one browser to
another. Outside of *Customize*, each widget header offers a button that opens
it in a Dolibarr window at full size.

## Contents

| File | Purpose |
| --- | --- |
| `style.css.php` | Includes eldy's stylesheet, then applies the Fusion shell overrides (scoped under `html.fusion`). |
| `fusion.js` | Restructures the menus into a single navigation (auto-loaded when `ALLOW_THEME_JS = 1`). |
| `fusion-dashboard.js` | Turns the widget area into a configurable grid (loaded only on pages showing widgets). |
| `dashboard.php` | Saves and loads the grid layout, per user, in `llx_user_param`. |
| `theme_vars.inc.php` | Reuses eldy's theme variables and options. |
| `activate_fusion.sql` | Enables the theme and the required constants. |
| `thumb.png` | Preview thumbnail shown in the theme list. |
| `CHANGELOG.md` | Release history. |

## Requirements

- A working Dolibarr installation with the **eldy** theme present
  (`htdocs/theme/eldy/`), which Fusion depends on.
- The theme JavaScript option enabled (constant `ALLOW_THEME_JS = 1`).

## Installation

1. Copy this folder into `htdocs/theme/fusion/` of your Dolibarr installation.
2. Enable the theme, either:

   **Via SQL** (replace `dolibarr` with your database name):

   ```sh
   mysql dolibarr < theme/fusion/activate_fusion.sql
   ```

   **Or via the UI**: *Home > Setup > Display*, pick the "fusion" theme and enable
   the theme JavaScript.

## Disabling

In *Home > Setup > Display*, select the "eldy" theme again (or set the
`MAIN_THEME` constant back to `eldy`).

## License

GPLv3 or later — see [`COPYING`](COPYING).

## Author

HABOT IT — <https://www.habot.it>