GeoBi Light Edition
========================

Welcome to GeoBi Light Edition

This document contains information on how to download, install, and start
using Symfony

1) Requirements

 - Linux server (CentOS 7) with Apache >= 2.4
 - PHP >= 5.4
 - MapServer  >= 6.4 with PHP MapScript
 - PostgreSQL >= 9.3 with PostGis >= 2.1
 - PostgreSQL contrib
 - Postgis-client
 - Git
 - proj-epsg
 
2) Installation
----------------------------------

### Download from git

Download source of GeoBI from GitHub and install it
We assume that the web server user is "apache", and the server name is http://maps.geobi.info/

    git clone https://github.com/tis-innovation-park/GeoBI-Light.git geobi 
    cd geobi
    curl -sS https://getcomposer.org/installer | php
    ./composer.phar install

Parameters to change (during composer install): 

    database_driver: pdo_pgsql
    database_name: geobi
    database_user: geobi
    database_password: geobi
    database_port:5432
    
Download source of FreeGIS / GisClient from GitHub and install it on geobi directory. 
You can change author setings in author/config/config.db.php and author/config/config.php

    git clone https://github.com/gisclient/branch-3.2.git -n author
    cd author
    git checkout v3.2-0f7f2df
    cd ../
    sudo sh script/init_gisclient.sh

Change permission and ownership

    sudo sh script/init_geobi.sh

Configure apache 

sudo vim /etc/httpd/conf/httpd.conf

and add at the end of the file

    <VirtualHost *:80>
        DocumentRoot /yourfolder/geobi/web/
        ServerName maps.geobi.info.local
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/x-javascript
        <Directory "/yourfolder/geobi/web/">
            Options Indexes FollowSymLinks
            AllowOverride All
            Order allow,deny
            Allow from all
        </Directory>
        DirectoryIndex index.htm index.html index.php
        # CustomLog /yourfolder/geobi/log/access_log combined
        # ErrorLog /yourfolder/geobi/log/error_log
        # FreeGIS / GisClient configuration
        Alias /author/ /yourfolder/geobi/author/public/
    </VirtualHost>

Create the database. The "geobi" user is a low-privileges user (no superuser, no create databases, no more new roles). 
Set your password same as the user name

    sudo su postgres
    
    ; Create users
    createuser geobi            ; Low privileges users
    
    ; Change users password
    psql -U postgres -d postgres -c "ALTER USER geobi PASSWORD 'geobi'"
    
    ; Create database
    createdb -l C -E UTF-8 -T template0 -O geobi geobi
    
    ; Initializing postgis database
    createlang plpgsql geobi
    psql geobi < /usr/pgsql-9.3/share/contrib/postgis-2.1/postgis.sql
    psql geobi < /usr/pgsql-9.3/share/contrib/postgis-2.1/spatial_ref_sys.sql
    psql geobi < sql/SetPostgisPermission.sql
    
    ; Importing the empty database
    psql -h127.0.0.1 -U geobi geobi < sql/CreateEmptyDatabase.sql
    
    ; Add unaccent extension with postgres privileged user 
    psql geobi -c "CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public"

    ; Return to normal user
    exit

Download gadm data, import and check it. 
This may take some hours, depends on your internet connection and hardware.
Data are downloaded in gadm-data.

    php script/download_gadm.php
    php script/import_gadm.php
    php script/label_gadm.php
    
INSTALLATION COMPLETED!

Default login and password for administrator (change it!): 
Login: admin@geobi
Password: password


go to http://maps.geobi.info/ and enjoy!
