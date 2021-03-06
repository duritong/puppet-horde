# manage module configs
define horde::module::config(
  $uid = 'root',
  $gid = 'apache'
){
  $horde_module_name = split($name,'/')

  require "horde::${horde_module_name[0]}"
  file{"/etc/horde/${name}":
    source => [ "puppet:///modules/site-horde/configs/${fqdn}/${name}",
                "puppet:///modules/site-horde/configs/${name}" ],
    owner => $uid, group => $gid, mode => 0440;
  }
}
