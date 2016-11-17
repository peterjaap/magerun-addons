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

        cd ~/.n98-magerun/modules/ && git clone git@github.com:peterjaap/magerun-addons.git pj-addons

3. It should be installed. To see that it was installed, check to see if one of the new commands is in there, like `media:sync`.

        n98-magerun.phar media:sync

Commands
--------

### Sync Media over SSH or FTP ###

This command lets you sync the `/media` folder over SSH (requires `rsync`) or FTP (requires `ncftpget`). You can choose to run this command either interactively or non-interactively.

#### Interactive mode #####

    $ n98-magerun.phar media:sync

This will spawn a few CLI dialogs asking you to synchronize using either SSH or FTP. After, that a few runtime options are asked for, of which SSH requires (obligatory) at least the following: `host` and `username`. When using the FTP mode, at least `host`, `username` and `password` are required.

The SSH mode does not allow a `password` values. Besides it not being supported by `rsync`, you should definitely set up SSH to function with [key based authorization](https://www.digitalocean.com/community/tutorials/how-to-configure-ssh-key-based-authentication-on-a-linux-server).

Additionally, you can set an alternative `port` for SSH, a `path` (both SSH and FTP) on the remote `host` to synchronize from (`/media` will be appended) and a comma-separated list with paths to `exclude`. Any `*cache*` directories will be excluded by default. Sadly, `ncftpget` does not support directory exclusion. Therefore - also due to security concerns of supplying a password on a CLI - using the SSH mode is recommended.

#### Non-interactive mode ####

    $ n98-magerun.phar media:sync --mode=[ssh|ftp]
    
Non-interactive mode is similar to the interactive mode, except that all listed runtime options as described above, have to be supplied as command options. For example:

    $ n98-magerun.phar media:sync --mode=ssh --host=yourhost.com --username=yourusername
    $ n98-magerun.phar media:sync --mode=ftp --host=yourhost.com --username=yourusername --password=yourpassword --ignore-permissions=true --path=public_html
    
Non-interactive mode is useful in, for example, a deployment or synchronization script which is executed automatically.

#### Persistent configuration ####

It is possible to have persistent configuration to further simplify the execution of the command. Both the interactive and non-interactive mode will first check the `app/etc/local.xml` file whether any configuration is present.

Configuration can be supplied in the following format:

    <config>
        <global>
            ...
            <production>
                <ssh>
                    <host>yourhost.com</host>
                    <username>yourusername</username>
                    <path>public_html</path>
                    <ignore-permissions>true</ignore-permissions>
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
        </global>
    </config>
    
### Add attribute to (multiple) attribute set(s) ###

This commands gives you an easy tool to quickly add an attribute to an attribute set, or even multiple (and all).

    $ n98-magerun.phar eav:attributes:add-to-set

### Merge attributes together ###

This will allow you to merge two existing product attributes together; all product values will be updated accordingly.

    $ n98-magerun.phar eav:attributes:merge

### Unduplicate product attribute options ###

This will automatically find duplicate attribute options for a given attribute (for example, merge option Blue into a different option with the same name Blue for attribute color).

Beware; this will ignore any translated option values as it will just pick the last attribute option from the query to keep.

    $ n98-magerun.phar eav:attributes:undupe-options

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

### Inspect entity information

    $ n98-magerun.phar dev:entity:inspect --order 1234 [--filter="shipping"]

This will give you all getData() parameters from the requested order object, along with invoices & creditmemos that belong to that order in a nice table format. Optional filter parameter to filter output on parameter name. Might be extended in the future to be more abstract and be able to load any gievn entity in Magento.

Example output;

```
➜  magento git:(master) ✗ n98-magerun.phar dev:entity:inspect --order 1000000147
+-------------+-----------+--------------+-------------------------------------+------------------------------------------------------------------------------+
| Entity Type | Entity ID | Increment ID | Parameter                           | Value                                                                        |
+-------------+-----------+--------------+-------------------------------------+------------------------------------------------------------------------------+
| Product     | 24074     | 161116342    | entity_id                           | 42421                                                                        |
| Product     | 24074     | 161116342    | state                               | closed                                                                       |
| Product     | 24074     | 161116342    | status                              | closed                                                                       |
| Product     | 24074     | 161116342    | coupon_code                         |                                                                              |
| Product     | 24074     | 161116342    | protect_code                        | 133ee7                                                                       |
| Product     | 24074     | 161116342    | shipping_description                | USPS First Class Mail                                                        |
| ..... etc                                                                                                                                                   |
| Invoice     | 21312     | 161116308    | entity_id                           | 24242                                                                        |
| Invoice     | 21312     | 161116308    | store_id                            | 1                                                                            |
| Invoice     | 21312     | 161116308    | base_grand_total                    | 133.3700                                                                     |
| Invoice     | 21312     | 161116308    | shipping_tax_amount                 | 0.0000                                                                       |
| Invoice     | 21312     | 161116308    | tax_amount                          | 13.3700                                                                      |
| ..... etc                                                                                                                                                   |
| Creditmemo  | 1513      | 161116214    | entity_id                           | 1513                                                                         |
| Creditmemo  | 1513      | 161116214    | store_id                            | 1                                                                            |
| Creditmemo  | 1513      | 161116214    | adjustment_positive                 | 0.0000                                                                       |
| Creditmemo  | 1513      | 161116214    | base_shipping_tax_amount            | 0.0000                                                                       |
| ..... etc                                                                                                                                                   |
+-------------+-----------+--------------+-------------------------------------+------------------------------------------------------------------------------+
```

### List the events that are listened to by observers

     $ n98-magerun.phar dev:events:list

This will give you a list of all events that are listened to by observers, by which module and by which class & method. Example output;

```
customer_login (catalog) catalog/product_compare_item::bindCustomerLogin
customer_login (loadCustomerQuote) checkout/observer::loadCustomerQuote
customer_login (log) log/visitor::bindCustomerLogin
customer_login (reports) reports/event_observer::customerLogin
customer_login (wishlist) wishlist/observer::customerLogin
customer_login (persistent) persistent/observer_session::synchronizePersistentOnLogin
```

It will also give a warning if an event is listened to that contains an uppercased letter, since this has changed in SUPEE-7405 / Magento 1.9.2.3. See http://magento.stackexchange.com/questions/98220/security-patch-supee-7405-possible-problems for more info.

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

### Export custom core rewrite URLs to Apache/nginx configuration ###

Some core_url_rewrite tables get very large due to various reasons. With this command, you can export the custom core rewrite URLs to an Apache or nginx configuration file to offload rewriting these URLs to the server instead of application level, giving you the chance to remove these rewrites from the database.

    $ n98-magerun.phar sys:store:url:rewrites:export

Credits due where credits due
--------

Thanks to [Netz98](http://www.netz98.de) for creating the awesome Swiss army knife for Magento, [magerun](https://github.com/netz98/n98-magerun/). And thanks to [Kalen Jordan](https://twitter.com/kalenjordan/) for showing me a blueprint on how to create modules for magerun by looking at [his addons](https://github.com/kalenjordan/magerun-addons/) and for the introductory text of this readme ;-)
