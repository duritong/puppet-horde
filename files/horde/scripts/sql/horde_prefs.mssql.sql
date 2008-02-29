-- $Horde: horde/scripts/sql/horde_prefs.mssql.sql,v 1.1.2.2 2006/06/29 16:29:03 jan Exp $

CREATE TABLE horde_prefs (
    pref_uid        VARCHAR(255) NOT NULL,
    pref_scope      VARCHAR(16) NOT NULL DEFAULT '',
    pref_name       VARCHAR(32) NOT NULL,
    pref_value      VARCHAR(MAX),
--
    PRIMARY KEY (pref_uid, pref_scope, pref_name)
);

CREATE INDEX pref_uid_idx ON horde_prefs (pref_uid);
CREATE INDEX pref_scope_idx ON horde_prefs (pref_scope);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_prefs TO horde;
