# manage horde stuff
# Copyright (C) 2007 admin@immerda.ch
class horde(
  $install_type=hiera('horde_install','pkg')
) {

  case $operatingsystem {
    centos: { include horde::centos }
    default: { include horde::base }
  }
}
