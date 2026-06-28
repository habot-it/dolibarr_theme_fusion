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

## Contents

| File | Purpose |
| --- | --- |
| `style.css.php` | Includes eldy's stylesheet, then applies the Fusion shell overrides (scoped under `html.fusion`). |
| `fusion.js` | Restructures the menus into a single navigation (auto-loaded when `ALLOW_THEME_JS = 1`). |
| `theme_vars.inc.php` | Reuses eldy's theme variables and options. |
| `activate_fusion.sql` | Enables the theme and the required constants. |
| `thumb.png` | Preview thumbnail shown in the theme list. |

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

Distributed under the **GNU General Public License v3.0 or later**
(GPL-3.0-or-later), like Dolibarr itself. See the [LICENSE](LICENSE) file.

Copyright (C) 2026 Alban DEZANDÉE
