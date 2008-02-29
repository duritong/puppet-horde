-- $Horde: nag/scripts/sql/nag.oci8.sql,v 1.1.2.2 2005/11/28 21:17:51 selsky Exp $

CREATE TABLE nag_tasks (
    task_id         VARCHAR2(32) NOT NULL,
    task_owner      VARCHAR2(255) NOT NULL,
    task_name       VARCHAR2(255) NOT NULL,
    task_uid        VARCHAR2(255) NOT NULL,
    task_desc       CLOB,
    task_due        INT,
    task_priority   INT DEFAULT 0 NOT NULL,
    task_category   VARCHAR2(80),
    task_completed  SMALLINT DEFAULT 0 NOT NULL,
    task_alarm      INT DEFAULT 0 NOT NULL,
    task_private    INT DEFAULT 0 NOT NULL,
--
    PRIMARY KEY (task_id)
);

CREATE INDEX nag_tasklist_idx ON nag_tasks (task_owner);
CREATE INDEX nag_uid_idx ON nag_tasks (task_uid);

GRANT SELECT, INSERT, UPDATE, DELETE ON nag_tasks TO horde;
