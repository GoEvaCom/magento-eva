Eva for Magento 2

This module integrates right into Magento 2 to include Eva's last-mile delivery service.

## Usage

### Module installation

The module is available on the Packagist repo:

`bin/composer require goevacom/magento-integration`

After installation, run

`bin/magento setup:upgrade`

Look if the module is listed
`bin/magento module:status`
It should be listed either under disabled or enable list as
`GoEvaCom_Integration`

If it is disabled, enable with
`bin/magento module:enable GoEvaCom_Integration`

Check if the Eva attribute has been created, head to [Attributes](#Attributes) to manually install if the setup hasn't done so

Then, rerun
`bin/magento setup:upgrade`

You're good to go! Configure the plugin in Stores -> Configuration -> Sales -> Shipping Methods

## Development

### Magento Installation (Development)

Create a directory for the magento installation

`mkdir magento`
`cd magento`

Run the one-liner script

`curl -s https://raw.githubusercontent.com/markshust/docker-magento/master/lib/onelinesetup | bash -s -- magento.test community 2.4.8`
(Replace 2.4.8 with the version you want to use)

The installer will prompt you with credentials to the Magento repo. Enter your public key for the username and private key for the password.

After installation, run the following commands:

`bin/magento sampledata:deploy`
`bin/magento setup:upgrade`

You are now setup with Magento. You can now access it on https://magento.test

The username and password for admin access is john.smith:password123

### Module installation (Development)

You will need an existing Magento installation to develop the module (follow previous steps).

After cloning the repo, go into the Magento's root directory, then /app/code/

Next, you will need to create a bind with Docker.
Go to the compose.yaml file of the Magento installation and insert a new volume in magento-nginx
`- /home/youruser/magento-eva/:/var/www/html/app/code/GoEvaCom/Integration`

Then, you will need to import the module to Magento's composer require.
Head to your Magento's composer.json and create a repository section & add the module:

`
{
...

    "repositories": {
        ...
        "goevacom": {
            "type": "path",
            "url": "./app/code/GoEvaCom/Integration",
            "options": {
                "symlink": true
            }
        }
    },

    "require": {
        ...
        "goevacom/magento-integration": "*"
    }

}

Then, restart the containers
`bin/stop`
`bin/start`

Next, run composer update to fetch all the needed packages:
`bin/composer update`

Then, run setup:upgrade:
`bin/magento setup:upgrade`

Look if the module is listed

`bin/magento module:status`
It should be listed either under disabled or enable list as
`GoEvaCom_Integration`

If it is not listed, it might be an issue with the bind, make sure you put in the right directory

You're done, you have now installed the module to Magento for development!

# Configuration

Go to etc/config.xml and modify the urls to the corresponding environments

# Attributes

If the is_eva_deliverable attribute is not present in the configuration, then you will need to run the manual commands

Add attributes:

`bin/magento evadelivery:attribute:create`

Remove attributes (to update or whatever else):

`bin/magento evadelivery:attribute:remove`
