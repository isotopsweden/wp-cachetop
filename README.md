# Cachetop

Cache plugin that create static HTML files that can be stored in Redis or on the file system.

### Unfragment

With `cachetop_unfragment( $fn, array $args = [] )` you can tell Cachetop to replace the cached html with the output of the given function or static method.

Example:

```
<div class="widget_shopping_cart_content">
    <?php cachetop_unfragment( 'woocommerce_mini_cart' ); ?>
</div>
```

The constant `DOING_CACHETOP` will be defined when Cachetop calls a function or static method when it replacing cached html with fresh html.
