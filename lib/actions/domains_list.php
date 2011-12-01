<?php

    require_once(dirname(__FILE__) . "/../functions.php");

    $auth = Auth::get_instance();
    $conf = Conf::get_instance();

    $domains = $auth->list_domains();
    $domains = LDAP::normalize_result($domains);

    if (isset($_GET['rest']) && !empty($_GET['rest'])) {
         $search_key = str_replace('/','',$_GET['rest']);

        $section = $conf->get('kolab', 'auth_mechanism');
        $domain_name_attr = $conf->get($section, 'domain_name_attribute');

        foreach ($domains as $id => $attributes) {

            if (is_array($attributes['associateddomain'])) {
                if (in_array($_GET['rest'], $attributes['associateddomain'])) {
                    print json_encode($attributes);
                }
            } elseif ($attributes['associateddomain'] == $search_key) {
                print json_encode($attributes);
            }
        }

    } else {
        print json_encode($domains);
    }

?>
