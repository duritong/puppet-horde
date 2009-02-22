# manifests/defines.pp

# manage horde modules
define horde::module($ensure = installed) {
    package{"$name":
        ensure => $ensure,
        require => Package['horde'],
    }
}

# manage horde configs
define horde::config(){
    file{"/etc/horde/${name}":
        source => [ "puppet://$server/files/horde/configs/${fqdn}/horde/${name}",
                    "puppet://$server/files/horde/configs/horde/${name}" ],
        owner => root, group => apache, mode => 0440;
    }
}
# manage module configs
define horde::module::config(){
    file{"/etc/horde/${name}":
        source => [ "puppet://$server/files/horde/configs/${fqdn}/${name}",
                    "puppet://$server/files/horde/configs/${name}" ],
        owner => root, group => apache, mode => 0440;
    }
}
