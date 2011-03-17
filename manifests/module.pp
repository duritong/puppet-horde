define horde::module($ensure = installed){
  package{"$name":
    ensure => $ensure,
    require => Package['horde'],
  }
}
