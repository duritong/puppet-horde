-- $Horde: horde/scripts/sql/script.initial_horde_user.sql,v 1.1 2004/09/18 17:20:59 chuck Exp $
--
-- This script will create an initial user in a horde_users table. The
-- password being used is 'admin', which you should change
-- IMMEDIATELY.

INSERT INTO horde_users (user_uid, user_pass) VALUES ('admin', '21232f297a57a5a743894a0e4a801fc3');
