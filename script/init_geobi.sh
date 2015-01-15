#!/bin/bash

# Remove symfony dev mode
# rm web/app_dev.php

mkdir log

chgrp -R apache app/cache
chmod -R g+ws app/cache

chgrp -R apache app/logs
chmod -R g+ws app/logs

php script/author/replace_settings.php script/author/stat.map src/R3gis/AppBundle/Resources/mapfile/stat.map




    
