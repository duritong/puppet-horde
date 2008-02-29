-- $Horde: horde/scripts/sql/horde_log.mssql.sql,v 1.1.2.1 2006/01/18 19:58:48 ben Exp $

CREATE TABLE horde_log (
    id          INT NOT NULL,
    logtime     TIMESTAMP NOT NULL,
    ident       CHAR(16) NOT NULL,
    priority    INT NOT NULL,
    -- For DBs that don't support the VARCHAR(MAX) field type:
    -- message  VARCHAR(2048),
    message     VARCHAR(MAX),
    PRIMARY KEY (id)
);

GRANT INSERT ON horde_log TO horde;
