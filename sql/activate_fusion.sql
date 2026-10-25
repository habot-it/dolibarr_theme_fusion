-- Activate the "fusion" unified responsive theme.
-- Apply with:  mysql dolibarr < theme/fusion/sql/activate_fusion.sql
-- Revert: in Home > Setup > Display, pick the "eldy" skin again (or set MAIN_THEME back to 'eldy').

SET @e := 1;  -- default company entity

DELETE FROM llx_const
 WHERE name IN ('ALLOW_THEME_JS','MAIN_THEME',
                'MAIN_USE_TOP_MENU_SEARCH_DROPDOWN','MAIN_USE_TOP_MENU_QUICKADD_DROPDOWN')
   AND entity = @e;

INSERT INTO llx_const (name, entity, value, type, visible, note) VALUES
 ('ALLOW_THEME_JS',                     @e, '1',      'chaine', 0, 'Fusion: autoload theme/<theme>/<theme>.js'),
 ('MAIN_THEME',                         @e, 'fusion', 'chaine', 0, 'Fusion unified responsive theme'),
 ('MAIN_USE_TOP_MENU_SEARCH_DROPDOWN',  @e, '1',      'chaine', 0, 'Fusion: provide search node to relocate'),
 ('MAIN_USE_TOP_MENU_QUICKADD_DROPDOWN',@e, '1',      'chaine', 0, 'Fusion: provide quick-add node to relocate');

-- Defaults that only apply when nothing was chosen yet (INSERT IGNORE: an existing value,
-- set in Setup > Display or in the Fusion theme tab, is kept).
INSERT IGNORE INTO llx_const (name, entity, value, type, visible, note) VALUES
 ('MAIN_DISABLE_GLOBAL_WORKBOARD',      @e, '1',      'chaine', 0, 'Fusion: hide the home page workboard tiles by default');
