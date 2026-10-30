-- Activate the "fusion" unified responsive theme.
-- Apply with:  mysql dolibarr < theme/fusion/sql/activate_fusion.sql
-- Revert: in Home > Setup > Display, pick the "eldy" skin again (or set MAIN_THEME back to 'eldy').

SET @e := 1;  -- default company entity

DELETE FROM llx_const
 WHERE name IN ('ALLOW_THEME_JS','MAIN_THEME','MAIN_USE_TOP_MENU_SEARCH_DROPDOWN')
   AND entity = @e;

-- The two constants the theme needs are visible in Setup > Other setup (same notes as
-- fz_fusion_required_consts() in branding/branding.lib.php); MAIN_THEME stays hidden, as
-- Setup > Display writes it.
INSERT INTO llx_const (name, entity, value, type, visible, note) VALUES
 ('ALLOW_THEME_JS',                     @e, '1',      'chaine', 1, 'Fusion theme: Dolibarr loads fusion.js, which builds the menu, dashboard, history and search'),
 ('MAIN_THEME',                         @e, 'fusion', 'chaine', 0, 'Fusion unified responsive theme'),
 ('MAIN_USE_TOP_MENU_SEARCH_DROPDOWN',  @e, '1',      'chaine', 1, 'Fusion theme: Dolibarr renders the search field Fusion moves into its sidebar (needed by the all types search)');

-- The theme's own settings, written hidden before v1.4.3
UPDATE llx_const SET visible = 1 WHERE name LIKE 'FUSION\_%' AND entity = @e;

-- Defaults that only apply when nothing was chosen yet (INSERT IGNORE: an existing value,
-- set in Setup > Display or in the Fusion theme tab, is kept).
INSERT IGNORE INTO llx_const (name, entity, value, type, visible, note) VALUES
 ('MAIN_USE_TOP_MENU_QUICKADD_DROPDOWN',@e, '1',      'chaine', 1, 'Fusion theme: Dolibarr renders the quick-add (+) menu Fusion moves into its sidebar'),
 ('MAIN_DISABLE_GLOBAL_WORKBOARD',      @e, '1',      'chaine', 0, 'Fusion: hide the home page workboard tiles by default');

-- The Bookmarks module (the star of the sidebar), on by default, cannot be enabled from SQL:
-- a module also needs its permissions and menus. The "Apply the required settings" button of
-- the Fusion theme tab enables it, or Home > Setup > Modules.
