class horde::imp::managesieve {
  if $use_shorewall {
    include shorewall::rules::managesieve
  }
}
