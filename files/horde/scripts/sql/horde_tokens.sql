-- $Horde: horde/scripts/sql/horde_tokens.sql,v 1.2.10.2 2005/10/18 11:34:00 jan Exp $

CREATE TABLE horde_tokens (
    token_address    VARCHAR(100) NOT NULL,
    token_id         VARCHAR(32) NOT NULL,
    token_timestamp  BIGINT NOT NULL,
--
    PRIMARY KEY (token_address, token_id)
);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_tokens TO horde;
