-- $Horde: horde/scripts/sql/horde_histories.mssql.sql,v 1.1.2.1 2006/01/18 19:58:42 ben Exp $

CREATE TABLE horde_histories (
    history_id       BIGINT NOT NULL,
    object_uid       VARCHAR(255) NOT NULL,
    history_action   VARCHAR(32) NOT NULL,
    history_ts       BIGINT NOT NULL,
    history_desc     VARCHAR(MAX),
    history_who      VARCHAR(255),
    history_extra    VARCHAR(MAX),
--
    PRIMARY KEY (history_id)
);

CREATE INDEX history_action_idx ON horde_histories (history_action);
CREATE INDEX history_ts_idx ON horde_histories (history_ts);
CREATE INDEX history_uid_idx ON horde_histories (object_uid);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_histories TO horde;
