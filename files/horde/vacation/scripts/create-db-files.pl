# $Horde: vacation/scripts/create-db-files.pl,v 1.3 2003/04/27 00:28:12 chuck Exp $
#
#
# Copyright 2001-2002 Eric Rostetter <eric.rostetter@physics.utexas.edu>
#
# See the enclosed file LICENSE for license information (BSD). If you
# did not receive this file, see http://www.horde.org/licenses/bsdl.php.
#
# This script generates some empty database files to be used by the
# vacation program.  Currently it tries to generate:
#
# empty.hash.bin    Berkeley DB 2.x hash, little endian
# empty.btree.bin   Berkeley DB 2.x btree, little endian
# empty.gdbm.bin    GNU dbm (gdbm) database, little endian
# empty.empty.bin   Empty file
#

use strict;
use DB_File;
#use DBM_File;
use NDBM_File;
use GDBM_File;

use vars qw (%h @a);

tie %h, "DB_File", "../files/empty.hash.bin", O_CREAT|O_TRUNC, 0644, $DB_HASH
    or die "Cannot open file empty.hash.bin: $! \n";
untie %h;

tie %h, "DB_File", "../files/empty.btree.bin", O_CREAT|O_TRUNC, 0644, $DB_BTREE
    or die "Cannot open file empty.btree.bin: $! \n";
untie %h;

tie @a, "DB_File", "../files/empty.empty.bin", O_CREAT|O_TRUNC, 0644, $DB_RECNO
    or die "Cannot open file empty.empty.bin: $! \n";
untie @a;

tie %h, "GDBM_File", "../files/empty.gdbm.bin", &GDBM_WRCREAT, 0644
    or die "Cannot open file empty.gdbm.bin: $! \n";
untie %h;

#tie %h, "NDBM_File", "../files/empty.dbmx.bin", O_CREAT|O_TRUNC, 0644
#    or die "Cannot open file empty.dbmx.bin: $! \n";
#untie %h;

