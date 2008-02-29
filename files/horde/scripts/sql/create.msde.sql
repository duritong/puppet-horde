-- $Horde: horde/scripts/sql/create.msde.sql,v 1.1.2.6 2007/03/29 23:06:42 jan Exp $

USE master
GO

CREATE DATABASE horde
GO

EXEC sp_addlogin 'horde', 'horde_mgr', 'horde'
GO

USE horde
GO

EXEC sp_grantdbaccess 'horde'
GO

CREATE TABLE horde_users (
    user_uid VARCHAR(255) NOT NULL,
    user_pass VARCHAR(255) NOT NULL,

    PRIMARY KEY (user_uid)
)
GO

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_users TO horde
GO

CREATE TABLE horde_prefs (
    pref_uid VARCHAR(200) NOT NULL,
    pref_scope VARCHAR(16) NOT NULL DEFAULT '',
    pref_name VARCHAR(32) NOT NULL,
    pref_value TEXT NULL,

    PRIMARY KEY (pref_uid, pref_scope, pref_name)
)
GO

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_prefs TO horde
GO

CREATE INDEX pref_uid_idx ON horde_prefs (pref_uid)
CREATE INDEX pref_scope_idx ON horde_prefs (pref_scope)
GO

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
)
GO

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_datatree TO horde
GO

CREATE INDEX datatree_datatree_name_idx ON horde_datatree (datatree_name)
CREATE INDEX datatree_group_idx ON horde_datatree (group_uid)
CREATE INDEX datatree_user_idx ON horde_datatree (user_uid)
CREATE INDEX datatree_serialized_idx ON horde_datatree (datatree_serialized)
GO

CREATE TABLE horde_datatree_attributes (
    datatree_id INT NOT NULL,
    attribute_name VARCHAR(255) NOT NULL,
    attribute_key VARCHAR(255) DEFAULT '' NOT NULL,
    attribute_value VARCHAR(MAX)
)
GO

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_datatree TO horde
GO

CREATE INDEX datatree_attribute_idx ON horde_datatree_attributes (datatree_id)
CREATE INDEX datatree_attribute_name_idx ON horde_datatree_attributes (attribute_name)
CREATE INDEX datatree_attribute_key_idx ON horde_datatree_attributes (attribute_key)
GO

CREATE TABLE horde_tokens (
    token_address VARCHAR(100) NOT NULL,
    token_id VARCHAR(32) NOT NULL,
    token_timestamp BIGINT NOT NULL,

    PRIMARY KEY (token_address, token_id)
)
GO

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_tokens TO horde
GO

CREATE TABLE horde_vfs (
    vfs_id BIGINT NOT NULL,
    vfs_type SMALLINT NOT NULL,
    vfs_path VARCHAR(255) NOT NULL,
    vfs_name VARCHAR(255) NOT NULL,
    vfs_modified BIGINT NOT NULL,
    vfs_owner VARCHAR(255) NOT NULL,
    vfs_data TEXT,

    PRIMARY KEY   (vfs_id)
)
GO

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_vfs TO horde
GO

CREATE INDEX vfs_path_idx ON horde_vfs (vfs_path)
CREATE INDEX vfs_name_idx ON horde_vfs (vfs_name)
GO

CREATE TABLE horde_histories (
    history_id       BIGINT NOT NULL,
    object_uid       VARCHAR(255) NOT NULL,
    history_action   VARCHAR(32) NOT NULL,
    history_ts       BIGINT NOT NULL,
    history_desc     TEXT,
    history_who      VARCHAR(255),
    history_extra    TEXT,

    PRIMARY KEY (history_id)
)
GO

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_histories TO horde
GO

CREATE INDEX history_action_idx ON horde_histories (history_action)
CREATE INDEX history_ts_idx ON horde_histories (history_ts)
CREATE INDEX history_uid_idx ON horde_histories (object_uid)
GO
