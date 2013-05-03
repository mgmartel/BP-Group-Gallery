# BP Group Gallery
Yet another gallery plugin.

This plugin uses the WP media uploader to insert and manage your group galleries. It currently has no admin interfaces, but it's stable for use on production servers. Group galleries are enabled for all groups by default, but you can use the `bp_group_gallery_enable_for_current_group` filter to tune this behaviour.

## Features
* Uses WP Media uploader
* Mods and admins can edit galleries (change order, remove images, etc.)
* Shows images on activity stream (see screenshot-3 )

It relies on the included wp-post-attachments library originally built for another plugin (and as of yet, unfinished..).