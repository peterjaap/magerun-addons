MageRun Addons
==============

Some additional commands for the excellent N98-MageRun Magento command-line tool.

The purpose of this project is just to have an easy way to deploy new, custom
commands that I need to use in various places.  It's easier for me to do this
than to maintain a fork of n98-magerun, but I'd be happy to merge any of these
commands into the main n98-magerun project if desired.

Installation
------------
There are a few options.  You can check out the different options in the [MageRun
docs](http://magerun.net/introducting-the-new-n98-magerun-module-system/).

Here's the easiest:

1. Create ~/.n98-magerun/modules/ if it doesn't already exist.

        mkdir -p ~/.n98-magerun/modules/

2. Clone the magerun-addons repository in there

        cd ~/.n98-magerun/modules/ && git clone git@github.com:peterjaap/magerun-addons.git

3. It should be installed. To see that it was installed, check to see if one of the new commands is in there, like `media:sync`.

        n98-magerun.phar media:sync

Commands
--------

### Sync Media over SSH or FTP ###

This command lets you sync the media folder over SSH or FTP. You can enter the SSH or FTP credentials everytime you run the command, or you can add them to your app/etc/local.xml like this;

    <config>
        <global>
            ...
            <production>
                <ssh>
                    <host>yourhost.com</host>
                    <username>yourusername</username>
                    <path>public_html</path>
                    <!--port>22</port-->
                </ssh>
                ... OR ...
                <ftp>
                    <host>yourhost.com</host>
                    <username>yourusername</username>
                    <password>yourpassword</password>
                    <path>public_html</path>
                </ftp>
            </production>
            ...
        </gobal>
    </config>

Note that the SSH config doesn't have a password option. This is because usually authentication is done through SSH keys anyway. Besides that, it is not possible to pass a password argument to the rsync package.
The port option is optional, it defaults to 22.
Also note that the path can be set both relative (without a leading slash) as well as absolute (with a leading slash).

    $ n98-magerun.phar media:sync

### Set base URL's ###

Magerun already has an option to show a list of set base URL's but no way to set them easily. It is possible through config:set but this is cumbersome. This command gives you a list of storeviews to choose from and asks you for your base URL. You have the option to set both the unsecure and the secure base URL.

    $ n98-magerun.phar sys:store:config:base-url:set

### Images: clean tables ###

Clean media tables by deleting rows with references to non-existing image.

    $ n98-magerun.phar media:images:cleantables

### Images: set default image ###

Set the default for a product where an image is available but isn't selected.

    $ n98-magerun.phar media:images:defaultimage

### Images: remove duplicate image files ###

Remove duplicate image files from disk and database. This command compares files using the [fdupes](https://github.com/adrianlopezroche/fdupes) library.

    $ n98-magerun.phar media:images:removeduplicates

### Images: remove orphaned files ###

Remove orphaned files from disk. Orphans are files which do exist on the disk but are not found the database.

    $ n98-magerun.phar media:images:removeorphans

### Clean up customers' taxvat fields ###

A large number of customers enter their Tax/VAT number incorrectly. Common mistakes are prefixing the country code and using dots and/or spaces. This command loops through the taxvat fields already in the database and cleans them up. So 'nl 01.23.45.67 b01' (which won't validate) will become '01234567B01' (which will validate). This is useful for future purchases by these customers.

    $ n98-magerun.phar customer:clean-taxvat

### Find non-whitelisted vars/blocks to be compatible with SUPEE-6788 and Magento 1.9.2.2

Thanks to @timvroom for the bulk of the code.

    dev:template-vars [--addblocks[="true|false"]] [--addvariables[="true|false"]]

### Find extensions that use old-style admin routing (which is not compatible with SUPEE-6788 and Magento 1.9.2.2)

    $ n98-magerun.phar dev:old-admin-routing

### Find files that are affected by APPSEC-1063, addressing possible SQL injection

    $ n98-magerun.phar dev:possible-sql-injection

### Listen for all Magento events on the fly ###

    $ n98-magerun.phar dev:events:listen

When running this command, magerun will edit app/Mage.php to log the events to a temporary log file. This file will consequently being 'tailed'. When hitting CTRL-C the command will revert the adjustment to app/Mage.php and clean up the log file.

### Dispatch/fire a Magento event ###

When building extensions, you often need to fire a certain event to trigger a function. With this command, you can choose one of the default events that can be found in the Magento core, or type in the name of another (custom) event. The command will also ask for any parameters.

You can instantiate an object and load a record into that object. You do this by using as parameter value 'Mage_Catalog_Model_Product:1337'. This will instantiate the model Mage_Catalog_Model_Product and load entity 1337 in that model.

    $ n98-magerun.phar dev:events:fire

It is also possible to give command line arguments. These are '--event' (-e for shortcut) and '--parameters' (-p for shortcut). Parameters can contain multiple parameters, in which the various parameters should be stringed together with ';' and the name/value pair should be stringed together with '::'. Be sure to enclose this in double quotes.

    $ n98-magerun.phar dev:events:fire --event your_event_that_will_fire --parameters "product::Mage_Catalog_Model_Product:1337;testparam::testvalue"
    Event your_event_that_will_fire has been fired with parameters;
     - object product: Mage_Catalog_Model_Product ID 196744
     - testparam: testvalue

### Find translations for given extension & language ###

This command lets you choose a language code and an installed extension. It will then look for translatable strings (strings that are run through __()) and look for its translation in the set language. It shows a table with the (un)translated strings and generates a pre-structured (and pre-filled, if applicable) locale (csv) file.

    $ n98-magerun.phar extension:translations

### Disable an extension ###

Disabling extensions in Magento is confusing for beginners. We have an option in System > Configuration > Advanced to 'enable' and 'disable' extensions but this only effects block outputs. There is also a tag 'active' in the module's XML file that suggests an extension can be disabled this way. This is also only partly true, since observers still run when this tag is set to false.
In our experience, the only true way to disable an extension is moving the XML file away from app/etc/modules or renaming it so Magento won't read it.

This command shows you all modules that have an XML file and when chosen, renames the module file from Namespace_Module.xml to Namespace_Module.xml.disabled so Magento doesn't read the XML and thus does not active the extension.

    $ n98-magerun.phar extension:disable

### Enable an extension ###

This command renames the file from Namespace_Module.xml.disabled back to Namespace_Module.xml. Thus this command can only be used when an extension is disabled with extension:disable (or when renamed manually).

    $ n98-magerun.phar extension:enable

### Export custom core rewrite URLs to Apache/nginx configuration ###

Some core_url_rewrite tables get very large due to various reasons. With this command, you can export the custom core rewrite URLs to an Apache or nginx configuration file to offload rewriting these URLs to the server instead of application level, giving you the chance to remove these rewrites from the database.

    $ n98-magerun.phar sys:store:url:rewrites:export

Credits due where credits due
--------

Thanks to [Netz98](http://www.netz98.de) for creating the awesome Swiss army knife for Magento, [magerun](https://github.com/netz98/n98-magerun/). And thanks to [Kalen Jordan](https://twitter.com/kalenjordan/) for showing me a blueprint on how to create modules for magerun by looking at [his addons](https://github.com/kalenjordan/magerun-addons/) and for the introductory text of this readme ;-)