# Cachetop [![Build Status](https://travis-ci.org/isotopsweden/wp-cachetop.svg?branch=master)](https://travis-ci.org/isotopsweden/wp-cachetop)

Cache plugin that create static HTML files that can be stored in Redis or on the file system. The default cache time is one hour. All posts that are cached will have to post meta keys, `_cachetop_hash` with the cache hash id and `_cachetop_time` with the time when the post was cached.

### Unfragment

With `cachetop_unfragment( $fn, array $args = [] )` you can tell Cachetop to replace the cached html with the output of the given function or static method.

Example:

```
<div class="widget_shopping_cart_content">
    <?php cachetop_unfragment( 'woocommerce_mini_cart' ); ?>
</div>
```

The constant `DOING_CACHETOP` will be defined when Cachetop calls a function or static method when it replacing cached html with fresh html.

### Will not cache:

- Logged in users (if not changed with the filter).
- Search page.
- 404 page.
- Feed page.
- Trackback page.
- Robots file.
- Preview page.
- Password protected post.
- WooCommerce cart page.
- WooCommerce checkout page.
- WooCommerce account page.
- Only GET requests, so no POST, PUT, PATCH, UPDATE, DELETE or so.
- No GET requests with query strings.

### Filters

- `cachetop/options` should be used to change Cachetop options, like changing from Redis to file system or setting expiration time.
- `cachetop/bypass` can be used to tell if a page that should be cached should be bypassed.
- `cachetop/exclude_url` can be used to exclude urls, the first argument is the current url.
- `cachetop/bypass_logged_in` can be used to tell if logged in users should be cached, this is bad if the admin bar is showed.

### Functions

- `cachetop_flush_hash( $hash )` can flush cache by hash.
- `cachetop_flush_post( $post_id )` can flush post by post id.
- `cachetop_flush_url( $url = '' )` can flush cache by url or the current url, query strings will be removed.

## License

MIT
