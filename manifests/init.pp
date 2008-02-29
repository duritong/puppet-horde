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
            source => "puppet://$servername/horde/horde",
            recurse => true, 
            mode => 0444, 
            owner => apache, group => apache;
    }
}

define horde::files::config (
    $baselocation = '/var/www/horde',
    $modulename = ''
){
    file {
        "$baselocation/$modulename/config":
	        source => [
	            "puppet://$server/dist/horde/configs/${fqdn}/config",
	            "puppet://$server/horde/configs/${fqdn}/config",
	            "puppet://$server/horde/configs/default/config"
	        ],
	        owner => root,
	        group => 0,
	        mode => 0644,
    }
}


