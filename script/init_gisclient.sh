#!/bin/bash

cd author
if [ $? -ne 0 ]; then
    echo "cd author faild!"
    exit 1
fi

php config/installer.php -c -g apache

cp fonts/fonts_sample.list fonts/fonts.list

cd ..
php script/author/replace_settings.php script/author/config.db.php author/config/config.db.php
php script/author/replace_settings.php script/author/config.php author/config/config.php




