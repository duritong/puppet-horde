-- $Horde: turba/scripts/upgrades/1.2_to_2.0.oci8.sql,v 1.1.2.1 2006/04/06 22:55:10 jan Exp $

ALTER TABLE turba_objects ADD object_uid VARCHAR(255);
ALTER TABLE turba_objects ADD object_freebusyurl VARCHAR(255);
ALTER TABLE turba_objects ADD object_smimepublickey CLOB;
ALTER TABLE turba_objects ADD object_pgppublickey CLOB;
