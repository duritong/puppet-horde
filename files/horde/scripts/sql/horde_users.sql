-- $Horde: horde/scripts/sql/horde_users.sql,v 1.2.10.2 2005/10/18 11:34:00 jan Exp $

CREATE TABLE horde_users (
    user_uid                    VARCHAR(255) NOT NULL,
    user_pass                   VARCHAR(255) NOT NULL,
    user_soft_expiration_date   INTEGER,
    user_hard_expiration_date   INTEGER,
--
    PRIMARY KEY (user_uid)
);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_users TO horde;
