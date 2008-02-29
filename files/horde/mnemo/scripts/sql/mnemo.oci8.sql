-- $Horde: mnemo/scripts/sql/mnemo.oci8.sql,v 1.1.2.3 2005/10/18 13:01:16 jan Exp $

CREATE TABLE mnemo_memos (
    memo_owner      VARCHAR2(255) NOT NULL,
    memo_id         VARCHAR2(32) NOT NULL,
    memo_uid        VARCHAR2(255) NOT NULL,
    memo_desc       VARCHAR2(64) NOT NULL,
    memo_body       VARCHAR2(4000),
    memo_category   VARCHAR2(80),
    memo_private    NUMBER(6) DEFAULT 0 NOT NULL,
--
    PRIMARY KEY (memo_owner, memo_id)
);

CREATE INDEX mnemo_notepad_idx ON mnemo_memos (memo_owner);
CREATE INDEX mnemo_uid_idx ON mnemo_memos (memo_uid);
