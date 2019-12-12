# AVideo-Storage

## What is it

It is a Simple Storage Service for the AVideo Sites. It is designed to make web-scale storage. 
ie with this service you will have unlimited storage capacity and low cost. 
For example when one of your storage is full, just plug in one more storage and your videos will continue to be saved to the new storage.

This service is designed to replace our current S3, BackBlaze B2 and FTP plugins, but with much more integration with our services, 
besides you will have no restriction on the amount of storages used

You can install as many storages as you want, without any geographical restrictions.

One of the advantages is that when a user is watching one of your videos he will use the bandwidth of the storage, so it will facilitate your server load balancing on the amount of the used bandwidth and speed due to the location of each video.

Please check this scenario to try to make the propose of this project clear: https://github.com/AVideo/AVideo-Storage/wiki/Scenario-Description

## Installation 

You will need the folowing prerequisites.

### What we use for that:
1. PHP 7+
1. Apache XSendFile
1. YPTStorage Plugin
1. AVideo 7.3+

### What will you need
1. Root Access to the server
1. Admin user for AVideo

### Copy and Paste

#### Ubuntu 16.04
    sudo apt-get update -y && sudo apt-get upgrade -y && sudo apt-get install nano curl apache2 php7.0 libapache2-mod-php7.0 php7.0-curl php7.0-gd php7.0-intl php-zip php-xml php-mbstring git -y && a2enmod headers && service apache2 restart && cd /var/www/html && git clone https://github.com/AVideo/AVideo-Storage.git && sudo a2enmod rewrite && sudo mkdir /var/www/html/AVideo-Storage/videos && sudo chown www-data:www-data /var/www/html/AVideo-Storage/videos
    
#### Ubuntu 18.04
    sudo apt-get update -y && sudo apt-get upgrade -y && sudo apt-get install nano curl apache2 php7.2 libapache2-mod-php7.2 php7.2-curl php7.2-gd php7.2-intl php-xml php-mbstring git -y && a2enmod headers && service apache2 restart && cd /var/www/html && sudo git clone https://github.com/AVideo/AVideo-Storage.git && sudo a2enmod rewrite && sudo mkdir /var/www/html/AVideo-Storage/videos && sudo chown www-data:www-data /var/www/html/AVideo-Storage/videos

### Install apache xsendfile

    sudo apt-get install libapache2-mod-xsendfile && sudo a2enmod xsendfile

### Configure your apache XSendFile

    sudo nano /etc/apache2/apache2.conf

    <Directory /var/www/html/AVideo-Storage/>
        Options Indexes FollowSymLinks
        XSendFile on
        XSendFilePath /var/www/html/AVideo-Storage/
        AllowOverride All
        Require all granted
        Order Allow,Deny
        Allow from All
    </Directory>

### Enable YPTStorage Plugin

Make sure you enable it before your installation, this is necessary because the Storage installation will check your plugin during the configuration assistant

If you do not have the plugin yet, get it [here](https://plugins.avideo.com/)

### Access your storage server

On the first access you will be requested for your streamer address. the installation script will try to create your videos directory and your configuration.php file. If any of those fail you will need to create it manually.

The script will create a Storage site for you on your streamer site, but this site will be inactive, you will need to activate it (On the YPTStorage plugin) before proceed.

## Demonstration

We currently have this feature enabled on the [demo](https://demo.avideo.com) site

We have the following storages:

* https://storage1.avideo.com/
* https://storage2.avideo.com/
* https://storage3.avideo.com/

You can switch the storages from the videos on the videos manager. You will find a move storage button, when you click on it. It will popup a window with the options to move your video to the local storage or one of the 3 storages above.
