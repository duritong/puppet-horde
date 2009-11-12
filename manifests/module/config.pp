# manage module configs
define horde::module::config(){
    file{"/etc/horde/${name}":
        source => [ "puppet://$server/modules/site-horde/configs/${fqdn}/${name}",
                    "puppet://$server/modules/site-horde/configs/${name}" ],
        owner => root, group => apache, mode => 0440;
    }
}
