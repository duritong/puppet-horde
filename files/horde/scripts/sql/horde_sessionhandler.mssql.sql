-- $Horde: horde/scripts/sql/horde_sessionhandler.mssql.sql,v 1.1.2.1 2006/01/18 19:59:10 ben Exp $

CREATE TABLE horde_sessionhandler (
    session_id             VARCHAR(32) NOT NULL,
    session_lastmodified   INT NOT NULL,
    session_data           VARBINARY(MAX),

    PRIMARY KEY (session_id)
);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_sessionhandler TO horde;
