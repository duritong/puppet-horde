# modules/horde/manifests/init.pp - manage horde stuff
# Copyright (C) 2007 admin@immerda.ch
#

# modules_dir { "horde": }

class horde {
    
}

define horde::files (
    $location='/var/www/horde'
) {
    file {
        $location:
            source => "puppet://$servername/horde/files/horde",
            recurse => true, 
            mode => 0444, 
            owner => apache, group => apache;
    }
}
