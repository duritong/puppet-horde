class horde::ingo::managesieve {
  if $use_shorewall {
    include shorewall::rules::out::managesieve
  }
}
