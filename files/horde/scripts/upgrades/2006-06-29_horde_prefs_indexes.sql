-- This script adds additional indexes to the horde_prefs table that should
-- improve loading of preferences from the preference table.
--
-- $Horde: horde/scripts/upgrades/2006-06-29_horde_prefs_indexes.sql,v 1.1.2.1 2006/06/29 16:29:03 jan Exp $

CREATE INDEX pref_uid_idx ON horde_prefs (pref_uid);
CREATE INDEX pref_scope_idx ON horde_prefs (pref_scope);

