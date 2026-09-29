# Fusion — unified responsive theme for Dolibarr

**Fusion** is a standalone [Dolibarr](https://www.dolibarr.org/) theme. Its
component styles originated from Eldy and are now maintained within Fusion; it
does not depend on any other theme directory. Fusion also
provides its own application shell, which **merges Dolibarr's two menus**
(horizontal top bar + vertical left menu) into a **single responsive navigation**:

- **landscape** — one retractable left sidebar (full ⇄ icons only);
- **portrait** — a top bar with a hamburger button that opens the same menu as a
  slide-in drawer.

The DOM merge is performed client-side by `fusion.js`, **with no change to the
Dolibarr core**: the script moves the existing DOM nodes, so every link,
dropdown, tooltip and permission stays intact.

Fusion also replaces the two fixed widget columns of the dashboards by a
**configurable board**: 192 fluid tracks across, 8-pixel units down, and every
widget placed on it with its own position and size — holes included. Click
*Customize*, then hover a widget: drag it by its header or its move button, pull
one of its four corners to resize it (the arrow keys work on a focused corner too),
or remove it. A ruler shows the common fractions (1/4, 1/3, 1/2…) while you edit,
and a drop pushes whatever it covered downwards. The layout is saved per user, so it
follows you from one browser to another. Outside of *Customize*, each widget header
offers a button that opens it in a Dolibarr window at full size. On a narrow screen
the board becomes a single column in reading order.

## Contents

The files Dolibarr itself looks for stay at the root of the theme; everything
else is grouped by role.

| Path | Purpose |
| --- | --- |
| `style.css.php` | The theme stylesheet: loads the component base, then applies the Fusion overrides (scoped under `html.fusion`). |
| `fusion.js` | Restructures the menus into a single navigation (auto-loaded when `ALLOW_THEME_JS = 1`). |
| `theme_vars.inc.php` | Component and graph color variables, also read by some Dolibarr pages. |
| `manifest.json.php`, `thumb.png`, `img/`, `ckeditor/` | Web app manifest, theme-list thumbnail, image resources, editor configuration. |
| `base/` | Fusion's component stylesheet (tables, forms, buttons, badges, dropdowns…). |
| `dashboard/` | The configurable dashboard: `fusion-dashboard.js` (loaded only on pages showing widgets) and `dashboard.php`, which stores each user's layout in `llx_user_param`. |
| `sql/activate_fusion.sql` | Enables the theme and the required constants. |
| `CHANGELOG.md` | Release history. |

## Requirements

- Dolibarr 22.x. Fusion does not load anything from another theme: its component
  base started as a copy of Eldy (Dolibarr 22.0.5) and is maintained within Fusion.
- The theme JavaScript option enabled (constant `ALLOW_THEME_JS = 1`).

## Installation

1. Copy this folder into `htdocs/theme/fusion/` of your Dolibarr installation.
2. Enable the theme, either:

   **Via SQL** (replace `dolibarr` with your database name):

   ```sh
   mysql dolibarr < theme/fusion/sql/activate_fusion.sql
   ```

   **Or via the UI**: *Home > Setup > Display*, pick the "fusion" theme and enable
   the theme JavaScript.

## Disabling

In *Home > Setup > Display*, select the "eldy" theme again (or set the
`MAIN_THEME` constant back to `eldy`).

## License

GPLv3 or later — see [`LICENSE`](LICENSE). The bundled component base retains
the original Dolibarr/Eldy copyright notices.

## Author

HABOT IT — <https://www.habot.it>
