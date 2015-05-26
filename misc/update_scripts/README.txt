Update Scripts
==============

This directory (``misc/update_scripts``) contains a collection of
command-line utilities for updating Newznab. Whilst you can run them stand-alone
for testing things out, it is intended that you should run the calling script
win_scripts\runme.bat or nix_scripts\newznab_screen.sh which runs each of these
scripts in the right order.


Updating
--------

These scripts should be run on a frequent basis in order to stay
current with the newest posts to usenet.

``update_binaries.php``
~~~~~~~~~~~~~~~~~~~~~~~

This script downloads new headers from the news server and puts them
in the database (binaries and parts tables).  

``update_binaries_threaded.php``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

This script runs on linux only and calls the update_binaries script in 10
separate threads.  

``update_releases.php``
~~~~~~~~~~~~~~~~~~~~~~~

This script creates releases from downloaded headers. It includes all the 
additional post processing which is performed as a release is formed.  

``update_theaters.php``
~~~~~~~~~~~~~~~~~~~~~~~

This script updates the 'whats on in theaters' data from rotten tomatoes if
a rotten tomatoes api key is present.  

``update_tvschedule.php``
~~~~~~~~~~~~~~~~~~~~~~~~~

This script updates the tv schedule data from thetvdb.

``import.php``
~~~~~~~~~~~~~~~~~~~~~~~

This script is used for importing .nzb files from a path into newznab.

Maintenance
-----------

These scripts should be run occasionally.

``optimise_db.php``
~~~~~~~~~~~~~~~~~~~

Optimises and repairs mysql tables if necessary. Pass in the true argument to
force an optimise and repair regardless of whether its necessary.

Backfilling
-----------

``backfill.php``
~~~~~~~~~~~~~~~~

The equivalent of ``update_binaries.php`` but for going forwards from the group.backfilldays to 
the latest post. Downloads headers from usenet and puts them in the database (binaries and parts tables).  

``backfill_date.php``
~~~~~~~~~~~~~~~~~~~~~

The same as ``backfill.php`` but goes back to a specific date passed as an argument.

``backfill_threaded.php``
~~~~~~~~~~~~~~~~~~~~~~~~~

Calls ``backfill.php`` with a thread for each group requiring backfilling.
