class horde::ingo::managesieve {
  require php::packages::net_sieve
  include ::horde::ingo
  if $use_shorewall {
    include shorewall::rules::out::managesieve
  }
}
