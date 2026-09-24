<?php
/*
 * Plugin Name: bsma LiteSpeed: no remote image size lookups
 * Description: LiteSpeed Cache's "Add Missing Sizes" calls getimagesize() over the network for <img> tags without width/height whose file is not on this server. If that host does not answer, PHP waits default_socket_timeout (60 s) while building the page. This uses LiteSpeed's own filter to skip those remote lookups; images stored on this server still get their sizes added.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_filter('litespeed_media_ignore_remote_missing_sizes', '__return_true');
