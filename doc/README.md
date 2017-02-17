timedtrashpurge
===============

Description
-----------

This extension stops objects that have **recently** been added to the trash from being purged.

**timedtrashpurge** adds a timestamp to track when content objects are moved into the trash. 
 
When a trash purge is run (either through a cronjob or a script), only objects that are older than **x** number of days
are actually removed. By default, this extension will not purge objects from that have been in the trash for less
than **7** days.
  
Note that this extension does not change the behaviour of objects that are deleted directly, without using the "move to trash" option.
Those objects are still immediately and permanently removed.
 
  
Installation
------------

* Run sql/mysql/schema.sql against your MySQL database. This creates one table, pt_trash, that performs the tracking.

* Renenerate kernel overrides:

      cd <ezpublish_root>
      php bin/php/ezpgenerateautoloads.php -o
      php bin/php/ezpgenerateautoloads.php -e
    
* Enable the **timedtrashpurge** extension in the site.ini file. Note that the extension must be accessible from:

    * The admin siteaccess
    * Any siteaccess which is used to run the trashpurge.php script or the trash purge cronjob.
      
      
Configuration
-------------

Edit or override extension/timedtrashpurge/settings/trash.ini to control the
amount of time an object stays in the trash.

    [TrashSettings]
    DaysInTrashBeforePurge=7

