# modules/horde/manifests/init.pp - manage horde stuff
# Copyright (C) 2007 admin@immerda.ch
#

# modules_dir { "horde": }

class horde {
    
}

#name is the module name here
define horde::files::config (
    $baselocation = '/var/www/horde'
){
    # horde module is / path
    case $name {
        'horde': { $module = '' }
        default: { $module = $name }
        
    }
    file {
        "$baselocation/${module}/config":
	        source => [
	            "puppet://$server/files/horde/configs/${fqdn}/${name}/config",
	            "puppet://$server/files/horde/configs/${name}/config",
	            "puppet://$server/horde/configs/${name}/config"
	        ],
	        owner => root,
	        group => 0,
	        mode => 0644,
    }
}


