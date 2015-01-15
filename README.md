GeoBi Standard Edition
========================

Welcome to GeoBi Standard Edition

This document contains information on how to download, install, and start
using Symfony

1) Requirements

 - Linux server (CentOS 6, ?, ...) with Apache >= 2.2
 - PHP >= 5.3.3
 - MapServer  >= 5.6 (6?) with PHP MapScript
 - PostgreSQL >= 9.1 with PostGis >= 1.5
 
 - FOP to print pdf map
 
 
 

2) Installation
----------------------------------

### Download from git (BitBucket)

Download source of GeoBI from GitHub and installing
We assume that the web server user is "apache", and the server name is http://maps.geobi.info/

    git clone https://youruser@bitbucket.org/r3_gis_dev/geobi.git geobi
    cd geobi
    composer install
    sudo sh script/init_geobi.sh

Parameters to change (during composer install): 

    database_driver: pdo_pgsql
    database_name: geobi
    database_user: geobi
    database_password: geobi
    
Download source of FreeGIS / GisClient from GitHub and install it on geobi directory. 
You can change author setings in author/config/config.db.php and author/config/config.php

    git clone https://github.com/gisclient/branch-3.2.git author
    sudo sh script/init_gisclient.sh
    
Configure apache 

sudo vim /etc/httpd/conf/httpd.conf

and add at the end of the file

    <VirtualHost *:80>
        DocumentRoot /yourfolder/geobi/web/
        ServerName maps.geobi.info
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/x-javascript
        <Directory "/yourfolder/geobi/web/">
            Options Indexes FollowSymLinks
            AllowOverride All
            Order allow,deny
            Allow from all
        </Directory>
        DirectoryIndex index.htm index.html index.php
        CustomLog /yourfolder/geobi/log/access_log combined
        ErrorLog /yourfolder/geobi/log/error_log
        # FreeGIS / GisClient configuration
        Alias /author/ /data/sites/kg-kaltern.r3-gis.com/author/public/
    </VirtualHost>

Create the database. The "geobi" user is a low-privileges user (no superuser, no create databases, no more new roles). 
Set your password same as the user name

    sudo su - postgres
    
    ; Create users
    createuser geobi            ; Low privileges users
    
    ; Change users password
    psql -U postgres -d postgres -c "ALTER USER geobi PASSWORD 'geobi'"
    
    ; Create database
    createdb -l C -E UTF-8 -T template0 -O geobi geobi
    
    ; Initializing postgis database
    createlang plpgsql geobi
    psql geobi < /usr/pgsql-9.1/share/contrib/postgis-1.5/postgis.sql
    psql geobi < /usr/pgsql-9.1/share/contrib/postgis-1.5/spatial_ref_sys.sql
    psql geobi < sql/SetPostgisPermission.sql
    
    ; Importing the empty database
    psql -h127.0.0.1 -U geobi geobi < sql/CreateEmptyDatabase.sql
    
    

Download gadm data, import and check it. 
This may take some hours, depends on your internet connection and hardware.
Data are downloaded in gadm-data.

    php script/download_gadm.php
    php script/import_gadm.php
    php script/label_gadm.php
    
    


======================================= END

    
When it comes to installing the Symfony Standard Edition, you have the
following options.

### Use Composer (*recommended*)

As Symfony uses [Composer][2] to manage its dependencies, the recommended way
to create a new project is to use it.

If you don't have Composer yet, download it following the instructions on
http://getcomposer.org/ or just run the following command:

    curl -s http://getcomposer.org/installer | php

Then, use the `create-project` command to generate a new Symfony application:

    php composer.phar create-project symfony/framework-standard-edition path/to/install

Composer will install Symfony and all its dependencies under the
`path/to/install` directory.

### Download an Archive File

To quickly test Symfony, you can also download an [archive][3] of the Standard
Edition and unpack it somewhere under your web server root directory.

If you downloaded an archive "without vendors", you also need to install all
the necessary dependencies. Download composer (see above) and run the
following command:

    php composer.phar install

2) Checking your System Configuration
-------------------------------------

Before starting coding, make sure that your local system is properly
configured for Symfony.

Execute the `check.php` script from the command line:

    php app/check.php

The script returns a status code of `0` if all mandatory requirements are met,
`1` otherwise.

Access the `config.php` script from a browser:

    http://localhost/path/to/symfony/app/web/config.php

If you get any warnings or recommendations, fix them before moving on.

3) Browsing the Demo Application
--------------------------------

Congratulations! You're now ready to use Symfony.

From the `config.php` page, click the "Bypass configuration and go to the
Welcome page" link to load up your first Symfony page.

You can also use a web-based configurator by clicking on the "Configure your
Symfony Application online" link of the `config.php` page.

To see a real-live Symfony page in action, access the following page:

    web/app_dev.php/demo/hello/Fabien

4) Getting started with Symfony
-------------------------------

This distribution is meant to be the starting point for your Symfony
applications, but it also contains some sample code that you can learn from
and play with.

A great way to start learning Symfony is via the [Quick Tour][4], which will
take you through all the basic features of Symfony2.

Once you're feeling good, you can move onto reading the official
[Symfony2 book][5].

A default bundle, `AcmeDemoBundle`, shows you Symfony2 in action. After
playing with it, you can remove it by following these steps:

  * delete the `src/Acme` directory;

  * remove the routing entry referencing AcmeDemoBundle in `app/config/routing_dev.yml`;

  * remove the AcmeDemoBundle from the registered bundles in `app/AppKernel.php`;

  * remove the `web/bundles/acmedemo` directory;

  * empty the `security.yml` file or tweak the security configuration to fit
    your needs.

What's inside?
---------------

The Symfony Standard Edition is configured with the following defaults:

  * Twig is the only configured template engine;

  * Doctrine ORM/DBAL is configured;

  * Swiftmailer is configured;

  * Annotations for everything are enabled.

It comes pre-configured with the following bundles:

  * **FrameworkBundle** - The core Symfony framework bundle

  * [**SensioFrameworkExtraBundle**][6] - Adds several enhancements, including
    template and routing annotation capability

  * [**DoctrineBundle**][7] - Adds support for the Doctrine ORM

  * [**TwigBundle**][8] - Adds support for the Twig templating engine

  * [**SecurityBundle**][9] - Adds security by integrating Symfony's security
    component

  * [**SwiftmailerBundle**][10] - Adds support for Swiftmailer, a library for
    sending emails

  * [**MonologBundle**][11] - Adds support for Monolog, a logging library

  * [**AsseticBundle**][12] - Adds support for Assetic, an asset processing
    library

  * **WebProfilerBundle** (in dev/test env) - Adds profiling functionality and
    the web debug toolbar

  * **SensioDistributionBundle** (in dev/test env) - Adds functionality for
    configuring and working with Symfony distributions

  * [**SensioGeneratorBundle**][13] (in dev/test env) - Adds code generation
    capabilities

  * **AcmeDemoBundle** (in dev/test env) - A demo bundle with some example
    code

All libraries and bundles included in the Symfony Standard Edition are
released under the MIT or BSD license.

Enjoy!

[1]:  http://symfony.com/doc/2.3/book/installation.html
[2]:  http://getcomposer.org/
[3]:  http://symfony.com/download
[4]:  http://symfony.com/doc/2.3/quick_tour/the_big_picture.html
[5]:  http://symfony.com/doc/2.3/index.html
[6]:  http://symfony.com/doc/2.3/bundles/SensioFrameworkExtraBundle/index.html
[7]:  http://symfony.com/doc/2.3/book/doctrine.html
[8]:  http://symfony.com/doc/2.3/book/templating.html
[9]:  http://symfony.com/doc/2.3/book/security.html
[10]: http://symfony.com/doc/2.3/cookbook/email.html
[11]: http://symfony.com/doc/2.3/cookbook/logging/monolog.html
[12]: http://symfony.com/doc/2.3/cookbook/assetic/asset_management.html
[13]: http://symfony.com/doc/2.3/bundles/SensioGeneratorBundle/index.html
