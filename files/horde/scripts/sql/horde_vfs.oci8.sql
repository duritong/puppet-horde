-- $Horde: horde/scripts/sql/horde_vfs.oci8.sql,v 1.3 2004/09/21 03:34:06 chuck Exp $

CREATE TABLE horde_vfs (
    vfs_id        NUMBER(16) NOT NULL,
    vfs_type      NUMBER(8) NOT NULL,
    vfs_path      VARCHAR2(255),
    vfs_name      VARCHAR2(255) NOT NULL,
    vfs_modified  NUMBER(16) NOT NULL,
    vfs_owner     VARCHAR2(255),
    vfs_data      BLOB,
--
    PRIMARY KEY   (vfs_id)
);

CREATE INDEX vfs_path_idx ON horde_vfs (vfs_path);
CREATE INDEX vfs_name_idx ON horde_vfs (vfs_name);

GRANT SELECT, INSERT, UPDATE, DELETE ON horde_vfs TO horde;
