# DigitalPianism ChangePassword

A small module to be able to change customer password via the console in Magento 2.

Tested on 2.1.2

# Setup
First install the module using composer:

    composer require digitalpianism/changepassword

Next, once the module is installed, set it up:

    php bin/magento setup:upgrade
    php bin/magento module:enable DigitalPianism_ChangePassword
    
# Command example

    php bin/magento customer:changepassword --customer-id=3 --customer-password=mynewpassword
    
Where:

* 3 is a customer id
* mynewpassword is the new password for this customer

