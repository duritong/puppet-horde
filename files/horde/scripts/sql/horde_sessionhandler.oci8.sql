-- $Horde: horde/scripts/sql/horde_sessionhandler.oci8.sql,v 1.2.10.1 2005/10/18 11:34:00 jan Exp $

CREATE TABLE horde_sessionhandler (
    session_id             VARCHAR2(32) NOT NULL,
    session_lastmodified   INT NOT NULL,
    session_data           BLOB,
--
    PRIMARY KEY (session_id)
);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_sessionhandler TO horde;
