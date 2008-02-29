-- $Horde: mnemo/scripts/sql/mnemo.mssql.sql,v 1.1.2.1 2006/01/18 19:59:28 ben Exp $

CREATE TABLE mnemo_memos (
    memo_owner      VARCHAR(255) NOT NULL,
    memo_id         VARCHAR(32) NOT NULL,
    memo_uid        VARCHAR(255) NOT NULL,
    memo_desc       VARCHAR(64) NOT NULL,
    memo_body       VARCHAR(MAX),
    memo_category   VARCHAR(80),
    memo_private    SMALLINT NOT NULL DEFAULT 0,
--
    PRIMARY KEY (memo_owner, memo_id)
);

CREATE INDEX mnemo_notepad_idx ON mnemo_memos (memo_owner);
CREATE INDEX mnemo_uid_idx ON mnemo_memos (memo_uid);

GRANT SELECT, INSERT, UPDATE, DELETE ON mnemo_memos TO horde;
