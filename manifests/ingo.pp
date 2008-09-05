# manifests/ingo.pp

class horde::ingo {
    include php::packages::ssh2

    horde::module{'ingo': }
}
