-- $Horde: turba/scripts/sql/turba.mssql.sql,v 1.1.2.1 2007/11/18 12:04:43 jan Exp $

CREATE TABLE turba_objects (
    object_id VARCHAR(32) NOT NULL,
    owner_id VARCHAR(255) NOT NULL,
    object_type VARCHAR(255) NOT NULL DEFAULT 'Object',
    object_uid VARCHAR(255),
    object_members BLOB,
    object_name VARCHAR(255),
    object_alias VARCHAR(32),
    object_email VARCHAR(255),
    object_homeaddress VARCHAR(255),
    object_workaddress VARCHAR(255),
    object_homephone VARCHAR(25),
    object_workphone VARCHAR(25),
    object_cellphone VARCHAR(25),
    object_fax VARCHAR(25),
    object_title VARCHAR(255),
    object_company VARCHAR(255),
    object_notes VARCHAR(MAX),
    object_pgppublickey VARCHAR(MAX),
    object_smimepublickey VARCHAR(MAX),
    object_freebusyurl VARCHAR(255),
--    
    PRIMARY KEY(object_id)
);

CREATE INDEX turba_owner_idx ON turba_objects (owner_id);

GRANT SELECT, INSERT, UPDATE, DELETE ON turba_objects TO horde;
