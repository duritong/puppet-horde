# manage horde configs
define horde::config(
  $uid = 'root',
  $gid = 'apache'
){
    file{"/etc/horde/${name}":
        source => [ "puppet://$server/modules/site-horde/configs/${fqdn}/horde/${name}",
                    "puppet://$server/modules/site-horde/configs/horde/${name}" ],
        owner => $uid, group => $gid, mode => 0440;
    }
}
