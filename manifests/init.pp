# manage horde stuff
# Copyright (C) 2007 admin@immerda.ch
class horde {
  case $operatingsystem {
    centos: { include horde::centos }
    default: { include horde::base }
  }
}
