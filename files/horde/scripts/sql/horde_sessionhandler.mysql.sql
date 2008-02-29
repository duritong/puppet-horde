-- $Horde: horde/scripts/sql/horde_sessionhandler.mysql.sql,v 1.1.2.1 2006/03/22 18:30:23 jan Exp $

CREATE TABLE horde_sessionhandler (
    session_id             VARCHAR(32) NOT NULL,
    session_lastmodified   INT NOT NULL,
    session_data           LONGBLOB,

    PRIMARY KEY (session_id)
) ENGINE = InnoDB;

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_sessionhandler TO horde@localhost;
