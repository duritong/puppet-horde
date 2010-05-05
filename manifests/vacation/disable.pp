class horde::vacation::disable inherits horde::vacation {
    Horde::Module['horde-vacation']{
      ensure => 'absent'
    }
}
