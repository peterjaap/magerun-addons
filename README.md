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

        cd ~/.n98-magerun/modules/
        git clone git@github.com:peterjaap/magerun-addons.git

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
Also note that the path can be set both relative (without a leading slash) as well as absolute (with a leading slash).

    $ n98-magerun.phar media:sync

### Set base URL's ###

Magerun already has an option to show a list of set base URL's but no way to set them easily. It is possible through config:set but this is cumbersome. This command gives you a list of storeviews to choose from and asks you for your base URL. You have the option to set both the unsecure and the secure base URL.

    $ n98-magerun.phar sys:store:config:base-url:set
    
### Disable an extension ###

Disabling extensions in Magento is confusing for beginners. We have an option in System > Configuration > Advanced to 'enable' and 'disable' extensions but this only effects block outputs. There is also a tag 'active' in the module's XML file that suggests an extension can be disabled this way. This is also only partly true, since observers still run when this tag is set to false.
In our experience, the only true way to disable an extension is moving the XML file away from app/etc/modules or renaming it so Magento won't read it.

This command shows you all modules that have an XML file and when chosen, renames the module file from Namespace_Module.xml to Namespace_Module.xml.disabled so Magento doesn't read the XML and thus does not active the extension.

    $ n98-magerun.phar extension:disable
    
### Find translations for given extension & language ###

This command lets you choose a language code and an installed extension. It will then look for translatable strings (strings that are run through __()) and look for its translation in the set language. It shows a table with the (un)translated strings and generates a pre-structured (and pre-filled, if applicable) locale (csv) file.

    $ n98-magerun.phar extension:translations

### Enable an extension ###

This command renames the file from Namespace_Module.xml.disabled back to Namespace_Module.xml. Thus this command can only be used when an extension is disabled with extension:disable (or when renamed manually).

    $ n98-magerun.phar extension:enable  
    
Credits due where credits due
--------

Thanks to [Netz98](http://www.netz98.de) for creating the awesome Swiss army knife for Magento, [magerun](https://github.com/netz98/n98-magerun/). And thanks to [Kalen Jordan](https://twitter.com/kalenjordan/) for showing me a blueprint on how to create modules for magerun by looking at [his addons](https://github.com/kalenjordan/magerun-addons/) and for the introductory text of this readme ;-)