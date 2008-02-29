-- $Horde: nag/scripts/sql/nag.sql,v 1.4.8.2 2007/09/27 22:41:03 jan Exp $

CREATE TABLE nag_tasks (
    task_id         VARCHAR(32) NOT NULL,
    task_owner      VARCHAR(255) NOT NULL,
    task_name       VARCHAR(255) NOT NULL,
    task_uid        VARCHAR(255) NOT NULL,
    task_desc       TEXT,
    task_due        INT,
    task_priority   INT NOT NULL DEFAULT 0,
    task_category   VARCHAR(80),
    task_completed  SMALLINT NOT NULL DEFAULT 0,
    task_alarm      INT NOT NULL DEFAULT 0,
    task_private    INT NOT NULL DEFAULT 0,
--
    PRIMARY KEY (task_id)
);

CREATE INDEX nag_tasklist_idx ON nag_tasks (task_owner);
CREATE INDEX nag_uid_idx ON nag_tasks (task_uid);
