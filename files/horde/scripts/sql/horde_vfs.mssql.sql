-- $Horde: horde/scripts/sql/horde_vfs.mssql.sql,v 1.1.2.1 2006/01/18 19:59:16 ben Exp $

CREATE TABLE horde_vfs (
    vfs_id        BIGINT NOT NULL,
    vfs_type      SMALLINT NOT NULL,
    vfs_path      VARCHAR(255) NOT NULL,
    vfs_name      VARCHAR(255) NOT NULL,
    vfs_modified  BIGINT NOT NULL,
    vfs_owner     VARCHAR(255) NOT NULL,
    vfs_data      VARBINARY(MAX),
    PRIMARY KEY   (vfs_id)
);

CREATE INDEX vfs_path_idx ON horde_vfs (vfs_path);
CREATE INDEX vfs_name_idx ON horde_vfs (vfs_name);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_vfs TO horde;
