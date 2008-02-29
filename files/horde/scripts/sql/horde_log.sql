-- $Horde: horde/scripts/sql/horde_log.sql,v 1.1 2004/09/18 17:20:59 chuck Exp $

CREATE TABLE horde_log (
    id          INT NOT NULL,
    logtime     TIMESTAMP NOT NULL,
    ident       CHAR(16) NOT NULL,
    priority    INT NOT NULL,
    -- For DBs that don't support the TEXT field type:
    -- message  VARCHAR(2048),
    message     TEXT,
    PRIMARY KEY (id)
);

GRANT INSERT ON horde_log TO horde;
