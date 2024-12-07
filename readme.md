# Fast File Cache for Wordpress oembed JSON calls.

This is a simple solution to address spikes in load generated by distributed social
media platforms (for example, Mastodon and other ActivityPub-based networks).

## The Problem

Clients using a social network can preload a linked page to display a preview of the site.
In a centralized network, the preview is loaded and cached by a centralized hub then
distributed to clients. This generates one request to the linked server
(possibly more than one if the hub is geographically distributed, but still not a large number).

In a federated network, there are thousands of servers/instances. These servers don't share a
centralized cache, so each one of them that needs a preview will make a request to the link target.

Because the post is rapidly shared between servers, the result is a sudden spike in requests for 
a preview. This load is sufficient to disrupt a shared hosting account, or even a small VPS. 
Even after bumping the number of available database connections, 
my little VPS is running out and throwing errors instead of previews. Not good.

In Wordpress, these calls come in the form of an oembed request like 
`https://example.com/wp-json/oembed/1.0/embed?url=https://example.com/some-page`

## Caching to the Rescue, Part 1

Unfortunately, most caching plugins won't cache this request. However, there is a plugin that
does, ["WP REST Cache"](https://wordpress.org/plugins/wp-rest-cache/). 
This really speeds things up. Setup is straightforward.

The problem with this plugin is that it's storing the cached copies in the database. 
This means even a cached request requires getting the WP core loaded and running, 
then querying the database for the cached file before sending it out. 
In a quick test in my environment, 
the cached page took about one fifth of the time as an uncached one.

In addition, the plugin is smart. Changes in the site that affect the preview 
cause the cached copy to be deleted and replaced with a new one. 
The default lifespan for a cached request is a year, which doesn't seem unreasonable.
It's a well-designed plugin, and I highly recommend it.

## Caching Part 2

Unfortunately, this approach doesn't help that much when it comes to a bottleneck of 
too many database connections. 
`wp-oembed` is a much less sophisticated file cache that doesn't need a database connection.
Unlike WP REST Cache, it's deliberately designed to be quick and unsophisticated.

Through a simple rewrite rule in `.htaccess`, embed requests are directed to `oembed.php`.
This code checks for an unexpired cached copy in the file system. If present,
the file is returned.

If no cached file is found, or if the cache has expired,
it adds a `bypass` parameter to the request and re-submits it.
The bypassed URL is routed to WP. 
If WP REST Cache is installed, a known current response is returned fairly quickly, 
and if not, then WP will generate a new version (slowly).
Either way, the "current" version is written to the file cache, ready for the next request.

Because `wp-oembed` doesn't track changes in Wordpress,
it's a good idea to keep the cache time low.
I've defaulted it to four days, but if all you want is to handle a burst,
just a few hours should do the trick.
This can be set in the `.oembed.json` file, along with the location where cached files 
should be stored. The `lifetime` setting is in seconds by default, but you can add m for minutes,
h for hours or d for days.

# Installation

Copy `oembed.php` and `.oembed.json` to your website root, and add the commands in `.htaccess.oembed`
to your .htaccess file before the "BEGIN WordPress" section.

# Maintenance

This utility doesn't scan to clean out expired cache files.
There will only be one cached file for every URL on your site where a preview has been requested.
If a cached page is renamed or deleted, the old cache file will stay there until it is
manually removed.
While that's not optimal, it shouldn't result in a huge number of obsolete files, 
even with formerly great search engines adding random `srsltid` arguments to the URLs they crawl.

If this becomes a big problem, I'll add some purge functionality that can be run through a cron job.
Worst case, there's no harm in removing all the files in the cache folder.

# Donations Welcome

If you find wp-oembed useful, please consider
[Buying me a coffee](https://buymeacoffee.com/alanlangford).

