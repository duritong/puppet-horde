# manage module configs
define horde::module::config(){
    file{"/etc/horde/${name}":
        source => [ "puppet://$server/files/horde/configs/${fqdn}/${name}",
                    "puppet://$server/files/horde/configs/${name}" ],
        owner => root, group => apache, mode => 0440;
    }
}
