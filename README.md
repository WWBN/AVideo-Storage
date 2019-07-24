# YouPHPTube-Storage

## What is it

It is a Simple Storage Service for the YouPHPTube Sites. It is designed to make web-scale storage. 
ie with this service you will have unlimited storage capacity and low cost. 
For example when one of your storage is full, just plug in one more storage and your videos will continue to be saved to the new storage.

This service is designed to replace our current S3, BackBlaze B2 and FTP plugins, but with much more integration with our services, 
besides you will have no restriction on the amount of storages used

You can install as many storagess as you want, without any geographical restrictions, when using storage, 
when a user is watching one of your videos he will use the bandwidth of his storage, so it will facilitate his load balancing with the amount of bandwidth and speed due to the location of each video.

## Installation 

You will need the folowing prerequisites.

### What we use for that:
1. PHP 7+
1. Apache XSendFile
1. YPTStorage Plugin

### What will you need
1. Root Access to the server
1. Admin user for YouPHPTube

### Enable YPTStorage 

This is necessary because the Storage installation will check your plugin during the configuration assistant

### Install apache xsendfile

    sudo apt-get install libapache2-mod-xsendfile && sudo a2enmod xsendfile

### Configure your apache XSendFile

    sudo nano /etc/apache2/apache2.conf

    <Directory /var/www/html/YouPHPTube-Storage/>
        Options Indexes FollowSymLinks
        XSendFile on
        XSendFilePath /var/www/html/YouPHPTube-Storage/
        AllowOverride All
        Require all granted
        Order Allow,Deny
        Allow from All
    </Directory>

