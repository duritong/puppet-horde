-- $Horde: nag/scripts/sql/nag.mssql.sql,v 1.1.2.1 2006/01/18 19:59:34 ben Exp $

CREATE TABLE nag_tasks (
    task_id         VARCHAR(32) NOT NULL,
    task_owner      VARCHAR(255) NOT NULL,
    task_name       VARCHAR(255) NOT NULL,
    task_uid        VARCHAR(255) NOT NULL,
    task_desc       VARCHAR(MAX),
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

GRANT SELECT, INSERT, UPDATE, DELETE ON nag_tasks TO horde;
