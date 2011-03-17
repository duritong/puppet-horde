define horde::config(
  $uid = 'root',
  $gid = 'apache'
){
  require ::horde
  file{"/etc/horde/${name}":
    source => [ "puppet:///modules/site-horde/configs/${fqdn}/horde/${name}",
                "puppet:///modules/site-horde/configs/horde/${name}" ],
    owner => $uid, group => $gid, mode => 0440;
  }
}
