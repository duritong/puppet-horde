-- $Horde: kronolith/scripts/upgrades/2004-12-21_add_event_uid.sql,v 1.1 2004/12/21 15:52:32 chuck Exp $

ALTER TABLE kronolith_events ADD COLUMN event_uid VARCHAR(255) NOT NULL;
CREATE INDEX kronolith_uid_idx ON kronolith_events (event_uid);
