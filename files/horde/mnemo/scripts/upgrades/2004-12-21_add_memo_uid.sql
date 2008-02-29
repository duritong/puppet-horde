-- $Horde: mnemo/scripts/upgrades/2004-12-21_add_memo_uid.sql,v 1.1 2004/12/21 15:38:47 chuck Exp $

ALTER TABLE mnemo_memos ADD COLUMN memo_uid VARCHAR(255) NOT NULL;
CREATE INDEX mnemo_uid_idx ON mnemo_memos (memo_uid);
