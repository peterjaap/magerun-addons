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

Magerun already has an option to show a list of set base URL's but now way to set them easily. It is possible through config:set but this is cumbersome. This command gives you a list of storeviews to choose from and asks you for your base URL. You have the option to set both the unsecure and the secure base URL.

    $ n98-magerun.phar sys:store:config:base-url:set
    
Credits due where credits due
--------

Thanks to [Netz98](http://www.netz98.de) for creating the awesome Swiss army knife for Magento, [magerun](https://github.com/netz98/n98-magerun/). And thanks to [Kalen Jordan](https://twitter.com/kalenjordan/) for showing me a blueprint on how to create modules for magerun by looking at [his addons](https://github.com/kalenjordan/magerun-addons/) and for the introductory text of this readme ;-)