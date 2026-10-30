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

Around that shell: a **navigation history** bar above the page, a **global search**
across every type of element, and a settings tab for the logos, the favicon and these
features.

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
| `history/` | Navigation history: `history.php` stores each user's last opened elements in `llx_user_param`. |
| `search/` | Global search: `search.php` is the results page listing the matches of every type. |
| `branding/` | Theme settings and changelog tabs: `setup.php` (settings page, admins only), `changelog.php` (renders `CHANGELOG.md`), `favicon.php` (favicon endpoint), `branding.lib.php` (shared helpers). |
| `sql/activate_fusion.sql` | Enables the theme and the required constants. |
| `CHANGELOG.md` | Release history. |

## Logos and favicon

Everything about the brand is in the **Fusion theme** tab added to *Setup > Display*
(`theme/fusion/branding/setup.php`, admins only), in two blocks:

- **Identity** — the application name (`MAIN_APPLICATION_TITLE`) and the two company
  logos, *Logo* and *Logo (squarred)*. Same constants, files and thumbnails as
  *Setup > Security > Miscellaneous* and *Setup > Company*: both places stay in sync.
- **Where the logos are used** — one source per place, with a live preview:

| Place | Constant | Sources |
| --- | --- | --- |
| Menu expanded | `FUSION_LOGO_EXPANDED` | automatic, logo, squarred logo, own image, initial letter |
| Menu collapsed | `FUSION_LOGO_COLLAPSED` | automatic, logo, squarred logo, own image, initial letter |
| Favicon | `FUSION_FAVICON` | Dolibarr default (squarred logo), logo, squarred logo, own image, Dolibarr icon |

*Automatic* keeps the former behaviour: the wide logo expanded when the application
name is empty, the squarred logo otherwise; the squarred logo collapsed. *Own image*
shows an upload field (browse, delete, preview, like *Setup > Company*); the file is
stored in `<documents>/mycompany/logos/fusion/`, its name in `FUSION_*_FILE`.

The favicon is printed by Dolibarr itself; to change it without touching the core, the
settings page points Dolibarr's `MAIN_FAVICON_URL` constant at `branding/favicon.php`,
which serves the chosen image. Set the favicon back to *Dolibarr default* before switching
to another theme, or `MAIN_FAVICON_URL` keeps pointing into this folder.

## Navigation history

Above the page, a breadcrumb-like bar lists the last elements opened (third parties,
products, invoices, warehouses…), the latest on the right, each with its picto and a
tooltip. An element is recognised from Dolibarr's own card banner, so modules' cards are
picked up too. The list is kept per user in `llx_user_param` (`FUSION_HISTORY`) through
`history/history.php`: it follows the user from one browser to another and survives a
new login.

Settings, in the **Fusion theme** tab: show the history or not (`FUSION_HISTORY_DISABLED`),
elements shown (`FUSION_HISTORY_SIZE`, 10 by default) and elements kept
(`FUSION_HISTORY_KEEP`, 30 by default).

## Global search

The search field no longer asks where to search: Dolibarr's *Search into …* list is
hidden, and Enter opens a results page listing the matches of **every** type at once —
third parties, contacts, members, users, products, lots/serials, manufacturing orders,
projects, tasks, proposals, orders, shipments, receptions, invoices, template invoices,
payments, contracts, interventions, tickets, knowledge base, expense reports, leaves,
resources — grouped by type, each group with a *See all* link to its filtered list.
A type added to the search by a module is listed too, with its own link. When an element
matched on a field that is not shown (a leave's description, a contact's email…), that
field is shown under it, with the searched words in bold.

The types searched are exactly those Dolibarr's search box offers to the user (enabled
modules, read permissions, `MAIN_SEARCHFORM_*_DISABLED`), and every query applies the
restrictions of the matching list (entity, external users, sales representatives,
private contacts, authorized projects, subordinates for expense reports and leaves).
`search/search.php` only reads.

Constants: `FUSION_SEARCH_DISABLED` brings back Dolibarr's own behavior,
`FUSION_SEARCH_LIMIT` sets the matches shown per type (10 by default, 50 at most).

## Home page

The workboard tiles of the home page (*Global view*, agenda, projects, proposals…) are
hidden by default: `sql/activate_fusion.sql` sets Dolibarr's own
`MAIN_DISABLE_GLOBAL_WORKBOARD` when it has no value yet. The **Fusion theme** tab (or
*Setup > Display > Dashboard*) shows them again.

## Version

Dolibarr has no versioning for themes: `CHANGELOG.md` is the theme's record of its releases,
shown in the **Fusion theme changelog** tab next to the settings tab in *Setup > Display*. To
release, add the new version's heading (`## [X.Y.Z] — date`) on top of the changelog, in the
same `vX.Y.Z` commit.

## Requirements

- Dolibarr 22.x. Fusion does not load anything from another theme: its component
  base started as a copy of Eldy (Dolibarr 22.0.5) and is maintained within Fusion.
- Three Dolibarr constants, set by the installation below: `ALLOW_THEME_JS = 1` (Dolibarr
  loads `fusion.js`), `MAIN_USE_TOP_MENU_SEARCH_DROPDOWN = 1` and
  `MAIN_USE_TOP_MENU_QUICKADD_DROPDOWN = 1` (Dolibarr renders the search and quick-add
  that Fusion moves into its sidebar).

## Installation

1. Copy this folder into `htdocs/theme/fusion/` of your Dolibarr installation.
2. Enable the theme, either:

   **Via SQL** (replace `dolibarr` with your database name):

   ```sh
   mysql dolibarr < theme/fusion/sql/activate_fusion.sql
   ```

   The script covers entity 1 only.

   **Or via the UI**, as an administrator:

   1. *Home > Setup > Display*: pick the "fusion" theme. This sets `MAIN_THEME` only —
      Dolibarr has no option for the other constants, and without `ALLOW_THEME_JS` it does
      not load `fusion.js`: the page keeps the plain component layout.
   2. Open `https://<your-dolibarr>/theme/fusion/branding/setup.php` directly — the
      **Fusion theme** tab is added to *Setup > Display* by `fusion.js`, so it only shows
      once the constants are set. A warning lists the missing ones; click
      *Apply the required settings*.

   With several entities (multicompany), do it once per entity.

## Disabling

First set the favicon back to *Dolibarr default* in the **Fusion theme** tab, so that
`MAIN_FAVICON_URL` stops pointing into this folder. Then, in *Home > Setup > Display*,
select the "eldy" theme again (or set the `MAIN_THEME` constant back to `eldy`). Of the
constants listed in *Requirements*, `ALLOW_THEME_JS` has no effect on Eldy (it has no
script); the two `MAIN_USE_TOP_MENU_*` keep the search and quick-add dropdowns in Eldy's
top bar — remove them in *Setup > Other setup* if you do not want them.

## License

GPLv3 or later — see [`LICENSE`](LICENSE). The bundled component base retains
the original Dolibarr/Eldy copyright notices.

## Author

HABOT IT — <https://www.habot.it>
