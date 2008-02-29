-- $Horde: horde/scripts/sql/create.pgsql.sql,v 1.1.10.5 2006/08/04 20:49:43 jan Exp $
--
-- Uncomment the ALTER line below, and change the password.  Then run as:
--
-- $ psql -d template1 -f create.pgsql.sql

CREATE DATABASE horde;

\c horde;

CREATE USER horde;

-- ALTER USER horde WITH PASSWORD 'pass';


CREATE TABLE horde_users (
    user_uid                    VARCHAR(255) NOT NULL,
    user_pass                   VARCHAR(255) NOT NULL,
    user_soft_expiration_date   INTEGER,
    user_hard_expiration_date   INTEGER,
--
    PRIMARY KEY (user_uid)
);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_users TO horde;


CREATE TABLE horde_prefs (
    pref_uid        VARCHAR(255) NOT NULL,
    pref_scope      VARCHAR(16) DEFAULT '' NOT NULL,
    pref_name       VARCHAR(32) NOT NULL,
    pref_value      TEXT,
--
    PRIMARY KEY (pref_uid, pref_scope, pref_name)
);

CREATE INDEX pref_uid_idx ON horde_prefs (pref_uid);
CREATE INDEX pref_scope_idx ON horde_prefs (pref_scope);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_prefs TO horde;


CREATE TABLE horde_datatree (
    datatree_id INT NOT NULL,
    group_uid VARCHAR(255) NOT NULL,
    user_uid VARCHAR(255) NOT NULL,
    datatree_name VARCHAR(255) NOT NULL,
    datatree_parents VARCHAR(255) NOT NULL,
    datatree_order INT,
    datatree_data TEXT,
    datatree_serialized SMALLINT DEFAULT 0 NOT NULL,

    PRIMARY KEY (datatree_id)
);

CREATE INDEX datatree_datatree_name_idx ON horde_datatree (datatree_name);
CREATE INDEX datatree_group_idx ON horde_datatree (group_uid);
CREATE INDEX datatree_user_idx ON horde_datatree (user_uid);
CREATE INDEX datatree_order_idx ON horde_datatree (datatree_order);
CREATE INDEX datatree_serialized_idx ON horde_datatree (datatree_serialized);


CREATE TABLE horde_datatree_attributes (
    datatree_id INT NOT NULL,
    attribute_name VARCHAR(255) NOT NULL,
    attribute_key VARCHAR(255),
    attribute_value TEXT
);

CREATE INDEX datatree_attribute_idx ON horde_datatree_attributes (datatree_id);
CREATE INDEX datatree_attribute_name_idx ON horde_datatree_attributes (attribute_name);
CREATE INDEX datatree_attribute_key_idx ON horde_datatree_attributes (attribute_key);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_datatree TO horde;
GRANT SELECT, INSERT, UPDATE, DELETE ON horde_datatree_attributes TO horde;


CREATE TABLE horde_tokens (
    token_address    VARCHAR(100) NOT NULL,
    token_id         VARCHAR(32) NOT NULL,
    token_timestamp  BIGINT NOT NULL,
--
    PRIMARY KEY (token_address, token_id)
);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_tokens TO horde;


CREATE TABLE horde_vfs (
    vfs_id        BIGINT NOT NULL,
    vfs_type      SMALLINT NOT NULL,
    vfs_path      VARCHAR(255) NOT NULL,
    vfs_name      VARCHAR(255) NOT NULL,
    vfs_modified  BIGINT NOT NULL,
    vfs_owner     VARCHAR(255) NOT NULL,
    vfs_data      TEXT,

    PRIMARY KEY   (vfs_id)
);

CREATE INDEX vfs_path_idx ON horde_vfs (vfs_path);
CREATE INDEX vfs_name_idx ON horde_vfs (vfs_name);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_vfs TO horde;


CREATE TABLE horde_sessionhandler (
    session_id             VARCHAR(32) NOT NULL,
    session_lastmodified   INT NOT NULL,
    session_data           TEXT,
    PRIMARY KEY (session_id)
);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_sessionhandler TO horde;


CREATE TABLE horde_histories (
    history_id       BIGINT NOT NULL,
    object_uid       VARCHAR(255) NOT NULL,
    history_action   VARCHAR(32) NOT NULL,
    history_ts       BIGINT NOT NULL,
    history_desc     TEXT,
    history_who      VARCHAR(255),
    history_extra    TEXT,
--
    PRIMARY KEY (history_id)
);

CREATE INDEX history_action_idx ON horde_histories (history_action);
CREATE INDEX history_ts_idx ON horde_histories (history_ts);
CREATE INDEX history_uid_idx ON horde_histories (object_uid);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_histories TO horde;
