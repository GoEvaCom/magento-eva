Eva for Magento

This module integrates right into Magento to include Eva's last mile delivery service.

## Development

### Magento Installation

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

### Module installation

You will need an existing Magento installation to develop the module (follow previous steps).

After cloning the repo, go into the Magento's root directory, then /app/code/

Next, you will need to create a bind with Docker.
Go to the compose.yaml file of the Magento installation and insert a new volume in magento-nginx
`- /home/youruser/magento-eva/:/var/www/html/app/code/GoEvaCom`

Then, restart the containers
`bin/stop`
`bin/start`

Then, run setup:upgrade:
`bin/magento setup:upgrade`

Look if the module is listed

`bin/magento module:status`
It should be listed either under disabled or enable list as
`GoEvaCom_Integration`

If it is not listed, it might be an issue with the bind, make sure you put in the right directory

You're done, you have now installed the module to Magento!
