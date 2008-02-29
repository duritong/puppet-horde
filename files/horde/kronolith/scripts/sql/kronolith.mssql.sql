-- $Horde: kronolith/scripts/sql/kronolith.mssql.sql,v 1.1.2.2 2006/08/20 22:39:56 chuck Exp $

CREATE TABLE kronolith_events (
    event_id VARCHAR(32) NOT NULL,
    event_uid VARCHAR(255) NOT NULL,
    calendar_id VARCHAR(255) NOT NULL,
    event_creator_id VARCHAR(255) NOT NULL,
    event_description VARCHAR(MAX),
    event_location VARCHAR(MAX),
    event_status INT DEFAULT 0,
    event_attendees VARCHAR(MAX),
    event_keywords VARCHAR(MAX),
    event_exceptions VARCHAR(MAX),
    event_title VARCHAR(255),
    event_category VARCHAR(80),
    event_recurtype INT DEFAULT 0,
    event_recurinterval INT,
    event_recurdays INT,
    event_recurenddate DATETIME,
    event_start DATETIME,
    event_end DATETIME,
    event_alarm INT DEFAULT 0,
    event_modified INT NOT NULL,

    PRIMARY KEY (event_id)
);

CREATE INDEX kronolith_calendar_idx ON kronolith_events (calendar_id);
CREATE INDEX kronolith_uid_idx ON kronolith_events (event_uid);

GRANT SELECT, INSERT, UPDATE, DELETE ON kronolith_events TO horde;


CREATE TABLE kronolith_storage (
    vfb_owner      VARCHAR(255) DEFAULT NULL,
    vfb_email      VARCHAR(255) NOT NULL DEFAULT '',
    vfb_serialized VARCHAR(MAX) NOT NULL
);

CREATE INDEX kronolith_vfb_owner_idx ON kronolith_storage (vfb_owner);
CREATE INDEX kronolith_vfb_email_idx ON kronolith_storage (vfb_email);

GRANT SELECT, INSERT, UPDATE, DELETE ON kronolith_storage TO horde;
