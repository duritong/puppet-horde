-- $Horde: kronolith/scripts/upgrades/2004-12-21_add_event_uid.pgsql.sql,v 1.1.2.2 2005/10/18 12:27:41 jan Exp $

ALTER TABLE kronolith_events ADD COLUMN event_uid VARCHAR(255);
UPDATE kronolith_events SET event_uid = '' WHERE event_uid IS NULL;
AlTER TABLE kronolith_events ALTER COLUMN event_uid SET NOT NULL;
CREATE INDEX kronolith_uid_idx ON kronolith_events (event_uid);
