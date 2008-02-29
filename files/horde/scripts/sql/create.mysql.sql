-- $Horde: horde/scripts/sql/create.mysql.sql,v 1.4.6.10 2007/09/27 22:36:49 jan Exp $
--
-- If you are installing Horde for the first time, you can simply
-- direct this file to mysql as STDIN:
--
-- $ mysql --user=root --password=<MySQL-root-password> < create.mysql.sql
--
-- If you are upgrading from a previous version, you will need to comment
-- out the the user creation steps below, as well as the schemas for any
-- tables that already exist.
--
-- If you choose to grant permissions manually, note that with MySQL, PEAR DB
-- emulates sequences by automatically creating extra tables ending in _seq,
-- so the MySQL "horde" user must have CREATE privilege on the "horde"
-- database.
--
-- If you are upgrading from Horde 1.x, the Horde tables you have from
-- that version are no longer used; you may wish to either delete those
-- tables or simply recreate the database anew.

USE mysql;

REPLACE INTO user (host, user, password)
    VALUES (
        'localhost',
        'horde',
-- IMPORTANT: Change this password!
        PASSWORD('horde')
);

REPLACE INTO db (host, db, user, select_priv, insert_priv, update_priv,
                 delete_priv, create_priv, drop_priv, index_priv)
    VALUES (
        'localhost',
        'horde',
        'horde',
        'Y', 'Y', 'Y', 'Y',
        'Y', 'Y', 'Y'
);

-- Make sure that priviliges are reloaded.
FLUSH PRIVILEGES;

-- MySQL 3.23.x appears to have "CREATE DATABASE IF NOT EXISTS" and
-- "CREATE TABLE IF NOT EXISTS" which would be a nice way to handle
-- reinstalls gracefully (someday).  For now, drop the database
-- manually to avoid CREATE errors.
CREATE DATABASE horde;

USE horde;

CREATE TABLE horde_users (
    user_uid                    VARCHAR(255) NOT NULL,
    user_pass                   VARCHAR(255) NOT NULL,
    user_soft_expiration_date   INT,
    user_hard_expiration_date   INT,

    PRIMARY KEY (user_uid)
);

CREATE TABLE horde_prefs (
    pref_uid        VARCHAR(200) NOT NULL,
    pref_scope      VARCHAR(16) NOT NULL DEFAULT '',
    pref_name       VARCHAR(32) NOT NULL,
    pref_value      LONGTEXT NULL,

    PRIMARY KEY (pref_uid, pref_scope, pref_name)
);

CREATE INDEX pref_uid_idx ON horde_prefs (pref_uid);
CREATE INDEX pref_scope_idx ON horde_prefs (pref_scope);

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
CREATE INDEX datatree_serialized_idx ON horde_datatree (datatree_serialized);

CREATE TABLE horde_datatree_attributes (
    datatree_id INT NOT NULL,
    attribute_name VARCHAR(255) NOT NULL,
    attribute_key VARCHAR(255) DEFAULT '' NOT NULL,
    attribute_value TEXT
);

CREATE INDEX datatree_attribute_idx ON horde_datatree_attributes (datatree_id);
CREATE INDEX datatree_attribute_name_idx ON horde_datatree_attributes (attribute_name);
CREATE INDEX datatree_attribute_key_idx ON horde_datatree_attributes (attribute_key);

CREATE TABLE horde_tokens (
    token_address    VARCHAR(100) NOT NULL,
    token_id         VARCHAR(32) NOT NULL,
    token_timestamp  BIGINT NOT NULL,

    PRIMARY KEY (token_address, token_id)
);

CREATE TABLE horde_vfs (
    vfs_id        BIGINT NOT NULL,
    vfs_type      SMALLINT NOT NULL,
    vfs_path      VARCHAR(255) NOT NULL,
    vfs_name      VARCHAR(255) NOT NULL,
    vfs_modified  BIGINT NOT NULL,
    vfs_owner     VARCHAR(255) NOT NULL,
    vfs_data      LONGBLOB,

    PRIMARY KEY (vfs_id)
);

CREATE INDEX vfs_path_idx ON horde_vfs (vfs_path);
CREATE INDEX vfs_name_idx ON horde_vfs (vfs_name);

CREATE TABLE horde_histories (
    history_id       BIGINT NOT NULL,
    object_uid       VARCHAR(255) NOT NULL,
    history_action   VARCHAR(32) NOT NULL,
    history_ts       BIGINT NOT NULL,
    history_desc     TEXT,
    history_who      VARCHAR(255),
    history_extra    TEXT,

    PRIMARY KEY (history_id)
);

CREATE INDEX history_action_idx ON horde_histories (history_action);
CREATE INDEX history_ts_idx ON horde_histories (history_ts);
CREATE INDEX history_uid_idx ON horde_histories (object_uid);

CREATE TABLE horde_sessionhandler (
    session_id             VARCHAR(32) NOT NULL,
    session_lastmodified   INT NOT NULL,
    session_data           LONGBLOB,

    PRIMARY KEY (session_id)
) ENGINE = InnoDB;

-- Done!
