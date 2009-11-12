# manage horde configs
define horde::config(){
    file{"/etc/horde/${name}":
        source => [ "puppet://$server/modules/site-horde/configs/${fqdn}/horde/${name}",
                    "puppet://$server/modules/site-horde/configs/horde/${name}" ],
        owner => root, group => apache, mode => 0440;
    }
}
