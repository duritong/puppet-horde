# manage module configs
define horde::module::config(
  $uid = 'root',
  $gid = 'apache'
){
    file{"/etc/horde/${name}":
        source => [ "puppet://$server/modules/site-horde/configs/${fqdn}/${name}",
                    "puppet://$server/modules/site-horde/configs/${name}" ],
        owner => $uid, group => $gid, mode => 0440;
    }
}
