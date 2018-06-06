<?php
namespace Stanford\OSF;
/** @var \Stanford\OSF\OSF $module */


require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$module->setHash('4',"asdfasdf");

$r = $module->getHash('4');

print "Hash: $r <br>";


?>
<h3>Example Config</h3>

<div>
    The hash should be set with a ?h=xxx in the external module url
</div>

<pre>

{
  "targets": [
    {
      "target_name": "R01 Project",
      "hash":"12345",
      "inclusion_logic": "[field] > 5",
      "target_pid": "123 The pid of the target project you want to send people to",
      "target_event_id": "12345",
      "target_naming_prefix": "X",
      "target_naming_padding": "4",
      "osf_field_for_target_id": "field in OSF project where newly generated target project id is stored",
      "priority": "4"
    }
  ]
}

</pre>
<?php

